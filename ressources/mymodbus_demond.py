#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Code : Demond Mymobus
date: 17/02/2021
Auteur: @Bebel27
Version: b2.0
"""
import sys
import time
import argparse
import os
import subprocess
from threading import Thread, Lock

# Conversion
from pymodbus.constants import Endian
from pymodbus.payload import BinaryPayloadDecoder
from pymodbus.payload import BinaryPayloadBuilder

#Compatibility
from pymodbus.compat import IS_PYTHON3, PYTHON_VERSION
if IS_PYTHON3 and PYTHON_VERSION >= (3, 4):
    print("Version de python ok")
    
else:
    sys.stderr("merci d'installer Python 3 ou de relancer les dépendances Mymodbus")
    sys.exit(1)



mymodbus = os.path.abspath(os.path.join(os.path.dirname(__file__), '../core/php/mymodbus.inc.php'))

parser = argparse.ArgumentParser(description='Mymodbus values.')
#-----------Générale---------------------------------------------------------------------
parser.add_argument("--verbosity", help="mode debug")
parser.add_argument("--protocol", type=str ,help="Choix protocole Modbus" ,required=True)
parser.add_argument("--host", type=str ,help="Choix de l'adresse host")
parser.add_argument("--port", type=str ,help="Choix du port", required=True)
parser.add_argument("--polling", type=int ,help="polling en s", required=True)
parser.add_argument("--unid", type=int ,help="choix Unit Id", required=True)
parser.add_argument("--keepopen", type=int ,help="Garde la connexion ouverte")
parser.add_argument("--eqid", type=int ,help="Numero equipement Jeedom", required=True)
#------------RTU-----------------------------------------------------------
parser.add_argument("--baudrate", type=int ,help="vitesse de com en bauds")
parser.add_argument("--stopbits", type=int ,help="bit de stop 1 ou 2")
parser.add_argument("--parity", type=int ,help="parity oui ou non ")
parser.add_argument("--bytesize", type=int ,help="Taile du mot 7 ou 8 ")
#-----------Fonctions---------------------------------------------
parser.add_argument("--coils", type=str ,help="Type Coils")
parser.add_argument("--dis", type=str, help="discrete imput")
parser.add_argument("--hrs", type=str ,help="Holding register")
parser.add_argument("--irs", type=str ,help="imput register")
#------------------------------------------------------------------
# Options demandées
#---------------------
parser.add_argument("--virg", type=str ,help="Holding à virgules")
parser.add_argument("--swapi32", type=str ,help="inverse 32bit")
parser.add_argument("--sign", type=str ,help="valeurs signées")

args = parser.parse_args()

#if args.verbosity:
#    print("verbosity turned on")
    
if args.protocol == 'rtu':
    from pymodbus.client.sync import ModbusSerialClient as ModbusClient
    client = ModbusClient(method='rtu', port=args.port, timeout=10,stopbits = 1, bytesize = 8, parity = 'N', baudrate= args.baudrate)
    
if args.protocol == 'tcpip':
    from pymodbus.client.sync import ModbusTcpClient as ModbusClient
    client = ModbusClient(host=args.host, port=args.port, retries=3, retry_on_empty=True, timeout=10)
    
if args.protocol == 'rtuovertcp':
    from pymodbus.client.sync import ModbusTcpClient as ModbusClient
    from pymodbus.transaction import ModbusRtuFramer as ModbusFramer
    client = ModbusClient(host=args.host, port=args.port, framer=ModbusFramer)

# mymodbus polling thread
def polling_thread():
  
    while True:
        client.connect()

        #lecture Discrete_inputs (2)

        if (args.dis) != None :
            List_dis = (args.dis).split(',')
            di_start=List_dis[0]
            i=1
            for di in List_dis:
                if int(di) == int(di_start):
                    di_previous=di_start
                    if int(di) == int(List_dis[-1]):
                        rr = client.read_discrete_inputs(int(di_start),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=discrete_inputs','sortie=1','inputs='+str(int(di_start)),'values='+str(rr.bits[:i])])
                        print(int(di_start))
                elif int(di) == int(di_previous) + 1 :
                    di_previous=int(di)
                    i += 1
                    if int(di) == int(List_dis[-1]):
                        rr = client.read_discrete_inputs(int(di_start),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=discrete_inputs','sortie=2','inputs='+str(list(range(int(di_start),int(di_start)+i))),'values='+str(rr.bits[:i])])
                else :
                    if int(di) != int(di_previous):
                        rr = client.read_discrete_inputs(int(di_start),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=discrete_inputs','sortie=3','inputs='+str(list(range(int(di_start),int(di_start)+i))),'values='+str(rr.bits[:i])])
                        di_start=int(di)
                        di_previous=int(di)
                        i=1
                        if int(di) == int(List_dis[-1]):
                            rr = client.read_discrete_inputs(int(di_start),i,unit=args.unid)
                            subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=discrete_inputs','sortie=4','inputs='+str(list(range(int(di_start),int(di_start)+i))),'values='+str(rr.bits[:i])])
                            print('sortie4')


        #lecture holding register (3)
                            
        if (args.hrs) != None :
            List_hrs = (args.hrs).split(',')
            hreg_first=List_hrs[0]
            i=1
            for table in List_hrs:
                if int(table) == int(hreg_first):
                    hr_previous=hreg_first
                    if int(table) == int(List_hrs[-1]):
                        rr = client.read_holding_registers(int(hreg_first),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=holding_registers','sortie=1','inputs='+str(int(hreg_first)),'values='+str(rr.registers)])
                elif int(table) == int(hr_previous)+1:
                    hr_previous=int(table)
                    i += 1
                    if int(table) == int(List_hrs[-1]):
                        rr = client.read_holding_registers(int(hreg_first),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=holding_registers','sortie=2','inputs='+str(list(range(int(hreg_first),int(hreg_first)+i))),'values='+str(rr.registers)])
                else :
                    if int(table) != int(hr_previous):
                    	rr = client.read_holding_registers(int(hreg_first),i,unit=args.unid)
                    	subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=holding_registers','sortie=3','inputs='+str(list(range(int(hreg_first),int(hreg_first)+i))),'values='+str(rr.registers)])
                    	hreg_first=int(table)
                    	hr_previous=int(table)
                    	i=1
                    	if int(table) == int(List_hrs[-1]):
                            rr = client.read_holding_registers(int(hreg_first),i,unit=args.unid)
                            subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=holding_registers','sortie=4','inputs='+str(list(range(int(hreg_first),int(hreg_first)+i))),'values='+str(rr.registers)])

        #lecture coils (1)
                        	
        if (args.coils) != None :
            List_coils = (args.coils).split(',')
            coil_start=List_coils[0]
            i=1
            for coil in List_coils:
                if int(coil) == int(coil_start):
                    coil_previous=coil_start
                    if int(coil) == int(List_coils[-1]):
                        rr = client.read_coils(int(coil_start),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=coils','sortie=1','inputs='+str(int(coil_start)),'values='+str(rr.bits[:i])])
                elif int(coil) == int(coil_previous) + 1 :
                    coil_previous=int(coil)
                    i += 1
                    if int(coil) == int(List_coils[-1]):
                        rr = client.read_coils(int(coil_start),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=coils','sortie=2','inputs='+str(list(range(int(coil_start),int(coil_start)+i))),'values='+str(rr.bits[:i])])
                else :
                    if int(coil) != int(coil_previous):
                        rr = client.read_coils(int(coil_start),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=coils','sortie=3','inputs='+str(list(range(int(coil_start),int(coil_start)+i))),'values='+str(rr.bits[:i])])
                        coil_start=int(coil)
                        coil_previous=int(coil)
                        i=1
                        if int(coil) == int(List_coils[-1]):
                            rr = client.read_coils(int(coil_start),i,unit=args.unid)
                            subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=coils','sortie=4','inputs='+str(list(range(int(coil_start),int(coil_start)+i))),'values='+str(rr.bits[:i])])

        #lecture input registers
                            
        if (args.irs) != None :
            List_irs = (args.irs).split(',')
            ir_start=List_irs[0]
            i=1
            for ir in List_irs:
                if int(ir) == int(ir_start):
                    ir_previous=ir_start
                    if int(ir) == int(List_irs[-1]):
                        rr = client.read_input_registers(int(ir_start),i,unit=args.unid)
                        #assert(not rr.isError()
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=input_registers','sortie=1','inputs='+str(int(ir_start)),'values='+str(rr.registers)])
                elif int(ir) == int(ir_previous) + 1 :
                    ir_previous=int(ir)
                    i += 1
                    if int(ir) == int(List_irs[-1]):
                        rr = client.read_input_registers(int(ir_start),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=input_registers','sortie=2','inputs='+str(list(range(int(ir_start),int(ir_start)+i))),'values='+str(rr.registers)])
                else :
                    if int(ir) != int(ir_previous):
                        rr = client.read_input_registers(int(ir_start),i,unit=args.unid)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=input_registers','sortie=3','inputs='+str(list(range(int(ir_start),int(ir_start)+i))),'values='+str(rr.registers)])
                        ir_start=int(ir)
                        ir_previous=int(ir)
                        i=1
                        if int(ir) == int(List_irs[-1]):
                            rr = client.read_input_registers(int(ir_start),i,unit=args.unid)
                            subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=input_registers','sortie=4','inputs='+str(list(range(int(ir_start),int(ir_start)+i))),'values='+str(rr.registers)])


        #lecture des valeurs signées
                            
        if (args.sign) != None :
            List_sign = (args.sign).split(',')
            i = 1
            int_first=List_sign[0]
            for sign_16 in List_sign:
                rr = client.read_holding_registers(int(sign_16),i,unit=args.unid)
                #assert(not rr.isError())
                decoder = BinaryPayloadDecoder.fromRegisters(rr.registers,byteorder=Endian.Big,wordorder=Endian.Little)
                #print (int (decoder.decode_16bit_int()))
                subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=sign','sortie=1','inputs='+str(int(sign_16)),'values='+str(int(decoder.decode_16bit_int()))])

        #lecture des valeurs à virgules

        if (args.virg) != None :
            List_virg = (args.virg).split(',')
            i= 2   
            virg_first=List_virg[0]
            for virg_reg in List_virg:
                rr = client.read_holding_registers(int(virg_reg),i,unit=args.unid)
                decoder = BinaryPayloadDecoder.fromRegisters(rr.registers,byteorder=Endian.Big,wordorder=Endian.Little)
                subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=virg','sortie=1','inputs='+str(int(virg_reg)),'values='+str(float(round(decoder.decode_32bit_float(),2)))])

        #lecture des imputregisters swapées

        if (args.swapi32) != None :
            List_swapi32 = (args.swapi32).split(',')
            i= 2   
            swapi32_first=List_swapi32[0]
            for swapi32_reg in List_swapi32:
                rr = client.read_input_registers(int(swapi32_reg),i,unit=args.unid)
                decoder = BinaryPayloadDecoder.fromRegisters(rr.registers,byteorder=Endian.Big,wordorder=Endian.Big)
                subprocess.Popen(['/usr/bin/php',mymodbus,'add='+args.host,'unit='+str(args.unid),'eqid='+str(args.eqid),'type=swapi32','sortie=1','inputs='+str(int(swapi32_reg)),'values='+str(float(round(decoder.decode_32bit_float(),2)))])

        # ----------------------------------------------------------------------- #
        # close the client
        # ----------------------------------------------------------------------- #
        if args.keepopen == 0 :
            client.close()
        time.sleep(args.polling)
        
    
# start polling thread
t = Thread(target=polling_thread)
# set demond
t.daemon = True
t.start()

if __name__ == '__main__':
    
    while True:
        if t.is_alive():
            pass
            #print("Thread_Ok")
        else:
            #print("thread_Ko")
            raise ParameterException('Thread en défaut')
        time.sleep(1)
