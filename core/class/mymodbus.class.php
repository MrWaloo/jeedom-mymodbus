<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once 'mymodbusConst.class.php';

class mymodbus extends eqLogic {
  /*   * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  const PYTHON_PATH = __DIR__ . '/../../resources/venv/bin/python3';

  const PROTOCOLS = [
    "serial",
    "tcp",
    "udp",
    "rtuovertcp",
    "shared_from"
  ];

  /*   * ***********************Methode static*************************** */
  
  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  
   * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  
   * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  
   * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  
   * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  
   * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function health() {}
  */

  public static function deamon_info() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    $daemon_info = array();
    $daemon_info['log'] = __CLASS__;
    $daemon_info['state'] = self::getDeamonState();
    $daemon_info['launchable'] = self::getDeamonLaunchable();
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * daemon_info = '%s'", json_encode($daemon_info)));
    return $daemon_info;
  }
  
  public static function deamon_start() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    
    if (!plugin::byId(__CLASS__)->isActive()) {
      log::add(__CLASS__, 'error', __('Le plugin Mymodbus n\'est pas actif.', __FILE__));
      return;
    }
    
    // Always stop first.
    self::deamon_stop();

    // Pas de démarrage si ce n'est pas possible
    if (self::getDeamonLaunchable() != 'ok') {
      log::add(__CLASS__, 'error', __('Démarrage du démon impossible, veuillez vérifier la configuration de MyModbus', __FILE__));
      return true;
    }
    
    $eqConfig = self::getCompleteConfiguration();

    $path = realpath(__DIR__ . '/../../resources/' . __CLASS__);
    $daemon_script_name = __CLASS__ . 'd.py';
    $cmd = self::PYTHON_PATH . " {$path}/{$daemon_script_name}";
    $cmd .= ' --loglevel ' . escapeshellarg(log::convertLogLevel(log::getLogLevel(__CLASS__)));
    $cmd .= ' --socketport ' . self::getSocketPort();
    $cmd .= ' --callback ' . escapeshellarg(self::getCallbackUrl());
    $cmd .= ' --apikey ' . escapeshellarg(jeedom::getApiKey(__CLASS__));
    $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
    $cmd .= ' --json ' . escapeshellarg(json_encode($eqConfig));
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * Ligne de commande : ' . $cmd);

    log::add(__CLASS__, 'info', __CLASS__ . '::' . __FUNCTION__ . ' * Lancement du démon MyModbus');
    $result = exec($cmd . ' >> ' . log::getPathToLog(__CLASS__ . '_daemon') . ' 2>&1 &');

    $i = 0;
    while ($i < 10) {
      sleep(1);
      if (self::getDeamonState() === 'ok') {
        break;
      }
      $i++;
    }
    if ($i >= 10) {
      log::add(__CLASS__, 'error', __('Impossible de lancer le démon', __FILE__), 'unableStartDeamon');
      return false;
    }
    message::removeAll(__CLASS__, 'unableStartDeamon');

    return true;
  }
  
  public static function deamon_stop() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    
    $deamon_state = self::getDeamonState();
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * $deamon_state ' . $deamon_state);
    if ($deamon_state === 'nok') {
      return true;
    }
    
    log::add(__CLASS__, 'info', __CLASS__ . '::' . __FUNCTION__ . ' * Arrêt du démon...');
    $message = array();
    $message['CMD'] = 'quit';
    self::sendToDaemon($message);

    $i = 0;
    while ($i < 10) {
      sleep(1);
      if (self::getDeamonState() === 'nok') {
        log::add(__CLASS__, 'info', __CLASS__ . '::' . __FUNCTION__ . ' * Démon arrêté');
        break;
      }
      $i++;
    }
    if ($i >= 10) {
      $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
      if (file_exists($pid_file)) {
        $pid = intval(trim(file_get_contents($pid_file)));
        system::kill($pid);
      }
      sleep(1);
      system::kill('mymodbusd.py');
      sleep(1);
      log::add(__CLASS__, 'info', __CLASS__ . '::' . __FUNCTION__ . ' * Démon tué');
    }
  }
  
  public static function sendToDaemon($params) {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * params = ' . var_export($params, true));
    if (self::getDeamonState() != 'ok') {
      throw new Exception("Le démon n'est pas démarré");
    }
    $params['apikey'] = jeedom::getApiKey(__CLASS__);
    $params['dt'] = date(DATE_ATOM);
    $payLoad = json_encode($params);
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    $socket_port = is_numeric(config::byKey('socketport', __CLASS__, mymodbusConst::DEFAULT_SOCKET_PORT, True)) ? config::byKey('socketport', __CLASS__, mymodbusConst::DEFAULT_SOCKET_PORT) : mymodbusConst::DEFAULT_SOCKET_PORT;
    socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, $socket_port));
    $socket_ok = socket_write($socket, $payLoad, strlen($payLoad));
    if (!$socket_ok) {
      $err = socket_last_error($socket);
      log::add(__CLASS__, 'error', __CLASS__ . '::' . __FUNCTION__ . ' * socket_write ERROR: ' . socket_strerror($err));
    }
    socket_close($socket);
  }

  public function sendNewConfig() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    if (self::getDeamonState() != 'ok') {
      return True;
    }
    
    $message = array();
    $message['CMD'] = 'newDaemonConfig';
    $message['config'] = self::getCompleteConfiguration();
    self::sendToDaemon($message);
  }
  
  public static function supportedProtocols() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    return self::PROTOCOLS;
  }
  
  public static function getSharedInterfaces() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    $interfaces = [];
    foreach (self::byType(__CLASS__) as $eqMymodbus) { // boucle sur les équipements
      if ($eqMymodbus->getIsEnable() && $eqMymodbus->getConfiguration('eqProtocol') != 'shared_from') {
        $interfaces[$eqMymodbus->getId()] = $eqMymodbus->getName();
      }
    }
    return $interfaces;
  }
  
  // tty interfaces
  public static function getTtyInterfaces() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    $interfaces = jeedom::getUsbMapping('', True);
    for ($i = 0; $i<10; $i++) {
      $tty = '/dev/ttyS' . strval($i);
      if (file_exists($tty)) {
        $interfaces[$tty] = $tty;
      } else {
        break;
      }
    }
    for ($i = 0; $i<10; $i++) {
      $tty = '/dev/ttyUSB' . strval($i);
      if (file_exists($tty)) {
        $interfaces[$tty] = $tty;
      } else {
        break;
      }
    }
    $perso_intf = explode(";", config::byKey('interfaces', __CLASS__, '', True));
    foreach ($perso_intf as $intf) {
      if ($intf && file_exists($intf)) {
        $interfaces[$intf] = $intf;
      }
    }
    return $interfaces;
  }
  
  public static function backupExclude() {
    return [
        'resources/venv'
    ];
  }

  public static function dependancy_install() {
      log::remove(__CLASS__ . '_update');
      return array('script' => __DIR__ . '/../../resources/install_#stype#.sh', 'log' => log::getPathToLog(__CLASS__ . '_update'));
  }

  public static function dependancy_info() {
    $return = array();
    $return['log'] = log::getPathToLog(__CLASS__ . '_update');
    $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
    $return['state'] = 'ok';
    if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependance')) {
      $return['state'] = 'in_progress';
    } elseif (!self::pythonRequirementsInstalled(self::PYTHON_PATH, __DIR__ . '/../../resources/requirements.txt')) {
      $return['state'] = 'nok';
    }
    return $return;
  }

  private static function pythonRequirementsInstalled(string $pythonPath, string $requirementsPath) {
    if (!file_exists($pythonPath) || !file_exists($requirementsPath)) {
      return false;
    }
    exec("{$pythonPath} -m pip freeze", $packages_installed);
    $packages = join("||", $packages_installed);
    exec("cat {$requirementsPath}", $packages_needed);
    foreach ($packages_needed as $line) {
      if (preg_match('/([^\s]+)[\s]*([>=~]=)[\s]*([\d+\.?]+)$/', $line, $need) === 1) {
        if (preg_match('/' . $need[1] . '==([\d+\.?]+)/', $packages, $install) === 1) {
          if ($need[2] === '==' && $need[3] != $install[1]) {
            return false;
          } elseif (version_compare($need[3], $install[1], '>')) {
            return false;
          }
        } else {
          return false;
        }
      }
    }
    return true;
  }

  /*
  * =-=-=-=-=-=-=-=-=-=-=-=-= Templates =-=-=-=-=-=-=-=-=-=-=-=-=
  * Les fonctions sont copiées ou inspirées du plugin jMQTT pour
  * la gestion des templates.
  */
  
  // Fonction copiée du plugin jMQTT
  public static function templateRead($_file) {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * file = '%s'", $_file));
    $content = file_get_contents($_file);
    $templateContent = json_decode($content, true);
    $templateKey = array_keys($templateContent)[0];
    return [$templateKey, $templateContent[$templateKey]];
  }

  // Fonction inspirée du plugin jMQTT
  public static function templateList() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    // Get personal and official templates
    $perso = self::getTemplateList(mymodbusConst::PATH_TEMPLATES_USER, mymodbusConst::PREFIX_TEMPLATE_USER);
    $official = self::getTemplateList(mymodbusConst::PATH_TEMPLATES_PUBLIC);
    return array_merge($perso, $official);
  }

  // Fonction inspirée du plugin jMQTT
  public static function getTemplateList($_patern, $_prefix = '') {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * patern = '%s', prefix = '%s'", $_patern, $_prefix));
    $return = array();
    foreach (glob(__DIR__ . '/../../' . $_patern . '*.json') as $file) {
      try {
        $file = realpath($file);
        [$templateKey, $templateValue] = self::templateRead($file);
        $return[] = array($_prefix . $templateKey, $file);
      } catch (Throwable $e) {
        log::add(__CLASS__, 'warning', sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), $file));
      }
    }
    return $return;
  }

  // Fonction inspirée du plugin jMQTT
  public static function templateByName($_name) {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * name = '%s'", $_name));
    if (strpos($_name , mymodbusConst::PREFIX_TEMPLATE_USER) === 0) {
      // Get personal templates
      $name = substr($_name, strlen(mymodbusConst::PREFIX_TEMPLATE_USER));
      $folder = '/../../' . mymodbusConst::PATH_TEMPLATES_USER;
    } else {
      // Get official templates
      $name = $_name;
      $folder = '/../../' . mymodbusConst::PATH_TEMPLATES_PUBLIC;
    }
    foreach (glob(__DIR__ . $folder . '*.json') as $file) {
      try {
        $file = realpath($file);
        [$templateKey, $templateValue] = self::templateRead($file);
        if ($templateKey === $name) {
          return $templateValue;
        }
      } catch (Throwable $e) {
      }
    }
    log::add(__CLASS__, 'warning', sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), $_name));
    throw new Exception($log);
  }

  // Fonction inspirée du plugin jMQTT
  public static function templateByFile($_filename = '') {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * filename = '%s'", $_filename));
    $existing_files = self::templateList();
    $exists = false;
    foreach ($existing_files as list($n, $f)) {
      if ($f === $_filename) {
        $exists = true;
        break;
      }
    }
    if (!$exists) {
      throw new Exception(__("Le template demandé n'existe pas !", __FILE__));
    }
    try {
      [$templateKey, $templateValue] = self::templateRead($_filename);
      return $templateValue;
    } catch (Throwable $e) {
      throw new Exception(sprintf(
          __("Erreur lors de la lecture du Template '%s'", __FILE__),
          $_filename
        )
      );
    }
  }

  // Fonction inspirée du plugin jMQTT
  public static function deleteTemplateByFile($_filename = null) {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * filename = '%s'", $_filename));
    if (!$_filename ||
        !file_exists($_filename) ||
        !is_file($_filename) ||
        dirname($_filename) != realpath(__DIR__ . '/../../' . mymodbusConst::PATH_TEMPLATES_USER)) {
      return false;
    }
    return unlink($_filename);
  }

  // Fonction inspirée du plugin jMQTT
  public function createTemplate($_tName) {
    $export = $this->export();
    
    // Suppression des paramètres d'équipement à spécifier
    if ($export['configuration']['eqProtocol'] === 'serial') {
      $export['configuration']['eqPortSerial'] = '';
    } else {
      $export['configuration']['eqAddr'] = '';
    }

    // Remplacement des id des plages de registres par leur '#[Nom]#'
    foreach ($export['commands'] as &$cmd) {
      if ($cmd['type'] === 'info' && $cmd['configuration']['cmdFctModbus'] === 'fromBlob') {
        $cmdSourceBlob_type = $cmd['subType'] === 'binary' ? 'cmdSourceBlobBin' : 'cmdSourceBlobNum';
        $sourceBlob_id = $cmd['configuration'][$cmdSourceBlob_type];
        $sourceBlob = mymodbusCmd::byId($sourceBlob_id);
        $cmd['configuration'][$cmdSourceBlob_type] = '#[' . $sourceBlob->getName() . ']#';
      }
    }
    unset($cmd);
    
    self::saveTemplateToFile($_tName, $export);
  }
  
  // Fonction inspirée du plugin jMQTT
  public static function saveTemplateToFile($_tName, $_template) {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * tName = '%s', template = '%s'", $_tName, $_template));
    // Cleanup template name
    $_tName = ucfirst(str_replace('  ', ' ', trim($_tName)));
    $_tName = preg_replace('/[^a-zA-Z0-9 ()_-]+/', '', $_tName);

    $template[$_tName] = $_template;
    $template[$_tName]['name'] = $_tName;

    // Convert and save to file
    $jsonExport = json_encode(
      $template,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    $templateDir = realpath(__DIR__ . '/../../' . mymodbusConst::PATH_TEMPLATES_USER) . '/';
    if (!file_exists($templateDir)) {
      if (!mkdir($templateDir, 0775, true)) {
        throw new Exception(__('Impossible de créer le répertoire de téléversement :', __FILE__) . ' ' . $templateDir);
      }
    }
    file_put_contents(
      $templateDir . str_replace(' ', '_', $_tName) . '.json',
      $jsonExport
    );
  }

  // Fonction inspirée du plugin jMQTT
  public function applyATemplate($_template, $_keepCmd = true) {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * template = '%s', keepCmd = '%s'", $_template, $_keepCmd));
    // import template
    $this->import($_template, $_keepCmd);
    $this->save();

    foreach ($this->getCmd() as $cmd) {
      $cmd->save();
    }
  }

  /*   * *********************Méthodes d'instance************************* */
  
  public function copy($_name) {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . sprintf(" * name = '%s'", $_name));
		$eqLogicCopy = clone $this;
		$eqLogicCopy->setName($_name);
		$eqLogicCopy->setId('');
		$eqLogicCopy->save();
		foreach (($eqLogicCopy->getCmd()) as $cmd) {
			$cmd->remove();
		}
		$cmd_link = array();
		foreach (($this->getCmd()) as $cmd) {
			$cmdCopy = clone $cmd;
			$cmdCopy->setId('');
			$cmdCopy->setEqLogic_id($eqLogicCopy->getId());
      if ($cmd->getType() === 'info' && $cmd->getConfiguration('cmdFctModbus') === 'fromBlob') {
        $cmdSourceBlob_type = $cmd->getSubType() === 'binary' ? 'cmdSourceBlobBin' : 'cmdSourceBlobNum';
        $sourceBlob_id = $cmd->getConfiguration($cmdSourceBlob_type);
        $sourceBlob = cmd::byId($sourceBlob_id);
        $copySourceBlob = cmd::byEqLogicIdCmdName($eqLogicCopy->getId(), $sourceBlob->getName());
        $cmdCopy->setConfiguration($cmdSourceBlob_type, $copySourceBlob->getId());
      }
			$cmdCopy->save();
			$cmd_link[$cmd->getId()] = $cmdCopy;
		}
		foreach (($this->getCmd()) as $cmd) {
			if (!isset($cmd_link[$cmd->getId()])) {
				continue;
			}
			if ($cmd->getValue() != '' && isset($cmd_link[$cmd->getValue()])) {
				$cmd_link[$cmd->getId()]->setValue($cmd_link[$cmd->getValue()]->getId());
				$cmd_link[$cmd->getId()]->save();
			}
		}
		return $eqLogicCopy;
	}
  
  // Fonction exécutée automatiquement avant la suppression de l'équipement
  //public function preRemove() {}

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    self::sendNewConfig();
  }
  
  // Fonction exécutée automatiquement avant la sauvegarde de l'équipement (création ou mise à jour)
  // La levée d'une exception invalide la sauvegarde
  public function preSave() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    $configKeys = array_keys($this->getConfiguration());
    // Equipement non activé, pas de vérification
    if ($this->getIsEnable()) {
      // Un nouvel équipement vient d'être ajouté, il faut retourner "true" sinon, l'ajout est invalidé
      if (!in_array('eqProtocol', $configKeys) && !in_array('eqRefreshMode', $configKeys) && !in_array('eqPolling', $configKeys)
      && !in_array('eqWriteCmdCheckTimeout', $configKeys) && !in_array('eqRetries', $configKeys) && !in_array('eqFirstDelay', $configKeys)
      && !in_array('eqErrorDelay', $configKeys)) {
        return True;
      }
      if (!in_array('eqProtocol', $configKeys) || !in_array('eqRefreshMode', $configKeys) || !in_array('eqPolling', $configKeys)
      || !in_array('eqWriteCmdCheckTimeout', $configKeys) || !in_array('eqRetries', $configKeys) || !in_array('eqFirstDelay', $configKeys)
      || !in_array('eqErrorDelay', $configKeys)) {
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Veuillez définir la configuration de base de l\'équipement', __FILE__));
      }
      
      $eqProtocol = $this->getConfiguration('eqProtocol');
      if (!in_array($eqProtocol, self::supportedProtocols())) {
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le protocol n\'est pas défini correctement.', __FILE__));
      }
      if ($eqProtocol != 'shared_from') {
        $eqRefreshMode = $this->getConfiguration('eqRefreshMode');
        $eqPolling = $this->getConfiguration('eqPolling');
        $eqTimeout = $this->getConfiguration('eqTimeout');
        $eqWriteCmdCheckTimeout = $this->getConfiguration('eqWriteCmdCheckTimeout');
        $eqRetries = $this->getConfiguration('eqRetries');
        $eqFirstDelay = $this->getConfiguration('eqFirstDelay');
        $eqErrorDelay = $this->getConfiguration('eqErrorDelay');
        if (!in_array($eqRefreshMode, array('polling', 'cyclic', 'on_event'))) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le mode de rafraîchissement n\'est pas défini correctement.', __FILE__));
        }
        if (!is_numeric($eqPolling)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Polling" doit être un nombre.', __FILE__));
        }
        if ($eqPolling < 0.01) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Polling" doit être au moins à 10 ms. Ou alors il faut passer en mode cyclique', __FILE__));
        }
        if (!is_numeric($eqTimeout)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Timeout" doit être un nombre.', __FILE__));
        }
        if ($eqTimeout < 1) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Timeout" doit être au moins à 1 seconde.', __FILE__));
        }
        if (!is_numeric($eqWriteCmdCheckTimeout)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Temps entre 2 requêtes de lecture (s)" doit être un nombre.', __FILE__));
        }
        if ($eqWriteCmdCheckTimeout < 0) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Temps entre 2 requêtes de lecture (s)" doit être positif.', __FILE__));
        }
        if (!is_numeric($eqRetries)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Nombre de tentatives en cas d\'erreur" doit être un nombre.', __FILE__));
        }
        if ($eqRetries <= 0) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Nombre de tentatives en cas d\'erreur" doit être positif non nul.', __FILE__));
        }
        if (!is_numeric($eqFirstDelay)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Temps d\'attente après la connexion" doit être un nombre.', __FILE__));
        }
        if ($eqFirstDelay < 0) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Temps d\'attente après la connexion" doit être positif.', __FILE__));
        }
        if (!is_numeric($eqErrorDelay)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Temps d\'attente après une erreur de lecture" doit être un nombre.', __FILE__));
        }
        if ($eqErrorDelay < 1) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Temps d\'attente après une erreur de lecture" doit être au moins à 1 seconde.', __FILE__));
        }
      }
      
      if ($eqProtocol === 'serial') {
        // Vérification du paramétrage d'une connexion série
        if (!in_array('eqPortSerial', $configKeys) || !in_array('eqSerialMethod', $configKeys) ||
            !in_array('eqSerialBaudrate', $configKeys) || !in_array('eqSerialBytesize', $configKeys) ||
            !in_array('eqSerialParity', $configKeys) || !in_array('eqSerialStopbits', $configKeys)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Veuillez définir la configuration série de l\'équipement', __FILE__));
        }
        $eqPortSerial = $this->getConfiguration('eqPortSerial');
        $eqSerialMethod = $this->getConfiguration('eqSerialMethod');
        $eqSerialBaudrate = $this->getConfiguration('eqSerialBaudrate');
        $eqSerialBytesize = $this->getConfiguration('eqSerialBytesize');
        $eqSerialParity = $this->getConfiguration('eqSerialParity');
        $eqSerialStopbits = $this->getConfiguration('eqSerialStopbits');
        if ($eqPortSerial === '') {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'interface doit être définie correctement.', __FILE__));
        }
        if (!in_array($eqSerialMethod, array('rtu', 'ascii', 'binary'))) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La méthode de transport n\'est pas définie correctement.', __FILE__));
        }
        if (!is_numeric($eqSerialBaudrate)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La vitesse de transmission Modbus doit être un nombre.', __FILE__));
        }
        if (!in_array($eqSerialBytesize, array('7', '8'))) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le nombre de bits par octet n\'est pas défini correctement.', __FILE__));
        }
        if (!in_array($eqSerialParity, array('E', 'O', 'N'))) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La parité n\'est pas définie correctement.', __FILE__));
        }
        if (!in_array($eqSerialStopbits, array('0', '1', '2'))) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le nombre de bits de stop n\'est pas défini correctement.', __FILE__));
        }

      } elseif ($eqProtocol != 'shared_from') {
        // Vérification du paramétrage d'une connexion TCP
        if (!in_array('eqAddr', $configKeys) || !in_array('eqPortNetwork', $configKeys)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Veuillez définir la configuration réseau de l\'équipement', __FILE__));
        }
        $eqAddr = $this->getConfiguration('eqAddr');
        $eqPortNetwork = $this->getConfiguration('eqPortNetwork');
        if (!filter_var($eqAddr, FILTER_VALIDATE_IP)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse IP n\'est pas valide', __FILE__));
        }
        log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * ' . $this->getHumanName() . ' ' . __('***** DEBUG DEBUG DEBUG ***** ', __FILE__) . sprintf("*'%d'*", var_export($eqPortNetwork, true)));
        if (!is_numeric($eqPortNetwork)) {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le port doit être un nombre.', __FILE__));
        }
      }
    }

    if ($this->getId() != '') {
      $refreshTimeCmdTest = $this->getCmd('info', 'refresh time');
      if (!is_object($refreshTimeCmdTest)) {
        log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * ' . $this->getHumanName() . ' ' . __('Création de commande : Temps de rafraîchissement', __FILE__));
        $refreshTimeCmd = (new mymodbusCmd)
          ->setLogicalId('refresh time')
          ->setEqLogic_id($this->getId())
          ->setName(__('Temps de rafraîchissement', __FILE__))
          ->setType('info')
          ->setSubType('numeric')
          ->setUnite('s')
          ->setOrder(0)
          ->save();
      }
      $refreshCmdTest = $this->getCmd('action', 'refresh');
      if (!is_object($refreshCmdTest)) {
        log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * ' . $this->getHumanName() . ' ' . __('Création de commande : Rafraîchir', __FILE__));
        $refreshCmd = (new mymodbusCmd)
          ->setLogicalId('refresh')
          ->setEqLogic_id($this->getId())
          ->setName(__('Rafraîchir', __FILE__))
          ->setType('action')
          ->setSubType('other');
        $refreshTimeCmd = $this->getCmd('info', 'refresh time');
        $refreshTimeCmd->setOrder(1);
        $refreshTimeCmd->save();
        $refreshCmd->setOrder(0);
        $refreshCmd->save();
      }
      $cycleOkCmdTest = $this->getCmd('info', 'cycle ok');
      if (!is_object($cycleOkCmdTest)) {
        log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * ' . $this->getHumanName() . ' ' . __('Création de commande : Cycle OK', __FILE__));
        $cycleOkCmd = (new mymodbusCmd)
          ->setLogicalId('cycle ok')
          ->setEqLogic_id($this->getId())
          ->setName(__('Cycle OK', __FILE__))
          ->setType('info')
          ->setSubType('binary')
          ->save();
      }
      $pollingCmdTest = $this->getCmd('info', 'polling');
      if (!is_object($pollingCmdTest)) {
        log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * ' . $this->getHumanName() . ' ' . __('Création de commande : Polling', __FILE__));
        $pollingCmd = (new mymodbusCmd)
          ->setLogicalId('polling')
          ->setEqLogic_id($this->getId())
          ->setName(__('Polling', __FILE__))
          ->setType('info')
          ->setSubType('numeric')
          ->setUnite('s')
          ->save();
      }
      
      $offset = 0;
      if (!is_object($refreshTimeCmdTest)) {
        $offset++;
      }
      if (!is_object($refreshCmdTest)) {
        $offset++;
      }
      if ($offset > 0) {
        foreach ($this->getCmd() as $cmdMymodbus) { // boucle sur les commandes
          if (in_array($cmdMymodbus->getLogicalId(), array('refresh', 'refresh time', 'cycle ok', 'polling'))) {
            continue;
          }
          if ($cmdMymodbus->getId() != '') {
            $cmdMymodbus->setOrder($cmdMymodbus->getOrder() + $offset);
            $cmdMymodbus->save();
          }
        }
      }
    }

    // Suppression de l'ancienne configuration
    log::add(__CLASS__, 'info', __CLASS__ . '::' . __FUNCTION__ . ' * ' . $this->getHumanName() . ' ' . __('Suppression de la configuration inutile', __FILE__));
    foreach (array('protocol', 'addr', 'port', 'keepopen', 'polling', 'mheure', 'auto_cmd', 'unit', 'baudrate', 'parity', 'bytesize', 'stopbits',
        'eqKeepopen', 'eqTcpPort', 'eqTcpAddr', 'eqUdpPort', 'eqUdpAddr', 'eqSerialInterface') as $attribut) {
      // log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * ' . $this->getHumanName() . ' ' . __('Check de la conf ', __FILE__) . sprintf("*'%s'*", var_export($attribut, true)));
      if (isset($this->configuration[$attribut])) {
        log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__ . ' * ' . $this->getHumanName() . ' ' . __('Suppression de la conf ', __FILE__) . sprintf("*'%s'*", var_export($attribut, true)));
        $this->setConfiguration($attribut, null);
      }
    }
    // Suppression des éléments de configuration inutiles
    $confOK = $this->getEqConfiguration();
    $conf = $this->getConfiguration();
    $shared_from = $conf['eqProtocol'] === 'shared_from';
    $serial_com = $conf['eqProtocol'] === 'serial';
    foreach ($conf as $key => $value) {
      if ((substr($key, 0, 2) === 'eq' && !in_array($key, array_keys($confOK)) || $value === '')
      && ($shared_from && !in_array($key, ['eqProtocol', 'eqInterfaceFromEqId']) || !$shared_from && (!$serial_com && $key != 'eqPortNetwork' || $serial_com && $key != 'eqPortSerial'))) {
        $this->setConfiguration($key, null);
      }
    }

    //if ($this->getChanged()) {
    //  $this->save();
    //}
    //log::add(__CLASS__, 'debug', 'Validation de la configuration pour l\'équipement *' . $this->getHumanName() . '* : OK');
  }

  // Fonction exécutée automatiquement après la sauvegarde de l'équipement (création ou mise à jour)
  public function postSave() {
  }
  
  public function postAjax() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    self::sendNewConfig();
  }

   /*
  * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
  public function toHtml($_version = 'dashboard') {}
  */

   /*
  * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
  public static function postConfig_<Variable>() {}
  */

   /*
  * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
  public static function preConfig_<Variable>() {}
  */

  /*   * **********************Getteur Setteur*************************** */
  
  // Retourne la configuration des équipements et de leurs commandes
  public static function getCompleteConfiguration() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    $completeConfig = array();
    foreach (self::byType(__CLASS__) as $eqMymodbus) { // boucle sur les équipements
      // ne pas exporter la configuration si l'équipement n'est pas activé
      if (!$eqMymodbus->getIsEnable()) {
        continue;
      }
      
      $EqConfiguration = $eqMymodbus->getEqConfiguration();
      if ($EqConfiguration != []) {
        $completeConfig[] = $EqConfiguration;
      }
    }
    //log::add(__CLASS__, 'debug', 'eqLogic mymodbus getCompleteConfiguration: ' . json_encode($completeConfig));
    return $completeConfig;
  }
  
  // Retourne la configuration de l'équipement et de ses commandes
  public function getEqConfiguration() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    $eqProtocol = $this->getConfiguration('eqProtocol');
    $eqConfig = array();
    if ($eqProtocol === 'shared_from') {
      return $eqConfig;
    }
    $eqConfig['id'] = $this->getId();
    $eqConfig['name'] = trim($this->getName());
    $eqConfig['eqProtocol'] = $eqProtocol;
    $eqConfig['eqRefreshMode'] = $this->getConfiguration('eqRefreshMode', 'polling');
    $eqConfig['eqPolling'] = trim($this->getConfiguration('eqPolling', '5'));
    $eqConfig['eqTimeout'] = trim($this->getConfiguration('eqTimeout', '5'));
    $eqConfig['eqWriteCmdCheckTimeout'] = trim($this->getConfiguration('eqWriteCmdCheckTimeout', '0.01'));
    $eqConfig['eqRetries'] = trim($this->getConfiguration('eqRetries', '3'));
    $eqConfig['eqFirstDelay'] = trim($this->getConfiguration('eqFirstDelay', '0'));
    $eqConfig['eqErrorDelay'] = trim($this->getConfiguration('eqErrorDelay', '1'));
    if ($eqProtocol === 'serial') {
      $eqConfig['eqPort'] = trim($this->getConfiguration('eqPortSerial'));
      $eqConfig['eqSerialMethod'] = $this->getConfiguration('eqSerialMethod');
      $eqConfig['eqSerialBaudrate'] = $this->getConfiguration('eqSerialBaudrate');
      $eqConfig['eqSerialBytesize'] = $this->getConfiguration('eqSerialBytesize');
      $eqConfig['eqSerialParity'] = $this->getConfiguration('eqSerialParity');
      $eqConfig['eqSerialStopbits'] = $this->getConfiguration('eqSerialStopbits');
      
    } else {
      $eqConfig['eqAddr'] = trim($this->getConfiguration('eqAddr'));
      $eqConfig['eqPort'] = trim($this->getConfiguration('eqPortNetwork'));
      
    }
    $eqConfig['cmds'] = array();
    foreach ($this->getCmd() as $cmdMymodbus) { // boucle sur les commandes
      if (in_array($cmdMymodbus->getLogicalId(), array('refresh', 'refresh time', 'cycle ok', 'polling'))) {
        continue;
      }
      $eqConfig['cmds'][] = $cmdMymodbus->getCmdConfiguration();
    }
    
    // Recherche des équipement qui utilise cette interface
    foreach (self::byType(__CLASS__) as $eqMymodbus) { // boucle sur les équipements
      if ($eqMymodbus->getIsEnable()
      && $eqMymodbus->getConfiguration('eqProtocol') === 'shared_from'
      && $eqMymodbus->getConfiguration('eqInterfaceFromEqId') === $this->getId()) {
        foreach ($eqMymodbus->getCmd() as $cmdMymodbus) { // boucle sur les commandes
          if (in_array($cmdMymodbus->getLogicalId(), array('refresh', 'refresh time', 'cycle ok', 'polling'))) {
            continue;
          }
          $eqConfig['cmds'][] = $cmdMymodbus->getCmdConfiguration();
        }
      }
    }
    
    return $eqConfig;
  }
  
  public static function getDeamonState() {
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
    $ret = 'nok';
    if (file_exists($pid_file)) {
      $pid = trim(file_get_contents($pid_file));
      if (@posix_getsid($pid)) {
        $ret = 'ok';
      } else {
        shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
        $ret = 'nok';
      }
    }
    return $ret;
  }
  
  public static function getDeamonLaunchable() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    if (!is_executable(self::PYTHON_PATH)) {
      return 'nok';
    }

    // Si 2 équipements utilisent la même connexion -> nok (workaround provisoire)
    $eqConfigs = self::getCompleteConfiguration();
    $serialIntf = array();
    foreach ($eqConfigs as $config) {
      if ($config['eqProtocol'] === 'serial') {
        $intf = $config['eqPort'];
        if (in_array($intf, $serialIntf)) {
          return 'nok';
        }
        $serialIntf[] = $intf;
      } 
    }
    
    foreach (self::byType(__CLASS__) as $eqMymodbus) { // boucle sur les équipements
      if ($eqMymodbus->getIsEnable()) {
        foreach ($eqMymodbus->getCmd('info') as $cmd) {
          // Au moins une commande enregistrée, donc la configuration est validée par preSave()
          return 'ok';
        }
      }
    }
    return 'nok';
  }
  
  public static function getSocketPort() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    $default_socket_port = mymodbusConst::DEFAULT_SOCKET_PORT;
    $config_socket_port = config::byKey('socketport', __CLASS__, $default_socket_port);
    return is_numeric($config_socket_port) ? $config_socket_port : $default_socket_port;
  }
  
  public static function getCallbackUrl() {
    log::add(__CLASS__, 'debug', __CLASS__ . '::' . __FUNCTION__);
    return network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/mymodbus/core/php/jeemymodbus.php';
  }
}

