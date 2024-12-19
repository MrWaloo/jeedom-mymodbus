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

from pymodbus.exceptions import ModbusException
from pymodbus.pdu import DecodePDU, ExceptionResponse, ModbusPDU

from mymodbuslib import Lib
from mymodbusbase import MyModbusBase


class MyModbusClient(MyModbusBase):

  def __init__(
    self,
    eqConfig: dict[str, any],
    log: logging.Logger | None = None
  ) -> None:
    super().__init__(eqConfig, log)

  async def run_loop(self) -> None:
    """
    The daemon main loop
    """
    refresh_mode = self.eqConfig["eqRefreshMode"]
    self.log.debug(f"{self.eqConfig['name']}: 'run_loop' launched in mode '{refresh_mode}'")
    try:
      self._read_cycle = 0
      self._cycle_times = [None, None, None, None, None]
      if refresh_mode == "polling":
        polling_config = float(self.eqConfig["eqPolling"])
        polling = polling_config
        asyncio.create_task(self.send_polling(polling))

      while not self.should_stop.is_set():
        if refresh_mode == "on_event":
          self.log.debug(f"{self.eqConfig['name']}: 'run_loop' wait for CMD read")
          await self.read.wait()
          if self.should_stop.is_set():
            break
          self.stopped.clear()
          await self.async_connect()
        #self.log.debug(f"{self.eqConfig['name']}: 'run_loop' cycle {self._read_cycle}")
        
        begin = self.loop.time()
        cycle_with_error = await asyncio.wait_for(self.one_cycle_read(), None)
        
        duration = self.loop.time() - begin
        if refresh_mode == "polling":
          if duration > polling and not cycle_with_error:
            polling = (duration // polling_config + 1) * polling_config
            asyncio.create_task(self.send_polling(polling))
            warning = f"the polling time is too short! Setting it to {polling}"
            self.log.warning(f"{self.eqConfig['name']}: {warning}")
          wait_time = max(0, math.floor((polling - duration) * 10) / 10) # Arrondi à 0.1s en dessous
          await asyncio.sleep(wait_time)
        
        changes = {}
        if not cycle_with_error:
          self._cycle_times[self._read_cycle % len(self._cycle_times)] = duration
          #self.log.debug(f"{self.eqConfig['name']}: 'run_loop' _cycle_times {self._cycle_times}")
          changes["values::cycle_ok"] = {
            "value": 1,
            "eqId": self.eqConfig["id"]
          }
          if None not in self._cycle_times:
            changes["values::cycle_time"] = {
              "value": fmean(self._cycle_times),
              "eqId": self.eqConfig["id"]
            }
            self._cycle_times = [None for _ in self._cycle_times]
          self._read_cycle += 1
        
        if changes:
          self.loop.create_task(self.add_change(changes))

        self.read.clear()
        if refresh_mode == "on_event":
          if not self.should_stop.is_set():
            self.close()
          self.stopped.set()

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
            rr: ModbusPDU = await self.client.execute(no_response_expected=False, request=pmb_req)
        except ModbusException as exc:
          error_on_current_read = True
          error = f"exception during read request on slave id {pmb_req.slave_id}, address {pmb_req.address} -> {exc!s}"
        if not error_on_current_read:
          try:
            if rr.isError():
              error_on_current_read = True
              error = f"error during read request on slave id {pmb_req.slave_id}, address {pmb_req.address} -> {rr}"
          except AttributeError:
            error_on_current_read = True
            error = f"return error during read request on slave id {pmb_req.slave_id}, address {pmb_req.address} -> {rr}"
        if not error_on_current_read:
          if isinstance(rr, ExceptionResponse):
            error_on_current_read = True
            error = f"exception during read request on slave id {pmb_req.slave_id}, address {pmb_req.address} -> {rr}"
        
        if error_on_current_read:
          self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: {error}")
          error_or_exception = True
          self.loop.create_task(self.set_error(cmd))
          await asyncio.sleep(eqErrorDelay) # Laisse le temps pour revenir à la normale
          
        else:
          self.loop.create_task(self.process_read_response(cmd, rr))
          await asyncio.sleep(eqWriteCmdCheckTimeout) # Cède le contrôle aux autres tâches

      self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read' exit with error_or_exception = {error_or_exception}")
      return error_or_exception
        
    except asyncio.CancelledError:
      self.log.debug(f"{self.eqConfig['name']}: 'one_cycle_read' cancelled")
  
  async def process_read_response(self, cmd: dict, response: DecodePDU) -> None:
    """
    Reads DecodePDU and returns the value(s) to Jeedom
    """
    self.log.debug(f"{self.eqConfig['name']}: 'process_read_response' launched for command id = {cmd['id']}")
    change = {}
    if cmd["cmdFormat"] == 'blob':
      change[f"values::{cmd['id']}"] = 1
    dest_ids = self._blob_dest.get(int(cmd["id"]), None)
    if dest_ids is not None: # Plage de registres
      for dest_id in dest_ids:
        dest = self.get_cmd_conf(dest_id)
        if dest is None:
          continue
        try:
          change[f"values::{dest_id}"] = self.cmd_decode(response, dest, cmd)
        except Exception as e:
          self.log.error(f"{self.eqConfig['name']}: 'process_read_response' 'cmd_decode' for command id = {cmd['id']} (in register range with command id = {dest_id}) raised an exception: {e!s}")
    elif cmd["cmdFormat"] != 'blob': # Lecture pour une commande et pas pour un blob sans destination
      try:
        change[f"values::{cmd['id']}"] = self.cmd_decode(response, cmd)
      except Exception as e:
        self.log.error(f"{self.eqConfig['name']}: 'process_read_response' 'cmd_decode' for command id = {cmd['id']} raised an exception: {e!s}")
    
    await self.add_change(change)

  async def command_write(self, command: dict) -> None:
    """
    Execute the write request
    """
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
        #data_type = Lib.get_data_type(cmd_format) # not needed
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
        result = re.search(pause_pattern, str(value_to_write), re.IGNORECASE)
        if result:
          value_to_write = eval(result.group(1))
          pause = float(result.group(2).replace(',', '.'))
        
        pause_log = ""
        if pause is not None:
          pause_log = (f" - pause = '{pause}'")
        self.log.debug(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' 'value_to_write' = '{value_to_write}' ({cmd_format}){pause_log}")
        self.log.debug(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' 'address' (count) = '{address}' ({count})")

        decoder = DecodePDU(True)
        request_func = decoder.lookup.get(int(cmd["cmdFctModbus"]), None)
        if request_func is None:
          self.log.error(f"{self.eqConfig['name']}/{cmd['name']}: 'command_write' the function code is not available: {cmd['cmdFctModbus']}")
          return

        payload:list = []
        if cmd_format == "bit":
          value = str(value_to_write).lower() not in ("0", "false") # anything else than '0' or 'false' will be True
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
        if request_func.function_code in (1, 2, 5, 15):
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
            rr: DecodePDU = await self.client.execute(no_response_expected=False, request=pmb_write_req)
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

  async def set_error(self, cmd: dict) -> None:
    """
    Set the value 0 for this command if it is a register range and set cycle_ok to 0
    """
    changes = {}
    if cmd["cmdFormat"] == 'blob':
      changes[f"values::{cmd['id']}"] = 0
    changes["values::cycle_ok"] = {
      "value": 0,
      "eqId": self.eqConfig["id"]
    }
    self.loop.create_task(self.add_change(changes))
  
  async def send_polling(self, polling: float) -> None:
    change = {
      "values::polling": {
        "value": polling,
        "eqId": self.eqConfig["id"]
      }
    }
    self.loop.create_task(self.add_change(change))
