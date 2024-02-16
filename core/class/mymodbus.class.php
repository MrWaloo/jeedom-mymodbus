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

  public static $_version = '2.0';

  /*   * ***********************Methode static*************************** */
  
   /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
   * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
   public static function cron5() {}
   */

  /*
   * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
   public static function cron10() {}
   */

  /*
   * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
   public static function cron15() {}
   */

  /*
   * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
   public static function cron30() {}
   */

  /*
   * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
   */

  /*
   * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
   */
  
  /*
   * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function health() {}
   */

  public static function deamon_info() {
    $daemon_info = array();
    $daemon_info['state'] = self::getDeamonState();
    $daemon_info['launchable'] = self::getDeamonLaunchable();
    
    log::add('mymodbus', 'debug', 'deamon_info = ' . json_encode($daemon_info));
    return $daemon_info;
  }
  
  public static function deamon_start() {
    // Always stop first.
    self::deamon_stop();
    
    if (!plugin::byId('mymodbus')->isActive())
      throw new Exception(__('Le plugin Mymodbus n\'est pas actif.', __FILE__));
    
    $eqConfig = self::getCompleteConfiguration();
    
    // Pas de démarrage si ce n'est pas possible
    if (self::getDeamonLaunchable() != 'ok') {
      log::add('mymodbus', 'error', __('Démarrage du démon impossible, veuillez vérifier la configuration de MyModbus', __FILE__));
      return true;
    }
    
    $socketPort = is_numeric(config::byKey('socketport', __CLASS__, mymodbusConst::DEFAULT_SOCKET_PORT, True)) ? config::byKey('socketport', __CLASS__, mymodbusConst::DEFAULT_SOCKET_PORT) : mymodbusConst::DEFAULT_SOCKET_PORT;
    $daemonLoglevel = escapeshellarg(log::convertLogLevel(log::getLogLevel(__CLASS__)));
    $daemonApikey = escapeshellarg(jeedom::getApiKey(__CLASS__));
    $daemonCallback = escapeshellarg(self::getCallbackUrl());
    $jsonEqConfig = escapeshellarg(json_encode($eqConfig));
    
    log::add('mymodbus', 'debug', 'deamon_start socketport *' . $socketPort . '*');
    log::add('mymodbus', 'debug', 'deamon_start API-key *' . $daemonApikey . '*');
    log::add('mymodbus', 'debug', 'deamon_start callbackURL *' . $daemonCallback . '*');
    log::add('mymodbus', 'debug', 'deamon_start config *' . $jsonEqConfig . '*');
    
    $request = ' --socketport ' . $socketPort . ' --loglevel ' . $daemonLoglevel . ' --apikey ' . $daemonApikey . ' --callback ' . $daemonCallback . ' --json ' . $jsonEqConfig;
    
    $mymodbus_path = realpath(__DIR__ . '/../../ressources/mymodbusd');
    $pyenv_path = realpath(__DIR__ . '/../../ressources/_pyenv');
    $cmd = 'export PYENV_ROOT="' . $pyenv_path . '"; command -v pyenv >/dev/null || export PATH="$PYENV_ROOT/bin:$PATH"; eval "$(pyenv init -)"; ';
    $cmd .= 'cd ' . $mymodbus_path . '; ';
    $cmd .= 'nice -n 19 python3 mymodbusd.py' . $request;
    log::add('mymodbus', 'info', 'Lancement du démon mymodbus : ' . $cmd);     
    $result = exec($cmd . ' >> ' . log::getPathToLog('mymodbus') . ' 2>&1 &');
    
    if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
      log::add('mymodbus', 'error', $result);
      return false;
    }
  }
  
  public static function deamon_stop() {
    log::add('mymodbus', 'info', 'deamon_stop: Début');
    
    $deamon_state = self::getDeamonState();
    log::add('mymodbus', 'debug', 'deamon_stop $deamon_state ' . $deamon_state);
    if ($deamon_state == 'nok')
      return True;
    
    log::add('mymodbus', 'info', 'deamon_stop: Arrêt du démon...');
    $message = array();
    $message['CMD'] = 'quit';
    self::sendToDaemon($message);
    sleep(3);
    
    log::add('mymodbus', 'info', 'deamon_stop: Démon arrêté');
  }
  
  public static function sendToDaemon($params) {
    if (self::getDeamonState() != 'ok') {
      throw new Exception("Le démon n'est pas démarré");
    }
    $params['apikey'] = jeedom::getApiKey(__CLASS__);
    $params['dt'] = date(DATE_ATOM);
    $payLoad = json_encode($params);
    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
    $socket_port = is_numeric(config::byKey('socketport', __CLASS__, mymodbusConst::DEFAULT_SOCKET_PORT, True)) ? config::byKey('socketport', __CLASS__, mymodbusConst::DEFAULT_SOCKET_PORT) : mymodbusConst::DEFAULT_SOCKET_PORT;
    socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, $socket_port));
    $socket_ok = socket_write($socket, $payLoad, strlen($payLoad));
    if (!$socket_ok) {
      $err = socket_last_error($socket);
      log::add('mymodbus', 'error', 'sendToDaemon: socket_write ERROR: ' . socket_strerror($err));
    }
    socket_close($socket);
  }

  public function sendNewConfig() {
    if (self::getDeamonState() != 'ok')
      return True;
    
    $message = array();
    $message['CMD'] = 'newDaemonConfig';
    $message['config'] = self::getCompleteConfiguration();
    self::sendToDaemon($message);
  }
  
  // Supported protocols are in desktop/modal/eqConfig_[protocol].php
  public static function supportedProtocols() {
    $protocols = array();
    foreach (glob(__DIR__ . '/../../desktop/modal/eqConfig_*.php') as $file) {
      $protocols[] = substr(basename($file), strlen('eqConfig_'), strlen('.php') * -1);
    }
    return $protocols;
  }
  
  // tty interfaces
  public static function getTtyInterfaces() {
    $interfaces = jeedom::getUsbMapping('', True);
    for ($i = 0; $i<10; $i++) {
      $tty = '/dev/ttyS' . strval($i);
      if (file_exists($tty))
        $interfaces[$tty] = $tty;
      else
        break;
    }
    for ($i = 0; $i<10; $i++) {
      $tty = '/dev/ttyUSB' . strval($i);
      if (file_exists($tty))
        $interfaces[$tty] = $tty;
      else
        break;
    }
    $perso_intf = explode(";", config::byKey('interfaces', __CLASS__, '', True));
    foreach ($perso_intf as $intf) {
      if ($intf && file_exists($intf))
        $interfaces[$intf] = $intf;
    }
    return $interfaces;
  }
  
  public static function changeLogLevel($level=null) {
    $message = array();
    $message['CMD'] = 'setLogLevel';
    $message['level'] = is_null($level) ? log::getLogLevel(__class__) : $level;
    if (is_numeric($message['level'])) // Replace numeric log level with text level
      $message['level'] = log::convertLogLevel($message['level']);
    self::sendToDaemon($message);
  }
  
  /*
  * =-=-=-=-=-=-=-=-=-=-=-=-= Templates =-=-=-=-=-=-=-=-=-=-=-=-=
  * Les fonctions sont copiées ou inspirées du plugin jMQTT pour
  * la gestion des templates.
  */
  
  // Fonction copiée du plugin jMQTT
  public static function templateRead($_file) {
    $content = file_get_contents($_file);
    $templateContent = json_decode($content, true);
    $templateKey = array_keys($templateContent)[0];
    return [$templateKey, $templateContent[$templateKey]];
  }

  // Fonction inspirée du plugin jMQTT
  public static function templateList() {
    // Get personal and official templates
    $perso = self::getTemplateList(mymodbusConst::PATH_TEMPLATES_PERSO, mymodbusConst::PREFIX_TEMPLATE_PERSO);
    $official = self::getTemplateList(mymodbusConst::PATH_TEMPLATES_MYMODBUS);
    return array_merge($perso, $official);
  }

  // Fonction inspirée du plugin jMQTT
  public static function getTemplateList($_patern, $_prefix = '') {
    $return = array();
    foreach (glob(__DIR__ . '/../../' . $_patern . '*.json') as $file) {
      try {
        $file = realpath($file);
        [$templateKey, $templateValue] = self::templateRead($file);
        $return[] = array($_prefix . $templateKey, $file);
      } catch (Throwable $e) {
        log::add('mymodbus', 'warning', sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), $file));
      }
    }
    return $return;
  }

  // Fonction inspirée du plugin jMQTT
  public static function templateByName($_name) {
    if (strpos($_name , mymodbusConst::PREFIX_TEMPLATE_PERSO) === 0) {
      // Get personal templates
      $name = substr($_name, strlen(mymodbusConst::PREFIX_TEMPLATE_PERSO));
      $folder = '/../../' . mymodbusConst::PATH_TEMPLATES_PERSO;
    } else {
      // Get official templates
      $name = $_name;
      $folder = '/../../' . mymodbusConst::PATH_TEMPLATES_MYMODBUS;
    }
    foreach (glob(__DIR__ . $folder . '*.json') as $file) {
      try {
        $file = realpath($file);
        [$templateKey, $templateValue] = self::templateRead($file);
        if ($templateKey == $name)
          return $templateValue;
      } catch (Throwable $e) {
      }
    }
    log::add('mymodbus', 'warning', sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), $_name));
    throw new Exception($log);
  }

  // Fonction inspirée du plugin jMQTT
  public static function templateByFile($_filename = ''){
    $existing_files = self::templateList();
    $exists = false;
    foreach ($existing_files as list($n, $f))
      if ($f == $_filename) {
        $exists = true;
        break;
      }
    if (!$exists)
      throw new Exception(__("Le template demandé n'existe pas !", __FILE__));
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
    if (!$_filename ||
        !file_exists($_filename) ||
        !is_file($_filename) ||
        dirname($_filename) != realpath(__DIR__ . '/../../' . mymodbusConst::PATH_TEMPLATES_PERSO))
      return false;
    return unlink($_filename);
  }

  // Fonction inspirée du plugin jMQTT
  public function createTemplate($_tName) {
    $export = $this->export();
    
    // Suppression des paramètres d'équipement à spécifier
    if ($export['configuration']['eqProtocol'] === 'serial') {
      $export['configuration']['eqSerialInterface'] = '';
    } elseif ($export['configuration']['eqProtocol'] === 'tcp') {
      $export['configuration']['eqTcpAddr'] = '';
    } elseif ($export['configuration']['eqProtocol'] === 'udp') {
      $export['configuration']['eqUdpAddr'] = '';
    }

    // Remplacement des id des plages de registres par leur '#[Nom]#'
    foreach ($export['commands'] as &$cmd) {
      if ($cmd['type'] == 'info' && $cmd['configuration']['cmdFctModbus'] == 'fromBlob') {
        if ($cmd['subType'] == 'binary')
          $cmdSourceBlob_type = 'cmdSourceBlobBin';
        else
          $cmdSourceBlob_type = 'cmdSourceBlobNum';
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
    $templateDir = __DIR__ . '/../../' . mymodbusConst::PATH_TEMPLATES_PERSO;
    if (!file_exists($templateDir)) {
      if (!mkdir($templateDir, 0775, true))
        throw new Exception(__('Impossible de créer le répertoire de téléversement :', __FILE__) . ' ' . $templateDir);
    }
    file_put_contents(
      $templateDir . str_replace(' ', '_', $_tName) . '.json',
      $jsonExport
    );
  }

  // Fonction inspirée du plugin jMQTT
  public function applyATemplate($_template, $_keepCmd = true) {
    // import template
    $this->import($_template, $_keepCmd);
    $this->save();

    foreach ($this->getCmd() as $cmd)
      $cmd->save();
  }

  /*   * *********************Méthodes d'instance************************* */
  
  public function copy($_name) {
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
      if ($cmd->getType() == 'info' && $cmd->getConfiguration('cmdFctModbus') == 'fromBlob') {
        if ($cmd->getSubType() == 'binary')
          $cmdSourceBlob_type = 'cmdSourceBlobBin';
        else
          $cmdSourceBlob_type = 'cmdSourceBlobNum';
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
    self::sendNewConfig();
  }
  
  // Fonction exécutée automatiquement avant la sauvegarde de l'équipement (création ou mise à jour)
  // La levée d'une exception invalide la sauvegarde
  public function preSave() {
    $configKeys = array_keys($this->getConfiguration());
    // Equipement non activé, pas de vérification
    if (!$this->getIsEnable())
      return True;
    // Un nouvel équipement vient d'être ajouté, il faut retourner "true" sinon, l'ajout est invalidé
    if (!in_array('eqProtocol', $configKeys) && !in_array('eqKeepopen', $configKeys) && !in_array('eqRefreshMode', $configKeys)
        && !in_array('eqPolling', $configKeys) && !in_array('eqWriteCmdCheckTimeout', $configKeys) && !in_array('eqFirstDelay', $configKeys))
      return True;
    if (!in_array('eqProtocol', $configKeys) || !in_array('eqKeepopen', $configKeys) || !in_array('eqRefreshMode', $configKeys)
        || !in_array('eqPolling', $configKeys) || !in_array('eqWriteCmdCheckTimeout', $configKeys) || !in_array('eqFirstDelay', $configKeys))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Veuillez définir la configuration de base de l\'équipement', __FILE__));
    
    $eqProtocol = $this->getConfiguration('eqProtocol');
    $eqRefreshMode = $this->getConfiguration('eqRefreshMode');
    $eqPolling = $this->getConfiguration('eqPolling');
    $eqWriteCmdCheckTimeout = $this->getConfiguration('eqWriteCmdCheckTimeout');
    $eqFirstDelay = $this->getConfiguration('eqFirstDelay');
    if (!in_array($eqProtocol, self::supportedProtocols()))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le protocol n\'est pas défini correctement.', __FILE__));
    if (!in_array($eqRefreshMode, array('polling', 'cyclic', 'on_event')))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le mode de rafraîchissement n\'est pas défini correctement.', __FILE__));
    if (!is_numeric($eqPolling))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Polling" doit être un nombre.', __FILE__));
    if ($eqPolling < 1)
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Polling" doit être au moins à 1 seconde', __FILE__));
    if (!is_numeric($eqWriteCmdCheckTimeout))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Timeout pour vérification d\'une commande action" doit être un nombre.', __FILE__));
    if ($eqWriteCmdCheckTimeout < 0.1)
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Timeout pour vérification d\'une commande action" doit être au moins à 0.1 seconde', __FILE__));
    if (!is_numeric($eqFirstDelay))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Temps entre la connexion et la première requête" doit être un nombre.', __FILE__));
    if ($eqFirstDelay < 0)
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre "Temps entre la connexion et la première requête" doit être positif.', __FILE__));
    
    if ($eqProtocol == 'tcp') {
      // Vérification du paramétrage d'une connexion TCP
      if (!in_array('eqTcpAddr', $configKeys) && !in_array('eqTcpPort', $configKeys) && !in_array('eqTcpRtu', $configKeys)) {
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Veuillez définir la configuration TCP de l\'équipement', __FILE__));
      }
      $eqTcpAddr = $this->getConfiguration('eqTcpAddr');
      $eqTcpPort = $this->getConfiguration('eqTcpPort');
      if (!filter_var($eqTcpAddr, FILTER_VALIDATE_IP))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse IP n\'est pas valide', __FILE__));
      if (!is_numeric($eqTcpPort))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le port doit être un nombre.', __FILE__));
      
    } elseif ($eqProtocol == 'udp') {
      // Vérification du paramétrage d'une connexion UDP
      if (!in_array('eqUdpAddr', $configKeys) && !in_array('eqUdpPort', $configKeys))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Veuillez définir la configuration UDP de l\'équipement', __FILE__));
      $eqUdpAddr = $this->getConfiguration('eqUdpAddr');
      $eqUdpPort = $this->getConfiguration('eqUdpPort');
      if (!filter_var($eqUdpAddr, FILTER_VALIDATE_IP))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse IP n\'est pas valide', __FILE__));
      if (!is_numeric($eqUdpPort))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le port doit être un nombre.', __FILE__));
      
    } elseif ($eqProtocol == 'serial') {
      // Vérification du paramétrage d'une connexion série
      if (!in_array('eqSerialInterface', $configKeys) || !in_array('eqSerialMethod', $configKeys) ||
          !in_array('eqSerialBaudrate', $configKeys) || !in_array('eqSerialBytesize', $configKeys) ||
          !in_array('eqSerialParity', $configKeys) || !in_array('eqSerialStopbits', $configKeys))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Veuillez définir la configuration série de l\'équipement', __FILE__));
      $eqSerialInterface = $this->getConfiguration('eqSerialInterface');
      $eqSerialMethod = $this->getConfiguration('eqSerialMethod');
      $eqSerialBaudrate = $this->getConfiguration('eqSerialBaudrate');
      $eqSerialBytesize = $this->getConfiguration('eqSerialBytesize');
      $eqSerialParity = $this->getConfiguration('eqSerialParity');
      $eqSerialStopbits = $this->getConfiguration('eqSerialStopbits');
      if ($eqSerialInterface == '')
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'interface doit être définie correctement.', __FILE__));
      if (!in_array($eqSerialMethod, array('rtu', 'ascii', 'binary')))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La méthode de transport n\'est pas définie correctement.', __FILE__));
      if (!is_numeric($eqSerialBaudrate))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La vitesse de transmission Modbus doit être un nombre.', __FILE__));
      if (!in_array($eqSerialBytesize, array('7', '8')))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le nombre de bits par octet n\'est pas défini correctement.', __FILE__));
      if (!in_array($eqSerialParity, array('E', 'O', 'N')))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La parité n\'est pas définie correctement.', __FILE__));
      if (!in_array($eqSerialStopbits, array('0', '1', '2')))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le nombre de bits de stop n\'est pas défini correctement.', __FILE__));
    }
    
    if ($this->getId() != '') {
      $refreshCmdTest = $this->getCmd('action', 'refresh');
      $refreshTimeCmdTest = $this->getCmd('info', 'refresh time');
      if (!is_object($refreshTimeCmdTest)) {
        log::add(__CLASS__, 'debug', $this->getHumanName() . ' ' . __('Création de commande : Temps de rafraîchissement', __FILE__));
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
      if (!is_object($refreshCmdTest)) {
        log::add(__CLASS__, 'debug', $this->getHumanName() . ' ' . __('Création de commande : Rafraîchir', __FILE__));
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
      
      $offset = 0;
      if (!is_object($refreshTimeCmdTest))
        $offset++;
      if (!is_object($refreshCmdTest))
        $offset++;
      if ($offset > 0)
        foreach ($this->getCmd() as $cmdMymodbus) { // boucle sur les commandes
          if (in_array($cmdMymodbus->getLogicalId(), array('refresh', 'refresh time')))
            continue;
          if ($cmdMymodbus->getId() != '') {
            $cmdMymodbus->setOrder($cmdMymodbus->getOrder() + $offset);
            $cmdMymodbus->save();
          }
        }
    }
    
    // Suppression de l'ancienne configuration
    $old_config = $this->configuration;
    foreach (array('protocol', 'addr', 'port', 'keepopen', 'polling', 'mheure', 'auto_cmd', 'unit', 'baudrate', 'parity', 'bytesize', 'stopbits') as $attribut)
      if (isset($this->configuration[$attribut])) {
        unset($this->configuration[$attribut]);
        $this->_changed = true;
      }
    // Suppression des éléments de configuration inutiles
    $confOK = $this->getEqConfiguration();
    $conf = $this->getConfiguration();
    foreach ($conf as $key => $value) {
      if (substr($key, 0, 2) == 'eq' && !in_array($key, array_keys($confOK))) {
        unset($this->configuration[$key]);
        $this->_changed = true;
      }
    }
    //log::add('mymodbus', 'debug', 'Validation de la configuration pour l\'équipement *' . $this->getHumanName() . '* : OK');
  }

   /*
  * Fonction exécutée automatiquement après la sauvegarde de l'équipement (création ou mise à jour)
  public function postSave() {}
  */
  
  public function postAjax() {
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
    $completeConfig = array();
    foreach (self::byType('mymodbus') as $eqMymodbus) { // boucle sur les équipements
      // ne pas exporter la configuration si l'équipement n'est pas activé
      if (!$eqMymodbus->getIsEnable())
        continue;
      
      $completeConfig[] = $eqMymodbus->getEqConfiguration();
    }
    log::add(__CLASS__, 'debug', 'eqLogic mymodbus getCompleteConfiguration: ' . json_encode($completeConfig));
    return $completeConfig;
  }
  
  // Retourne la configuration de l'équipement et de ses commandes
  public function getEqConfiguration() {
    $eqConfig = array();
    $eqConfig['id'] = $this->getId();
    $eqConfig['name'] = $this->getName();
    $eqConfig['eqProtocol'] = $this->getConfiguration('eqProtocol');
    $eqConfig['eqKeepopen'] = $this->getConfiguration('eqKeepopen');
    $eqConfig['eqRefreshMode'] = $this->getConfiguration('eqRefreshMode', 'polling');
    $eqConfig['eqPolling'] = $this->getConfiguration('eqPolling', '5');
    $eqConfig['eqWriteCmdCheckTimeout'] = $this->getConfiguration('eqWriteCmdCheckTimeout', '1');
    $eqConfig['eqFirstDelay'] = $this->getConfiguration('eqFirstDelay', '0');
    if ($eqConfig['eqProtocol'] == 'serial') {
      $eqConfig['eqSerialInterface'] = $this->getConfiguration('eqSerialInterface');
      $eqConfig['eqSerialMethod'] = $this->getConfiguration('eqSerialMethod');
      $eqConfig['eqSerialBaudrate'] = $this->getConfiguration('eqSerialBaudrate');
      $eqConfig['eqSerialBytesize'] = $this->getConfiguration('eqSerialBytesize');
      $eqConfig['eqSerialParity'] = $this->getConfiguration('eqSerialParity');
      $eqConfig['eqSerialStopbits'] = $this->getConfiguration('eqSerialStopbits');
      
    } elseif ($eqConfig['eqProtocol'] == 'tcp') {
      $eqConfig['eqTcpAddr'] = $this->getConfiguration('eqTcpAddr');
      $eqConfig['eqTcpPort'] = $this->getConfiguration('eqTcpPort');
      $eqConfig['eqTcpRtu'] = $this->getConfiguration('eqTcpRtu');
      
    } elseif  ($eqConfig['eqProtocol'] == 'udp') {
      $eqConfig['eqUdpAddr'] = $this->getConfiguration('eqUdpAddr');
      $eqConfig['eqUdpPort'] = $this->getConfiguration('eqUdpPort');
      
    }
    $eqConfig['cmds'] = array();
    foreach ($this->getCmd() as $cmdMymodbus) { // boucle sur les commandes
      if (in_array($cmdMymodbus->getLogicalId(), array('refresh', 'refresh time')))
        continue;
      
      $eqConfig['cmds'][] = $cmdMymodbus->getCmdConfiguration();
    }
    return $eqConfig;
  }
  
  public static function getDeamonState() {
    $pid_file = '/tmp/mymodbusd.pid';
    if (file_exists($pid_file)) {
      $pid = file_get_contents($pid_file);
      //log::add('mymodbus', 'debug', 'getDeamonState $pid: ' . strval($pid));
      $running_pid = exec("ps -eo pid,command | grep `cat $pid_file` | grep -v grep | awk '{print $1}'");
      //log::add('mymodbus', 'debug', 'getDeamonState $running_pid: ' . strval($running_pid));
      return (($running_pid != 0) && (intval($running_pid) == intval($pid)))? 'ok': 'nok';
    }
    return 'nok';
  }
  
  public static function getDeamonLaunchable() {
    // Si 2 équipements utilisent la même connexion -> nok (workaround provisoire)
    if ($eqConfig != '') {
      $serialIntf = array();
      foreach ($eqConfig as $config) {
        if ($config['eqProtocol'] == 'serial') {
          $intf = $config['eqSerialInterface'];
          if (in_array($intf, $serialIntf))
            return 'nok';
          $serialIntf[] = $intf;
        } 
      }
    }
    
    foreach (self::byType('mymodbus') as $eqMymodbus) { // boucle sur les équipements
      if ($eqMymodbus->getIsEnable()) {
        foreach ($eqMymodbus->getCmd('info') as $cmd) {
          // Au moins une commande enregistrée, donc la configuration est validée par preSave()
          return 'ok';
        }
      }
    }
    return'nok';
  }
  
  public static function getCallbackUrl() {
    $port = config::byKey('internalPort', 'core', 80);
    $comp = trim(config::byKey('internalComplement', 'core', ''), '/');
    if ($comp !== '') $comp .= '/';
    $callback = 'localhost:' . $port . '/' . $comp . 'plugins/mymodbus/core/php/jeemymodbus.php';
    if ((file_exists('/.dockerenv') || config::byKey('forceDocker', __CLASS__, '0')) && config::byKey('urlOverrideEnable', __CLASS__, '0') == '1')
      $callback = config::byKey('urlOverrideValue', __CLASS__, $callback);
    return $callback;
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
    
    log::add('mymodbus', 'debug', '**************** execute *****: ' . json_encode($_option));
    
    if ($this->getType() != 'action')
      return;
    
    $eqMymodbus = $this->getEqLogic();
    
    $command = array();
    $command['eqId'] = $eqMymodbus->getId();
    
    if ($this->getLogicalId() == 'refresh') {
      $message = array();
      $message['CMD'] = 'read';
      $message['read_cmd'] = $command;
      
    } else {
      $cmdFormat = $this->getConfiguration('cmdFormat');
      
      if (strstr($cmdFormat, '8') || $cmdFormat == 'blob')
        return;
      
      $value = $this->getConfiguration('cmdWriteValue');
      
      if ($this->getSubtype() == 'message') {
        $value = $_option['message']; // the title is ignored
      } elseif ($this->getSubtype() == 'slider') {
        $value = trim(str_replace('#slider#', $_option['slider'], $value));
      } elseif ($this->getSubtype() == 'color') {
        $value = trim(str_replace('#color#', $_option['color'], $value));
      }elseif ($this->getSubtype() == 'select') {
        $value = trim(str_replace('#select#', $_option['select'], $value));
      }
      $command['cmdWriteValue'] = jeedom::evaluateExpression($value);
      if ($command['cmdWriteValue'] === '')
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La valeur à écrire est vide. L\'écriture est ignorée.', __FILE__));

      $command['cmdId'] = $this->getId();
      
      $message = array();
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
    // Suppression de l'ancienne configuration
    foreach (array('type', 'datatype', 'location', 'request', 'parameters') as $attribut)
      if (isset($this->configuration[$attribut])) {
        unset($this->configuration[$attribut]);
        $this->_changed = true;
      }
    
    // Suppression des éléments de configuration inutiles
    $confOK = $this->getCmdConfiguration();
    $conf = $this->getConfiguration();
    foreach ($conf as $key => $value) {
      if (substr($key, 0, 3) == 'cmd' && !in_array($key, array_keys($confOK)) && substr($key, 0, 13) !== 'cmdSourceBlob' && $key !== 'cmdOption' && $key !== 'cmdWriteValue' ||
          substr($key, 0, 13) === 'cmdSourceBlob' && $conf['cmdFctModbus'] != 'fromBlob' ||
          $conf['cmdFctModbus'] == 'fromBlob' && $this->getSubType() == 'binary' && $key === 'cmdSourceBlobNum' ||
          $conf['cmdFctModbus'] == 'fromBlob' && $this->getSubType() !== 'binary' && $key === 'cmdSourceBlobBin' ||
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

    if (in_array($this->getLogicalId(), array('refresh', 'refresh time')))
      return true;
    $cmdSlave = $this->getConfiguration('cmdSlave');
    $cmdAddress = $this->getConfiguration('cmdAddress');
    $cmdFrequency = $this->getConfiguration('cmdFrequency');
    $cmdFormat = $this->getConfiguration('cmdFormat');
    $cmdFctModbus = $this->getConfiguration('cmdFctModbus');
    $cmdOption = $this->getConfiguration('cmdOption');
    if ($cmdSlave == '') {
      $cmdSlave = '0';
      $this->setConfiguration('cmdSlave', $cmdSlave);
    }
    if (!is_numeric($cmdSlave))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse esclave doit être un nombre.<br>\'0\' si pas de bus série.', __FILE__));
    if ($this->getType() == 'info') {
      if ($cmdFrequency == '') {
        $cmdFrequency = '1';
        $this->setConfiguration('cmdFrequency', $cmdFrequency);
      }
      if (!is_numeric($cmdFrequency))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La configuration \'Lecture 1x sur\' doit être un nombre.', __FILE__));
      if ($this->getSubType() == 'binary' && in_array($cmdFctModbus, array('3', '4')) && !preg_match('/#value# & \d+/', $cmdOption))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Pour pouvoir utiliser une fonction de lecture de registre numérique, une commande de type binaire doit avoir un filtre en option', __FILE__));
    }
    if (!is_numeric($cmdAddress) && $cmdFormat != 'string' && $cmdFormat != 'blob' && !strstr($cmdFormat, 'sp-sf'))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse Modbus doit être un nombre.', __FILE__));
    if ($cmdFormat == 'string' && !preg_match('/\d+\s*\[\s*\d+\s*\]/', $cmdAddress))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse Modbus d\'une chaine de caractère doit être de la forme<br>adresse[longueur]', __FILE__));
    if ($cmdFormat == 'blob' && !preg_match('/\d+\s*\[\s*\d+\s*\]/', $cmdAddress))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse Modbus d\'une plage de registres doit être de la forme<br>adresse[longueur]', __FILE__));
    if (strstr($cmdFormat, 'sp-sf') && !preg_match('/\d+\s*sf\s*\d+/i', $cmdAddress))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('L\'adresse Modbus d\'un scale factor doit être de la forme&nbsp;:<br><i>adresse_valeur </i>sf<i> adresse_scale_factor</i>', __FILE__));
    if ($cmdOption != '' && (!strstr($cmdOption, '#value#') || strstr($cmdOption, ';')))
      throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Le paramètre \'Option\' doit contenir \'#value#\' et aucun \';\'.', __FILE__));
    if ($this->getType() == 'action') {
      if ($cmdFctModbus == '6' && (strstr($cmdFormat, '32') || strstr($cmdFormat, '64')))
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La fonction "[0x06] Write register" ne permet pas d\'écrire une variable de cette longueur.', __FILE__));
      if (strstr($cmdFormat, '8') || $cmdFormat == 'blob' || $cmdFctModbus == 'fromBlob')
        log::add('mymodbus', 'warning', $this->getHumanName() . '&nbsp;:<br>' . __('L\'écriture sera ignorée.', __FILE__));
      if ($this->getConfiguration('cmdWriteValue') == '') {
        if ($this->getSubType() == 'slider') {
            $this->setConfiguration('cmdWriteValue', '#slider#');
            $this->_changed = true;
        }
        if ($this->getSubType() == 'select') {
            $this->setConfiguration('cmdWriteValue', '#select#');
            $this->_changed = true;
        }
        if ($this->getSubType() == 'color') {
            $this->setConfiguration('cmdWriteValue', '#color#');
            $this->_changed = true;
        }
        if ($this->getConfiguration('cmdWriteValue') == '')
          throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('La valeur à écrire est vide.', __FILE__));
      } else {
        $cmdWriteValue = $this->getConfiguration('cmdWriteValue');
        if ($this->getSubType() == 'slider' && !strstr($cmdWriteValue, '#slider#'))
          log::add('mymodbus', 'error', $this->getHumanName() . '&nbsp;:<br>' . __('Le champ valeur ne contient pas \'#slider#\'.', __FILE__));
        if ($this->getSubType() == 'select' && !strstr($cmdWriteValue, '#select#'))
          log::add('mymodbus', 'error', $this->getHumanName() . '&nbsp;:<br>' . __('Le champ valeur ne contient pas \'#select#\'.', __FILE__));
        if ($this->getSubType() == 'color' && !strstr($cmdWriteValue, '#color#'))
          log::add('mymodbus', 'error', $this->getHumanName() . '&nbsp;:<br>' . __('Le champ valeur ne contient pas \'#color#\'.', __FILE__));
      }
    }
    if ($cmdFctModbus == 'fromBlob') {
      if ($cmdFormat == 'blob')
        throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('On ne peut pas extraire une plage de registres d\'une plage de registres.', __FILE__));
      if ($this->getSubtype() == 'binary')
        $cmdSourceBlob = $this->getConfiguration('cmdSourceBlobBin');
      else
        $cmdSourceBlob = $this->getConfiguration('cmdSourceBlobNum');
      
      if (!is_numeric($cmdSourceBlob) && preg_match('/#\[.*\]#/', $cmdSourceBlob)) {
        $eqMymodbus = $this->getEqLogic();
        foreach ($eqMymodbus->getCmd() as $cmd) {
          if ($cmdSourceBlob == '#[' . $cmd->getName() . ']#') {
            $cmdSourceBlob = $cmd->getId();
            if ($this->getSubtype() == 'binary')
              $this->setConfiguration('cmdSourceBlobBin', $cmdSourceBlob);
            else
              $this->setConfiguration('cmdSourceBlobNum', $cmdSourceBlob);
          }
        }
      }
      
      if (is_numeric($cmdSourceBlob)) {
        $blobCmd = mymodbusCmd::byId($cmdSourceBlob);
        $blobAddress = $blobCmd->getConfiguration('cmdAddress');
        preg_match('/(\d+)\s*\[\s*(\d+)\s*\]/', $blobAddress, $matches);
        $minAddr = intval($matches[1]);
        $maxAddr = $minAddr + intval($matches[2]) - 1;
        
        if ($cmdFormat != 'string' && !strstr($cmdFormat, 'sp-sf')) {
          $cmdAddress = intval($cmdAddress);
          if ($cmdAddress < $minAddr or $cmdAddress > $maxAddr)
            throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Adresse Modbus en dehors de la plage de registres.', __FILE__));
          if ($this->getSubtype() == 'binary') {
            if ($blobCmd->getSubtype() == 'numeric' && !preg_match('/#value# & \d+/', $cmdOption))
              throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Pour pouvoir utiliser une plage de lecture de registre numérique, une commande de type binaire doit avoir un filtre en option', __FILE__));
          }
        }
        if ($cmdFormat == 'string') {
          preg_match('/(\d+)\s*\[\s*(\d+)\s*\]/', $cmdAddress, $matches);
          $startAddr = intval($matches[1]);
          $endAddr = $startAddr + intval($matches[2]);
          if ($startAddr < $minAddr or $startAddr > $maxAddr or $endAddr < $minAddr or $endAddr > $maxAddr)
            throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Adresse Modbus en dehors de la plage de registres.', __FILE__));
        }
        if (strstr($cmdFormat, 'sp-sf')) {
          preg_match('/(\d+)\s*sf\s*(\d+)/i', $cmdAddress, $matches);
          $startAddr = intval($matches[1]);
          $endAddr = intval($matches[2]);
          if ($startAddr < $minAddr or $startAddr > $maxAddr or $endAddr < $minAddr or $endAddr > $maxAddr)
            throw new Exception($this->getHumanName() . '&nbsp;:<br>' . __('Adresse Modbus en dehors de la plage de registres.', __FILE__));
        }
      }
    }
    
    //log::add('mymodbus', 'debug', 'Validation de la configuration pour la commande *' . $this->getHumanName() . '* : OK');
  }

  public function getCmdConfiguration() {
    $return = array();
    $return['id'] = $this->getId();
    $return['name'] = $this->getName();
    $return['type'] = $this->getType();
    $return['cmdSlave'] = $this->getConfiguration('cmdSlave');
    $return['cmdFctModbus'] = $this->getConfiguration('cmdFctModbus');
    if ($return['cmdFctModbus'] == 'fromBlob') {
      if ($this->getSubType() == 'binary')
        $return['cmdSourceBlob'] = $this->getConfiguration('cmdSourceBlobBin');
      else
        $return['cmdSourceBlob'] = $this->getConfiguration('cmdSourceBlobNum');
    }
    $return['cmdFormat'] = $this->getConfiguration('cmdFormat');
    $return['cmdAddress'] = $this->getConfiguration('cmdAddress');
    $return['cmdFrequency'] = $this->getConfiguration('cmdFrequency');
    $return['cmdInvertBytes'] = $this->getConfiguration('cmdInvertBytes');
    $return['cmdInvertWords'] = $this->getConfiguration('cmdInvertWords');
    $return['repeat'] = $this->getConfiguration('repeatEventManagement', 'never') === 'always' ? '1' : '0';

    return $return;
  }
  
  /*   * **********************Getteur Setteur*************************** */
}

?>
