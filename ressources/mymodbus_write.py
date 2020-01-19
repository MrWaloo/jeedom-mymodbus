#!/usr/bin/env python
# -*- coding: utf-8 -*-

# TCP/IP
from pyModbusTCP.client import ModbusClient

import time
import sys
import getopt
import os
import subprocess

try:
    opts, args = getopt.getopt(sys.argv[1:], "h:p:P", ["help","unit_id=","wsc=","wsr=","value="])
except getopt.GetoptError, err:
    print str(err)
    usage()
    sys.exit()

for o, a in opts:
    if o == "-h":
        host = str(a)
    elif o == "-p":
        port = a
    elif o == "--unit_id":
        unit_id = int(a)
    elif o in ("-h", "--help"):
        usage()
        sys.exit()
    elif o == "--wsc":
        write_single_coil = int(a)
    elif o == "--wsr":
        write_single_register = int(a)
    elif o == "--value":
        value = int(a)
    else:
        usage()
        sys.exit()    

#SERVER_HOST = "localhost"
#SERVER_PORT = 502

c = ModbusClient()
#c = ModbusClient(host=host, port=port, unit_id=unit_id, debug=False)
# uncomment this line to see debug message
#c.debug(True)

# define modbus server host, port , unit_id
c.host(host)
c.port(port)
c.unit_id(unit_id)
#if 'unit_id' in globals() :
#	slave_id = unit_id[0]
if 'write_single_coil' in globals() and value == 1:
    val = True
elif 'write_single_coil' in globals() and value == 0:
    val = False

if not c.open():
    print("unable to connect to "+host+":"+str(port))

if c.is_open():
    print("")
    print("write bits")
    print("----------")
    print("")
    if 'write_single_coil' in globals() :
        is_ok = c.write_single_coil(write_single_coil, val)
        if is_ok:
            print("bit #" + str(write_single_coil) + ": write to " + str(val))
        else:
            print("bit #" + str(write_single_coil) + ": unable to write " + str(val))
    if 'write_single_register' in globals() :
        is_ok = c.write_single_register(write_single_register, value)
        if is_ok:
            print("bit #" + str(write_single_register) + ": write to " + str(value))
        else:
            print("bit #" + str(write_single_register) + ": unable to write " + str(value))
    c.close()