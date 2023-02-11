# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# 
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

#import sys
#import os
import logging
import threading
import asyncio
import json

from pymodbus.payload import BinaryPayloadBuilder, BinaryPayloadDecoder
#from pymodbus.constants import Endian
#from pymodbus.exceptions import *

"""
-----------------------------------------------------------------------------
example: [{"id":"34","createtime":"2023-02-04 03:13:17","eqProtocol":"tcp","eqKeepopen":"0","eqPolling":"20","eqTcpAddr":"192.168.25.25","eqTcpPort":"502","eqTcpRtu":"0","eqWordEndianess":">","eqDWordEndianess":">","updatetime":"2023-02-07 16:58:18","eqSerialInterface":"/dev/tty0","eqSerialSlave":"20","eqSerialMethod":"rtu","eqSerialBaudrate":"19200","eqSerialBytesize":"8","eqSerialParity":"E","eqSerialStopbits":"1","refreshes":[],"cmds":[{"id":"117","infFctModbus":"1","infFormat":"bit","infAddr":"25","request":"","minValue":"","maxValue":""},{"id":"118","infFctModbus":"3","infFormat":"int16","infAddr":"74","request":"","minValue":"","maxValue":""}]}]
-----------------------------------------------------------------------------
>>> from pymodbus.client import ModbusTcpClient
>>> client = ModbusTcpClient(host='192.168.1.20',port='502')
>>> client.connect()
True
>>> rhr = client.read_holding_registers(12308, 6)
>>> rhr.registers
[2023, 2, 9, 22, 40, 0]
>>> client.close()

-----------------------------------------------------------------------------
asyncio
-----------------------------------------------------------------------------
async def run(self):

await AsyncModbus*Client.connect()
await AsyncModbus*Client.read/write_*()
await AsyncModbus*Client.close()

-----------------------------------------------------------------------------
Test
-----------------------------------------------------------------------------
from mymodbus import PyModbusClient
import json
config = json.loads('[{"id":"34","createtime":"2023-02-04 03:13:17","eqProtocol":"tcp","eqKeepopen":"0","eqPolling":"20","eqTcpAddr":"192.168.25.25","eqTcpPort":"502","eqTcpRtu":"0","eqWordEndianess":">","eqDWordEndianess":">","updatetime":"2023-02-07 16:58:18","eqSerialInterface":"/dev/tty0","eqSerialSlave":"20","eqSerialMethod":"rtu","eqSerialBaudrate":"19200","eqSerialBytesize":"8","eqSerialParity":"E","eqSerialStopbits":"1","refreshes":[],"cmds":[{"id":"117","infFctModbus":"1","infFormat":"bit","infAddr":"25","request":"","minValue":"","maxValue":""},{"id":"118","infFctModbus":"3","infFormat":"int16","infAddr":"74","request":"","minValue":"","maxValue":""}]}]')
t1 = PyModbusClient(config[0])


-----------------------------------------------------------------------------
"""

# -----------------------------------------------------------------------------
# MyModbus class
# -----------------------------------------------------------------------------

class MyModbus():
    def __init__(self, config):
        pass

# -----------------------------------------------------------------------------
# PyModbusClient classe
# -----------------------------------------------------------------------------

