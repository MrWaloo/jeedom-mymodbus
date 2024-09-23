"""
Interface between MyModbus and pymodbus

In this code 'pmb' is used for PyModBus

The logic is the same than in the modbus implementation in Home Assistant as far as I could
"""

import asyncio
import logging
import math
import re
from array import array
from statistics import fmean

from jeedomdaemon.utils import Utils

from pymodbus import FramerType
from pymodbus.client import AsyncModbusSerialClient, AsyncModbusTcpClient, AsyncModbusUdpClient
from pymodbus.exceptions import ModbusException
from pymodbus.factory import ServerDecoder
from pymodbus.logging import pymodbus_apply_logging_config
from pymodbus.pdu import ExceptionResponse, ModbusRequest, ModbusResponse
from pymodbus.utilities import (
    pack_bitstring,
    unpack_bitstring,
)

from mymodbuslib import Lib


class MyModbusClient(object):

  _pmb_clients = {
    "serial": AsyncModbusSerialClient,
    "tcp": AsyncModbusTcpClient,
    "udp": AsyncModbusUdpClient,
    "rtuovertcp": AsyncModbusTcpClient,
  }

  def __init__(
      self,
      eqConfig: dict[str, any],
      log: logging.Logger | None = None
    ) -> None:

    self.eqConfig = eqConfig
    if log:
      self.log = log
    else:
      logging_name = eqConfig['name'] if eqConfig['name'] else __name__
      self.log = logging.getLogger(f"MyModbus_{logging_name}")
    self.client: (
      AsyncModbusSerialClient | AsyncModbusTcpClient | AsyncModbusUdpClient | None
    ) = None
    self._client_params: dict[str, any] = {}
    self._requests: dict[str, ModbusRequest] = {}
    self._payload: array = array("H")
    self._blob_dest: dict[str, list] = {}
    self._read_cycle: int = 0
    self._cycle_times: list = []
    self._changes: dict = {}

    # tasks to be referenced so that the garbage collector won't delete them
    self._async_tasks: list[asyncio.Task] = []
    self.loop: asyncio.AbstractEventLoop = asyncio.get_running_loop()
    self._lock = asyncio.Lock()
    self._lock_w = asyncio.Lock()
    self.read = asyncio.Event()
    self.should_stop = asyncio.Event()
    self.stopped = asyncio.Event()
    self.stopped.set()
    self.connected = asyncio.Event()
    self.should_terminate = asyncio.Event()
    self.downstream = asyncio.Queue() # Daemon -> MyModbusClient
    self.upstream = asyncio.Queue() # MyModbusClient -> Daemon

    self._async_tasks.append(self.loop.create_task(
      self.read_downstream(),
      name = f"read_downstream_{self.eqConfig['id']}"
    ))

