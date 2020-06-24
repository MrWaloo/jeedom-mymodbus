#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Code : Demond Mymobus
date: 28/03/2020
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

import getopt
import os
import subprocess
from threading import Thread, Lock

# RTU
from pymodbus.client.sync import ModbusSerialClient as ModbusClient

# RTU over TCP
from pymodbus.client.sync import ModbusTcpClient
from pymodbus.transaction import ModbusRtuFramer

# TCP/IP
from pymodbus.client.sync import ModbusTcpClient as ModbusClient

# Conversion
from pymodbus.constants import Endian
from pymodbus.payload import BinaryPayloadDecoder
from pymodbus.payload import BinaryPayloadBuilder

try:
    opts, args = getopt.getopt(sys.argv[1:], "h:p:P", ["help","unit_id=","polling=","virg=","swapi32=","coils=","dis=","hrs=","sign=","irs=","protocol=","keepopen=","eqid="])
except getopt.GetoptError as err:
    print(err)
    sys.exit(2)

for o, a in opts:
    if o == "-h":
        host = str(a)
    elif o == "-p":
        port = a
    elif o == "--unit_id":
        unit_id = int(a)
    elif o == "--polling":
        polling = int(a)
    elif o == "--size":
        size = int(a)
    elif o == "--keepopen":
        keepopen = int(a) 
    elif o in ("-h", "--help"):
        usage()
        sys.exit()
    elif o == "--coils":
        coils = a.split(',')
        coils.sort(key=int)
    elif o == "--dis":
        dis = a.split(',')
        dis.sort(key=int)
    elif o == "--hrs":
        hrs = a.split(',')
        hrs.sort(key=int)
    elif o == "--sign":
        sign = a.split(',')
        sign.sort(key=int)        
    elif o == "--virg":
        virg = a.split(',')
        virg.sort(key=int)
    elif o == "--swapi32":
        swapi32 = a.split(',')
        swapi32.sort(key=int)
    elif o == "--irs":
        irs = a.split(',')
        irs.sort(key=int)
    elif o == "--protocol":
        protocol = a.split(',')
        protocol.sort(key=str)
    elif o == "--eqid":
        eq_id = int(a)

mymodbus = os.path.abspath(os.path.join(os.path.dirname(__file__), '../core/php/mymodbus.inc.php'))

# set global

# PID
PID = os.getppid()

