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
    - self._requests
    - self._blob_dest
    """
    pass

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
