import json
import asyncio

from jeedomdaemon.base_daemon import BaseDaemon
from jeedomdaemon.base_config import BaseConfig
from jeedomdaemon.utils import Utils

from mymodbusclient import MyModbusClient

# --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

class MyModbusConfig(BaseConfig):
  def __init__(self):
    super().__init__()

    self.add_argument("--json", help="MyModbus configuration json string", type=str, default='{}')

  @property
  def json(self):
    """Returns the decoded json."""
    return json.loads(self._args.json)
  
  @json.setter
  def json(self, config):
    self._args.json = json.dumps(config)

# --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

class MyModbusd(BaseDaemon):
  def __init__(self) -> None:
    self._config = MyModbusConfig()
    super().__init__(
      config = self._config,
      on_start_cb = self.on_start,
      on_message_cb = self.on_message,
      on_stop_cb = self.on_stop
    )
    self.set_logger_log_level("MyModbus")

    self._mymodbus_clients: dict[str, MyModbusClient] = {}
    self._async_tasks: list[asyncio.Task] = []
    self.__n = self.__class__.__name__

  async def on_start(self) -> None:
    for eqConfig in self._config.json:
      asyncio.create_task(self.start_client(eqConfig))

  async def on_message(self, message: dict) -> None:
    """
    Message reçu de Jeedom
    """
    self._logger.debug(f"{self.__n}: 'on_message' '''{message}'''")
    # Checking if it is a command
    if "CMD" not in message:
      self._logger.error(f"{self.__n}: Received data without CMD: {message}")
      return
    else:
      # Quit
      if message["CMD"] == "quit":
        self._logger.info(f"{self.__n}: Command 'quit' received from jeedom: exiting")
        eqIds = [id for id in self._mymodbus_clients.keys()]
        for eqId in eqIds:
          await self.terminate_client(eqId)
        asyncio.create_task(self.stop())
        
      # Write
      elif message["CMD"] == "write":
        if "write_cmd" not in message:
          self._logger.error(f"{self.__n}: Received CMD=write without write_cmd: {message}")
          return
        self._logger.info(f"{self.__n}: Command 'write' received from jeedom: sending the command to MyModbusClient {eqId}: {message['write_cmd']}")
        await self.send_downstream(message["write_cmd"]["eqId"], {"write": message["write_cmd"]})
        
      # Read
      elif message["CMD"] == "read":
        if "read_cmd" not in message:
          self._logger.error(f"{self.__n}: Received CMD=read without read_cmd: {message}")
          return
        self._logger.info(f"{self.__n}: Command 'read' received from jeedom: sending the command to MyModbusClient")
        await self.send_downstream(message["read_cmd"]["eqId"], {"read": message["read_cmd"]})
        
      # newDaemonConfig
      elif message["CMD"] == "newDaemonConfig":
        if "config" not in message:
          self._logger.error(f"{self.__n}: Received CMD=newDaemonConfig without config: {message}")
          return
        asyncio.create_task(self.manage_new_config(message["config"]))

  async def on_stop(self) -> None:
    pass

  async def manage_new_config(self, new_config: dict) -> None:
    self._logger.info(f"{self.__n}: Command 'newDaemonConfig' received from jeedom: sending the new config to all MyModbusClients")
    old_json = self._config.json
    self._config.json = new_config
    old_eqIds, eqIds = [], []
    for cfg in old_json:
      old_eqIds.append(cfg['id'])
    for cfg in self._config.json:
      eqIds.append(cfg['id'])

    # Step 1: terminate daemons of deleted or deactivated equipments
    for eqId in old_eqIds:
      if eqId not in eqIds:
        eqConfig = self.get_config(eqId)
        if eqConfig is not None:
          self._logger.info(f"{self.__n}: 'manage_new_config' Stopping equipment {eqConfig['name']} (id {eqId})")
        else:
          self._logger.info(f"{self.__n}: 'manage_new_config' Stopping equipment id {eqId}")
        await self.terminate_client(eqId)
    
    # Step 2: actualize the config of running daemons
    for eqId in old_eqIds:
      if eqId in eqIds:
        client = self._mymodbus_clients.get(eqId, None)
        if client is None:
          self.clean_client(eqId)
          continue
        if (eqConfig := self.get_config(eqId)) is not None:
          self._logger.info(f"{self.__n}: 'manage_new_config' Actualising the configuration of equipment {eqConfig['name']} (id {eqId})")
          await self.send_downstream(eqId, {"newDaemonConfig": eqConfig})
        
    # Step 3: run new daemons
    for eqId in eqIds:
      if eqId not in old_eqIds:
        if (eqConfig := self.get_config(eqId)) is not None:
          self._logger.info(f"{self.__n}: 'manage_new_config' Starting equipment {eqConfig['name']} (id {eqId})")
          asyncio.create_task(self.start_client(eqConfig))
  
  async def start_client(self, eqConfig: dict) -> None:
    new_client = MyModbusClient(eqConfig)
    self._async_tasks.append(asyncio.create_task(
      self.read_upstream(new_client.upstream, eqConfig["id"]),
      name = eqConfig["id"]
    ))
    new_client.read_eqConfig()
    new_client.connect()
    self._logger.info(f"{self.__n}: Starting the task for the equipement {eqConfig['name']}")
    self._mymodbus_clients[eqConfig["id"]] = new_client

  async def terminate_client(self, eqId: str) -> None:
    await self.send_downstream(eqId, {"quit": None})
    client = self._mymodbus_clients.get(eqId, None)
    if client is None:
      self.clean_client(eqId)
      return
    await client.stopped.wait()
    self.clean_client(eqId)

  def clean_client(self, eqId) -> None:
    task_del = []
    for task in self._async_tasks:
      if task.get_name() == eqId:
        task.cancel()
        task_del.append(task)
    for task in task_del:
      self._async_tasks.remove(task)
    if eqId in self._mymodbus_clients:
      del self._mymodbus_clients[eqId]

  def get_config(self, eqId: str, config = None) -> dict:
    if config is None:
      config = self._config.json
    for eqConfig in config:
      if eqConfig["id"] == eqId:
        return eqConfig

  async def send_downstream(self, eqId, payload) -> None:
    """
    Ecriture dans la Queue Daemon -> MyModbusClient
    """
    mymodbus_client = self._mymodbus_clients.get(eqId, None)
    if mymodbus_client is None:
      self._logger.error(f"{self.__n}: No equipment ID in the message to send to MyModbusClient: {payload}")
      return
    await mymodbus_client.downstream.put(payload)

  async def read_upstream(self, upstream: asyncio.Queue, eqId: str) -> None:
    """
    Lecture des Queue MyModbusClient -> Daemon
    """
    eq_name = self.get_config(eqId)["name"]
    self._logger.debug(f"{self.__n}: 'read_upstream' run for {eq_name} (id = {eqId})")
    try:
      while True:
        message = await upstream.get()
        self._logger.debug(f"{self.__n}: Message received from MyModbusClient {eq_name}: {message}")
        for action, payload in message.items():
          if action in ("to_jeedom", "add_change"):
            for k, v in payload.items():
              try:
                await getattr(self, action)(k, v)
              except Exception as e:
                self._logger.debug(f"{self.__n}: {eq_name}: Error while sending '{action}' - {k} => {v}: {e!s}")
          
        upstream.task_done()

    except asyncio.CancelledError:
      self._logger.debug(f"{self.__n}: 'read_upstream' for {eq_name} (id = {eqId}) cancelled")

    self._logger.debug(f"{self.__n}: 'read_upstream' exit for {eq_name} (id = {eqId})")

MyModbusd().run()
