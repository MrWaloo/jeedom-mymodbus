#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Code : Write Mymobus
date: 27/02/2021
Auteur: @Bebel27
Version: b2.0
"""

from pymodbus.compat import IS_PYTHON3, PYTHON_VERSION
if IS_PYTHON3 and PYTHON_VERSION >= (3, 4):
    print("Version de python ok")
    
else:
    sys.stderr("merci d'installer Python 3 ou de relancer les dépendances Mymodbus")
    sys.exit(1)

import time
import sys
import argparse
import os
import subprocess

parser = argparse.ArgumentParser(description='Mymodbus Write')
#-----------Générale---------------------------------------------------------------------
parser.add_argument("--verbosity", help="mode debug")
parser.add_argument("--protocol", type=str ,help="Choix protocole Modbus" ,required=True)
parser.add_argument("--host", type=str ,help="Choix de l'adresse host")
parser.add_argument("--port", type=str ,help="Choix du port", required=True)
parser.add_argument("--unid", type=int ,help="choix Unit Id", required=True)
parser.add_argument("--eqid", type=int ,help="Numero equipement Jeedom")
#------------RTU-----------------------------------------------------------
parser.add_argument("--baudrate", type=int ,help="vitesse de com en bauds")
parser.add_argument("--stopbits", type=int ,help="bit de stop 1 ou 2")
parser.add_argument("--parity", type=int ,help="parity oui ou non ")
parser.add_argument("--bytesize", type=int ,help="Taile du mot 7 ou 8 ")
#-----------Fonctions---------------------------------------------
parser.add_argument("--wsc", type=int ,help="Write single Coil")
parser.add_argument("--whr", type=int, help="Write holding register")
parser.add_argument("--wmhr", type=int ,help="Write multiple holdings registers")
parser.add_argument("--value", type=int ,help="value")
#------------------------------------------------------------------
# Options demandées
#---------------------
#parser.add_argument("--virg", type=str ,help="Holding à virgules")
#parser.add_argument("--swapi32", type=str ,help="inverse 32bit")
#parser.add_argument("--sign", type=str ,help="valeurs signées")

args = parser.parse_args()

#if args.verbosity:
#    print("verbosity turned on")
    
if args.protocol == 'rtu':
    from pymodbus.client.sync import ModbusSerialClient as ModbusClient
    #client = ModbusClient(method='rtu', port=args.port, timeout=1,baudrate=38400)
    client = ModbusClient(method='rtu', port=args.port, timeout=1,stopbits = 1, bytesize = 8, parity = 'N', baudrate= args.baudrate)
    
if args.protocol == 'tcpip':
    from pymodbus.client.sync import ModbusTcpClient as ModbusClient
    client = ModbusClient(host=args.host, port=args.port,retries=3, retry_on_empty=True)
    
if args.protocol == 'rtuovertcp':
    from pymodbus.client.sync import ModbusTcpClient as ModbusClient
    from pymodbus.transaction import ModbusRtuFramer as ModbusFramer
    client = ModbusClient(host=args.host, port=args.port, framer=ModbusFramer)


    
#if not client.connect():
#    print("unable to connect to "+host+":"+str(port))

client.connect()

if (args.wsc) != None :
    if (args.value) == 1:
        val = True
    if (args.value) == 0 :
        val = False
    rq = client.write_coil(args.wsc, val, unit=args.unid)
    assert(not rq.isError())     # test that we are not an error
        
if (args.whr) != None :
    rq = client.write_register(args.whr, args.value, unit=args.unid)
    assert(not rq.isError())     # test that we are not an error
        
if (args.wmhr) != None :
    rq = client.write_registers(args.wmhr, args.value, unit=args.unid)
        
client.close()
