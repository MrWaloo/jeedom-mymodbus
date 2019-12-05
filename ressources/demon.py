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
#from pymodbus.client.sync import ModbusSerialClient

# RTU over TCP
#from pymodbus.client.sync import ModbusTcpClient
#from pymodbus.transaction import ModbusRtuFramer

# TCP/IP
from pyModbusTCP.client import ModbusClient


try:
    opts, args = getopt.getopt(sys.argv[1:], "h:p:P", ["help","unit_id=","polling=","keepopen=","coils=","dis=","hrs=","irs="])
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
      
mymodbus = os.path.abspath(os.path.join(os.path.dirname(__file__), '../core/php/mymodbus.inc.php')) 

# set global
regs = []
rhr = []
rc = []
rdi = []
rir = []
#read_holding_registers = []
#read_coils = []
#read_discrete_inputs = []
#read_input_registers = []
#print polling
# init a thread lock
regs_lock = Lock()
read_holding_registers_lock = Lock()
read_coils_lock = Lock()
read_discrete_inputs_lock = Lock()
read_input_registers_lock = Lock()

# modbus polling thread
#Lecture mode bus over TCP
#c = ModbusTcpClient(host=host, port=port, unit_id=unit_id, debug=False)
def polling_thread():
    global regs
	###################################
    # pymodbusTCP: TCP/IP
    ###################################
    c = ModbusClient(host=host, port=port, unit_id=unit_id, debug=False)
    # polling loop
    while True:
        # keep TCP open
        if not c.is_open():
            print "ouverture de "
            print host
            print port
            c.open()
        if 'hrs' in globals() :
            hr_start=hrs[0]
            i=1
            for hr in hrs:
                if int(hr) == int(hr_start):
                    hr_previous=hr_start
                    if int(hr) == int(hrs[-1]):
                        read_hrs_list = c.read_holding_registers(int(hr_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','sortie=1','inputs='+str(int(hr_start)),'values='+str(read_hrs_list),'add='+host])
                elif int(hr) == int(hr_previous) + 1 :
                    hr_previous=int(hr)
                    i += 1
                    if int(hr) == int(hrs[-1]):
                        read_hrs_list = c.read_holding_registers(int(hr_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','sortie=2','inputs='+str(range(int(hr_start),int(hr_start)+i)),'values='+str(read_hrs_list),'add='+host])
                else :
                    read_hrs_list = c.read_holding_registers(int(hr_start),i)
                    subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','sortie=3','inputs='+str(range(int(hr_start),int(hr_start)+i)),'values='+str(read_hrs_list),'add='+host])
                    hr_start=int(hr)
                    hr_previous=int(hr)
                    i=1
                    if int(hr) == int(hrs[-1]):
                        read_hrs_list = c.read_holding_registers(int(hr_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','sortie=4','inputs='+str(range(int(hr_start),int(hr_start)+i)),'values='+str(read_hrs_list),'add='+host])
             
            #print 'read holding'
           #read_holding_registers_list = c.read_holding_registers(read_holding_registers,read_holding_registers_length-(read_holding_registers-1))
            #read_holding_registers_list = c.read_holding_registers(0,124)
            #print str(read_holding_registers_list)
            #subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','inputs='+str(range(read_holding_registers,read_holding_registers_length+1)),'values='+str(read_holding_registers_list),'add='+host])
        if 'coils' in globals() :
            coil_start=coils[0]
            i=1
            for coil in coils:
                if int(coil) == int(coil_start):
                    coil_previous=coil_start
                    if int(coil) == int(coils[-1]):
                        read_coils_list = c.read_coils(int(coil_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','sortie=1','inputs='+str(int(coil_start)),'values='+str(read_coils_list),'add='+host])
                elif int(coil) == int(coil_previous) + 1 :
                    coil_previous=int(coil)
                    i += 1
                    if int(coil) == int(coils[-1]):
                        read_coils_list = c.read_coils(int(coil_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','sortie=2','inputs='+str(range(int(coil_start),int(coil_start)+i)),'values='+str(read_coils_list),'add='+host])
                else :
                    read_coils_list = c.read_coils(int(coil_start),i)
                    subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','sortie=3','inputs='+str(range(int(coil_start),int(coil_start)+i)),'values='+str(read_coils_list),'add='+host])
                    coil_start=int(coil)
                    coil_previous=int(coil)
                    i=1
                    if int(coil) == int(coils[-1]):
                        read_coils_list = c.read_coils(int(coil_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','sortie=4','inputs='+str(range(int(coil_start),int(coil_start)+i)),'values='+str(read_coils_list),'add='+host])
                    
            #print 'read coil'
            #read_coils_list = c.read_coils(read_coils,read_coils_length-(read_coils-1))
            #print str(read_coils_list)
            #subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','inputs='+str(range(read_coils,read_coils_length+1)),'values='+str(read_coils_list),'add='+host])
        if 'dis' in globals() :
            di_start=dis[0]
            i=1
            for di in dis:
                if int(di) == int(di_start):
                    di_previous=di_start
                    if int(di) == int(dis[-1]):
                        read_dis_list = c.read_discrete_inputs(int(di_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','sortie=1','inputs='+str(int(di_start)),'values='+str(read_dis_list),'add='+host])
                elif int(di) == int(di_previous) + 1 :
                    di_previous=int(di)
                    i += 1
                    if int(di) == int(dis[-1]):
                        read_dis_list = c.read_discrete_inputs(int(di_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','sortie=2','inputs='+str(range(int(di_start),int(di_start)+i)),'values='+str(read_dis_list),'add='+host])
                else :
                    read_dis_list = c.read_discrete_inputs(int(di_start),i)
                    subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','sortie=3','inputs='+str(range(int(di_start),int(di_start)+i)),'values='+str(read_dis_list),'add='+host])
                    di_start=int(di)
                    di_previous=int(di)
                    i=1
                    if int(di) == int(dis[-1]):
                        read_dis_list = c.read_discrete_inputs(int(di_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','sortie=4','inputs='+str(range(int(di_start),int(di_start)+i)),'values='+str(read_dis_list),'add='+host])
            
            #read_discrete_inputs_list = c.read_discrete_inputs(read_discrete_inputs,read_discrete_inputs_length-(read_discrete_inputs-1))
            #print str(read_discrete_inputs_list)
            #subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','inputs='+str(range(read_discrete_inputs,read_discrete_inputs_length+1)),'values='+str(read_discrete_inputs_list),'add='+host])
        if 'irs' in globals() :
            ir_start=irs[0]
            i=1
            for ir in irs:
                if int(ir) == int(ir_start):
                    ir_previous=ir_start
                    if int(ir) == int(irs[-1]):
                        read_irs_list = c.read_input_registers(int(ir_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','sortie=1','inputs='+str(int(ir_start)),'values='+str(read_irs_list),'add='+host])
                elif int(ir) == int(ir_previous) + 1 :
                    ir_previous=int(ir)
                    i += 1
                    if int(ir) == int(irs[-1]):
                        read_irs_list = c.read_input_registers(int(ir_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','sortie=2','inputs='+str(range(int(ir_start),int(ir_start)+i)),'values='+str(read_irs_list),'add='+host])
                else :
                    read_irs_list = c.read_input_registers(int(ir_start),i)
                    subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','sortie=3','inputs='+str(range(int(ir_start),int(ir_start)+i)),'values='+str(read_irs_list),'add='+host])
                    ir_start=int(ir)
                    ir_previous=int(ir)
                    i=1
                    if int(ir) == int(irs[-1]):
                        read_irs_list = c.read_input_registers(int(ir_start),i)
                        subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','sortie=4','inputs='+str(range(int(ir_start),int(ir_start)+i)),'values='+str(read_irs_list),'add='+host])
            
            #read_input_registers_list = c.read_input_registers(read_input_registers,read_input_registers_length-(read_input_registers-1))
            #print str(read_input_registers_list)
            #subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','inputs='+str(range(read_input_registers,read_input_registers_length+1)),'values='+str(read_input_registers_list),'add='+host])
        if keepopen == 0 :
            c.close()
        time.sleep(polling)

# start polling thread
tp = Thread(target=polling_thread)
# set daemon: polling thread will exit if main thread exit
tp.daemon = True
tp.start()

# display loop (in main thread)
while True:
    # print regs list (with thread lock synchronization)
    pass
    #with read_holding_registers_lock:
    #    print ""
        #subprocess.Popen(['/usr/bin/php',mymodbus,'type=holding_registers','values='+str(rhr),'add='+host])
    #with read_coils_lock:
    #    print ""
        #subprocess.Popen(['/usr/bin/php',mymodbus,'type=coils','values='+str(rc),'add='+host])
    #with read_discrete_inputs_lock:
    #    print ""
        #subprocess.Popen(['/usr/bin/php',mymodbus,'type=discrete_inputs','values='+str(rdi),'add='+host])
    #with read_input_registers_lock:
    #    print ""
        #subprocess.Popen(['/usr/bin/php',mymodbus,'type=input_registers','values='+str(rir),'add='+host])
    time.sleep(polling)