# mymodbus polling thread
def polling_thread():
    #global regs
    if 'protocol' in globals():
        model = protocol[0]
        if model == "tcpip":
            # Lecture mode TCP: TCP/IP
            #client = ModbusClient(host=host, port=port)
            client = ModbusClient(host=host, port=port,retries=3, retry_on_empty=True)
        if model == "rtuovertcp":
            #Lecture mode bus over TCP
            client = ModbusClient(host=host, port=port, framer=ModbusRtuFramer)
        if model == "rtu":
            #Lecture mode rtu
            client = ModbusClient(method='rtu', port=port, timeout=1,baudrate=9600)
            #client= ModbusClient(method = 'rtu', port=port, stopbits = 1, bytesize = 8, parity = 'N', baudrate= 9600)   
    while True:
        client.connect()
        if 'hrs' in globals() : #lecture des valeurs holding_register simple
            hreg_first=hrs[0]
            i=1
            for table in hrs:
                if int(table) == int(hreg_first):
                    hr_previous=hreg_first
                    if int(table) == int(hrs[-1]):
                        rr = client.read_holding_registers(int(hreg_first),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=holding_registers','sortie=1','inputs='+str(int(hreg_first)),'values='+str(rr.registers)])
                elif int(table) == int(hr_previous)+1:
                    hr_previous=int(table)
                    i += 1
                    if int(table) == int(hrs[-1]):
                        rr = client.read_holding_registers(int(hreg_first),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=holding_registers','sortie=2','inputs='+str(list(range(int(hreg_first),int(hreg_first)+i))),'values='+str(rr.registers)])
                else :
                    if int(table) != int(hr_previous):
                    	rr = client.read_holding_registers(int(hreg_first),i,unit=unit_id)
                    	subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=holding_registers','sortie=3','inputs='+str(list(range(int(hreg_first),int(hreg_first)+i))),'values='+str(rr.registers)])
                    	hreg_first=int(table)
                    	hr_previous=int(table)
                    	i=1
                    	if int(table) == int(hrs[-1]):
                        	rr = client.read_holding_registers(int(hreg_first),i,unit=unit_id)
                        	subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=holding_registers','sortie=4','inputs='+str(list(range(int(hreg_first),int(hreg_first)+i))),'values='+str(rr.registers)])

        if 'coils' in globals() :
            coil_start=coils[0]
            i=1
            for coil in coils:
                if int(coil) == int(coil_start):
                    coil_previous=coil_start
                    if int(coil) == int(coils[-1]):
                        rr = client.read_coils(int(coil_start),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=coils','sortie=1','inputs='+str(int(coil_start)),'values='+str(rr.bits[:i])])
                elif int(coil) == int(coil_previous) + 1 :
                    coil_previous=int(coil)
                    i += 1
                    if int(coil) == int(coils[-1]):
                        rr = client.read_coils(int(coil_start),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=coils','sortie=2','inputs='+str(list(range(int(coil_start),int(coil_start)+i))),'values='+str(rr.bits[:i])])
                else :
                    if int(coil) != int(coil_previous):
                        rr = client.read_coils(int(coil_start),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=coils','sortie=3','inputs='+str(list(range(int(coil_start),int(coil_start)+i))),'values='+str(rr.bits[:i])])
                        coil_start=int(coil)
                        coil_previous=int(coil)
                        i=1
                        if int(coil) == int(coils[-1]):
                            rr = client.read_coils(int(coil_start),i,unit=unit_id)
                            subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=coils','sortie=4','inputs='+str(list(range(int(coil_start),int(coil_start)+i))),'values='+str(rr.bits[:i])])

        if 'dis' in globals() :
            di_start=dis[0]
            i=1
            for di in dis:
                if int(di) == int(di_start):
                    di_previous=di_start
                    if int(di) == int(dis[-1]):
                        rr = client.read_discrete_inputs(int(di_start),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=discrete_inputs','sortie=1','inputs='+str(int(di_start)),'values='+str(rr.bits[:i])])
                elif int(di) == int(di_previous) + 1 :
                    di_previous=int(di)
                    i += 1
                    if int(di) == int(dis[-1]):
                        rr = client.read_discrete_inputs(int(di_start),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=discrete_inputs','sortie=2','inputs='+str(list(range(int(di_start),int(di_start)+i))),'values='+str(rr.bits[:i])])
                else :
                    if int(di) != int(di_previous):
                        rr = client.read_discrete_inputs(int(di_start),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=discrete_inputs','sortie=3','inputs='+str(list(range(int(di_start),int(di_start)+i))),'values='+str(rr.bits[:i])])
                        di_start=int(di)
                        di_previous=int(di)
                        i=1
                        if int(di) == int(dis[-1]):
                            rr = client.read_discrete_inputs(int(di_start),i,unit=unit_id)
                            subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=discrete_inputs','sortie=4','inputs='+str(list(range(int(di_start),int(di_start)+i))),'values='+str(rr.bits[:i])])

        if 'irs' in globals() :
            ir_start=irs[0]
            i=1
            for ir in irs:
                if int(ir) == int(ir_start):
                    ir_previous=ir_start
                    if int(ir) == int(irs[-1]):
                        rr = client.read_input_registers(int(ir_start),i,unit=unit_id)
                        #assert(not rr.isError()
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=input_registers','sortie=1','inputs='+str(int(ir_start)),'values='+str(rr.registers)])
                elif int(ir) == int(ir_previous) + 1 :
                    ir_previous=int(ir)
                    i += 1
                    if int(ir) == int(irs[-1]):
                        rr = client.read_input_registers(int(ir_start),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=input_registers','sortie=2','inputs='+str(list(range(int(ir_start),int(ir_start)+i))),'values='+str(rr.registers)])
                else :
                    if int(ir) != int(ir_previous):
                        rr = client.read_input_registers(int(ir_start),i,unit=unit_id)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=input_registers','sortie=3','inputs='+str(list(range(int(ir_start),int(ir_start)+i))),'values='+str(rr.registers)])
                        ir_start=int(ir)
                        ir_previous=int(ir)
                        i=1
                        if int(ir) == int(irs[-1]):
                            rr = client.read_input_registers(int(ir_start),i,unit=unit_id)
                            subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=input_registers','sortie=4','inputs='+str(list(range(int(ir_start),int(ir_start)+i))),'values='+str(rr.registers)])

        if 'sign' in globals() : #lecture des valeurs signées
            i = 1
            int_first=sign[0]
            for sign_16 in sign:
                rr = client.read_holding_registers(int(sign_16),i,unit=unit_id)
                #assert(not rr.isError())
                decoder = BinaryPayloadDecoder.fromRegisters(rr.registers,byteorder=Endian.Big,wordorder=Endian.Little)
                #print (int (decoder.decode_16bit_int()))
                subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=sign','sortie=1','inputs='+str(int(sign_16)),'values='+str(int(decoder.decode_16bit_int()))])


        if 'virg' in globals() : #lecture des valeurs à virgules
            i= 2   
            virg_first=virg[0]
            for virg_reg in virg:
                rr = client.read_holding_registers(int(virg_reg),i,unit=unit_id)
                decoder = BinaryPayloadDecoder.fromRegisters(rr.registers,byteorder=Endian.Big,wordorder=Endian.Little)
                subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=virg','sortie=1','inputs='+str(int(virg_reg)),'values='+str(float(round(decoder.decode_32bit_float(),2)))])

        if 'swapi32' in globals() : #lecture des imputregisterwap
            i= 2   
            swapi32_first=swapi32[0]
            for swapi32_reg in swapi32:
                rr = client.read_input_registers(int(swapi32_reg),i,unit=unit_id)
                decoder = BinaryPayloadDecoder.fromRegisters(rr.registers,byteorder=Endian.Big,wordorder=Endian.Big)
                subprocess.Popen(['/usr/bin/php',mymodbus,'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id),'type=swapi32','sortie=1','inputs='+str(int(swapi32_reg)),'values='+str(float(round(decoder.decode_32bit_float(),2)))])

        # ----------------------------------------------------------------------- #
        # close the client
        # ----------------------------------------------------------------------- #
        if keepopen == 0 :
            client.close()
        time.sleep(polling)
        
    
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
        time.sleep(polling)
