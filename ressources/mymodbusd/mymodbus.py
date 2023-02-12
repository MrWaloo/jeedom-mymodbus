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

from pymodbus.payload import BinaryPayloadDecoder # BinaryPayloadBuilder
#from pymodbus.constants import Endian
from pymodbus.exceptions import *
from pymodbus.pdu import ExceptionResponse

"""
-----------------------------------------------------------------------------
example: [{"id":"34","createtime":"2023-02-04 03:13:17","eqProtocol":"tcp","eqKeepopen":"1","eqPolling":"10","eqTcpAddr":"192.168.1.20","eqTcpPort":"502","eqTcpRtu":"0","eqWordEndianess":">","eqDWordEndianess":"<","updatetime":"2023-02-12 14:19:48","eqSerialAddr":"20","eqSerialMethod":"rtu","eqSerialBaudrate":"19200","eqSerialBytesize":"8","eqSerialParity":"E","eqSerialStopbits":"1","refreshes":[],"eqUnitId":"1","cmds":[{"id":"118","infFctModbus":"3","infFormat":"int16","infAddr":"12308","request":"","minValue":"","maxValue":""},{"id":"121","infFctModbus":"3","infFormat":"int16","infAddr":"12309","request":"","minValue":"","maxValue":""},{"id":"122","infFctModbus":"3","infFormat":"int16","infAddr":"12310","request":"","minValue":"","maxValue":""},{"id":"123","infFctModbus":"3","infFormat":"int16","infAddr":"12311","request":"","minValue":"","maxValue":""},{"id":"124","infFctModbus":"3","infFormat":"int16","infAddr":"12312","request":"","minValue":"","maxValue":""},{"id":"125","infFctModbus":"3","infFormat":"int16","infAddr":"12313","request":"","minValue":"","maxValue":""},{"id":"126","infFctModbus":"3","infFormat":"float32","infAddr":"12352","request":"","minValue":"","maxValue":""}]}]
0487|[2023-02-12 14:19:54]INFO : Writing PID 1495991 to /tm
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
config = json.loads('[{"id":"34","createtime":"2023-02-04 03:13:17","eqProtocol":"tcp","eqKeepopen":"1","eqPolling":"10","eqTcpAddr":"192.168.1.20","eqTcpPort":"502","eqTcpRtu":"0","eqWordEndianess":">","eqDWordEndianess":"<","updatetime":"2023-02-12 14:19:48","eqSerialAddr":"20","eqSerialMethod":"rtu","eqSerialBaudrate":"19200","eqSerialBytesize":"8","eqSerialParity":"E","eqSerialStopbits":"1","refreshes":[],"eqUnitId":"1","cmds":[{"id":"118","infFctModbus":"3","infFormat":"int16","infAddr":"12308","request":"","minValue":"","maxValue":""},{"id":"121","infFctModbus":"3","infFormat":"int16","infAddr":"12309","request":"","minValue":"","maxValue":""},{"id":"122","infFctModbus":"3","infFormat":"int16","infAddr":"12310","request":"","minValue":"","maxValue":""},{"id":"123","infFctModbus":"3","infFormat":"int16","infAddr":"12311","request":"","minValue":"","maxValue":""},{"id":"124","infFctModbus":"3","infFormat":"int16","infAddr":"12312","request":"","minValue":"","maxValue":""},{"id":"125","infFctModbus":"3","infFormat":"int16","infAddr":"12313","request":"","minValue":"","maxValue":""},{"id":"126","infFctModbus":"3","infFormat":"float32","infAddr":"12352","request":"","minValue":"","maxValue":""}]}]')
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
        self.unit = float(config['eqUnitId']) # FIXME
        self.pooling = float(config['eqPolling'])
        
        if self.protocol == 'tcp':
            # To determine the framer
            self.rtu = config['eqTcpRtu'] == '1'
            # To determine the client
            self.address = config['eqTcpAddr']
            self.port = int(config['eqTcpPort'])
            
        elif self.protocol == 'udp':
            # To determine the framer
            self.rtu = config['eqUdpRtu'] == '1'
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
            if self.rtu:
                from pymodbus.framer.rtu_framer import ModbusRtuFramer
                return ModbusRtuFramer
                
            else:
                from pymodbus.framer.socket_framer import ModbusSocketFramer
                return ModbusSocketFramer
                
        elif self.protocol == 'serial':
            if self.method == 'rtu':
                from pymodbus.framer.rtu_framer import ModbusRtuFramer
                return ModbusRtuFramer
                
            elif self.method == 'ascii':
                from pymodbus.framer.ascii_framer import ModbusAsciiFramer
                return ModbusAsciiFramer
        
    def get_client(self):
        logging.debug('PyModbusClient: client protocol is:' + self.protocol)
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
                
            request['unit'] = self.unit # FIXME
            
            request['fct_modbus'] = req_config[prefix + 'FctModbus']
            request['addr'] = int(req_config[prefix + 'Addr'])
            request['data_type'] = req_config[prefix + 'Format']
            
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
        self.loop.stop()
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
        self.loop.add_signal_handler(signal.SIGTERM, self.signal_handler)
        
        # Polling loop
        while not self.should_stop.is_set():
            # connect() # FIXME
            reconnect = True
            # first method to determine if a reconnection is needed
            try:
                reconnect = not self.client.is_socket_open()
            except:
                reconnect = True
            # second method to determine if a reconnection is needed
            if reconnect:
                try:
                    reconnect = not self.connected
                except:
                    reconnect = True
            
            if reconnect:
                try:
                    await self.client.connect()
                except:
                    logging.error('PyModbusClient: Something went wront while connecting equipment id ' + self.id)
            
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
                            response = await self.client.read_coils(address=request['addr'], count=count, salve=request['slave']) # FIXME unit=request['unit']
                        elif request['fct_modbus'] == '2':
                            response = await self.client.read_discrete_inputs(address=request['addr'], count=count, salve=request['slave']) # FIXME unit=request['unit']
                        
                        request_ok = self.check_response(response)
                        if request_ok:
                            value = response.bits[0]
                            if request['data_type'] == 'bin-inv':
                                value = not value
                    
                    except ModbusException as exc:
                        request_ok = False
                        
                # Read holding registers (code 0x03) || Read input registers (code 0x04)
                if request['fct_modbus'] in ('3', '4'):
                    count = 1 # valide for 8bit and 16bit
                    if request['data_type'].endswith('32'):
                        count = 2
                    elif request['data_type'].endswith('64'):
                        count = 4
                    
                    try:
                        if request['fct_modbus'] == '3':
                            response = await self.client.read_holding_registers(address=request['addr'], count=count, salve=request['slave']) # FIXME unit=request['unit']
                        elif request['fct_modbus'] == '4':
                            response = await self.client.read_input_registers(address=request['addr'], count=count, salve=request['slave']) # FIXME unit=request['unit']
                        
                        request_ok = self.check_response(response)
                        if request_ok:
                            decoder = BinaryPayloadDecoder.fromRegisters(response.registers, self.byteorder, self.wordorder)
                            
                            # Typ: Byte
                            if '8' in request['data_type']:
                                if request['data_type'].endswith('msb'): # FIXME: vérifier si msb ou lsb
                                    skip = decoder.decode_8bit_int() # skip one byte
                                
                                if request['data_type'].startswith('int8'):
                                    value = decoder.decode_8bit_int()
                                elif request['data_type'].startswith('uint8'):
                                    value = decoder.decode_8bit_uint()
                                
                            # Typ: Word (16bit)
                            elif request['data_type'] == 'int16':
                                value = decoder.decode_16bit_int()
                            elif request['data_type'] == 'uint16':
                                value = decoder.decode_16bit_uint()
                            elif request['data_type'] == 'float16':
                                value = decoder.decode_16bit_float()
                            
                            # Typ: Dword (32bit)
                            elif request['data_type'] == 'int32':
                                value = decoder.decode_32bit_int()
                            elif request['data_type'] == 'uint32':
                                value = decoder.decode_32bit_uint()
                            elif request['data_type'] == 'float32':
                                value = decoder.decode_32bit_float()
                            
                            # Typ: Double Dword (64bit)
                            elif request['data_type'] == 'int64':
                                value = decoder.decode_64bit_int()
                            elif request['data_type'] == 'uint64':
                                value = decoder.decode_64bit_uint()
                            elif request['data_type'] == 'float64':
                                value = decoder.decode_64bit_float()
                    
                    except ModbusException as exc:
                        request_ok = False
                        
                # Save the result of this request
                if request_ok:
                    read_results[cmd_id] = value
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
            await asyncio.sleep(self.pooling)
            
        # The loop has exited (should never happend)
        try:
            await self.client.close()
        except:
            pass
        
    def shutdown(self):
        self.should_stop.set()
        self.client.close()
        
