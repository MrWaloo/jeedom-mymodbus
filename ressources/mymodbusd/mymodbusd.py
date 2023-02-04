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
import time
import datetime
import traceback
import re
import signal
from optparse import OptionParser
from os.path import join
import json
import argparse

try:
    from jeedom.jeedom import *
except ImportError:
    print("Error: importing module jeedom.jeedom")
    sys.exit(1)

# mymodbus specific imports
#import threading ##### peut-être...
from pymodbus.client import ModbusTcpClient
from pymodbus.payload import BinaryPayloadBuilder, BinaryPayloadDecoder
from pymodbus.constants import Endian
from pymodbus.exceptions import *

# -----------------------------------------------------------------------------

class Main():
    def __init__(self, should_stop):
        # Deamon parameters
        self.read_args()
        
        jeedom_utils.set_log_level(self._log_level)

        logging.info( 'Start daemon mymodbusd')
        logging.info( 'Log level:     ' + self._log_level)
        logging.debug('API key:       ' + self._api_key)
        logging.debug('Callback:      ' + self._callback)
        logging.debug('Configuration: ' + str(self._config))
        
        # Handle Run & Shutdown
        self.should_stop = should_stop
        self.has_stopped = threading.Event()
        self.has_stopped.set()
        
        self.modbus_clients = {}
        
    def read_args(self):
        ''' Reads arguments from the command line and set self.param
        '''
#TODO: adapter la fonction "deamon_start()" dans core/class/mymodbus.class.php
        # Initialisation with hard coded parameters
        self._socket_host = 'localhost'
        self._socket_port =  55502
        self._pidfile     =  '/tmp/mymodbusd.pid'
        self._cycle       =  0.3
        # These parameters can be passed as arguments:
        self._log_level   =  "debug" # #TODO: quand le debug sera fait, remplacer par "error"
        self._api_key     =  ''
        self._callback    =  ''
        self._config      =  {}
        
        # Parameters passed as command line arguments
        parser = argparse.ArgumentParser(description='Mymodbus parameters')
        
        parser.add_argument("--loglevel",   help="Log Level for the daemon",    type=str)
        parser.add_argument("--apikey",     help="Apikey from Jeedom",          type=str)
        parser.add_argument("--callback",   help="Callback url",                type=str)
        parser.add_argument("--config",     help="MyModbus configuration json", type=str)
        args = parser.parse_args()
        
        if args.loglevel:
            self._log_level = args.loglevel
        if args.apikey:
            self._api_key = args.apikey
        if args.callback:
            self._callback = args.callback
        if args.config:
            self._config = args.config
        
    def prepare(self):
        '''Checks mandatory arguments and pid file
        '''
        # Callback url is mandatory
        if self._callback is None:
            logging.critical('Missing callback url')
            sys.exit(2)
        # API key is mandatory
        if self._api_key is None:
            logginglog.critical('Missing API key')
            sys.exit(2)

        # Check the pid file
        if os.path.isfile(self._pidfile):
            logging.debug('pid File "%s" already exists.', self._pidfile)
            with open(self._pidfile, "r") as f:
                f.seek(0)
                pid = int(f.readline())
            try:
                # Try to ping the pid
                os.kill(pid, 0)
            except OSError: # pid does not run we can continue
                pass
            except: # just in case
                logging.exception("Unexpected error when checking pid")
                sys.exit(3)
            else: # pid is alive -> we die
                logging.error('This daemon already runs! Exit 0')
                sys.exit(0)
        # Write pid file
        jeedom_utils.write_pid(self._pidfile)
        
    def open_comm(self):
        '''Returns True when the communication to jeedom core is opened
        '''
        # jeedom_com : communication daemon --> php
        self.jcom = jeedom_com(apikey=self._api_key, url=self._callback, cycle=self._cycle) # création de l'objet jeedom_com
        try:
            if not self.jcom.test(): #premier test pour vérifier que l'url de callback est correcte
                logging.error('Network communication issues. Please fixe your Jeedom network configuration.')
                return False
# Commande à utiliser pour envoyer un json au php
#self.jcom.send_change_immediate({'key1': 'value1', 'key2': 'value2'})

            # jeedom_socket : communication php --> daemon
            self.jsock = jeedom_socket(address=self._socket_host, port=self._socket_port)
            #self.listen()
            
        except Exception as e:
            logging.error('Fatal error: ' + str(e))
            logging.info(traceback.format_exc())
            return False
        
        return True
        
    def read_socket():
        global JEEDOM_SOCKET_MESSAGE
        if not JEEDOM_SOCKET_MESSAGE.empty():
            logging.debug("Message received in socket JEEDOM_SOCKET_MESSAGE")
            message = json.loads(jeedom_utils.stripped(JEEDOM_SOCKET_MESSAGE.get()))
            if message['apikey'] != self._apikey:
                logging.error("Invalid apikey from socket: " + str(message))
                return
            try:
                # c'est ici que doit être interprété le json envoyé par le php (Pour interpréter les commandes action ?)
                print('read') # dummy...
            except Exception as e:
                logging.error('Send command to daemon error: ' + str(e))
    
    def run():
        self.jsock.open()
        try:
            while True:
                time.sleep(self._cycle)
                self.read_socket()
        except KeyboardInterrupt:
            logging.debug("KeyboardInterrupt raised")
            self.has_stopped.set()
            self.shutdown()
        
    def shutdown(self):
        logging.debug("Shutdown Mymodbus python daemon")
        self.should_stop.set()
        self.has_stopped.wait(timeout=4)
        
        logging.debug("Removing PID file " + self._pidfile)
        try:
            os.remove(self._pidfile)
        except:
            pass
        try:
            self.jsock.close()
        except:
            pass
        # Always exit well
        logging.debug("Exit 0")
        sys.stdout.flush()
        sys.exit(0)

# -----------------------------------------------------------------------------

if __name__ == '__main__':
    # Get an instance of Main
    should_stop = threading.Event()
    should_stop.clear()
    m = Main(should_stop)
    
    # Interrupt handler
    def signal_handler(signum=None, frame=None):
        logging.debug("Signal %d caught, exiting...", signum)
        should_stop.set()

    # Connect the signals to the handler
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    # Ready ? Let's do something now
    m.prepare()
    if m.open_comm():
        m.run()
    m.shutdown()

    # Always exit well
    logger.debug("Exit 0")
    sys.stdout.flush()
    sys.exit(0)