class PyModbusClient():
    def __init__(self, config, jcom=None):
        """ For pymodbus client
        """
        # Handle Run & Shutdown
        self.should_stop = threading.Event()
        self.should_stop.clear()
        
        # FIXME: utile ????
        self.jcom = jcom
        
        # Jeedom equipment id
        self.id = config['id']
        # To determine the framer and the client
        self.protocol = config['eqProtocol']
        # To configure the payload decoder
        self.byteorder = config['eqWordEndianess']
        self.wordorder = config['eqDWordEndianess']
        self.keepopen = config['eqKeepopen']
        self.pooling = float(config['eqPolling'])
        
        if self.protocol == 'tcp':
            # To determine the framer
            self.rtu = config['eqTcpRtu']
            # To determine the client
            self.address = config['eqTcpAddr']
            self.port = int(config['eqTcpPort'])
            
        elif self.protocol == 'udp':
            # To determine the framer
            self.rtu = config['eqUdpRtu']
            # To determine the client
            self.address = config['eqUdpAddr']
            self.port = config['eqUdpPort']
            
        elif self.protocol == 'serial':
            # To determine the framer
            self.method = int(config['eqSerialMethod'])
            # To determine the client
            self.interface = config['eqSerialInterface']
            self.baudrate = int(config['eqSerialBaudrate'])
            self.bytesize = int(config['eqSerialBytesize'])
            self.parity = config['eqSerialParity']
            self.stopbits = int(config['eqSerialStopbits'])
            # To configure the request
            self.slave = int(config['eqSerialSlave'])
        
        self.framer = self.get_framer()
        self.client = self.get_client()
        
        self.requests = self.get_requests(config['cmds'])
        
    def get_framer(self):
        if self.protocol in ('tcp', 'udp'):
            if self.rtu == '0':
                from pymodbus.framer.socket_framer import ModbusSocketFramer
                return ModbusSocketFramer
                
            elif self.rtu == '1':
                from pymodbus.framer.rtu_framer import ModbusRtuFramer
                return ModbusRtuFramer
                
        elif self.protocol == 'serial':
            if self.method == 'rtu':
                from pymodbus.framer.rtu_framer import ModbusRtuFramer
                return ModbusRtuFramer
                
            elif self.method == 'ascii':
                from pymodbus.framer.ascii_framer import ModbusAsciiFramer
                return ModbusAsciiFramer
        
    def get_client(self):
        if self.protocol == 'tcp':
            from pymodbus.client import AsyncModbusTcpClient
            return AsyncModbusTcpClient(host=self.address, port=self.port, framer=self.framer)
            
        elif self.protocol == 'udp':
            from pymodbus.client import AsyncModbusUdpClient
            return AsyncModbusUdpClient(host=self.address, port=self.port, framer=self.framer)
            
        elif self.protocol == 'serial':
            from pymodbus.client import AsyncModbusSerialClient
            return AsyncModbusSerialClient(port=self.interface, baudrate=self.baudrate, bytesize=self.bytesize,
                                            parity=self.parity, stopbits=self.stopbits, framer=self.framer)
        
    def get_requests(self, cmds):
        requests = {}
        for req_config in cmds:
            request = {}
            if 'infAddr' in req_config:
                request['type'] = 'r'
                prefix = 'inf'
            else:
                request['type'] = 'w'
                prefix = 'act'
                
            request['slave'] = 0
            if hasattr(self, 'slave'):
                request['slave'] = self.slave
                
            request['fct_modbus'] = req_config[prefix + 'FctModbus']
            request['addr'] = req_config[prefix + 'Addr']
            request['data_type'] = req_config[prefix + 'Format']
            
            # req_config['id'] is the Jeedom command id
            requests[req_config['id']] = request
        return requests
        
    async def read_coils(self, **kwargs):
        return self.client.read_coils(kwargs)
        
    def run(self):
        asyncio.run(self.run_loop())
        
    async def run_loop(self):
        # Don't do anything if there is no info command (read)
        for cmd_id, request in self.requests.items():
            if request['type'] == 'r':
                break
        else:
            return
        
        # Polling loop
        while not self.should_stop.is_set():
            # connect()
            if not self.client.is_socket_open():
                await self.client.connect()
            
            read_results = {}
            for cmd_id, request in self.requests.items():
                # Only read requests in the loop
                if request['type'] == 'w':
                    continue
                
                # Read coils (code 0x01) || Read discrete inputs (code 0x02)
                if request['fct_modbus'] in ('1', '2'):
                    if request['fct_modbus'] == '1':
                        await response = self.read_coils(address=request['addr'], count=1, slave=request['slave'])
                    elif request['fct_modbus'] == '2':
                        await response = self.client.read_discrete_inputs(address=request['addr'], count=1, slave=request['slave'])
                    
                    value = response.bits[0]
                    if request['data_type'] == 'bin-inv':
                        value = not value
                
                # Read holding registers (code 0x03) || Read input registers (code 0x04)
                if request['fct_modbus'] in ('3', '4'):count = 1 # valide for 8bit and 16bit
                    if request['data_type'].endswith('32'):
                        count = 2
                    elif request['data_type'].endswith('64'):
                        count = 4
                    
                    if request['fct_modbus'] == '3':
                        await response = self.client.read_holding_registers(address=request['addr'], count=count, slave=request['slave'])
                    elif request['fct_modbus'] == '4':
                        await response = self.client.read_input_registers(address=request['addr'], count=count, slave=request['slave'])
                    
                    decoder = BinaryPayloadDecoder.fromRegisters(response.registers, self.byteorder, self.wordorder)
                    
                    # Typ: Byte
                    if '8' in request['data_type']:
                        if request['data_type'].endswith('msb'): # FIXME: vérifier si msb ou lsb
                            skip = decoder.decode_8bit_int() # skip one byte
                        
                        if request['data_type'].startswith('int8'):
                            value = decoder.decode_8bit_int()
                        elif request['data_type'].startswith('uint8'):
                            value = decoder.decode_8bit_uint()
                        
                    # Typ: Word
                    elif request['data_type'].endswith('int16'):
                        value = decoder.decode_16bit_int()
                    elif request['data_type'].endswith('uint16'):
                        value = decoder.decode_16bit_uint()
                    elif request['data_type'].endswith('float16'):
                        value = decoder.decode_16bit_float()
                    
                    # Typ: Double word (DWord)
                    elif request['data_type'].endswith('int32'):
                        value = decoder.decode_32bit_int()
                    elif request['data_type'].endswith('uint32'):
                        value = decoder.decode_32bit_uint()
                    elif request['data_type'].endswith('float32'):
                        value = decoder.decode_32bit_float()
                    
                    # Typ: Double Dword
                    elif request['data_type'].endswith('int64'):
                        value = decoder.decode_64bit_int()
                    elif request['data_type'].endswith('uint64'):
                        value = decoder.decode_64bit_uint()
                    elif request['data_type'].endswith('float64'):
                        value = decoder.decode_64bit_float()
                    
                # Save the result of this request
                read_results[cmd_id] = value
            
            # Keep the connection open or not...
            if not self.keepopen:
                await self.client.close()
            
            # After all the requests
            if self.jcom is not None:
                self.jcom.send_change_immediate(json.dumps(read_results))
            
            # Pool time
            asyncio.sleep(self.pooling)
            
        
    def shutdown(self):
        self.should_stop.set()
        self.client.close()

# -----------------------------------------------------------------------------
    
