"""
Fonctions utilitaires pour le démon MyModbus
"""

import re

from array import array
from collections import namedtuple
from enum import Enum
from pymodbus.client.mixin import ModbusClientMixin


Request = namedtuple("Request", "func_code attr")
PMB_READ_REQUESTS: list[Request] = [
  Request(
    1,
    "bits"
  ),
  Request(
    2,
    "bits"
  ),
  Request(
    3,
    "registers"
  ),
  Request(
    4,
    "registers"
  ),
]
PMB_WRITE_REQUESTS = [
  Request(
    5,
    "value"
  ),
  Request(
    15,
    "values"
  ),
  Request(
    6,
    "value"
  ),
  Request(
    16,
    "values"
  ),
]
PMB_REQUESTS: list[Request] = []
PMB_REQUESTS.extend(PMB_READ_REQUESTS)
PMB_REQUESTS.extend(PMB_WRITE_REQUESTS)


class Lib():

  @classmethod
  def value_to_sf(cls, value) -> tuple:
    """Returns a tuple with ScaleFactor format"""
    if not isinstance(value, (int, float)) or value == 0:
      return (0, 0)
    
    s = str(value).rstrip('0')
    if '.' in s:
      sf = len(s.split('.')[1]) * -1
      val = value * 10 ** (sf * -1)
    else:
      val, sf = value, 0
      while val % 10 == 0:
        sf += 1
        val //= 10
    
    return (int(val), sf)

  @classmethod
  def is_normal_number(cls, cmd: dict) -> bool:
    cmd_format: str = cmd["cmdFormat"]
    return (
      cmd_format in ("h", "H", "i", "I", "q", "Q", "f", "d")
      or cmd_format.startswith("uint8")
    )

  @classmethod
  def get_val_sf(cls, cmd: dict) -> tuple:
    sf_pattern = r"(\d+)\s*?sf\s*?(\d+)"
    result = re.match(sf_pattern, cmd["cmdAddress"], re.IGNORECASE)
    addr_val = int(result.group(1))
    addr_sf = int(result.group(2))
    return (addr_val, addr_sf)

  @classmethod
  def get_data_type(cls, format: str) -> Enum:
      """Return the ModbusClientMixin.DATATYPE according to the format"""
      if format.startswith("uint8"):
        return cls.Uint8.UINT8
      for data_type in ModbusClientMixin.DATATYPE:
          if data_type.value[0] == format[0]:
              return data_type

  @classmethod
  def get_request_addr_count(cls, cmd: dict) -> tuple:
    """
    Returns a tuple with the address and the registers' count of the Jeedom command
    """
    #if cmd["cmdFctModbus"] == "fromBlob":
    #  return (None, None)
    cmd_format: str = cmd["cmdFormat"]
    cmd_address: str = cmd["cmdAddress"]

    normal_number = cls.is_normal_number(cmd)

    address = None
    count = 1 # valid for 1bit, 8bit and 16bit
    if normal_number:
      address = int(cmd_address)
      data_type = cls.get_data_type(cmd_format)
      count = data_type.value[1]

    else:
      array_pattern = r"(\d+)\s*\[\s*(\d+)\s*\]"
      if "bit" in cmd_format:
        result = re.match(array_pattern, cmd_address)
        if result:
          address = int(result.group(1))
          count = int(result.group(2))
        else:
          address = int(cmd_address)

      elif cmd_format == "s":
        result = re.match(array_pattern, cmd_address)
        address = int(result.group(1))
        strlen = int(result.group(2))
        if strlen % 2 == 1:
          strlen -= 1
        count = int(strlen / 2)

      elif cmd_format == "blob":
        result = re.match(array_pattern, cmd_address)
        address = int(result.group(1))
        count = int(result.group(2))

      elif cmd_format.endswith("_sf"):
        addr_val, addr_sf = cls.get_val_sf(cmd)
        data_type = cls.get_data_type(cmd_format)
        offset = 1 if addr_sf > addr_val else data_type.value[1]
        address = min(addr_val, addr_sf)
        count = abs(addr_val - addr_sf) + offset
    
    return (address, count)

  class Uint8(Enum):
    UINT8 = ("uint8", 1)

  @classmethod
  def wordswap(cls, payload: array) -> array:
    i = 0
    for e0, e1 in zip(payload[::2], payload[1::2]):
      payload[i] = e1
      payload[i + 1] = e0
      i += 2
    return payload

  @classmethod
  def get_request_attribute(cls, func_code) -> str | None:
    for entry in PMB_REQUESTS:
      if entry.func_code == func_code:
        return entry.attr
    return None