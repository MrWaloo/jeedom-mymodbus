#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Code : Write Mymobus
date: 27/12/2020
Auteur: @Bebel27
Version: b1.0
"""
import sys
import time

from pymodbus.compat import IS_PYTHON3, PYTHON_VERSION
if IS_PYTHON3 and PYTHON_VERSION >= (3, 4):
    print("Version de python ok")
    
else:
    sys.stderr("merci d'installer Python 3 ou de relancer les dépendances Mymodbus")
    sys.exit(1)

import time
import sys
import getopt
import os
import subprocess

#from pymodbus.client.sync import ModbusTcpClient as ModbusClient
from pymodbus.client.sync import ModbusTcpClient as ModbusClient
try:
    opts, args = getopt.getopt(sys.argv[1:], "h:p:P", ["help","unit_id=","wsc=","wsr=","value="])
except getopt.GetoptError as err:
    print (str(err))
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

client = ModbusClient(host=host, port=port)
#c = ModbusClient(host=host, port=port, unit_id=unit_id, debug=False)
# uncomment this line to see debug message
#c.debug(True)


#if 'unit_id' in globals() :
#	slave_id = unit_id[0]
if 'write_single_coil' in globals() and value == 1:
    val = True
elif 'write_single_coil' in globals() and value == 0:
    val = False


    
if not client.connect():
    print("unable to connect to "+host+":"+str(port))

if  client.connect():
    print("")
    print("write bits")
    print("----------")
    print("")
    if 'write_single_coil' in globals() :
        is_ok = client.write_coil(write_single_coil, val, unit=unit_id)
        if is_ok:
            print("bit #" + str(write_single_coil) + ": write to " + str(val))
        else:
            print("bit #" + str(write_single_coil) + ": unable to write " + str(val))
    if 'write_single_register' in globals() :
        is_ok = client.write_registers(write_single_register, value, unit=unit_id)
        if is_ok:
            print("bit #" + str(write_single_register) + ":unit Id " + str(unit_id)+ ": write to " + str(value))
        else:
            print("bit #" + str(write_single_register) + ": unable to write " + str(value))
    client.close()
