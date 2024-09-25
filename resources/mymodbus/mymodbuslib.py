"""
Fonctions utilitaires pour le démon MyModbus
"""

import re
import struct
import sys

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
PMB_WRITE_REQUESTS: list[Request] = [
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
  def convert_from_registers(
    cls, registers: list[int], format: str
  ) -> int | float | str:
    """Inspired from ModbusClientMixin.convert_from_registers but with strict byte and word order
    """
    byte_list = bytearray()
    for x in registers:
      byte_list.extend(int.to_bytes(x, 2, sys.byteorder))
    if format == ModbusClientMixin.DATATYPE.STRING.value[0]:
      if byte_list[-1:] == b"\00":
        byte_list = byte_list[:-1]
      return byte_list.decode("utf-8")
    return struct.unpack(f"{format}", byte_list)[0]

  @classmethod
  def convert_to_registers(
    cls, value: int | float | str, format: str
  ) -> list[int]:
    """Inspired from ModbusClientMixin.convert_to_registers but with strict byte and word order
    """
    if format == ModbusClientMixin.DATATYPE.STRING.value[0]:
      if not isinstance(value, str):
        raise TypeError(f"Value should be string but is {type(value)}.")
      byte_list = value.encode()
      if len(byte_list) % 2:
        byte_list += b"\x00"
    else:
      byte_list = struct.pack(f"{format}", value)
    regs = [
      int.from_bytes(byte_list[x : x + 2], sys.byteorder)
      for x in range(0, len(byte_list), 2)
    ]
    return regs

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
    result = re.search(sf_pattern, cmd["cmdAddress"], re.IGNORECASE)
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
        result = re.search(array_pattern, cmd_address)
        if result:
          address = int(result.group(1))
          count = int(result.group(2))
        else:
          address = int(cmd_address)

      elif cmd_format == "s":
        result = re.search(array_pattern, cmd_address)
        address = int(result.group(1))
        strlen = int(result.group(2))
        if strlen % 2 == 1:
          strlen -= 1
        count = int(strlen / 2)

      elif cmd_format == "blob":
        result = re.search(array_pattern, cmd_address)
        address = int(result.group(1))
        count = int(result.group(2))

      elif cmd_format.endswith("_sf"):
        addr_val, addr_sf = cls.get_val_sf(cmd)
        data_type = cls.get_data_type(cmd_format)
        offset = 1 if addr_sf > addr_val else data_type.value[1]
        address = min(addr_val, addr_sf)
        count = abs(addr_val - addr_sf) + offset
    
    return (address, count)

  @classmethod
  def wordswap(cls, payload: array, cmd: dict, blob: dict | None = None) -> array:
    i = 0
    offset = 0
    if blob is not None:
      addr, count = cls.get_request_addr_count(cmd)
      blob_addr, blob_count = cls.get_request_addr_count(blob)
      offset = addr - blob_addr
      i = offset

    for e0, e1 in zip(payload[offset::2], payload[offset + 1::2]):
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

  class Uint8(Enum):
    UINT8 = ("uint8", 1)