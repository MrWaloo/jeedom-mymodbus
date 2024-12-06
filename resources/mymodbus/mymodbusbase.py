"""
Interface between MyModbus and pymodbus

In this code 'pmb' is used for PyModBus

The logic is the same than in the modbus implementation in Home Assistant as far as I could
"""

import asyncio
import logging
from array import array
from statistics import fmean

from pymodbus import FramerType
from pymodbus.client import AsyncModbusSerialClient, AsyncModbusTcpClient, AsyncModbusUdpClient
from pymodbus.exceptions import ModbusException
from pymodbus.logging import pymodbus_apply_logging_config
from pymodbus.pdu import ModbusPDU
from pymodbus.pdu.decoders import DecodePDU
from pymodbus.utilities import (
    pack_bitstring,
    unpack_bitstring,
)

from mymodbuslib import Lib


class MyModbusBase(object):

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
    self._requests: dict[str, ModbusPDU] = {}
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
    self.downstream = asyncio.Queue() # Daemon -> MyModbusBase
    self.upstream = asyncio.Queue() # MyModbusBase -> Daemon

    self._async_tasks.append(self.loop.create_task(
      self.read_downstream(),
      name = f"read_downstream_{self.eqConfig['id']}"
    ))

  def read_eqConfig(self, eqConfig: dict[str, any] | None = None) -> None:
    """
    Creates the client and the requests according to the configuration
    """
    if self.client and self.client.connected or self.connected.is_set():
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
    decoder = DecodePDU(True)
    for cmd in self.eqConfig["cmds"]:
      if cmd["type"] != "info":
        continue
      if cmd["cmdFctModbus"] == "fromBlob":
        if self._blob_dest.get(int(cmd["cmdSourceBlob"]), None) is None:
          self._blob_dest[int(cmd["cmdSourceBlob"])] = []
        self._blob_dest[int(cmd["cmdSourceBlob"])].append(cmd["id"])
        
      else: # not fromBlob
        request_func = decoder.lookup.get(int(cmd["cmdFctModbus"]), None)
        if request_func is None:
          error = f"le code de fonction Modbus n'est pas disponible: {cmd['cmdFctModbus']}"
          self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: {error}")
          continue
        address, count = Lib.get_request_addr_count(cmd)
        slave = int(cmd["cmdSlave"])
        self._requests[cmd["id"]] = request_func(address, count, slave)
        self.log.debug(f"{self.eqConfig['name']}: 'read_eqConfig' Modbus request for cmd id {cmd['id']}: {self._requests[cmd['id']]}")

  async def read_downstream(self) -> None:
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
            
          elif action == "write" and hasattr(self, "command_write"):
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

  async def send_to_jeedom(self, payload) -> None:
    self.log.debug(f"{self.eqConfig['name']}: 'send_to_jeedom' launched with payload = {payload}")
    await self.upstream.put({"to_jeedom": payload})

  async def add_change(self, payload) -> None:
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
    
    if first_call and hasattr(self, "run_loop"):
      self.log.info(f"{self.eqConfig['name']}: 'async_connect' first call")
      await asyncio.sleep(float(self.eqConfig["eqFirstDelay"]))
      self._async_tasks.append(self.loop.create_task(
        self.run_loop(),
        name = f"run_loop_{self.eqConfig['id']}"
      ))

  async def run_loop(self) -> None:
    """
    The daemon main loop
    Has to be defined in the sub class
    """
    pass

  def cmd_decode(self, response: DecodePDU, cmd: dict, blob: dict | None = None) -> any:
    self.log.debug(f"{self.eqConfig['name']}: 'cmd_decode' launched for command id = {cmd['id']}")
    address, count = Lib.get_request_addr_count(cmd)
    cmd_format: str = cmd["cmdFormat"]
    #data_type = Lib.get_data_type(cmd_format) # not needed
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
    
    try:
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
      
    except Exception as e:
      raise e

  async def command_write(self, command: dict) -> None:
    """
    Execute the write request
    Has to be defined in the sub class
    """
    pass
  
  def get_cmd_conf(self, cmd_id: str) -> dict | None:
    for cmd in self.eqConfig["cmds"]:
      if cmd["id"] == cmd_id:
        return cmd
    return None

  def get_payload(self, response: DecodePDU, cmd: dict, blob: dict | None = None) -> array:
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
    if cmd["cmdInvertDWords"] != "0":
      payload = Lib.dwordswap(payload, cmd, blob)
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

  async def wait_for_stopped(self) -> None:
    while not self.stopped.is_set():
      try:
        await asyncio.wait_for(self.stopped.wait(), 2)
      except TimeoutError:
        self.cancel_run_loop()
    if self.eqConfig["eqRefreshMode"] == "on_event":
      self.cancel_run_loop()
    self.remove_done_run_loop()

  def cancel_run_loop(self) -> None:
    if hasattr(self, "_async_tasks"):
      for task in self._async_tasks:
        if (
          task.get_name() == f"run_loop_{self.eqConfig['id']}"
          and not task.done()
          and not task.cancelled()
        ):
          task.cancel()

  def remove_done_run_loop(self) -> None:
    if hasattr(self, "_async_tasks"):
      for i in range(len(self._async_tasks) - 1, -1, -1):
        task = self._async_tasks[i]
        if task.get_name() == f"run_loop_{self.eqConfig['id']}" and task.done():
          del self._async_tasks[i]

  def terminate(self) -> asyncio.Task:
    return self.loop.create_task(self.async_terminate())
  
  async def async_terminate(self) -> None:
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
