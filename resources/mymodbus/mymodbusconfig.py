import json

from jeedomdaemon.base_config import BaseConfig # type: ignore


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
