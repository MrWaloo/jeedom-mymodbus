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
from pymodbus.pdu import ExceptionResponse, ModbusPDU
from pymodbus.pdu.decoders import DecodePDU

from mymodbuslib import Lib
from mymodbusbase import MyModbusBase


class MyModbusTest(MyModbusBase):

  def read_eqConfig(self, eqConfig: dict[str, any] | None = None) -> None:
    """
    Creates the client and the requests according to the configuration

    Sets:
    - eventually self.eqConfig
    - self._client_params
    - self._requests (in the subclass)
    - self._blob_dest (in the subclass)
    """
    super().read_eqConfig(eqConfig)
    
    self._requests = {}
    self._blob_dest = {}
    
    # Création de la liste des requêtes pymodbus
    decoder = DecodePDU(True)
    request_func = decoder.lookup.get(int(eqConfig["eqRegTestFunction"]), None)
    if request_func is None:
      error = f"le code de fonction Modbus n'est pas disponible: {eqConfig["eqRegTestFunction"]}"
      self.log.error(f"{self.eqConfig['name']}: {error}")
      return
    eqRegTestFirst = int(eqConfig['eqRegTestFirst'])
    eqRegTestLast = int(eqConfig['eqRegTestLast'])
    dev_id = int(eqConfig['eqRegTestSlave'])
    for address in range(eqRegTestFirst, eqRegTestLast + 1):
      self._requests[reg] = request_func(address=address, count=1, dev_id=dev_id)
      self.log.debug(f"{self.eqConfig['name']}: 'read_eqConfig' Modbus request for address {address}: {self._requests[address]}")

  async def run_loop(self) -> None:
    """
    The daemon main loop
    """
    pass

  async def command_write(self, command: dict) -> None:
    """
    Execute the write request
    """
    pass
