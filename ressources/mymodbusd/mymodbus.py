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

import signal
import json
import logging
import threading
import asyncio
import re
import struct

from pymodbus.client import AsyncModbusTcpClient, AsyncModbusUdpClient, AsyncModbusSerialClient

from pymodbus.framer.socket_framer import ModbusSocketFramer
from pymodbus.framer.rtu_framer import ModbusRtuFramer
from pymodbus.framer.ascii_framer import ModbusAsciiFramer
from pymodbus.framer.binary_framer import ModbusBinaryFramer

from pymodbus.payload import BinaryPayloadDecoder # BinaryPayloadBuilder
#from pymodbus.constants import Endian
from pymodbus.exceptions import *
from pymodbus.pdu import ExceptionResponse

"""
-----------------------------------------------------------------------------
example:  [{"createtime":"2023-02-04 03:13:17","eqProtocol":"tcp","eqKeepopen":"0","eqPolling":"60","eqTcpAddr":"192.168.1.20","eqTcpPort":"502","eqTcpRtu":"0","eqWordEndianess":">","eqDWordEndianess":"<","updatetime":"2023-02-14 21:35:08","eqSerialAddr":"20","eqSerialMethod":"rtu","eqSerialBaudrate":"19200","eqSerialBytesize":"8","eqSerialParity":"E","eqSerialStopbits":"1","refreshes":[],"eqUnitId":"1","id":"34","cmds":[{"infFctModbus":"3","infFormat":"int16","infAddr":"12308","request":"","minValue":"","maxValue":"","infSlave":"0","id":"118"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12309","request":"","minValue":"","maxValue":"","infSlave":"0","id":"121"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12310","request":"","minValue":"","maxValue":"","infSlave":"0","id":"122"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12311","request":"","minValue":"","maxValue":"","infSlave":"0","id":"123"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12312","request":"","minValue":"","maxValue":"","infSlave":"0","id":"124"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12313","request":"","minValue":"","maxValue":"","infSlave":"0","id":"125"},{"infFctModbus":"3","infFormat":"float32","infAddr":"12352","request":"","minValue":"","maxValue":"","infSlave":"0","id":"126"}]}]
-----------------------------------------------------------------------------
from pymodbus.client import ModbusTcpClient
client = ModbusTcpClient(host='192.168.1.20',port='502')
client.connect()
# True
rhr = client.read_holding_registers(12308, 6)
rhr.registers
# [2023, 2, 9, 22, 40, 0]
client.close()

-----------------------------------------------------------------------------
Test
-----------------------------------------------------------------------------
from mymodbus import PyModbusClient
import json
config = json.loads('[{"createtime":"2023-02-04 03:13:17","eqProtocol":"tcp","eqKeepopen":"0","eqPolling":"60","eqTcpAddr":"192.168.1.20","eqTcpPort":"502","eqTcpRtu":"0","eqWordEndianess":">","eqDWordEndianess":"<","updatetime":"2023-02-14 21:35:08","eqSerialAddr":"20","eqSerialMethod":"rtu","eqSerialBaudrate":"19200","eqSerialBytesize":"8","eqSerialParity":"E","eqSerialStopbits":"1","refreshes":[],"eqUnitId":"1","id":"34","cmds":[{"infFctModbus":"3","infFormat":"int16","infAddr":"12308","request":"","minValue":"","maxValue":"","infSlave":"0","id":"118"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12309","request":"","minValue":"","maxValue":"","infSlave":"0","id":"121"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12310","request":"","minValue":"","maxValue":"","infSlave":"0","id":"122"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12311","request":"","minValue":"","maxValue":"","infSlave":"0","id":"123"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12312","request":"","minValue":"","maxValue":"","infSlave":"0","id":"124"},{"infFctModbus":"3","infFormat":"int16","infAddr":"12313","request":"","minValue":"","maxValue":"","infSlave":"0","id":"125"},{"infFctModbus":"3","infFormat":"float32","infAddr":"12352","request":"","minValue":"","maxValue":"","infSlave":"0","id":"126"}]}]')
foo = PyModbusClient(config[0])

-----------------------------------------------------------------------------
"""

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
        
        self.jcom = jcom
        
        # Jeedom equipment id
        self.id = config['id']
        # To determine the framer and the client
        self.protocol = config['eqProtocol']
        # To configure the payload decoder
        self.byteorder = config['eqWordEndianess']
        self.wordorder = config['eqDWordEndianess']
        self.keepopen = config['eqKeepopen'] == '1'
        self.pooling = max(float(config['eqPolling']), 10.0) # at least 10 seconds
        
        if self.protocol == 'tcp':
            # To determine the framer
            self.rtu = config['eqTcpRtu'] == '1'
            # To determine the client
            self.address = config['eqTcpAddr']
            self.port = int(config['eqTcpPort'])
            
        elif self.protocol == 'udp':
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
        
        self.framer = self.get_framer()
        self.client = self.get_client()
        
        self.requests = self.get_requests(config['cmds'])
        
    def get_framer(self):
        if self.protocol == 'tcp':
            if self.rtu:
                return ModbusRtuFramer
            else:
                return ModbusSocketFramer
        
        if self.protocol == 'udp':
            return ModbusSocketFramer
                
        elif self.protocol == 'serial':
            if self.method == 'rtu':
                return ModbusRtuFramer
            elif self.method == 'ascii':
                return ModbusAsciiFramer
            elif self.method == 'binary':
                return ModbusBinaryFramer
        
    def get_client(self):
        logging.debug('PyModbusClient: client protocol is:' + self.protocol)
        if self.protocol == 'tcp':
            return AsyncModbusTcpClient(host=self.address, port=self.port, framer=self.framer)
            
        elif self.protocol == 'udp':
            return AsyncModbusUdpClient(host=self.address, port=self.port, framer=self.framer)
            
        elif self.protocol == 'serial':
            return AsyncModbusSerialClient(port=self.interface, baudrate=self.baudrate, bytesize=self.bytesize,
                                            parity=self.parity, stopbits=self.stopbits, framer=self.framer)
        
    def get_requests(self, cmds):
        re_string_address = re.compile(r"(\d+)[\(\[\{](\d+)[\)\]\}]")
        re_sf = re.compile(r"(\d+)sf(\d+)")
        requests = {}
        for req_config in cmds:
            request = {}
            if 'infAddr' in req_config:
                request['type'] = 'r'
                prefix = 'inf'
            else:
                request['type'] = 'w'
                prefix = 'act'
                
            request['slave'] = req_config[prefix + 'Slave']
            request['fct_modbus'] = req_config[prefix + 'FctModbus']
            request['data_type'] = req_config[prefix + 'Format']
            # address according to data type
            # string
            if request['data_type'].startswith('string'):
                re_match = re_string_address.match(req_config[prefix + 'Addr'])
                if re_match:
                    request['addr'] = int(re_match.group(1))
                    request['strlen'] = int(re_match.group(2))
                    
            # solaredge scale factor
            elif request['data_type'].endswith('se-sf'):
                re_match = re_sf.match(req_config[prefix + 'Addr'])
                if re_match:
                    request['addr'] = int(re_match.group(1))
                    request['sf'] = int(re_match.group(2))
                    
            else:
                request['addr'] = int(req_config[prefix + 'Addr'])
            
            # req_config['id'] is the Jeedom command id
            requests[req_config['id']] = request
        logging.debug('PyModbusClient: requests:' + json.dumps(requests))
        return requests
        
    def check_response(self, response):
        if response.isError():
            logging.debug('PyModbusClient: pymodbus returned an error!')
            return False
        if isinstance(response, ExceptionResponse):
            logging.debug('PyModbusClient: received exception from device')
            return False
        return True
        
    def signal_handler(self, signum=None, frame=None):
        self.shutdown()
        
    async def run(self):
        # Don't do anything if there is no info command (read)
        for cmd_id, request in self.requests.items():
            if request['type'] == 'r':
                break
        else:
            logging.debug('PyModbusClient: run: nothing to do... exit')
            return
        
        # SIGTERM catcher # FIXME
        self.loop = asyncio.get_event_loop()
        self.loop.add_signal_handler(signal.SIGINT, self.signal_handler)
        self.loop.add_signal_handler(signal.SIGTERM, self.signal_handler)
        
        # Polling loop
        while not self.should_stop.is_set():
            
            # Connect
            try:
                await self.client.connect()
                await asyncio.sleep(1)
            except:
                logging.error('PyModbusClient: Something went wront while connecting to equipment id ' + self.id)
            
            # Request: await self.client.read_*
            read_results = {}
            for cmd_id, request in self.requests.items():
                # Only read requests in the loop
                if request['type'] == 'w':
                    continue
                
                request_ok = True
                
                # Read coils (code 0x01) || Read discrete inputs (code 0x02)
                if request['fct_modbus'] in ('1', '2'):
                    count = 1
                    
                    try:
                        if request['fct_modbus'] == '1':
                            response = await self.client.read_coils(address=request['addr'], count=count, salve=request['slave'])
                        elif request['fct_modbus'] == '2':
                            response = await self.client.read_discrete_inputs(address=request['addr'], count=count, salve=request['slave'])
                        
                        request_ok = self.check_response(response)
                        if request_ok:
                            value = response.bits[0]
                            if request['data_type'] == 'bin-inv':
                                value = not value
                    
                    except ModbusException as exc:
                        request_ok = False
                        
                # Read holding registers (code 0x03) || Read input registers (code 0x04)
                elif request['fct_modbus'] in ('3', '4'):
                    normal_number = request['data_type'][-2:] in ('16', '32', '64') and request['data_type'][:-2] in ('int', 'uint', 'float')
                    se_sf = request['data_type'].endswith('se-sf') # solaredge scale factor
                    
                    # bytes count to read
                    count = 1 # valid for 8bit and 16bit
                    if normal_number:
                        if  request['data_type'].endswith('32'):
                            count = 2
                        elif request['data_type'].endswith('64'):
                            count = 4
                    elif request['data_type'].startswith('string'):
                        if request['strlen'] % 2 == 1:
                            request['strlen'] -= 1
                        count = int(request['strlen'] / 2)
                    elif se_sf:
                        count = request['sf'] - request['addr'] + 1
                    
                    try:
                        if request['fct_modbus'] == '3':
                            response = await self.client.read_holding_registers(address=request['addr'], count=count, salve=request['slave'])
                        elif request['fct_modbus'] == '4':
                            response = await self.client.read_input_registers(address=request['addr'], count=count, salve=request['slave'])
                        
                        request_ok = self.check_response(response)
                        if request_ok:
                            decoder = BinaryPayloadDecoder.fromRegisters(response.registers, self.byteorder, self.wordorder)
                            
                            # Typ: Byte
                            if '8' in request['data_type']:
                                if request['data_type'].endswith('msb'): # FIXME: vérifier si msb ou lsb
                                    decoder.skip_bytes(1)
                                
                                if request['data_type'].startswith('int8'):
                                    value = decoder.decode_8bit_int()
                                elif request['data_type'].startswith('uint8'):
                                    value = decoder.decode_8bit_uint()
                                
                            # Typ: Word (16bit) || Dword (32bit) || Double Dword (64bit)
                            elif normal_number:
                               value = getattr(decoder, 'decode_' + request['data_type'][-2:] + 'bit_' + request['data_type'][:-2])()
                               
                            # string
                            elif request['data_type'].startswith('string'):
                                value = decoder.decode_string(request['strlen'])
                                if request['data_type'] == 'string-swap':
                                    value = struct.pack('>' + 'H' * count, *struct.unpack('<' + 'H' * count, value))
                                
                            # solaredge scale factor
                            elif se_sf:
                                offset = 0
                                if request['data_type'].startswith('int16'):
                                    value = decoder.decode_16bit_int()
                                    offset = 1
                                elif request['data_type'].startswith('uint16'):
                                    value = decoder.decode_16bit_uint()
                                    offset = 1
                                elif request['data_type'].startswith('uint32'):
                                    value = decoder.decode_32bit_uint()
                                    offset = 2
                                decoder.skip_bytes((count - offset - 1) * 2)
                                sf = decoder.decode_16bit_int()
                                value = value * 10 ** sf
                    
                    except ModbusException as exc:
                        request_ok = False
                        
                # Save the result of this request
                if request_ok:
                    read_results[cmd_id] = value
                    if request['data_type'].startswith('string'):
                        read_results[cmd_id] = value.decode()
                    logging.debug('PyModbusClient: read value: ' + str(value))
                else:
                    logging.error('PyModbusClient: Something went wront while reading command id ' + cmd_id)
            
            # After all the requests
            # Keep the connection open or not...
            if not self.keepopen:
                try:
                    await self.client.close()
                except:
                    logging.error('PyModbusClient: Something went wront while closing connection to equipment id ' + self.id)
            
            # Send results to jeedom
            if read_results:
                if self.jcom is not None:
                    self.jcom.send_change_immediate({'values': read_results})
                # Or show them in the log
                else:
                    logging.info('PyModbusClient: read_results:' + json.dumps(read_results))
            
            # Polling time
            await asyncio.sleep(self.pooling - 1)
            
        # The loop has exited (should never happend)
        try:
            await self.client.close()
        except:
            pass
        
    def shutdown(self):
        self.should_stop.set()
        self.loop.stop()
        try:
            self.client.close()
        except:
            pass
        
