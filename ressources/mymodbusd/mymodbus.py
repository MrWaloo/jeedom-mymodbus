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
import time
import threading
import asyncio
import re
from queue import (Empty, Full)

from pymodbus.client import (AsyncModbusTcpClient, AsyncModbusUdpClient, AsyncModbusSerialClient)
from pymodbus.framer.socket_framer import ModbusSocketFramer
from pymodbus.framer.rtu_framer import ModbusRtuFramer
from pymodbus.framer.ascii_framer import ModbusAsciiFramer
from pymodbus.framer.binary_framer import ModbusBinaryFramer
from pymodbus.payload import (BinaryPayloadDecoder, BinaryPayloadBuilder)
from pymodbus.pdu import ExceptionResponse

from mymodbuslib import *

"""
-----------------------------------------------------------------------------
Test
-----------------------------------------------------------------------------
cd /var/www/html/plugins/mymodbus/ressources/mymodbusd/

from mymodbus import PyModbusClient
import json
config = json.loads('[{"id":"34","name":"Wago-garage","eqProtocol":"tcp","eqKeepopen":"0","eqPolling":"10","eqTcpAddr":"192.168.1.21","eqTcpPort":"502","eqTcpRtu":"0","cmds":[{"id":"115","name":"Besoin \u00e9clairage","type":"info","cmdSlave":"0","cmdFctModbus":"1","cmdFormat":"bit","cmdAddress":"12288","cmdFrequency":"1","cmdInvertBytes":"0","cmdInvertWords":"1"}]},{"id":"33","name":"Wago-knx","eqProtocol":"tcp","eqKeepopen":"0","eqPolling":"10","eqTcpAddr":"192.168.1.20","eqTcpPort":"502","eqTcpRtu":"0","cmds":[{"id":"113","name":"year","type":"info","cmdSlave":"0","cmdFctModbus":"3","cmdFormat":"int16","cmdAddress":"12308","cmdFrequency":"100","cmdInvertBytes":"0","cmdInvertWords":"1"},{"id":"116","name":"month","type":"info","cmdSlave":"0","cmdFctModbus":"3","cmdFormat":"int16","cmdAddress":"12309","cmdFrequency":"100","cmdInvertBytes":"0","cmdInvertWords":"1"},{"id":"117","name":"day","type":"info","cmdSlave":"0","cmdFctModbus":"3","cmdFormat":"int16","cmdAddress":"12310","cmdFrequency":"10","cmdInvertBytes":"0","cmdInvertWords":"1"},{"id":"118","name":"hour","type":"info","cmdSlave":"0","cmdFctModbus":"3","cmdFormat":"int16","cmdAddress":"12311","cmdFrequency":"1","cmdInvertBytes":"0","cmdInvertWords":"1"},{"id":"124","name":"minutes","type":"info","cmdSlave":"0","cmdFctModbus":"3","cmdFormat":"int16","cmdAddress":"12312","cmdFrequency":"1","cmdInvertBytes":"0","cmdInvertWords":"1"},{"id":"114","name":"wr test","type":"action","cmdSlave":"0","cmdFctModbus":"16","cmdFormat":"uint16","cmdAddress":"12468","cmdFrequency":"","cmdInvertBytes":"0","cmdInvertWords":"1"},{"id":"123","name":"wr read 12468","type":"info","cmdSlave":"0","cmdFctModbus":"3","cmdFormat":"uint16","cmdAddress":"12468","cmdFrequency":"1","cmdInvertBytes":"0","cmdInvertWords":"1"},{"id":"125","name":"wr read 12468 LSB","type":"info","cmdSlave":"0","cmdFctModbus":"3","cmdFormat":"uint8-lsb","cmdAddress":"12468","cmdFrequency":"1","cmdInvertBytes":"0","cmdInvertWords":"1"},{"id":"126","name":"wr read 12468 MSB","type":"info","cmdSlave":"0","cmdFctModbus":"3","cmdFormat":"uint8-msb","cmdAddress":"12468","cmdFrequency":"1","cmdInvertBytes":"0","cmdInvertWords":"1"}]}]')
foo = PyModbusClient(config[0])
bar = PyModbusClient(config[1])

"""

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
        self.polling_config = float(config['eqPolling'])
        self.polling = float(config['eqPolling']) * 1.0
        self.keepopen = config['eqKeepopen'] == '1'
        self.eqProtocol = config['eqProtocol']
        
        self.framer, self.client = PyModbusClient.get_framer_and_client(config)
        
        self.requests = PyModbusClient.get_requests(config['cmds'])
        self.write_cmds = []
        
        self.cycle = 0
        
    @staticmethod
    def get_framer_and_client(config):
        logging.debug('PyModbusClient: client protocol is:' + config['eqProtocol'])
        if config['eqProtocol'] == 'tcp':
            if config['eqTcpRtu'] == '1':
                framer = ModbusRtuFramer
            else:
                framer = ModbusSocketFramer
        
            client = AsyncModbusTcpClient(host=config['eqTcpAddr'], port=int(config['eqTcpPort']), framer=framer)
            
        if config['eqProtocol'] == 'udp':
            framer = ModbusSocketFramer
            client = AsyncModbusUdpClient(host=config['eqUdpAddr'], port=int(config['eqUdpPort']), framer=framer)
                
        elif config['eqProtocol'] == 'serial':
            if config['eqSerialMethod'] == 'rtu':
                framer = ModbusRtuFramer
            elif config['eqSerialMethod'] == 'ascii':
                framer = ModbusAsciiFramer
            elif config['eqSerialMethod'] == 'binary':
                framer = ModbusBinaryFramer
            
            client = AsyncModbusSerialClient(port=config['eqSerialInterface'], baudrate=int(config['eqSerialBaudrate']), bytesize=int(config['eqSerialBytesize']),
                                            parity=config['eqSerialParity'], stopbits=int(config['eqSerialStopbits']), framer=framer)
        return framer, client
        
    @staticmethod
    def get_requests(cmds):
        re_string_address = re.compile(r"(\d+)\s*?[\(\[\{]\s*?(\d+)\s*?[\)\]\}]")
        re_sf = re.compile(r"(\d+)\s*?(sf|SF|Sf|sF)\s*?(\d+)")
        requests = {}
        for req_config in cmds:
            request = {}
            request['last_value'] = None
            request['type'] = req_config['type']
            request['name'] = req_config['name']
            request['slave'] = int(req_config['cmdSlave'])
            request['fct_modbus'] = req_config['cmdFctModbus']
            request['data_type'] = req_config['cmdFormat']
            # address according to data type
            # string
            if request['data_type'] == 'string':
                re_match = re_string_address.match(req_config['cmdAddress'])
                if re_match:
                    request['addr'] = int(re_match.group(1))
                    request['strlen'] = int(re_match.group(2))
                    
            # SunSpec scale factor
            elif request['data_type'].endswith('sp-sf'):
                re_match = re_sf.match(req_config['cmdAddress'])
                if re_match:
                    request['addr'] = int(re_match.group(1))
                    request['sf'] = int(re_match.group(3))
                    
            else:
                request['addr'] = int(req_config['cmdAddress'])
            if request['type'] == 'info':
                request['freq'] = int(req_config['cmdFrequency'])
            # Endianess
            request['byteorder'] = '>' if req_config['cmdInvertBytes'] == '0' else '<'
            request['wordorder'] = '>' if req_config['cmdInvertWords'] == '0' else '<'
            # req_config['id'] is the Jeedom command id
            requests[req_config['id']] = request
        logging.debug('PyModbusClient: requests:' + json.dumps(requests))
        return requests
        
    @staticmethod
    def check_response(response):
        if response.isError():
            logging.debug('PyModbusClient: pymodbus returned an error!')
            return False
        if isinstance(response, ExceptionResponse):
            logging.debug('PyModbusClient: received exception from device')
            return False
        return True
        
    @staticmethod
    def request_info(request):
        normal_number = request['data_type'][-2:] in ('16', '32', '64') and request['data_type'][:-2] in ('int', 'uint', 'float')
        sp_sf = request['data_type'].endswith('sp-sf') # SunSpec scale factor
        
        # bytes count to read
        count = 1 # valid for 8bit and 16bit
        if normal_number:
            if  request['data_type'].endswith('32'):
                count = 2
            elif request['data_type'].endswith('64'):
                count = 4
        elif request['data_type'] == 'string':
            if request['strlen'] % 2 == 1:
                request['strlen'] -= 1
            count = int(request['strlen'] / 2)
        elif sp_sf:
            count = request['sf'] - request['addr'] + 1
        
        return normal_number, count, sp_sf
        
    def send_results_to_jeedom(self, results):
        # Send results to jeedom
        if results:
            if self.jcom is not None:
                self.jcom.send_change_immediate({'values': results})
            # Or show them in the log
            else:
                logging.info('PyModbusClient: read_results:' + json.dumps(results))
        
    def check_queue(self, timeout=0.1):
        try:
            write_cmd = self.queue.get(block=True, timeout=timeout)
        except Empty:
            pass
        else:
            self.write_cmds.append(write_cmd)
        
    def run(self, queue):
        self.queue = queue
        
        # SIGTERM catcher
        logging.getLogger('asyncio').setLevel(logging.WARNING)
        self.loop = asyncio.get_event_loop()
        self.loop.add_signal_handler(signal.SIGINT, self.signal_handler)
        self.loop.add_signal_handler(signal.SIGTERM, self.signal_handler)
        
        # Don't do anything if there is no info command (read)
        for cmd_id, request in self.requests.items():
            if request['type'] == 'info':
                break
        else:
            logging.debug('PyModbusClient: run: nothing to do... exit')
            return
        
        asyncio.run(self.run_loop())
        
    async def run_loop(self):
        # Polling loop
        while not self.should_stop.is_set():
            # for time measuring
            t_begin = time.time()
            
            # Connect
            try:
                await self.client.connect()
            except:
                logging.error('PyModbusClient: Something went wrong while connecting to equipment id ' + self.id)
            
            #----------------------------------------------------------------------
            # Read requests
            #----------------------------------------------------------------------
            read_results = {}
            self.cycle += 1
            for cmd_id, request in self.requests.items():
                # Only read requests in the loop
                if request['type'] == 'action':
                    continue
                
                # Read once every n cycles
                if self.cycle % request['freq'] != 0:
                    continue
                
                request_ok = True
                
                # Read coils (code 0x01) || Read discrete inputs (code 0x02)
                if request['fct_modbus'] in ('1', '2'):
                    count = 1
                    
                    try:
                        if request['fct_modbus'] == '1':
                            response = await self.client.read_coils(address=request['addr'], count=count, slave=request['slave'])
                        elif request['fct_modbus'] == '2':
                            response = await self.client.read_discrete_inputs(address=request['addr'], count=count, slave=request['slave'])
                        
                        request_ok = PyModbusClient.check_response(response)
                    
                    except:
                        request_ok = False
                        
                    if request_ok:
                        value = response.bits[0]
                        if request['data_type'] == 'bin-inv':
                            value = not value
                        
                # Read holding registers (code 0x03) || Read input registers (code 0x04)
                elif request['fct_modbus'] in ('3', '4'):
                    normal_number, count, sp_sf = PyModbusClient.request_info(request)
                    
                    try:
                        if request['fct_modbus'] == '3':
                            response = await self.client.read_holding_registers(address=request['addr'], count=count, slave=request['slave'])
                        elif request['fct_modbus'] == '4':
                            response = await self.client.read_input_registers(address=request['addr'], count=count, slave=request['slave'])
                        
                        request_ok = PyModbusClient.check_response(response)
                        
                    except:
                        request_ok = False
                        
                    if request_ok:
                        decoder = BinaryPayloadDecoder.fromRegisters(response.registers, request['byteorder'], request['wordorder'])
                        
                        # Type: Byte
                        if '8' in request['data_type']:
                            if request['data_type'].endswith('-lsb'):
                                decoder.skip_bytes(1)
                            
                            if request['data_type'].startswith('int8'):
                                value = decoder.decode_8bit_int()
                            elif request['data_type'].startswith('uint8'):
                                value = decoder.decode_8bit_uint()
                            
                        # Type: Word (16bit) || Dword (32bit) || Double Dword (64bit)
                        elif normal_number:
                            value = getattr(decoder, 'decode_' + request['data_type'][-2:] + 'bit_' + request['data_type'][:-2])()
                            
                        # string
                        elif request['data_type'] == 'string':
                            value = decoder.decode_string(request['strlen'])
                            
                        #---------------
                        # Special cases
                        # SunSpec scale factor
                        elif sp_sf:
                            sp_pf_data_type = request['data_type'][:-5]
                            offset = 1
                            if sp_pf_data_type[-2:] == '32':
                                offset = 2
                            value = getattr(decoder, 'decode_' + sp_pf_data_type[-2:] + 'bit_' + sp_pf_data_type[:-2])()
                            
                            decoder.skip_bytes((count - offset - 1) * 2)
                            sf = decoder.decode_16bit_int()
                            value = value * 10 ** sf
                        
                # Save the result of this request
                if request_ok:
                    read_results[cmd_id] = value
                    if request['data_type'] == 'string':
                        try:
                            read_results[cmd_id] = value.decode()
                        except:
                            read_results[cmd_id] = '<*ERROR*>'[:request['strlen']]
                    self.requests[cmd_id]['last_value'] = read_results[cmd_id]
                    logging.debug('PyModbusClient: read value for ' + request['name'] + ' (command id ' + cmd_id + '): ' + str(value))
                else:
                    logging.error('PyModbusClient: Something went wrong while reading ' + request['name'] + ' (command id ' + cmd_id + ')')
                
                if self.eqProtocol == 'serial':
                    await asyncio.sleep(0.05)
                
            #    # Send results to jeedom if len(json) > 400 (arbitrary length)
            #    if len(json.dumps(read_results)) > 400:
            #        self.send_results_to_jeedom(read_results)
            #        read_results = {}
                
            # After all the info requests
            # Send results to jeedom
            self.send_results_to_jeedom(read_results)
            
            if not self.queue.empty():
                self.check_queue()
            
            #----------------------------------------------------------------------
            # Write requests
            #----------------------------------------------------------------------
            while len(self.write_cmds) > 0:
                write_cmd = self.write_cmds.pop(0)
                request = self.requests[write_cmd['cmdId']]
                
                logging.debug('PyModbusClient: *-*-*-*-*-*-*-*-*-*-*-**-*-*-*-*-* request ' + repr(request))
                
                request_ok = True
                
                # Write single coil (code 0x05) || Write coils (code 0x0F)
                if request['fct_modbus'] in ('5', '15'):
                    value = not (write_cmd['cmdWriteValue'] == '0' or write_cmd['cmdWriteValue'].lower() == 'false') # anything else than '0' or 'false' will be True
                    
                    if request['data_type'] == 'bin-inv':
                            value = not value
                    
                    try:
                        if request['fct_modbus'] == '5':
                            response = await self.client.write_coil(address=request['addr'], value=value, slave=request['slave'])
                        elif request['fct_modbus'] == '15':
                            values = []
                            values.append(value)
                            response = await self.client.write_coils(address=request['addr'], values=values, slave=request['slave'])
                        
                        request_ok = PyModbusClient.check_response(response)
                    
                    except:
                        request_ok = False
                        
                    if not request_ok:
                        logging.error('PyModbusClient: Something went wrong while writing ' + request['name'] + ' (command id ' + cmd_id + ')')
                    
                # Write register (code 0x06) || Write registers (code 0x10)
                elif request['fct_modbus'] in ('6', '16'):
                    normal_number, count, sp_sf = PyModbusClient.request_info(request)
                    
                    builder = BinaryPayloadBuilder(byteorder=request['byteorder'], wordorder=request['wordorder'])
                    
                    try:
                        # Type: Byte
                        if '8' in request['data_type']:
                            pass # ignore this command
                            
                        # Type: Word (16bit) || Dword (32bit) || Double Dword (64bit)
                        elif normal_number:
                            value = float(write_cmd['cmdWriteValue']) if request['data_type'][:-2] == 'float' else int(write_cmd['cmdWriteValue'])
                            getattr(builder, 'add_' + request['data_type'][-2:] + 'bit_' + request['data_type'][:-2])(value)
                            
                        # string
                        elif request['data_type'] == 'string':
                            value = write_cmd['cmdWriteValue'][:request['strlen']]
                            builder.add_string(value)
                            
                        #---------------
                        # Special cases
                        # SunSpec scale factor
                        elif sp_sf:
                            sp_pf_data_type = request['data_type'][:-5]
                            offset = 1
                            if sp_pf_data_type[-2:] == '32':
                                offset = 2
                            
                            if count == offset + 1:
                                value, sf = value_to_sf(float(write_cmd['cmdWriteValue']))
                                getattr(builder, 'add_' + sp_pf_data_type[-2:] + 'bit_' + sp_pf_data_type[:-2])(value)
                                builder.add_16bit_int(sf)
                                
                            else:
                                logging.warning('PyModbusClient: Cannot write ' + request['name'] + ' (command id ' + cmd_id + '), the registers aren\'t consecutive.')
                        
                    except:
                        logging.error('PyModbusClient: Something went wrong while building the write request for ' + request['name'] + ' (command id ' + cmd_id + ')')
                        
                    else:
                        # build registers
                        registers = builder.to_registers()
                        if len(registers):
                            try:
                                if request['fct_modbus'] == '6':
                                    response = await self.client.write_register(address=request['addr'], value=registers, slave=request['slave'])
                                elif request['fct_modbus'] == '16':
                                    response = await self.client.write_registers(address=request['addr'], values=registers, slave=request['slave'])
                                
                                request_ok = PyModbusClient.check_response(response)
                            
                            except:
                                request_ok = False
                                
                            if not request_ok:
                                logging.error('PyModbusClient: Something went wrong while writing ' + request['name'] + ' (command id ' + cmd_id + ')')
                    
                if self.eqProtocol == 'serial':
                    await asyncio.sleep(0.05)
                    
            # After all the action requests
            # Keep the connection open or not...
            if not self.keepopen:
                try:
                    await self.client.close()
                except:
                    logging.error('PyModbusClient: Something went wront while closing connection to equipment id ' + self.id)
            
            # Polling time
            elapsed_time = time.time() - t_begin
            if elapsed_time >= self.polling:
                self.polling = (elapsed_time // self.polling_config + 1) * self.polling_config
                logging.warning('PyModbusClient: the polling time is too short, setting it to ' + str(self.polling) + ' s.')
            while self.polling - elapsed_time > 0:
                self.check_queue(self.polling - elapsed_time)
                elapsed_time = time.time() - t_begin
            
        # The loop has exited (should never happend)
        try:
            await self.client.close()
        except:
            pass
        self.shutdown()
        
    def signal_handler(self, signum=None, frame=None):
        self.shutdown()
        
    def shutdown(self):
        self.should_stop.set()
        self.loop.stop()
        try:
            self.client.close()
        except:
            pass
        
