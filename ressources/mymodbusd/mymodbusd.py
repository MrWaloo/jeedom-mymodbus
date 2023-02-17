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

import logging
import string
import sys
import os
import traceback
import signal
from optparse import OptionParser
import json
import argparse
import threading
import multiprocessing
import socket
import asyncio
from mymodbus import PyModbusClient

try:
    from jeedom.jeedom import jeedom_utils, jeedom_com
except ImportError:
    print("Error: importing module jeedom.jeedom")
    sys.exit(1)

# -----------------------------------------------------------------------------

# Compatibility (pymodbus requires python >= 3.8)
if (sys.version_info < (3, 8)):
    sys.stderr("Please install python V3.8 or newer and check the MyModbus dependencies")
    sys.exit(1)

# -----------------------------------------------------------------------------

class Main():
    def __init__(self, should_stop):
        # Deamon parameters
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
        self.pymodbus_clients = {}
        
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
            logginglog.critical('mymodbusd: Missing API key')
            sys.exit(2)
        # Json data is mandatory
        if self._json is None:
            logginglog.critical('mymodbusd: Missing json data')
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
        if 'CMD' in message:
            if message['CMD'] == 'quit':
                logging.info("mymodbusd: Command 'quit' received from jeedom: exiting")
                self.should_stop.set()
                return
        
    def run(self):
        self.clear_to_leave.clear()
        
        self.config = json.loads(self._json)
        for eqConfig in self.config:
            multiprocessing.Process(target=self.create_instance, name='eqLogic_'+eqConfig['id'], args=(eqConfig,), daemon=True).start()
        
        # Incoming communication from php
        self.jsock.listen()
        self.jsock.settimeout(self._cycle)
        while not self.should_stop.is_set():
            try:
                conn, addr = self.jsock.accept()
                data = conn.recv(1024)
                if data and addr[0] == '127.0.0.1':
                    self.read_socket(data)
                conn.close()
            except socket.timeout:
                pass
            
            # Test if child process are defunk
            for process in multiprocessing.active_children():
                try:
                    process.join(0.1)
                except BrokenProcessPool:
                    pass
                    #process.kill() OR process.terminate() ??? # FIXME
                    
                    
            
        # Stop all communication threads properly
        for eqId, pymodbus_client in self.pymodbus_clients.items():
            pymodbus_client.shutdown()
        
        self.clear_to_leave.set()
        
    def create_instance(self, eqConfig):
        pymodbus_client = PyModbusClient(eqConfig, self.jcom)
        self.pymodbus_clients[eqConfig['id']] = pymodbus_client
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        loop.run_until_complete(pymodbus_client.run())
        
    def shutdown(self):
        logging.debug("mymodbusd: Shutdown Mymodbus python daemon")
        self.should_stop.set() # stops the loop for incoming messages from php
        try:
            self.jsock.close()
        except:
            pass
        self.clear_to_leave.wait(timeout=4)
        
        # Stop all communication threads forced (kill)
        for process in multiprocessing.active_children():
            logging.debug("mymodbusd: Process: " + process.name)
            if process.is_alive():
                logging.debug("mymodbusd: Process: " + process.name + ' is killed')
                process.kill()
        
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
        should_stop.set()
    
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
