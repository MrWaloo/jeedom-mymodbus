"""
Interface between MyModbus and pymodbus

In this code 'pmb' is used for PyModBus

The logic is the same than in the modbus implementation in Home Assistant as far as I could
"""

import asyncio
import logging
import math
import re
from array import array
from statistics import fmean

from pymodbus.exceptions import ModbusException
from pymodbus.pdu import DecodePDU, ExceptionResponse, ModbusPDU
from pymodbus.pdu.pdu import pack_bitstring, unpack_bitstring

from mymodbuslib import Lib
from mymodbusbase import MyModbusBase


class MyModbusTest(MyModbusBase):

	def read_eqConfig(self, eqConfig: dict[str, any] | None = None) -> None:
		"""
		Creates the client and the requests according to the configuration

		Sets:
		- eventually self.eqConfig
		- self._client_params
		- self._requests (in the subclass)
		- self._blob_dest (in the subclass)
		"""
		super().read_eqConfig(eqConfig)
		
		self._requests = {}
		self._blob_dest = {}
		self._changes = {}
		
		# Création de la liste des requêtes pymodbus
		decoder = DecodePDU(True)
		request_func = decoder.lookup.get(int(self.eqConfig["eqRegTestFunction"]), None)
		
		if request_func is None:
			error = f"le code de fonction Modbus n'est pas disponible: {eqConfig['eqRegTestFunction']}"
			self.log.error(f"{self.eqConfig['name']}: {error}")
			return
		eqRegTestFirst = int(self.eqConfig['eqRegTestFirst'])
		eqRegTestLast = int(self.eqConfig['eqRegTestLast'])
		dev_id = int(self.eqConfig['eqRegTestSlave'])
		count = self.get_count()
		for address in range(eqRegTestFirst, eqRegTestLast + 1):
			self._requests[address] = request_func(address=address, count=count, dev_id=dev_id)
			self.log.debug(f"{self.eqConfig['name']}: 'read_eqConfig' Modbus request for address {address}: {self._requests[address]}")

	async def run_loop(self) -> None:
		"""
		The daemon main loop
		"""
		self.log.debug(f"{self.eqConfig['name']}: 'run_loop' launched in test mode")
		eqWriteCmdCheckTimeout = float(self.eqConfig['eqWriteCmdCheckTimeout'])
		eqErrorDelay = float(self.eqConfig['eqErrorDelay'])
		try:
			while not self.should_stop.is_set():
				self.log.debug(f"{self.eqConfig['name']}: 'run_loop' wait for CMD read")
				await self.read.wait()
				if not self.should_stop.is_set():
					self.stopped.clear()
					await self.async_connect()

					for reg_add, pmb_req in self._requests.items():
						self.log.debug(f"{self.eqConfig['name']}: 'run_loop' Modbus request for address {reg_add}: {pmb_req}")
						if self.should_stop.is_set():
							break

						await self.async_connect()
						error_on_current_read = False

						try:
							async with self._lock:
								self.log.debug(f"{self.eqConfig['name']}: 'run_loop' in test mode requesting read register address = {reg_add}")
								rr: ModbusPDU = await self.client.execute(False, pmb_req)
						except ModbusException as exc:
							error_on_current_read = True
							error = f"exception during read request on device id {pmb_req.dev_id}, address {pmb_req.address} -> {exc!s}"
						if not error_on_current_read:
							try:
								if rr.isError():
									error_on_current_read = True
									error = f"error during read request on device id {pmb_req.dev_id}, address {pmb_req.address} -> {rr}"
							except AttributeError:
								error_on_current_read = True
								error = f"return error during read request on device id {pmb_req.dev_id}, address {pmb_req.address} -> {rr}"
						if not error_on_current_read:
							if isinstance(rr, ExceptionResponse):
								error_on_current_read = True
								error = f"exception during read request on device id {pmb_req.dev_id}, address {pmb_req.address} -> {rr}"
						
						if error_on_current_read:
							self.log.error(f"{self.eqConfig['name']}: {error}")
							await asyncio.sleep(eqErrorDelay) # Laisse le temps pour revenir à la normale
							
						else:
							await asyncio.sleep(eqWriteCmdCheckTimeout) # Cède le contrôle aux autres tâches
						
						self.loop.create_task(self.send_test_result(reg_add, rr, error_on_current_read))

				self.read.clear()
				self.close()
				await asyncio.sleep(eqWriteCmdCheckTimeout) # Cède le contrôle aux autres tâches
				self.stopped.set()

		except asyncio.CancelledError:
			self.log.debug(f"{self.eqConfig['name']}: 'run_loop' cancelled")

		self.close()
		self.log.debug(f"{self.eqConfig['name']}: 'run_loop' exit")

	async def send_test_result(self, reg_add: int, response: ModbusPDU, error: bool) -> None:
		"""
		Reads ModbusPDU and returns the value(s) to Jeedom
		"""
		self.log.debug(f"{self.eqConfig['name']}: 'send_test_result' launched for address = {reg_add}")
		change = {}
		value = None
		if error:
			value = 'ERROR'
		else:
			payload = self.get_payload(response)
			cmd_format: str = self.eqConfig["eqRegTestFormat"]
			if cmd_format == 'bits':
				value = int(payload[0])
			else:
				value = Lib.convert_from_registers(payload, cmd_format)
		change[f"RegTest::{self.eqConfig['id']}::{reg_add}"] = value
		await self.add_change(change)

	def get_payload(self, response: ModbusPDU) -> array:
		attr = Lib.get_request_attribute(response.function_code)
		result = getattr(response, attr)
		payload = b''
		if attr == "bits":
			payload = pack_bitstring(result)
			if len(payload) % 2 == 1:
				payload += b'\x00'
		else:
			payload = result
		payload = array("H", payload)
		return self.get_ordered_payload(payload)
	
	def get_ordered_payload(self, payload: array) -> array:
		count = self.get_count()
		
		payload = array("H", payload)
		if self.eqConfig["eqRegTestInvertBytes"] != "0":
			payload.byteswap()
		if self.eqConfig["eqRegTestInvertWords"] != "0" and count >= 2:
			payload = Lib.wordswap_strict(payload)
		if self.eqConfig["eqRegTestInvertDWords"] != "0" and count >= 4:
			payload = Lib.dwordswap_strict(payload)
		return payload

	def get_count(self) -> int:
		data_type = Lib.get_data_type(self.eqConfig["eqRegTestFormat"])
		count = data_type.value[1]
		if count == 0:
			count = 1
		return count

	async def command_write(self, command: dict) -> None:
		"""
		Execute the write request
		"""
		pass