#  def __del__(self):
#    attr_list = [
#      "eqConfig", "log", "client", "_client_params", "_requests", "_blob_dest",
#      "_read_cycle", "_cycle_times", "_async_tasks", "loop", "_lock", "_lock_w",
#      "read", "should_stop", "stopped", "should_terminate", "downstream", "upstream"
#    ]
#    for attr in attr_list:
#      if hasattr(self, attr):
#        value = getattr(self, attr)
#        if value is not None:
#          delattr(self, attr)

  def read_eqConfig(self, eqConfig: dict[str, any] | None = None) -> None:
    """
    Creates the client and the requests according to the configuration
    """
    if self.client:
      self.close()
    if eqConfig is not None:
      self.eqConfig = eqConfig
    del self.client
    self.client = None
    self._client_params = {
      "name": self.eqConfig["name"],
      "timeout": float(self.eqConfig["eqTimeout"]),
      "retries": float(self.eqConfig["eqRetries"]),
      "on_connect_callback": self.on_connect_callback,
    }
    framer = None
    self._requests = {}
    self._blob_dest = {}

    # Client pymodbus
    if self.eqConfig["eqProtocol"] == "serial":
      # Liaison série
      if self.eqConfig["eqSerialMethod"] == "ascii":
        framer = FramerType.ASCII
      else:
        framer = FramerType.RTU
      self._client_params.update(
        {
          "port": self.eqConfig["eqPort"],
          "baudrate": int(self.eqConfig["eqSerialBaudrate"]),
          "stopbits": int(self.eqConfig["eqSerialStopbits"]),
          "bytesize": int(self.eqConfig["eqSerialBytesize"]),
          "parity": self.eqConfig["eqSerialParity"],
        }
      )
    else:
      # Liaison Ethernet
      self._client_params.update(
        {
          "port": int(self.eqConfig["eqPort"]),
        }
      )
      if self.eqConfig["eqProtocol"] == "rtuovertcp":
        framer = FramerType.RTU
      else:
        framer = FramerType.SOCKET
      self._client_params["host"] = self.eqConfig["eqAddr"]
    self._client_params["framer"] = framer
    self.log.debug(f"{self.eqConfig['name']}: 'read_eqConfig' client params for {self.eqConfig['name']}: {self._client_params}")
    
    # Création de la liste des requêtes pymodbus
    func_code_dict = ServerDecoder.getFCdict()
    for cmd in self.eqConfig["cmds"]:
      if cmd["type"] != "info":
        continue
      if cmd["cmdFctModbus"] == "fromBlob":
        if self._blob_dest.get(int(cmd["cmdSourceBlob"]), None) is None:
          self._blob_dest[int(cmd["cmdSourceBlob"])] = []
        self._blob_dest[int(cmd["cmdSourceBlob"])].append(cmd["id"])
        
      else: # not fromBlob
        request_func = func_code_dict.get(int(cmd["cmdFctModbus"]), None)
        if request_func is None:
          error = f"le code de fonction Modbus n'est pas disponible: {cmd['cmdFctModbus']}"
          self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: {error}")
          continue
        address, count = Lib.get_request_addr_count(cmd)
        slave = int(cmd["cmdSlave"])
        self._requests[cmd["id"]] = request_func(address, count, slave)
        self.log.debug(f"{self.eqConfig['name']}: 'read_eqConfig' ModbusRequest for cmd id {cmd['id']}: {self._requests[cmd['id']]}")

  async def read_downstream(self):
    self.log.debug(f"{self.eqConfig['name']}: 'read_downstream' launched")
    try:
      while not self.should_terminate.is_set():
        message = await self.downstream.get()

        self.log.debug(f"{self.eqConfig['name']}: 'read_downstream' Message received from daemon: {message}")
        for action, payload in message.items():
          if action == "quit":
            self.should_terminate.set()
            self.should_stop.set()
            await self.wait_for_stopped()
            
          elif action == "write":
            self._async_tasks.append(self.loop.create_task(
              self.command_write(payload),
              name = payload["cmdId"]
            ))

          elif action == "read":
            self.read.set()          

          elif action =="newDaemonConfig":
            self.should_stop.set()
            await self.wait_for_stopped()
            self.read_eqConfig(payload)
            self.should_stop.clear()
            self.connect()

        self.downstream.task_done()

    except asyncio.CancelledError:
      self.log.debug(f"{self.eqConfig['name']}: 'read_downstream' cancelled")

    self.log.debug(f"{self.eqConfig['name']}: 'read_downstream' exit")

  async def send_to_jeedom(self, payload):
    self.log.debug(f"{self.eqConfig['name']}: 'send_to_jeedom' launched with payload = {payload}")
    await self.upstream.put({"to_jeedom": payload})

  async def add_change(self, payload):
    self.log.debug(f"{self.eqConfig['name']}: 'add_change' launched with payload = {payload}")
    changes_to_send: dict = {}
    for k, v in payload.items():
      if k not in self._changes.keys() or self._changes[k] != v:
        changes_to_send[k] = self._changes[k] = v
    if changes_to_send:
      try:
        await self.upstream.put({"add_change": changes_to_send})
      except ValueError as e:
        self.log.error(f"{self.eqConfig['name']}: 'add_change' Send not possible : {e!s}")
    else:
      self.log.debug(f"{self.eqConfig['name']}: 'add_change' No modification to send")

  def connect(self) -> asyncio.Task:
    self.client = self._pmb_clients[self.eqConfig["eqProtocol"]](**self._client_params)
    self.log.debug(f"{self.eqConfig['name']}: 'connect' ModbusClient of {self.eqConfig['name']} = {self.client}")
    return self.loop.create_task(self.async_connect(True))

  async def async_connect(self, first_call: bool = False) -> None:
    if not (self.eqConfig["eqRefreshMode"] == "on_event" and first_call):
      self.stopped.clear()
      if not self.client.connected or not self.connected.is_set():
        try:
          async with self._lock:
            await self.client.connect()
        except ModbusException as e:
          self.log.error(f"{self.eqConfig['name']}: Connection could not be opened: {e!s}")
          return
        self.log.debug(f"{self.eqConfig['name']}: connection opened")
    
    if first_call and self.client.connected:
      self.log.info(f"{self.eqConfig['name']}: connection opened")
      await asyncio.sleep(float(self.eqConfig["eqFirstDelay"]))
      self._async_tasks.append(self.loop.create_task(
        self.run_loop(),
        name = f"run_loop_{self.eqConfig['id']}"
      ))

  async def run_loop(self) -> None:
    """
    The daemon main loop
    """
    refresh_mode = self.eqConfig["eqRefreshMode"]
    self.log.debug(f"{self.eqConfig['name']}: 'run_loop' launched in mode '{refresh_mode}'")
    try:
      self._read_cycle = 0
      self._cycle_times = [None, None, None, None, None]
      polling_config = float(self.eqConfig["eqPolling"])
      polling = polling_config

      while not self.should_stop.is_set():
        if refresh_mode == "on_event":
          self.log.debug(f"{self.eqConfig['name']}: 'run_loop' wait for CMD read")
          await self.read.wait()
          if self.should_stop.is_set():
            break
          await self.async_connect()
        #self.log.debug(f"{self.eqConfig['name']}: 'run_loop' cycle {self._read_cycle}")
        
        begin = self.loop.time()
        cycle_with_error = await asyncio.wait_for(self.one_cycle_read(), None)
        
        duration = self.loop.time() - begin
        if refresh_mode == "polling":
          if duration > polling:
            polling = (duration // polling_config + 1) * polling_config
            warning = f"the polling time is too short! Setting it to {polling}"
            self.log.warning(f"{self.eqConfig['name']}: {warning}")
          await asyncio.sleep(math.floor((polling - duration) * 10) / 10) # Arrondi à 0.1s en dessous
        
        if cycle_with_error:
          payload = {
            "values::cycle_ok": {
              "value": 0,
              "eqId": self.eqConfig["id"]
            }
          }

        else:
          self._cycle_times[self._read_cycle % len(self._cycle_times)] = duration
          #self.log.debug(f"{self.eqConfig['name']}: 'run_loop' _cycle_times {self._cycle_times}")
          payload = {
            "values::cycle_ok": {
              "value": 1,
              "eqId": self.eqConfig["id"]
            }
          }
          if None not in self._cycle_times:
            payload["values::cycle_time"] = {
              "value": fmean(self._cycle_times),
              "eqId": self.eqConfig["id"]
            }
            self._cycle_times = [None for _ in self._cycle_times]
          self._read_cycle += 1
        
        self.loop.create_task(self.add_change(payload))

        self.read.clear()
        if refresh_mode == "on_event":
          self.close()

    except asyncio.CancelledError:
      self.log.debug(f"{self.eqConfig['name']}: 'run_loop' cancelled")

    self.close()
    self.log.debug(f"{self.eqConfig['name']}: 'run_loop' exit")

  async def one_cycle_read(self) -> bool:
    """
    One read cycle of all the info commands
    """
    self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read' launched")
    error_on_current_read = False
    error_or_exception = False
    eqWriteCmdCheckTimeout = float(self.eqConfig['eqWriteCmdCheckTimeout'])
    eqErrorDelay = float(self.eqConfig['eqErrorDelay'])
    try:
      for cmd_id, pmb_req in self._requests.items():
        error_on_current_read = False
        self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read' treatment cmd_id = {cmd_id}")
        if self.should_stop.is_set():
          break
        
        cmd = self.get_cmd_conf(cmd_id)
        if cmd is None:
          self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read' {cmd['id']} cmd is None")
          continue
        if cmd["type"] != "info":
          self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read'/{cmd['name']}: command action")
          continue
        if self._read_cycle % int(cmd["cmdFrequency"]) != 0:
          self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read'/{cmd['name']}: no read this cycle")
          continue

        await self.async_connect()

        try:
          async with self._lock:
            self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read'/{cmd['name']}: requesting read")
            rr: ModbusResponse = await self.client.execute(pmb_req)
        except ModbusException as exc:
          self.loop.create_task(self.invalidate_blob(cmd["id"]))
          error_on_current_read = True
          error = f"exception during read request on slave id {pmb_req.slave_id}, address {pmb_req.address} -> {exc!s}"
          self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: {error}")
        if not error_on_current_read:
          try:
            if rr.isError():
              error_on_current_read = True
              error = f"error during read request on slave id {pmb_req.slave_id}, address {pmb_req.address} -> {rr}"
              self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: {error}")
          except AttributeError:
            error_on_current_read = True
            error = f"return error during read request on slave id {pmb_req.slave_id}, address {pmb_req.address} -> {rr}"
            self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: {error}")
        if not error_on_current_read:
          if isinstance(rr, ExceptionResponse):
            error_on_current_read = True
            error = f"exception during read request on slave id {pmb_req.slave_id}, address {pmb_req.address} -> {rr}"
            self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: {error}")
        
        if error_on_current_read:
          error_or_exception = True
          self.loop.create_task(self.invalidate_blob(cmd["id"]))
          await asyncio.sleep(eqErrorDelay) # Laisse le temps pour revenir à la normale
        else:
          self.loop.create_task(self.process_read_response(cmd["id"], rr))
          await asyncio.sleep(eqWriteCmdCheckTimeout) # Cède le contrôle aux autres tâches

      self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read' exit with error_or_exception = {error_or_exception}")
      return error_or_exception
        
    except asyncio.CancelledError:
      self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read' cancelled")
  
  async def process_read_response(self, cmd_id: str, response: ModbusResponse) -> None:
    """
    Reads ModbusResponse and returns the value(s) to Jeedom
    """
    self.log.debug(f"{self.eqConfig['name']}: 'process_read_response' launched for command id = {cmd_id}")
    change = {}
    cmd = self.get_cmd_conf(cmd_id)
    if cmd is None:
      self.log.debug(f"{self.eqConfig['name']}: 'process_read_response' cmd is None")
      return
    if cmd["cmdFormat"] == 'blob':
      change[f"values::{cmd['id']}"] = 1
    dest_ids = self._blob_dest.get(int(cmd["id"]), None)
    if dest_ids is not None: # Plage de registres
      for dest_id in dest_ids:
        dest = self.get_cmd_conf(dest_id)
        if dest is None:
          continue
        change[f"values::{dest_id}"] = self.cmd_decode(response, dest, cmd)
    elif cmd["cmdFormat"] != 'blob': # Lecture pour une commande et pas pour un blob sans destination
      change[f"values::{cmd['id']}"] = self.cmd_decode(response, cmd)
    
    await self.add_change(change)

  def cmd_decode(self, response: ModbusResponse, cmd: dict, blob: dict | None = None) -> any:
    self.log.debug(f"{self.eqConfig['name']}: 'cmd_decode' launched for command id = {cmd['id']}")
    address, count = Lib.get_request_addr_count(cmd)
    cmd_format: str = cmd["cmdFormat"]
    data_type = Lib.get_data_type(cmd_format)
    payload = self.get_payload(response, cmd, blob)
    if blob is not None:
      blob_addr, blob_count = Lib.get_request_addr_count(blob)
      if address < blob_addr or address + count > blob_addr + blob_count:
        error = f"the size of the register range {blob['name']} is too small"
        self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: {error}")
        return
      offset = address - blob_addr
      if cmd_format == "bit":
        payload = payload.tobytes()[offset // 8:]
      else:
        payload = payload.tolist()[offset:]
    
    # Bit
    if cmd_format == "bit":
      mask = 1
      if blob is not None:
        offset = address - blob_addr
        mask = 2 ** offset % 8
      return int(payload[0]) & mask != 0

    # Type: Byte
    elif cmd_format.startswith("uint8"):
      if cmd_format.endswith("-msb"):
        return payload[0] >> 8
      return payload[0] & 255

    # Type: Word (16bit) || Dword (32bit) || Double Dword (64bit) || String
    elif Lib.is_normal_number(cmd) or cmd_format == "s":
      payload = payload[:count]
      return Lib.convert_from_registers(payload, cmd_format)

    # Type: ScaleFactor
    elif cmd_format.endswith("_sf"):
      val_data_type = Lib.get_data_type(cmd_format[0])
      val_addr, sf_addr = Lib.get_val_sf(cmd)

      val_payload = payload[val_addr - address:val_addr - address + val_data_type.value[1]]
      val = Lib.convert_from_registers(val_payload, cmd_format[0])

      if val_data_type.value[1] >= 2 and cmd["cmdInvertWords"] != "0":
        payload = Lib.wordswap(payload, cmd)
      sf_payload = payload[sf_addr - address:sf_addr - address + 1]
      sf = Lib.convert_from_registers(sf_payload, "h")

      return val * 10 ** sf

  async def command_write(self, command: dict) -> None:
    self.log.debug(f"{self.eqConfig['name']}: 'command_write' launched with command = '{command}'")
    try:
      if self.should_stop.is_set():
        return
      
      async with self._lock_w:
        if not all(key in command for key in ("cmdId", "cmdWriteValue")):
          self.log.error(f"{self.eqConfig['name']}: 'command_write' write command without 'cmdId' or 'cmdWriteValue': {command}")
          return
        cmd = self.get_cmd_conf(command["cmdId"])
        if cmd is None:
          self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' write command with unknown 'cmdId': {command}")
          return
        cmd_format: str = cmd["cmdFormat"]
        data_type = Lib.get_data_type(cmd_format)
        if (
          cmd["cmdFctModbus"] == "fromBlob"
          or cmd_format == "blob"
          or cmd_format.startswith("uint8")
        ):
          return
        
        address, count = Lib.get_request_addr_count(cmd)
        value_to_write = command["cmdWriteValue"]
        pause = None
        pause_pattern = r"(.*)\s*?pause\s*?(\d+([\.\,]\d+)?)\s*?$"
        result = re.match(pause_pattern, str(value_to_write), re.IGNORECASE)
        if result:
          value_to_write = eval(result.group(1))
          pause = float(result.group(2).replace(',', '.'))
        
        pause_log = ""
        if pause is not None:
          pause_log = (f" - pause = '{pause}'")
        self.log.debug(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' 'value_to_write' = '{value_to_write}' ({cmd_format}){pause_log}")
        self.log.debug(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' 'address' (count) = '{address}' ({count})")

        func_code_dict = ServerDecoder.getFCdict()
        request_func = func_code_dict.get(int(cmd["cmdFctModbus"]), None)
        if request_func is None:
          self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' the function code is not available: {cmd['cmdFctModbus']}")
          return
        #func = getattr(self.client, request_func.function_code_name)

        payload:list = []
        if cmd_format == "bit":
          value = not (str(value_to_write) == '0' or str(value_to_write).lower() == 'false') # anything else than '0' or 'false' will be True
          payload = [value]

        else:
          try:
            if Lib.is_normal_number(cmd):
              value = float(value_to_write) if cmd_format in ("f", "d") else int(value_to_write)
              payload = Lib.convert_to_registers(value, cmd_format)

            elif cmd_format == "s":
              value = str(value_to_write)[:count * 2]
              payload = Lib.convert_to_registers(value, cmd_format)
              
            elif cmd_format.endswith("_sf"):
              value, sf = Lib.value_to_sf(value_to_write)
              payload = Lib.convert_to_registers(value, cmd_format[0]) + Lib.convert_to_registers(sf, "h")

              if len(payload) != count:
                self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' write command scale factor not possible: the registers are not contiguous")
                return
          
          except Exception as e:
            self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' register creation for the write command not possible: {e!s}")
            return

        attr = Lib.get_request_attribute(int(cmd["cmdFctModbus"]))
        req_payload = None
        if "coil" in request_func.function_code_name:
          req_payload = value
        else:
          payload = self.get_ordered_payload(array('H', payload), cmd)
          req_payload = payload
        if not attr.endswith("s") and hasattr(req_payload, "__iter__"):
          req_payload = req_payload[0]

        write_req_params = {
          "address": address,
          "slave": int(cmd["cmdSlave"]),
          attr: req_payload
        }
        self.log.debug(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' write_req_params = {write_req_params}")

        pmb_write_req = request_func(**write_req_params)
        self.log.debug(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' Fonction {pmb_write_req}")

        await self.async_connect()
        
        err_handeled = False
        try:
          async with self._lock:
            self.log.debug(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' Request sent")
            rr: ModbusResponse = await self.client.execute(pmb_write_req)
        except ModbusException as exc:
          error = f"modbus exception during write request on slave id {pmb_write_req.slave_id}, address {pmb_write_req.address} -> {exc!s}"
          self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' {error}")
          err_handeled = True
        if not err_handeled:
          if rr.isError():
            error = f"error during write request on slave id {pmb_write_req.slave_id}, address {pmb_write_req.address} -> {rr}"
            self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' {error}")
            err_handeled = True
        if not err_handeled:
          if isinstance(rr, ExceptionResponse):
            error = f"exception response during write request on slave id {pmb_write_req.slave_id}, address {pmb_write_req.address} -> {rr}"
            self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' {error}")
      
        if pause is not None:
          self.log.debug(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' Pausing for {pause} seconds")
          await asyncio.sleep(pause)

    except asyncio.CancelledError:
      self.log.debug(f"{self.eqConfig['name']}: 'command_write' cancelled")

  async def invalidate_blob(self, cmd_id: str) -> None:
    """
    Set the value 0 for this command if it is a register range
    """
    cmd = self.get_cmd_conf(cmd_id)
    if cmd is None:
      self.log.debug(f"{self.eqConfig['name']}: 'invalidate_blob' cmd is None")
      return
    if cmd["cmdFormat"] == 'blob':
      await self.add_change({f"values::{cmd['id']}": 0})
  
  def get_cmd_conf(self, cmd_id: str) -> dict | None:
    for cmd in self.eqConfig["cmds"]:
      if cmd["id"] == cmd_id:
        return cmd
    return None
  
  def get_payload(self, response: ModbusResponse, cmd: dict, blob: dict | None = None) -> array:
    attr = Lib.get_request_attribute(response.function_code)
    result = getattr(response, attr)
    payload = b''
    if attr == "bits":
      payload = pack_bitstring(result)
      if len(payload) % 2 == 1:
        payload += b'\x00'
    else:
      payload = result
    payload = array("H", payload)
    return self.get_ordered_payload(payload, cmd, blob)
  
  def get_ordered_payload(self, payload: array, cmd: dict, blob: dict | None = None) -> array:
    payload = array("H", payload)
    if cmd["cmdInvertBytes"] != "0":
      payload.byteswap()
    if cmd["cmdInvertWords"] != "0":
      payload = Lib.wordswap(payload, cmd, blob)
    return payload
  
  def on_connect_callback(self, connected: bool):
    self.log.debug(f"{self.eqConfig['name']}: 'on_connect_callback' called with connected = {connected}")
    if connected:
      self.connected.set()
    else:
      self.connected.clear()

  def close(self) -> asyncio.Task:
    return self.loop.create_task(self.async_close())

  async def async_close(self) -> None:
    if self.client:
      try:
        async with self._lock:
          self.client.close()
      except ModbusException as e:
        self.log.error(f"{self.eqConfig['name']}: the connection could not be closed: {e!s}")
    self.log.info(f"{self.eqConfig['name']}: Modbus communication closed")

    if self.should_terminate.is_set():
      self.terminate()

    self.stopped.set()
    self.connected.clear()

  async def wait_for_stopped(self):
    while not self.stopped.is_set():
      try:
        await asyncio.wait_for(self.stopped.wait(), 2)
      except TimeoutError:
        if hasattr(self, "_async_tasks"):
          for task in self._async_tasks:
            if (
              task.get_name() == f"run_loop_{self.eqConfig['id']}"
              and not task.done()
              and not task.cancelled()
            ):
              task.cancel()

  def terminate(self):
    return self.loop.create_task(self.async_terminate())
  
  async def async_terminate(self):
    if hasattr(self, "_async_tasks"):
      for task in self._async_tasks:
        task.cancel()
    await self.wait_for_stopped()
    try:
      del self._async_tasks
    except AttributeError:
      pass
    try:
      del self.client
    except AttributeError:
      pass