class mymodbusCmd extends cmd {
  /*   * *************************Attributs****************************** */


  /*   * ***********************Methode static*************************** */


  /*   * *********************Methode d'instance************************* */

  /*
   * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
    public function dontRemoveCmd() {
    return true;
    }
   */
  
  public function execute($_option=array()) {
    log::add('mymodbus', 'debug', __CLASS__ . '::' . __FUNCTION__ . ' **************** execute *****: ' . json_encode($_option));
    
    if ($this->getType() != 'action') {
      return;
    }
    
    $eqMymodbus = $this->getEqLogic();
    
    $command = array();
    $command['eqId'] = $eqMymodbus->getId();
    
    $message = array();
    if ($this->getLogicalId() === 'refresh') {
      if ($eqMymodbus->getConfiguration('eqProtocol') === 'shared_from') {
        $command['eqId'] = $eqMymodbus->getConfiguration('eqInterfaceFromEqId');
      }
      $message['CMD'] = 'read';
      $message['read_cmd'] = $command;
      
    } else {
      $cmdFormat = $this->getConfiguration('cmdFormat');
      
      if (strstr($cmdFormat, 'uint8') || $cmdFormat === 'blob') {
        return;
      }
      
      $value = $this->getConfiguration('cmdWriteValue');
      
      if ($this->getSubtype() === 'message') {
        $value = strval($_option['message']); // the title is ignored
      } elseif ($this->getSubtype() === 'slider') {
        $value = trim(str_replace('#slider#', $_option['slider'], $value));
      } elseif ($this->getSubtype() === 'color') {
        $value = trim(str_replace('#color#', $_option['color'], $value));
      } elseif ($this->getSubtype() === 'select') {
        $value = trim(str_replace('#select#', $_option['select'], $value));
      }
      if (strstr($value, '#value#') && $this->getValue()) {
        $cmdInfo = self::byId($this->getValue());
        $cmdValue = $cmdInfo->execCmd();
        $value = trim(str_replace('#value#', $cmdValue, $value));
      }
      $command['cmdWriteValue'] = jeedom::evaluateExpression($value);
      if ($command['cmdWriteValue'] === '') {
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La valeur à écrire est vide. L\'écriture est ignorée.', __FILE__));
      }

