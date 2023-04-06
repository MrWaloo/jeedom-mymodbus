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
import multiprocessing as mp
import asyncio
import re
from statistics import fmean
from math import isnan
from queue import (Empty, Full)

from pymodbus.client import (AsyncModbusTcpClient, AsyncModbusUdpClient, AsyncModbusSerialClient)
from pymodbus.framer.socket_framer import ModbusSocketFramer
from pymodbus.framer.rtu_framer import ModbusRtuFramer
from pymodbus.framer.ascii_framer import ModbusAsciiFramer
from pymodbus.framer.binary_framer import ModbusBinaryFramer
from pymodbus.payload import (BinaryPayloadDecoder, BinaryPayloadBuilder)
from pymodbus.pdu import ExceptionResponse
from pymodbus.exceptions import ModbusException

from jeedom.jeedom import jeedom_utils

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
    def __init__(self, config, jcom=None, log_level='debug'):
        """ For pymodbus client
        """
        self.should_stop = mp.Event()
        self.should_stop.clear()
        self.read_cmd = mp.Event()
        self.read_cmd.clear()
        
        self.jcom = jcom
        jeedom_utils.set_log_level(log_level)
        
        self.new_config = config
        self.polling_config = None
        self.polling = None
        
        self.eqConfig = None
        
        self.requests = None
        self.framer = None
        self.client = None
        self.blobs = {}
        self.write_cmds = []
        self.next_write_time = time.time()
        
        self.cycle = 0
        self.cycle_times = [None, None, None, None, None]
        
    @staticmethod
    def get_framer_and_client(config):
        logging.debug('PyModbusClient: client protocol is:' + config['eqProtocol'])
        if config['eqProtocol'] == 'tcp':
            if config['eqTcpRtu'] == '1':
                framer = ModbusRtuFramer
            else:
                framer = ModbusSocketFramer
        
            client = AsyncModbusTcpClient(host=config['eqTcpAddr'], port=int(config['eqTcpPort']), framer=framer, reconnect_delay=0)
            
        if config['eqProtocol'] == 'udp':
            framer = ModbusSocketFramer
            client = AsyncModbusUdpClient(host=config['eqUdpAddr'], port=int(config['eqUdpPort']), framer=framer, reconnect_delay=0)
                
        elif config['eqProtocol'] == 'serial':
            if config['eqSerialMethod'] == 'rtu':
                framer = ModbusRtuFramer
            elif config['eqSerialMethod'] == 'ascii':
                framer = ModbusAsciiFramer
            elif config['eqSerialMethod'] == 'binary':
                framer = ModbusBinaryFramer
            
            client = AsyncModbusSerialClient(port=config['eqSerialInterface'], baudrate=int(config['eqSerialBaudrate']), bytesize=int(config['eqSerialBytesize']),
                                            parity=config['eqSerialParity'], stopbits=int(config['eqSerialStopbits']), framer=framer, reconnect_delay=0)
        return framer, client
        
    @staticmethod
    def get_requests(cmds):
        re_array = re.compile(r"(\d+)\s*\[\s*(\d+)\s*\]")
        re_sf = re.compile(r"(\d+)\s*?sf\s*?(\d+)", re.IGNORECASE)
        requests = {}
        for req_config in cmds:
            request = {}
            request['last_value'] = None
            request['name'] = req_config['name'].rstrip()
            request['type'] = req_config['type']
            request['slave'] = int(req_config['cmdSlave'])
            request['fct_modbus'] = req_config['cmdFctModbus']
            request['data_type'] = req_config['cmdFormat']
            # address according to data type
            # blob part
            if request['fct_modbus'] == 'fromBlob':
                request['freq'] = 1
                request['blobId'] = req_config['cmdSourceBlob']
                
            # string
            if request['data_type'] == 'string':
                re_match = re_array.match(req_config['cmdAddress'])
                if re_match:
                    request['addr'] = int(re_match.group(1))
                    request['strlen'] = int(re_match.group(2))
                
            # blob
            elif request['data_type'] == 'blob':
                re_match = re_array.match(req_config['cmdAddress'])
                if re_match:
                    request['addr'] = int(re_match.group(1))
                    request['count'] = int(re_match.group(2))
                
            # SunSpec scale factor
            elif request['data_type'].endswith('sp-sf'):
                re_match = re_sf.match(req_config['cmdAddress'])
                if re_match:
                    request['addr'] = int(re_match.group(1))
                    request['sf'] = int(re_match.group(2))
                
            else:
                request['addr'] = int(req_config['cmdAddress'])
            
            if request['type'] == 'info' and request['fct_modbus'] != 'fromBlob':
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
        elif request['data_type'] == 'blob':
            count = int(request['count'])
        elif sp_sf:
            count = request['sf'] - request['addr'] + 1
        
        return normal_number, count, sp_sf
        
    def send_results_to_jeedom(self, results):
        # Send results to jeedom
        if results:
            if self.jcom is not None:
                self.jcom.send_change_immediate({'eqId': self.eqConfig['id'], 'values': results})
            # Or show them in the log
            else:
                logging.info('PyModbusClient: read_results:' + json.dumps(results))
        
    def check_queue(self, timeout=None):
        if timeout is None or float(timeout) < float(self.eqConfig['eqWriteCmdCheckTimeout']):
            timeout = float(self.eqConfig['eqWriteCmdCheckTimeout'])
        
        try:
            daemon_cmd = self.queue.get(block=True, timeout=timeout)
        except Empty:
            pass
        else:
            logging.debug('PyModbusClient: check_queue - daemon_cmd: ' + json.dumps(daemon_cmd))
            if 'write_cmd' in daemon_cmd.keys():
                self.write_cmds.append(daemon_cmd['write_cmd'])
                
            elif 'read_cmd' in daemon_cmd.keys():
                self.read_cmd.set()
                
            elif 'log_level' in daemon_cmd.keys():
                log = logging.getLogger()
                for hdlr in log.handlers[:]:
                    log.removeHandler(hdlr)
                jeedom_utils.set_log_level(daemon_cmd['log_level'])
                
            elif 'new_config' in daemon_cmd.keys():
                self.new_config = daemon_cmd['new_config']
                
            elif 'stop' in daemon_cmd.keys():
                self.should_stop.set()
        
    def apply_new_config(self):
        self.polling_config = float(self.new_config['eqPolling'])
        self.polling = float(self.new_config['eqPolling']) * 1.0
        
        # if the refresh mode is different -> exit, this process will be restarted from the daemon
        if self.eqConfig and self.eqConfig['eqRefreshMode'] != self.new_config['eqRefreshMode']:
            self.should_stop.set()
            self.new_config = None
            return
        
        self.eqConfig = {}
        for k, v in self.new_config.items():
            if k != 'cmds':
                self.eqConfig[k] = v
        
        self.requests = PyModbusClient.get_requests(self.new_config['cmds'])
        self.framer, self.client = PyModbusClient.get_framer_and_client(self.eqConfig)
        self.blobs = {}
        self.cycle = 0
        self.cycle_times = [None, None, None, None, None]
        
        self.new_config = None
        
    def get_value(self, cmd_id):
        request = self.requests[cmd_id]
        
        if request['blobId'] in self.requests.keys() and request['blobId'] in self.blobs.keys():
            blob_start_addr = self.requests[request['blobId']]['addr']
            registers = self.blobs[request['blobId']]
        else: 
            return None
        if not registers:
            return None
        
        index = request['addr'] - blob_start_addr
        if index > len(registers):
            return None
        
        # binary
        if 'bit' in request['data_type']:
            value = registers[index]
            if request['data_type'] == 'bin-inv':
                value = not value
            return value
        
        # else
        normal_number, count, sp_sf = PyModbusClient.request_info(request)
        
        decoder = BinaryPayloadDecoder.fromRegisters(registers, request['byteorder'], request['wordorder'])
        
        # Type: Byte
        if '8' in request['data_type']:
            decoder.skip_bytes(index * 2)
            if request['data_type'].endswith('-lsb'):
                decoder.skip_bytes(1)
            
            if request['data_type'].startswith('int8'):
                value = decoder.decode_8bit_int()
            elif request['data_type'].startswith('uint8'):
                value = decoder.decode_8bit_uint()
            
        # Type: Word (16bit) || Dword (32bit) || Double Dword (64bit)
        elif normal_number:
            decoder.skip_bytes(index * 2)
            value = getattr(decoder, 'decode_' + request['data_type'][-2:] + 'bit_' + request['data_type'][:-2])()
            
        # string
        elif request['data_type'] == 'string':
            decoder.skip_bytes(index * 2)
            value = decoder.decode_string(request['strlen'])
            
        #---------------
        # Special cases
        # SunSpec scale factor
        elif sp_sf:
            if request['sf'] - blob_start_addr > len(registers):
                return None
            sp_pf_data_type = request['data_type'][:-5]
            decoder.skip_bytes(index * 2)
            value = getattr(decoder, 'decode_' + sp_pf_data_type[-2:] + 'bit_' + sp_pf_data_type[:-2])()
            
            decoder.reset()
            decoder.skip_bytes((request['sf'] - blob_start_addr) * 2)
            sf = decoder.decode_16bit_int()
            value = value * 10 ** sf
        
        return value
        
    def run(self, queue):
        self.queue = queue
        
        logging.getLogger('asyncio').setLevel(logging.WARNING)
        self.loop = asyncio.get_event_loop()
        
        if self.new_config is not None:
            self.apply_new_config()
        
        if self.eqConfig['eqRefreshMode'] == 'polling':
            self.loop.run_until_complete(self.run_polling())
            
        elif self.eqConfig['eqRefreshMode'] == 'cyclic':
            self.loop.run_until_complete(self.run_cyclic())
            
        elif self.eqConfig['eqRefreshMode'] == 'on_event':
            self.loop.run_until_complete(self.run_one())
            
        self.loop.close()
        self.shutdown()
        
    async def run_polling(self):
        self.connected = False
        
        # Polling loop
        while not self.should_stop.is_set():
            # for time measuring
            t_begin = time.time()
            
            if self.new_config is not None:
                self.connected = await self.disconnect()
                self.apply_new_config()
            
            # Connect
            if not self.connected or not self.client.connected:
                self.connected = await self.connect()
            
            await self.read_all()
            
            # Keep the connection open or not...
            if self.eqConfig['eqKeepopen'] == '0' or not self.connected:
                self.connected = await self.disconnect()
            
            # Polling time
            elapsed_time = time.time() - t_begin
            if elapsed_time >= self.polling:
                self.polling = (elapsed_time // self.polling_config + 1) * self.polling_config
                logging.warning('PyModbusClient: the polling time is too short, setting it to ' + str(self.polling) + ' s.')
            while self.polling - elapsed_time > 0 and not self.should_stop.is_set():
                self.check_queue(self.polling - elapsed_time)
                await self.execute_write_requests(True, self.connected)
                elapsed_time = time.time() - t_begin
            
            self.cycle_times[self.cycle % 5] = time.time() - t_begin
            
        # The loop has exited
        self.connected = await self.disconnect()
        
    async def run_cyclic(self):
        self.connected = False
        
        # Polling loop
        while not self.should_stop.is_set():
            # for time measuring
            t_begin = time.time()
            
            if self.new_config is not None:
                self.connected = await self.disconnect()
                self.apply_new_config()
            
            # Connect
            if not self.connected or not self.client.connected:
                self.connected = await self.connect()
            
            await self.read_all()
            
            self.check_queue()
            await self.execute_write_requests(False, self.connected)
            
            self.cycle_times[self.cycle % 5] = time.time() - t_begin
            
        # The loop has exited
        self.connected = await self.disconnect()
        
    async def run_one(self):
        self.connected = False
        
        while not self.should_stop.is_set():
            # for time measuring
            t_begin = time.time()
            
            self.check_queue()
            await self.execute_write_requests(True, self.connected)
            
            if self.new_config is not None:
                self.apply_new_config()
            
            if self.read_cmd.is_set():
                self.read_cmd.clear()
                # Connect
                if not self.connected or not self.client.connected:
                    self.connected = await self.connect()
                
                await self.read_all()
                
                # The loop has exited
                self.connected = await self.disconnect()
                
                self.cycle_times[self.cycle % 5] = time.time() - t_begin
        
    async def connect(self):
        logging.debug('PyModbusClient: connect called')
        try:
            if not self.client.connected:
                logging.debug('PyModbusClient: connecting...')
                await self.client.connect()
            ret = True
        except Exception as e:
            logging.error('PyModbusClient: Something went wrong while connecting to equipment id ' + self.eqConfig['id'] + ': ' + repr(e) + ' - ' + e.string)
            ret = False
        delay = float(self.eqConfig['eqFirstDelay'])
        await asyncio.sleep(delay)
        return ret
        
    async def disconnect(self):
        logging.debug('PyModbusClient: disconnect called')
        try:
            await self.client.close()
        except Exception as e:
            logging.error('PyModbusClient: Something went wrong while closing connection to equipment id ' + self.eqConfig['id'] + ': ' + repr(e) + ' - ' + e.string)
        return False
        
    async def read_all(self):
        """Read every info commands
        """
        exception = None
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
            value = None
            
            # Read coils (code 0x01) || Read discrete inputs (code 0x02)
            if request['fct_modbus'] in ('1', '2'):
                count = 1
                if request['data_type'] == 'blob':
                    count = int(request['count'])
                
                try:
                    if request['fct_modbus'] == '1':
                        response = await self.client.read_coils(address=request['addr'], count=count, slave=request['slave'])
                    elif request['fct_modbus'] == '2':
                        response = await self.client.read_discrete_inputs(address=request['addr'], count=count, slave=request['slave'])
                    
                    request_ok = PyModbusClient.check_response(response)
                
                except Exception as e:
                    request_ok = False
                    exception = e
                    self.connected = False
                    
                if request_ok:
                    value = response.bits[0]
                    if request['data_type'] == 'bin-inv':
                        value = not value
                        
                    elif request['data_type'] == 'blob':
                        value = True
                        self.blobs[cmd_id] = response.bits
                    
                # if not request_ok
                else:
                    if request['data_type'] == 'blob':
                        value = False
                        self.blobs[cmd_id] = None
                    
            # Read holding registers (code 0x03) || Read input registers (code 0x04)
            elif request['fct_modbus'] in ('3', '4'):
                normal_number, count, sp_sf = PyModbusClient.request_info(request)
                
                try:
                    if request['fct_modbus'] == '3':
                        response = await self.client.read_holding_registers(address=request['addr'], count=count, slave=request['slave'])
                    elif request['fct_modbus'] == '4':
                        response = await self.client.read_input_registers(address=request['addr'], count=count, slave=request['slave'])
                    
                    request_ok = PyModbusClient.check_response(response)
                    
                except Exception as e:
                    request_ok = False
                    exception = e
                    self.connected = False
                    
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
                        
                    # Type: Word (16bit) || Dword (32bit) || Qword (64bit)
                    elif normal_number:
                        value = getattr(decoder, 'decode_' + request['data_type'][-2:] + 'bit_' + request['data_type'][:-2])()
                        
                    # string
                    elif request['data_type'] == 'string':
                        value = decoder.decode_string(request['strlen'])
                        
                    # blob
                    elif request['data_type'] == 'blob':
                        value = 1
                        self.blobs[cmd_id] = response.registers
                        
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
                    
                # if not request_ok
                else:
                    # blob
                    if request['data_type'] == 'blob':
                        value = 0
                        self.blobs[cmd_id] = None
                    
            # blob part
            elif request['fct_modbus'] == 'fromBlob':
                # Read once every n cycles
                if request['blobId'] in self.requests.keys():
                    if self.cycle % self.requests[request['blobId']]['freq'] != 0:
                        continue
                else: 
                    continue
                
                value = self.get_value(cmd_id)
                if value is None:
                    request_ok = False
                
            # Save the result of this request
            if request_ok:
                if request['data_type'] == 'string':
                    try:
                        value = value.decode()
                    except:
                        value = '<*ERROR*>'[:request['strlen']]
                
                if isnan(value):
                    logging.error('PyModbusClient: read value for ' + request['name'] + ' (command id ' + cmd_id + '): NaN!')
                    
                elif value != request['last_value']:
                    read_results[cmd_id] = value
                    
                    self.requests[cmd_id]['last_value'] = read_results[cmd_id]
                    logging.debug('PyModbusClient: read value for ' + request['name'] + ' (command id ' + cmd_id + '): ' + str(read_results[cmd_id]))
            else:
                error_log = 'PyModbusClient: Something went wrong while reading ' + request['name'] + ' (command id ' + cmd_id + ')'
                if exception:
                    if isinstance(exception, ModbusException):
                        logging.error(error_log + ': ' + repr(exception) + ' - ' + exception.string)
                    else:
                        logging.error(error_log + ': ' + repr(exception) + ' - ' + str(exception))
                else:
                    logging.error(error_log)
            
            # Small pause if serial
            if self.eqConfig['eqProtocol'] == 'serial' and request['fct_modbus'] != 'fromBlob':
                await asyncio.sleep(0.05)
            
            ################################################################
            # Checking if write commands have been received and execute them
            if not self.queue.empty():
                self.check_queue()
            
            await self.execute_write_requests(False, self.connected)
            ################################################################
            
        # After all the info requests
        # Send results to jeedom
        if self.cycle % 5 == 1 and self.cycle_times[0] is not None:
            read_results['cycle_time'] = fmean(self.cycle_times)
            
        self.send_results_to_jeedom(read_results)
        
    async def execute_write_requests(self, connect=False, connected=False):
        if len(self.write_cmds) == 0 or time.time() < self.next_write_time:
            return
        
        re_pause = re.compile(r"(.*)\s*?pause\s*?(\d+([\.\,]\d+)?)\s*?$", re.IGNORECASE)
        write_connected = connected
        
        if connect or not write_connected or not self.client.connected:
            logging.debug('PyModbusClient: connect to execute write commands')
            write_connected = await self.connect()
        
        while len(self.write_cmds) > 0:
            write_cmd = self.write_cmds.pop(0)
            request = self.requests[write_cmd['cmdId']]
            
            # Type: Byte or blob
            if request['fct_modbus'] == 'fromBlob' or '8' in request['data_type'] or request['data_type'] == 'blob':
                continue # ignore this command that should not be received...
            
            logging.debug('PyModbusClient: write_request ' + repr(request))
            
            re_match = re_pause.match(str(write_cmd['cmdWriteValue']))
            pause = None
            if re_match:
                value_to_write = eval(re_match.group(1))
                pause = float(re_match.group(2).replace(',', '.'))
                self.next_write_time = time.time() + pause
            else:
                value_to_write = write_cmd['cmdWriteValue']
            
            request_ok = True
            exception = None
            
            # Write single coil (code 0x05) || Write coils (code 0x0F)
            if request['fct_modbus'] in ('5', '15'):
                value = not (str(value_to_write) == '0' or str(value_to_write).lower() == 'false') # anything else than '0' or 'false' will be True
                
                if request['data_type'] == 'bin-inv':
                    value = not value
                
                try:
                    if request['fct_modbus'] == '5':
                        response = await self.client.write_coil(address=request['addr'], value=value, slave=request['slave'])
                    elif request['fct_modbus'] == '15':
                        response = await self.client.write_coils(address=request['addr'], values=[value], slave=request['slave'])
                    
                    request_ok = PyModbusClient.check_response(response)
                
                except Exception as e:
                    request_ok = False
                    exception = e
                    write_connected = False
                    
                if not request_ok:
                    error_log = 'PyModbusClient: Something went wrong while writing ' + request['name'] + ' (command id ' + write_cmd['cmdId'] + ')'
                    if exception:
                        if isinstance(exception, ModbusException):
                            logging.error(error_log + ': ' + repr(exception) + ' - ' + exception.string)
                        else:
                            logging.error(error_log + ': ' + repr(exception) + ' - ' + str(exception))
                    else:
                        logging.error(error_log)
                
            # Write register (code 0x06) || Write registers (code 0x10)
            elif request['fct_modbus'] in ('6', '16'):
                normal_number, count, sp_sf = PyModbusClient.request_info(request)
                
                builder = BinaryPayloadBuilder(byteorder=request['byteorder'], wordorder=request['wordorder'])
                
                try:
                    # Type: Word (16bit) || Dword (32bit) || Double Dword (64bit)
                    if normal_number:
                        value = float(value_to_write) if request['data_type'][:-2] == 'float' else int(value_to_write)
                        getattr(builder, 'add_' + request['data_type'][-2:] + 'bit_' + request['data_type'][:-2])(value)
                        
                    # string
                    elif request['data_type'] == 'string':
                        value = value_to_write[:request['strlen']]
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
                            value, sf = value_to_sf(float(value_to_write))
                            getattr(builder, 'add_' + sp_pf_data_type[-2:] + 'bit_' + sp_pf_data_type[:-2])(value)
                            builder.add_16bit_int(sf)
                            
                        else:
                            logging.warning('PyModbusClient: Cannot write ' + request['name'] + ' (command id ' + write_cmd['cmdId'] + '), the registers aren\'t consecutive.')
                    
                except Exception as e:
                    logging.error('PyModbusClient: Something went wrong while building the write request for ' + request['name'] + ' (command id ' + write_cmd['cmdId'] + '): ' + repr(e))
                    
                else:
                    # build registers
                    registers = builder.to_registers()
                    if len(registers):
                        try:
                            if request['fct_modbus'] == '6':
                                response = await self.client.write_register(address=request['addr'], value=registers[0], slave=request['slave'])
                            elif request['fct_modbus'] == '16':
                                response = await self.client.write_registers(address=request['addr'], values=registers, slave=request['slave'])
                            
                            request_ok = PyModbusClient.check_response(response)
                        
                        except Exception as e:
                            request_ok = False
                            exception = e
                            write_connected = False
                            
                        if not request_ok:
                            error_log = 'PyModbusClient: Something went wrong while writing ' + request['name'] + ' (command id ' + write_cmd['cmdId'] + ')'
                            if exception:
                                if isinstance(exception, ModbusException):
                                    logging.error(error_log + ': ' + repr(exception) + ' - ' + exception.string)
                                else:
                                    logging.error(error_log + ': ' + repr(exception) + ' - ' + str(exception))
                            else:
                                logging.error(error_log)
                
            if self.eqConfig['eqProtocol'] == 'serial':
                await asyncio.sleep(0.05)
            
        # After all the requests
        # Keep the connection open or not...
        if connect and self.eqConfig['eqKeepopen'] == '0':
            logging.debug('PyModbusClient: disconnect after write')
            write_connected = await self.disconnect()
        
    def shutdown(self):
        logging.info('PyModbusClient: shutdown called')
        self.loop.stop()
        
