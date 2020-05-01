#!/usr/bin/env python
# -*- coding: utf-8 -*-

# modbus_thread
# start a thread for polling a set of registers, display result on console
# exit with ctrl+c

import time
import sys
import getopt
import os
import subprocess
from threading import Thread, Lock

# RTU
from pymodbus.client.sync import ModbusSerialClient

# RTU over TCP
from pymodbus.client.sync import ModbusTcpClient
from pymodbus.transaction import ModbusRtuFramer

# TCP/IP
from pyModbusTCP.client import ModbusClient


try:
    opts, args = getopt.getopt(sys.argv[1:], "h:p:P", ["help","unit_id=","polling=","keepopen=","coils=","dis=","hrs=","irs=","protocol=","eqid="])
except getopt.GetoptError, err:
    print str(err)
    sys.exit()

for o, a in opts:
    if o == "-h":
        host = str(a)
    elif o == "-p":
        port = a
    elif o == "--unit_id":
        unit_id = int(a)
    elif o == "--polling":
        polling = int(a)
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
regs = []
rhr = []
rc = []
rdi = []
rir = []
regs_lock = Lock()
read_holding_registers_lock = Lock()
read_coils_lock = Lock()
read_discrete_inputs_lock = Lock()
read_input_registers_lock = Lock()

# modbus polling thread
def polling_thread():
    #global regs
    if 'protocol' in globals() :
		model = protocol[0]
		if model == "tcpip":
		        # Lecture mode TCP: TCP/IP
			c = ModbusClient(host=host, port=port, unit_id=unit_id, auto_open=True, auto_close=False)
		if model == "rtuovertcp":
		#Lecture mode bus over TCP
			#c = ModbusTcpClient(host=host, port=port, framer=ModbusRtuFramer, debug=False)
			c = ModbusTcpClient(host=host, port=port, framer=ModbusRtuFramer, auto_open=True, auto_close=True, timeout=5)
		if model == "rtu":
			#Lecture mode rtu
			c = ModbusClient(method = "rtu", port=port, stopbits = 1, bytesize = 8, parity = 'N', baudrate= 19200)
    while True:

        if 'hrs' in globals() :
            hr_start=hrs[0]
            i=1
            for hr in hrs:
                if int(hr) == int(hr_start):
                    hr_previous=hr_start
                    if int(hr) == int(hrs[-1]):
                        read_hrs_list = c.read_holding_registers(int(hr_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','sortie=1','inputs='+str(int(hr_start)),'values='+str(read_hrs_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                elif int(hr) == int(hr_previous) + 1 :
                    hr_previous=int(hr)
                    i += 1
                    if int(hr) == int(hrs[-1]):
                        read_hrs_list = c.read_holding_registers(int(hr_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','sortie=2','inputs='+str(range(int(hr_start),int(hr_start)+i)),'values='+str(read_hrs_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                else :
                    read_hrs_list = c.read_holding_registers(int(hr_start),i)
                    subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','sortie=3','inputs='+str(range(int(hr_start),int(hr_start)+i)),'values='+str(read_hrs_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                    hr_start=int(hr)
                    hr_previous=int(hr)
                    i=1
                    if int(hr) == int(hrs[-1]):
                        read_hrs_list = c.read_holding_registers(int(hr_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','sortie=4','inputs='+str(range(int(hr_start),int(hr_start)+i)),'values='+str(read_hrs_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
        if 'coils' in globals() :
            coil_start=coils[0]
            i=1
            for coil in coils:
                if int(coil) == int(coil_start):
                    coil_previous=coil_start
                    if int(coil) == int(coils[-1]):
                        read_coils_list = c.read_coils(int(coil_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','sortie=1','inputs='+str(int(coil_start)),'values='+str(read_coils_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                elif int(coil) == int(coil_previous) + 1 :
                    coil_previous=int(coil)
                    i += 1
                    if int(coil) == int(coils[-1]):
                        read_coils_list = c.read_coils(int(coil_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','sortie=2','inputs='+str(range(int(coil_start),int(coil_start)+i)),'values='+str(read_coils_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                else :
                    read_coils_list = c.read_coils(int(coil_start),i)
                    subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','sortie=3','inputs='+str(range(int(coil_start),int(coil_start)+i)),'values='+str(read_coils_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                    coil_start=int(coil)
                    coil_previous=int(coil)
                    i=1
                    if int(coil) == int(coils[-1]):
                        read_coils_list = c.read_coils(int(coil_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','sortie=4','inputs='+str(range(int(coil_start),int(coil_start)+i)),'values='+str(read_coils_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])

        if 'dis' in globals() :
            di_start=dis[0]
            i=1
            for di in dis:
                if int(di) == int(di_start):
                    di_previous=di_start
                    if int(di) == int(dis[-1]):
                        read_dis_list = c.read_discrete_inputs(int(di_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','sortie=1','inputs='+str(int(di_start)),'values='+str(read_dis_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                elif int(di) == int(di_previous) + 1 :
                    di_previous=int(di)
                    i += 1
                    if int(di) == int(dis[-1]):
                        read_dis_list = c.read_discrete_inputs(int(di_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','sortie=2','inputs='+str(range(int(di_start),int(di_start)+i)),'values='+str(read_dis_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                else :
                    read_dis_list = c.read_discrete_inputs(int(di_start),i)
                    subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','sortie=3','inputs='+str(range(int(di_start),int(di_start)+i)),'values='+str(read_dis_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                    di_start=int(di)
                    di_previous=int(di)
                    i=1
                    if int(di) == int(dis[-1]):
                        read_dis_list = c.read_discrete_inputs(int(di_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','sortie=4','inputs='+str(range(int(di_start),int(di_start)+i)),'values='+str(read_dis_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])

        if 'irs' in globals() :
            ir_start=irs[0]
            i=1
            for ir in irs:
                if int(ir) == int(ir_start):
                    ir_previous=ir_start
                    if int(ir) == int(irs[-1]):
                        read_irs_list = c.read_input_registers(int(ir_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','sortie=1','inputs='+str(int(ir_start)),'values='+str(read_irs_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                elif int(ir) == int(ir_previous) + 1 :
                    ir_previous=int(ir)
                    i += 1
                    if int(ir) == int(irs[-1]):
                        read_irs_list = c.read_input_registers(int(ir_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','sortie=2','inputs='+str(range(int(ir_start),int(ir_start)+i)),'values='+str(read_irs_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                else :
                    read_irs_list = c.read_input_registers(int(ir_start),i)
                    subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','sortie=3','inputs='+str(range(int(ir_start),int(ir_start)+i)),'values='+str(read_irs_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])
                    ir_start=int(ir)
                    ir_previous=int(ir)
                    i=1
                    if int(ir) == int(irs[-1]):
                        read_irs_list = c.read_input_registers(int(ir_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','sortie=4','inputs='+str(range(int(ir_start),int(ir_start)+i)),'values='+str(read_irs_list),'add='+host,'unit='+str(unit_id),'eqid='+str(eq_id)])

        time.sleep(polling)

# start polling thread
tp = Thread(target=polling_thread)
# set daemon: polling thread will exit if main thread exit
tp.daemon = True
tp.start()


while True:
    pass
    time.sleep(polling)