      $command['cmdId'] = $this->getId();
      
      $message['CMD'] = 'write';
      $message['write_cmd'] = $command;
    }
    mymodbus::sendToDaemon($message);
  }

//  public function postInsert() {}
//  public function postRemove() {}
//  public function postSave() {}
  
  // Fonction exécutée automatiquement avant la sauvegarde de la commande (création ou mise à jour)
  // La levée d'une exception invalide la sauvegarde
  public function preSave() {
    log::add('mymodbus', 'debug', __CLASS__ . '::' . __FUNCTION__);
    // Suppression de l'ancienne configuration
    foreach (array('type', 'datatype', 'location', 'request', 'parameters') as $attribut) {
      if (isset($this->configuration[$attribut])) {
        unset($this->configuration[$attribut]);
        $this->_changed = true;
      }
    }
    
    // Suppression des éléments de configuration inutiles
    $confOK = $this->getCmdConfiguration();
    $conf = $this->getConfiguration();
    foreach ($conf as $key => $value) {
      if (substr($key, 0, 3) === 'cmd' && !in_array($key, array_keys($confOK)) && substr($key, 0, 13) !== 'cmdSourceBlob' && $key !== 'cmdOption' && $key !== 'cmdWriteValue' ||
          substr($key, 0, 13) === 'cmdSourceBlob' && $conf['cmdFctModbus'] != 'fromBlob' ||
          $conf['cmdFctModbus'] === 'fromBlob' && $this->getSubType() === 'binary' && $key === 'cmdSourceBlobNum' ||
          $conf['cmdFctModbus'] === 'fromBlob' && $this->getSubType() !== 'binary' && $key === 'cmdSourceBlobBin' ||
          $key === 'cmdSourceBlob') {
        unset($this->configuration[$key]);
        $this->_changed = true;
      }
    }
    
    if (is_null($this->getLogicalId())) {
      $this->setLogicalId('');
      $this->_changed = true;
    }
    if (is_null($this->getValue())) {
      $this->setValue('');
      $this->_changed = true;
    }

    if (in_array($this->getLogicalId(), array('refresh', 'refresh time', 'cycle ok', 'polling'))) {
      return true;
    }
    $cmdSlave = $this->getConfiguration('cmdSlave');
    $cmdAddress = $this->getConfiguration('cmdAddress');
    $cmdFrequency = $this->getConfiguration('cmdFrequency');
    $cmdFormat = $this->getConfiguration('cmdFormat');
    $cmdFctModbus = $this->getConfiguration('cmdFctModbus');
    $cmdOption = $this->getConfiguration('cmdOption');
    if ($cmdSlave === '') {
      $cmdSlave = '1';
      $this->setConfiguration('cmdSlave', $cmdSlave);
    }
    if (!is_numeric($cmdSlave)) {
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse esclave doit être un nombre.<br>\'1\' par défaut.', __FILE__));
    }
    if ($this->getType() === 'info') {
      if ($cmdFrequency === '') {
        $cmdFrequency = '1';
        $this->setConfiguration('cmdFrequency', $cmdFrequency);
      }
      if (!is_numeric($cmdFrequency)) {
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La configuration \'Lecture 1x sur\' doit être un nombre.', __FILE__));
      }
      if ($this->getSubType() === 'binary' && in_array($cmdFctModbus, array('3', '4')) && !preg_match('/#value# & \d+/', $cmdOption)) {
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Pour pouvoir utiliser une fonction de lecture de registre numérique, une commande de type binaire doit avoir un filtre en option', __FILE__));
      }
    }
    if (!is_numeric($cmdAddress) && $cmdFormat != 's' && $cmdFormat != 'blob' && !strstr($cmdFormat, '_sf')) {
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse Modbus doit être un nombre.', __FILE__));
    }
    if ($cmdFormat === 's' && !preg_match('/\d+\s*\[\s*\d+\s*\]/', $cmdAddress)) {
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse Modbus d\'une chaine de caractère doit être de la forme<br>adresse[longueur]', __FILE__));
    }
    if ($cmdFormat === 'blob' && !preg_match('/\d+\s*\[\s*\d+\s*\]/', $cmdAddress)) {
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse Modbus d\'une plage de registres doit être de la forme<br>adresse[longueur]', __FILE__));
    }
    if (strstr($cmdFormat, '_sf') && !preg_match('/\d+\s*sf\s*\d+/i', $cmdAddress)) {
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse Modbus d\'un scale factor doit être de la forme&nbsp;:<br><i>adresse_valeur </i>sf<i> adresse_scale_factor</i>', __FILE__));
    }
    if ($cmdOption != '' && (!strstr($cmdOption, '#value#') || strstr($cmdOption, ';'))) {
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre \'Option\' doit contenir \'#value#\' et aucun \';\'.', __FILE__));
    }
    if ($this->getType() === 'action') {
      if ($cmdFctModbus === '6' && (in_array($cmdFormat, ['i', 'I', 'q', 'Q', 'f', 'd']))) {
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La fonction "[0x06] Write register" ne permet pas d\'écrire une variable de cette longueur.', __FILE__));
      }
      if (strstr($cmdFormat, 'uint8') || $cmdFormat === 'blob' || $cmdFctModbus === 'fromBlob') {
        log::add('mymodbus', 'warning', $this->getHumanName() . '&nbsp;:<br>' . __('L\'écriture sera ignorée.', __FILE__));
      }
      if ($this->getConfiguration('cmdWriteValue') === '') {
        if ($this->getSubType() === 'slider') {
            $this->setConfiguration('cmdWriteValue', '#slider#');
            $this->_changed = true;
        }
        if ($this->getSubType() === 'select') {
            $this->setConfiguration('cmdWriteValue', '#select#');
            $this->_changed = true;
        }
        if ($this->getSubType() === 'color') {
            $this->setConfiguration('cmdWriteValue', '#color#');
            $this->_changed = true;
        }
        if ($this->getConfiguration('cmdWriteValue') === '') {
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La valeur à écrire est vide.', __FILE__));
        }
      } else {
        $cmdWriteValue = $this->getConfiguration('cmdWriteValue');
        if ($this->getSubType() === 'slider' && !strstr($cmdWriteValue, '#slider#')) {
          log::add('mymodbus', 'error', $this->getHumanName() . '&nbsp;:<br>' . __('Le champ valeur ne contient pas \'#slider#\'.', __FILE__));
        }
        if ($this->getSubType() === 'select' && !strstr($cmdWriteValue, '#select#')) {
          log::add('mymodbus', 'error', $this->getHumanName() . '&nbsp;:<br>' . __('Le champ valeur ne contient pas \'#select#\'.', __FILE__));
        }
        if ($this->getSubType() === 'color' && !strstr($cmdWriteValue, '#color#')) {
          log::add('mymodbus', 'error', $this->getHumanName() . '&nbsp;:<br>' . __('Le champ valeur ne contient pas \'#color#\'.', __FILE__));
        }
      }
    }
    if ($cmdFctModbus === 'fromBlob') {
      if ($cmdFormat === 'blob') {
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('On ne peut pas extraire une plage de registres d\'une plage de registres.', __FILE__));
      }
      if ($this->getSubtype() === 'binary') {
        $cmdSourceBlob = $this->getConfiguration('cmdSourceBlobBin');
      } else {
        $cmdSourceBlob = $this->getConfiguration('cmdSourceBlobNum');
      }
      
      if (!is_numeric($cmdSourceBlob) && preg_match('/#\[.*\]#/', $cmdSourceBlob)) {
        $eqMymodbus = $this->getEqLogic();
        foreach ($eqMymodbus->getCmd() as $cmd) {
          if ($cmdSourceBlob === '#[' . $cmd->getName() . ']#') {
            $cmdSourceBlob = $cmd->getId();
            if ($this->getSubtype() === 'binary'){
              $this->setConfiguration('cmdSourceBlobBin', $cmdSourceBlob);
            } else {
              $this->setConfiguration('cmdSourceBlobNum', $cmdSourceBlob);
            }
          }
        }
      }
      
      if (is_numeric($cmdSourceBlob)) {
        $blobCmd = mymodbusCmd::byId($cmdSourceBlob);
        $blobAddress = $blobCmd->getConfiguration('cmdAddress');
        preg_match('/(\d+)\s*\[\s*(\d+)\s*\]/', $blobAddress, $matches);
        $minAddr = intval($matches[1]);
        $maxAddr = $minAddr + intval($matches[2]) - 1;
        
        if ($cmdFormat != 's' && !strstr($cmdFormat, '_sf')) {
          $cmdAddress = intval($cmdAddress);
          if ($cmdAddress < $minAddr or $cmdAddress > $maxAddr) {
            throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Adresse Modbus en dehors de la plage de registres.', __FILE__));
          }
          if ($this->getSubtype() === 'binary') {
            if ($blobCmd->getSubtype() === 'numeric' && !preg_match('/#value# & \d+/', $cmdOption)) {
              throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Pour pouvoir utiliser une plage de lecture de registre numérique, une commande de type binaire doit avoir un filtre en option', __FILE__));
            }
          }
        }
        if ($cmdFormat === 's') {
          preg_match('/(\d+)\s*\[\s*(\d+)\s*\]/', $cmdAddress, $matches);
          $startAddr = intval($matches[1]);
          $endAddr = $startAddr + intval($matches[2]);
          if ($startAddr < $minAddr or $startAddr > $maxAddr or $endAddr < $minAddr or $endAddr > $maxAddr) {
            throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Adresse Modbus en dehors de la plage de registres.', __FILE__));
          }
        }
        if (strstr($cmdFormat, '_sf')) {
          preg_match('/(\d+)\s*sf\s*(\d+)/i', $cmdAddress, $matches);
          $startAddr = intval($matches[1]);
          $endAddr = intval($matches[2]);
          if ($startAddr < $minAddr or $startAddr > $maxAddr or $endAddr < $minAddr or $endAddr > $maxAddr) {
            throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Adresse Modbus en dehors de la plage de registres.', __FILE__));
          }
        }
      }
    }
    
    if (!in_array($cmdFormat, ['i', 'I', 'f', 'q', 'Q', 'd', 's', 'blob', 'i_sf', 'I_sf'])) {
      $this->setConfiguration('cmdInvertWords', '0');
    }
    if (!in_array($cmdFormat, ['q', 'Q', 'd'])) {
      $this->setConfiguration('cmdInvertDWords', '0');
    }
    //log::add('mymodbus', 'debug', 'Validation de la configuration pour la commande *' . $this->getHumanName() . '* : OK');
  }

  public function getCmdConfiguration() {
    //log::add('mymodbus', 'debug', __CLASS__ . '::' . __FUNCTION__);
    $return = array();
    $return['id'] = $this->getId();
    $return['name'] = trim($this->getName());
    $return['type'] = $this->getType();
    $return['cmdSlave'] = trim($this->getConfiguration('cmdSlave'));
    $return['cmdFctModbus'] = $this->getConfiguration('cmdFctModbus');
    if ($return['cmdFctModbus'] === 'fromBlob') {
      if ($this->getSubType() === 'binary') {
        $return['cmdSourceBlob'] = $this->getConfiguration('cmdSourceBlobBin');
      } else {
        $return['cmdSourceBlob'] = $this->getConfiguration('cmdSourceBlobNum');
      }
    }
    $return['cmdFormat'] = trim($this->getConfiguration('cmdFormat'));
    $return['cmdAddress'] = trim($this->getConfiguration('cmdAddress'));
    $return['cmdFrequency'] = trim($this->getConfiguration('cmdFrequency'));
    $return['cmdInvertBytes'] = $this->getConfiguration('cmdInvertBytes');
    $return['cmdInvertWords'] = $this->getConfiguration('cmdInvertWords');
    $return['cmdInvertDWords'] = $this->getConfiguration('cmdInvertDWords');
    $return['repeat'] = $this->getConfiguration('repeatEventManagement', 'never') === 'always' ? '1' : '0';

    return $return;
  }
  
  /*   * **********************Getteur Setteur*************************** */
}

?>
