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

def read_socket():
	global JEEDOM_SOCKET_MESSAGE
	if not JEEDOM_SOCKET_MESSAGE.empty():
		logging.debug("Message received in socket JEEDOM_SOCKET_MESSAGE")
		message = json.loads(jeedom_utils.stripped(JEEDOM_SOCKET_MESSAGE.get()))
		if message['apikey'] != _apikey:
			logging.error("Invalid apikey from socket: " + str(message))
			return
		try:
            # c'est ici que doit être interprété le json envoyé par le php (mais est-ce vraiment utile ?)
			print('read') # dummy...
		except Exception as e:
			logging.error('Send command to daemon error: ' + str(e))

def listen():
	jeedom_socket.open()
	try:
		while 1:
			time.sleep(0.5)
			read_socket()
	except KeyboardInterrupt:
		shutdown()

# ----------------------------------------------------------------------------

def handler(signum=None, frame=None):
	logging.debug("Signal %i caught, exiting..." % int(signum))
	shutdown()

def shutdown():
	logging.debug("Shutdown")
	logging.debug("Removing PID file " + str(_pidfile))
	try:
		os.remove(_pidfile)
	except:
		pass
	try:
		jeedom_socket.close()
	except:
		pass
	try:
		jeedom_serial.close()
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

# ----------------------------------------------------------------------------
# Section de gestion des arguments passés lors de l'appel
#TODO: à définir entièrement
#TODO: adapter la fonction "deamon_start()" dans core/class/mymodbus.class.php

_log_level = "error"
_socket_port = 55502
_socket_host = 'localhost'
_device = 'auto'
_pidfile = '/tmp/mymodbusd.pid'
_apikey = ''
_callback = ''
_cycle = 0.3

parser = argparse.ArgumentParser(description='Mymodbus values')

parser.add_argument("--device", help="Device", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
parser.add_argument("--socketport", help="Port for Zigbee server", type=int)
args = parser.parse_args()

if args.device:
	_device = args.device
if args.loglevel:
    _log_level = args.loglevel
if args.callback:
    _callback = args.callback
if args.apikey:
    _apikey = args.apikey
if args.pid:
    _pidfile = args.pid
if args.cycle:
    _cycle = float(args.cycle)
if args.socketport:
	_socketport = int(args.socketport)

# ----------------------------------------------------------------------------


jeedom_utils.set_log_level(_log_level)

logging.info('Start daemond')
logging.info('Log level : '+str(_log_level))
logging.info('Socket port : '+str(_socket_port))
logging.info('Socket host : '+str(_socket_host))
logging.info('PID file : '+str(_pidfile))
logging.info('Apikey : '+str(_apikey))
logging.info('Device : '+str(_device))

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)	

try:
	jeedom_utils.write_pid(str(_pidfile))
    
    # jeedom_com : communication daemon --> php
    jeedom_com = jeedom_com(apikey = _apikey,url = _callback,cycle=_cycle) # création de l'objet jeedom_com
    if not jeedom_com.test(): #premier test pour vérifier que l'url de callback est correcte
        logging.error('Network communication issues. Please fixe your Jeedom network configuration.')
        shutdown()

    # jeedom_socket : communication php --> daemon
	jeedom_socket = jeedom_socket(port=_socket_port,address=_socket_host)
	listen()
except Exception as e:
	logging.error('Fatal error : '+str(e))
	logging.info(traceback.format_exc())
	shutdown()

#TODO: déplacer n maximum de code après ce 'if' et éventuellement définir une classe Main() comme dans jMQTT
#if __name__ == '__main__':
    
    
    
    
    

#jeedom_com.send_change_immediate({'key1' : 'value1', 'key2' : 'value2' })