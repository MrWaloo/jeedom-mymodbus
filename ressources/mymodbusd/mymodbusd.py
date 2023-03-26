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

import sys

# -----------------------------------------------------------------------------

# Compatibility (pymodbus requires python >= 3.8)
if (sys.version_info < (3, 8)):
    print("Please check the MyModbus dependencies or install python V3.8 or newer", file=sys.stderr)
    sys.exit(1)

# -----------------------------------------------------------------------------

import os
import logging
import time
import traceback
import signal
from optparse import OptionParser
import json
import argparse
import threading
import multiprocessing as mp
from queue import Empty, Full
import socket
import asyncio

from mymodbus import PyModbusClient

from jeedom.jeedom import (jeedom_utils, jeedom_com)

# -----------------------------------------------------------------------------

class Main():
    def __init__(self, should_stop):
        # Daemon parameters
        self.read_args()
        
        jeedom_utils.set_log_level(self._log_level)
        
        logging.info( 'mymodbusd: Start daemon mymodbusd')
        logging.info( 'mymodbusd: Log level:     ' + self._log_level)
        logging.debug('mymodbusd: API key:       ' + self._apikey)
        logging.debug('mymodbusd: Callback:      ' + self._callback)
        logging.debug('mymodbusd: Configuration: ' + str(self._json))
        
        # Handle Run & Shutdown
        self.should_stop = should_stop
        self.clear_to_leave = threading.Event()
        self.clear_to_leave.set()
        
        # Input socket (php -> daemon)
        self.jsock = socket.socket()
        
        # Communication to php
        self.jcom = None
        
        self.config = None
        self.sub_process = {}
        
    def read_args(self):
        ''' Reads arguments from the command line and set self.param
        '''
        # Initialisation with hard coded parameters
        self._socket_host   = 'localhost'
        self._pidfile       = '/tmp/mymodbusd.pid'
        self._cycle         = 0.3
        # These parameters can be passed as arguments:
        self._socket_port   = 55502
        self._log_level     = 'error'
        self._apikey        = ''
        self._callback      = ''
        self._json          = ''
        
        # Parameters passed as command line arguments
        parser = argparse.ArgumentParser(description='Mymodbus parameters')
        
        parser.add_argument('--socketport', help='Communication socket to Jeedom',  type=int)
        parser.add_argument('--loglevel',   help='Log Level for the daemon',        type=str)
        parser.add_argument('--apikey',     help='Apikey from Jeedom',              type=str)
        parser.add_argument('--callback',   help='Callback url',                    type=str)
        parser.add_argument('--json',       help='MyModbus json data',              type=str)
        args = parser.parse_args()
        
        if args.socketport:
            try:
                self._socket_port = int(args.socketport)
            except:
                pass
        if args.loglevel:
            self._log_level = args.loglevel
        if args.apikey:
            self._apikey = args.apikey
        if args.callback:
            self._callback = args.callback
        if args.json:
            self._json = args.json
        
    def prepare(self):
        '''Checks mandatory arguments and pid file
        '''
        # Callback url is mandatory
        if self._callback is None:
            logging.critical('mymodbusd: Missing callback url')
            sys.exit(2)
        # API key is mandatory
        if self._apikey is None:
            logging.critical('mymodbusd: Missing API key')
            sys.exit(2)
        # Json data is mandatory
        if self._json is None:
            logging.critical('mymodbusd: Missing json data')
            sys.exit(2)
        
        # Check the pid file
        if os.path.isfile(self._pidfile):
            logging.debug('mymodbusd: pid File "%s" already exists.', self._pidfile)
            with open(self._pidfile, "r") as f:
                f.seek(0)
                pid = int(f.readline())
            try:
                # Try to ping the pid
                os.kill(pid, 0)
            except OSError: # pid does not run we can continue
                pass
            except: # just in case
                logging.exception("mymodbusd: Unexpected error when checking pid")
                sys.exit(3)
            else: # pid is alive -> we die
                logging.error('mymodbusd: This daemon already runs! Exit 0')
                sys.exit(0)
        
        # Write pid file
        jeedom_utils.write_pid(self._pidfile)
        
    def open_comm(self):
        '''Returns True when the communication to jeedom core is opened
        '''
        # jeedom_com: communication daemon --> php
        self.jcom = jeedom_com(apikey=self._apikey, url='http://' + self._callback, cycle=0)
        try:
            if not self.jcom.test(): # first test to check the callback url
                logging.error('mymodbusd: Network communication issues. Please fixe your Jeedom network configuration.')
                return False
                
            # Connection from php
            self.jsock.bind((self._socket_host, self._socket_port))
            
        except Exception as e:
            logging.error('mymodbusd: Fatal error: ' + str(e))
            logging.info(traceback.format_exc())
            return False
        
        return True
        
    def get_config(self, eqId, config=None):
        if config == None:
            config = self.config
        for eqConfig in config:
            if eqConfig['id'] == eqId:
                return eqConfig
        
    def read_socket(self, data):
        '''Interprets the json received from the php
        '''
        message = json.loads(data)
        logging.debug("mymodbusd: Received message: " + repr(message))
        # Checking the API key existance and value
        if 'apikey' not in message:
            logging.error("mymodbusd: Received data without API key: " + str(message))
            return
        if message['apikey'] != self._apikey:
            logging.error("mymodbusd: Invalid apikey from socket: " + str(message))
            return
        # Checking if it is a command
        if 'CMD' not in message:
            logging.error("mymodbusd: Received data without CMD: " + str(message))
            return
        else:
            # Quit
            if message['CMD'] == 'quit':
                logging.info("mymodbusd: Command 'quit' received from jeedom: exiting")
                self.should_stop.set()
                return
            # Write
            elif message['CMD'] == 'write':
                if 'write_cmd' not in message:
                    logging.error("mymodbusd: Received CMD=write without write_cmd: " + str(message))
                    return
                logging.info("mymodbusd: Command 'write' received from jeedom: sending the command to the daemon")
                self.send_write_cmd(message['write_cmd'])
                return
            # setLogLevel
            elif message['CMD'] == 'setLogLevel':
                if 'level' not in message:
                    logging.error("mymodbusd: Received CMD=setLogLevel without level: " + str(message))
                    return
                logging.info("mymodbusd: Command 'setLogLevel' received from jeedom: sending the log level to the daemons")
                self.send_log_level(message['level'])
                return
            # newDaemonConfig
            elif message['CMD'] == 'newDaemonConfig':
                if 'config' not in message:
                    logging.error("mymodbusd: Received CMD=newDaemonConfig without config: " + str(message))
                    return
                logging.info("mymodbusd: Command 'newDaemonConfig' received from jeedom: sending the new config to the daemons")
                self.send_new_config(message['config'])
                return
            # Heartbeat
            elif message['CMD'] == 'heartbeat_answer':
                self.hb_recv_time = (time.time(), int(message['answer']))
        
    def send_write_cmd(self, write_cmd):
        if self.clear_to_leave.is_set() or self.should_stop.is_set():
            return
        if 'eqId' not in write_cmd:
            logging.error("mymodbusd: Received CMD=write: no 'eqId' write_cmd: " + str(write_cmd))
            return
        
        [process, queue] = self.sub_process[write_cmd['eqId']]
        eqConfig = self.get_config(write_cmd['eqId'])
        try:
            queue.put({'write_cmd': write_cmd}, True, float(eqConfig['eqPolling']) * 2)
        except Full:
            logging.error("mymodbusd: send_write_cmd: Full/Timeout !!!!")
        
    def send_log_level(self, level):
        if self.clear_to_leave.is_set() or self.should_stop.is_set():
            return
        
        self._log_level = level
        log = logging.getLogger()
        for hdlr in log.handlers[:]:
            log.removeHandler(hdlr)
        jeedom_utils.set_log_level(level)
        for eqId, [process, queue] in self.sub_process.items():
            eqConfig = self.get_config(eqId)
            try:
                queue.put({'log_level': level}, True, float(eqConfig['eqPolling']) * 2)
            except Full:
                logging.error("mymodbusd: send_log_level: Full/Timeout !!!!")
        
    def send_new_config(self, config):
        if self.clear_to_leave.is_set() or self.should_stop.is_set():
            return
        
        old_config = self.config
        self.config = config
        old_eqIds, eqIds = [], []
        for cgf in old_config:
            old_eqIds.append(cgf['id'])
        for cgf in self.config:
            eqIds.append(cgf['id'])
        
        # Step 1: terminate daemons of deleted or deactivated equipments
        for eqId in old_eqIds:
            if eqId not in eqIds:
                [process, queue] = self.sub_process[eqId]
                eqConfig = self.get_config(eqId, old_config)
                try:
                    queue.put({'stop': None}, True, float(eqConfig['eqPolling']) * 2)
                except Full:
                    process.kill()
                process.join()
                del self.sub_process[eqId]
        
        # Step 2: actualize the config of running daemons
        for eqId in old_eqIds:
            if eqId in eqIds:
                [process, queue] = self.sub_process[eqId]
                eqConfig = self.get_config(eqId)
                try:
                    queue.put({'new_config': eqConfig}, True, float(eqConfig['eqPolling']) * 2)
                except Full:
                    logging.error("mymodbusd: send_new_config: Full/Timeout !!!!")
                
        # Step 3: run new daemons
        for eqId in eqIds:
            if eqId not in old_eqIds:
                eqConfig = self.get_config(eqId)
                self.start_sub_process(eqConfig)
        
    def start_sub_process(self, eqConfig):
        if eqConfig['id'] in self.sub_process.keys():
            [process, queue] = self.sub_process[eqConfig['id']]
            try:
                queue.put({'stop': None}, True, float(eqConfig['eqPolling']) * 2)
            except Full:
                process.kill()
            process.join()
        
        pymodbus_client = PyModbusClient(eqConfig, self.jcom, jeedom_utils.convert_log_level(self._log_level))
        queue = mp.Queue()
        process = mp.Process(target=pymodbus_client.run, args=(queue, ), name=eqConfig['name'], daemon=True)
        process.start()
        self.sub_process[eqConfig['id']] = [process, queue]
        time.sleep(1)
        
    def run(self):
        self.clear_to_leave.clear()
        
        self.config = json.loads(self._json)
        for eqConfig in self.config:
            self.start_sub_process(eqConfig)
        
        # heartbeat variables
        start_time = time.time()
        hb_send_time = start_time
        self.hb_recv_time = (start_time, int(start_time))
        
        # Incoming communication from php
        self.jsock.listen()
        self.jsock.settimeout(self._cycle)
        while not self.should_stop.is_set():
            data = b''
            try:
                conn, addr = self.jsock.accept()
                while True:
                    buffer = conn.recv(8192)
                    if not buffer:
                        break
                    data += buffer
                if data and addr[0] == '127.0.0.1':
                    self.read_socket(data)
                conn.close()
            except socket.timeout:
                if data != b'':
                    logging.debug("mymodbusd: SOCKET: socket.timeout avec des données" + str(data))
                #pass
            
            # Kill defunk children
            for process in mp.active_children():
                try:
                    process.join(0.1)
                except BrokenProcessPool:
                    process.kill()
                    process.join()
            
            if len(mp.active_children()) < len(self.sub_process):
                logging.debug("mymodbusd: active_children: " + str(len(mp.active_children())))
                for eqId, [process, queue] in self.sub_process.items():
                    if process not in mp.active_children():
                        for eqConfig in self.config:
                            if eqId == eqConfig['id']:
                                logging.warning("mymodbusd: process re-run: " + process.name)
                                self.start_sub_process(eqConfig)
                                break
            
            # Send heartbeat echo
            now = time.time()
            if self.hb_recv_time[0] < hb_send_time:
                if now - hb_send_time > 15:
                    self.should_stop.set()
                    logging.error("mymodbusd: Stopping pid " + str(os.getpid()) + ": heartbeat timeout")
            elif self.hb_recv_time[1] != int(hb_send_time):
                self.should_stop.set()
                logging.error("mymodbusd: Stopping pid " + str(os.getpid()) + ": wrong heartbeat received")
                
            if now - hb_send_time > 60:
                self.jcom.send_change_immediate({'heartbeat_request': int(now)})
                hb_send_time = now
            
        # Stop all daemons properly
        for eqId, [process, queue] in self.sub_process.items():
            logging.debug("mymodbusd: Stopping " + process.name)
            eqConfig = self.get_config(eqId)
            try:
                queue.put({'stop': None}, True, float(eqConfig['eqPolling']) * 2)
            except Full:
                process.kill()
            
        for eqId, [process, queue] in self.sub_process.items():
            logging.debug("mymodbusd: Waiting for " + process.name + "...")
            process.join()
            logging.debug("mymodbusd: " + process.name + " joined")
        
        self.clear_to_leave.set()
        
    def shutdown(self):
        logging.debug("mymodbusd: Shutdown Mymodbus python daemon")
        self.should_stop.set() # stops the loop for incoming messages from php
        try:
            self.jsock.close()
        except:
            pass
        self.clear_to_leave.wait(timeout=4)
        
        # Stop all communication threads forced (kill)
        for process in mp.active_children():
            if process.is_alive():
                logging.debug("mymodbusd: Process: " + process.name + " still alive")
                process.kill()
                process.join()
                logging.debug("mymodbusd: Process: " + process.name + " has been killed")
        
        logging.debug("mymodbusd: Removing PID file " + self._pidfile)
        try:
            os.remove(self._pidfile)
        except:
            pass
        logging.debug("mymodbusd: Shutdown")

# -----------------------------------------------------------------------------

if __name__ == '__main__':
    # Get an instance of Main
    should_stop = threading.Event()
    should_stop.clear()
    m = Main(should_stop)
    
    # Interrupt handler
    def signal_handler(signum=None, frame=None):
        logging.debug("mymodbusd: Signal %d caught, exiting...", signum)
        m.should_stop.set()
    
    # Connect the signals to the handler
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    # Ready? Let's do something now
    m.prepare()
    if m.open_comm():
        m.run()
    m.shutdown()
    
    # Always exit well
    logging.debug("mymodbusd: Exit 0")
    sys.stdout.flush()
    sys.exit(0)
