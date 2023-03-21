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

class mymodbus extends eqLogic {
    /*     * *************************Attributs****************************** */

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
    
    public static $_DEFAULT_SOCKET_PORT = 55502;

    /*     * ***********************Methode static*************************** */
    
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
        
        // Pas de démarrage si ce n'est pas possible
        if (self::getDeamonLaunchable() != 'ok') {
            log::add('mymodbus', 'error', __('Démarrage du démon impossible, veuillez vérifier la configuration de MyModbus', __FILE__));
            return true;
        }
        
        $jsonData = self::getCompleteConfiguration();
        
        $socketPort = is_numeric(config::byKey('socketport', __CLASS__, self::$_DEFAULT_SOCKET_PORT, True)) ? config::byKey('socketport', __CLASS__, self::$_DEFAULT_SOCKET_PORT) : self::$_DEFAULT_SOCKET_PORT;
        $daemonLoglevel = escapeshellarg(log::convertLogLevel(log::getLogLevel(__CLASS__)));
        $daemonApikey = escapeshellarg(jeedom::getApiKey(__CLASS__));
        $daemonCallback = escapeshellarg(self::getCallbackUrl());
        $daemonJson = escapeshellarg(json_encode($jsonData));
        
        log::add('mymodbus', 'debug', 'deamon_start socketport *' . $socketPort . '*');
        log::add('mymodbus', 'debug', 'deamon_start API-key *' . $daemonApikey . '*');
        log::add('mymodbus', 'debug', 'deamon_start callbackURL *' . $daemonCallback . '*');
        log::add('mymodbus', 'debug', 'deamon_start config *' . $daemonJson . '*');
        
        $request = ' --socketport ' . $socketPort . ' --loglevel ' . $daemonLoglevel . ' --apikey ' . $daemonApikey . ' --callback ' . $daemonCallback . ' --json ' . $daemonJson;
        
        $mymodbus_path = realpath(dirname(__FILE__) . '/../../ressources/mymodbusd');
        $pyenv_path = realpath(dirname(__FILE__) . '/../../ressources/_pyenv');
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
        $socket_port = is_numeric(config::byKey('socketport', __CLASS__, self::$_DEFAULT_SOCKET_PORT, True)) ? config::byKey('socketport', __CLASS__, self::$_DEFAULT_SOCKET_PORT) : self::$_DEFAULT_SOCKET_PORT;
        socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, $socket_port));
        $socket_ok = socket_write($socket, $payLoad, strlen($payLoad));
        if (!$socket_ok) {
            $err = socket_last_error($socket);
            log::add('mymodbus', 'error', 'sendToDaemon: socket_write ERROR: ' . socket_strerror($err));
        }
        socket_close($socket);
    }
    
    // Supported protocols are in desktop/modal/configuration.[protocol].php
    public static function supportedProtocols() {
        $protocols = array();
        foreach (glob(dirname(__FILE__) . '/../../desktop/modal/configuration.*.php') as $file) {
            $protocols[] = substr(basename($file), strlen('configuration.'), strlen('.php') * -1);
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
        return $interfaces;
    }

    /*     * *********************Méthodes d'instance************************* */

    // Fonction exécutée automatiquement avant la suppression de l'équipement
    public function preRemove() {
        if ($this->getIsEnable())
            self::deamon_stop();
    }

    // Fonction exécutée automatiquement après la suppression de l'équipement
    public function postRemove() {
        if (self::getDeamonState() != 'ok')
            self::deamon_start();
    }
    
    // Fonction exécutée automatiquement avant la sauvegarde de l'équipement (création ou mise à jour)
    // La levée d'une exception invalide la sauvegarde
    public function preSave() {
        // ----------------------------------
        // A supprimer dans quelques temps
        $cron = cron::byClassAndFunction('mymodbus', 'cronDaily');
        if (is_object($cron)) {
            $cron->remove();
        }
        // ----------------------------------
        
        $configKeys = array();
        foreach ($this->getConfiguration() as $key => $value) {
            $configKeys[] = $key;
            //log::add('mymodbus', 'debug', 'eqLogic Configuration *' . $key . '* : *' . $value . '*');
        }
        // Equipement non activé, pas de vérification
        if (!$this->getIsEnable())
            return True;
        // Un nouvel équipement vient d'être ajouté, il faut retourner "true" sinon, l'ajout est invalidé
        if (!in_array('eqProtocol', $configKeys) && !in_array('eqKeepopen', $configKeys) && !in_array('eqPolling', $configKeys))
            return True;
        if (!in_array('eqProtocol', $configKeys) || !in_array('eqKeepopen', $configKeys) || !in_array('eqPolling', $configKeys))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('Veuillez définir la configuration de base de l\'équipement', __FILE__));
        
        $eqProtocol = $this->getConfiguration('eqProtocol');
        $eqPolling = $this->getConfiguration('eqPolling');
        if (!in_array($eqProtocol, self::supportedProtocols()))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('Le protocol n\'est pas défini correctement.', __FILE__));
        if (!is_numeric($eqPolling))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('Le paramètre "Polling" doit être un nombre.', __FILE__));
        if ($eqPolling < 10)
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('Le paramètre "Polling" doit être au moins à 10 secondes', __FILE__));
        
        if ($eqProtocol == 'tcp') {
            // Vérification du paramétrage d'une connexion TCP
            if (!in_array('eqTcpAddr', $configKeys) && !in_array('eqTcpPort', $configKeys) && !in_array('eqTcpRtu', $configKeys)) {
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('Veuillez définir la configuration TCP de l\'équipement', __FILE__));
            }
            $eqTcpAddr = $this->getConfiguration('eqTcpAddr');
            $eqTcpPort = $this->getConfiguration('eqTcpPort');
            if (!filter_var($eqTcpAddr, FILTER_VALIDATE_IP))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('L\'adresse IP n\'est pas valide', __FILE__));
            if (!is_numeric($eqTcpPort))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('Le port doit être un nombre.', __FILE__));
            
        } elseif ($eqProtocol == 'udp') {
            // Vérification du paramétrage d'une connexion UDP
            if (!in_array('eqUdpAddr', $configKeys) && !in_array('eqUdpPort', $configKeys) && !in_array('eqUdpRtu', $configKeys))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('Veuillez définir la configuration UDP de l\'équipement', __FILE__));
            $eqUdpAddr = $this->getConfiguration('eqUdpAddr');
            $eqUdpPort = $this->getConfiguration('eqUdpPort');
            if (!filter_var($eqUdpAddr, FILTER_VALIDATE_IP))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('L\'adresse IP n\'est pas valide', __FILE__));
            if (!is_numeric($eqUdpPort))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('Le port doit être un nombre.', __FILE__));
            
        } elseif ($eqProtocol == 'serial') {
            // Vérification du paramétrage d'une connexion série
            if (!in_array('eqSerialInterface', $configKeys) || !in_array('eqSerialMethod', $configKeys) ||
                    !in_array('eqSerialBaudrate', $configKeys) || !in_array('eqSerialBytesize', $configKeys) ||
                    !in_array('eqSerialParity', $configKeys) || !in_array('eqSerialStopbits', $configKeys))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('Veuillez définir la configuration série de l\'équipement', __FILE__));
            $eqSerialInterface = $this->getConfiguration('eqSerialInterface');
            $eqSerialMethod = $this->getConfiguration('eqSerialMethod');
            $eqSerialBaudrate = $this->getConfiguration('eqSerialBaudrate');
            $eqSerialBytesize = $this->getConfiguration('eqSerialBytesize');
            $eqSerialParity = $this->getConfiguration('eqSerialParity');
            $eqSerialStopbits = $this->getConfiguration('eqSerialStopbits');
            if ($eqSerialInterface == '')
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('L\'interface doit être définie correctement.', __FILE__));
            if (!in_array($eqSerialMethod, array('rtu', 'ascii', 'binary')))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('La méthode de transport n\'est pas définie correctement.', __FILE__));
            if (!is_numeric($eqSerialBaudrate))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('La vitesse de transmission Modbus doit être un nombre.', __FILE__));
            if (!in_array($eqSerialBytesize, array('7', '8')))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('Le nombre de bits par octet n\'est pas défini correctement.', __FILE__));
            if (!in_array($eqSerialParity, array('E', 'O', 'N')))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('La parité n\'est pas définie correctement.', __FILE__));
            if (!in_array($eqSerialStopbits, array('0', '1', '2')))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('Le nombre de bits de stop n\'est pas défini correctement.', __FILE__));
        }
        //log::add('mymodbus', 'debug', 'Validation de la configuration pour l\'équipement *' . $this->getName() . '* : OK');
    }

   /*
    * Fonction exécutée automatiquement après la sauvegarde de l'équipement (création ou mise à jour)
    public function postSave() {}
    */

    public function postAjax() {
        self::deamon_start();
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

    /*     * **********************Getteur Setteur*************************** */
    
    // Retourne la configuration des équipements et de leurs commandes
    public static function getCompleteConfiguration() {
        $completeConfig = array();
        foreach (self::byType('mymodbus') as $eqMymodbus) { // boucle sur les équipements
            // ne pas exporter la configuration si l'équipement n'est pas activé
            if (!$eqMymodbus->getIsEnable())
                continue;
            
            $completeConfig[] = $eqMymodbus->getEqConfiguration();
        }
        log::add('mymodbus', 'debug', 'eqLogic mymodbus getCompleteConfiguration: ' . json_encode($completeConfig));
        return $completeConfig;
    }
    
    // Retourne la configuration de l'équipement et de ses commandes
    public function getEqConfiguration() {
        $eqConfig = array();
        $eqConfig['id'] = $this->getId();
        $eqConfig['name'] = $this->getName();
        $eqConfig['eqProtocol'] = $this->getConfiguration('eqProtocol');
        $eqConfig['eqKeepopen'] = $this->getConfiguration('eqKeepopen');
        $eqConfig['eqPolling'] = $this->getConfiguration('eqPolling');
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
            $cmdConfig = array();
            $cmdConfig['id'] = $cmdMymodbus->getId();
            $cmdConfig['name'] = $cmdMymodbus->getName();
            $cmdConfig['type'] = $cmdMymodbus->getType();
            $cmdConfig['cmdSlave'] = $cmdMymodbus->getConfiguration('cmdSlave');
            $cmdConfig['cmdFctModbus'] = $cmdMymodbus->getConfiguration('cmdFctModbus');
            if ($cmdConfig['cmdFctModbus'] == 'fromBlob')
                $cmdConfig['cmdSourceBlob'] = $cmdMymodbus->getConfiguration('cmdSourceBlob');
            $cmdConfig['cmdFormat'] = $cmdMymodbus->getConfiguration('cmdFormat');
            $cmdConfig['cmdAddress'] = $cmdMymodbus->getConfiguration('cmdAddress');
            $cmdConfig['cmdFrequency'] = $cmdMymodbus->getConfiguration('cmdFrequency');
            $cmdConfig['cmdInvertBytes'] = $cmdMymodbus->getConfiguration('cmdInvertBytes');
            $cmdConfig['cmdInvertWords'] = $cmdMymodbus->getConfiguration('cmdInvertWords');
            $eqConfig['cmds'][] = $cmdConfig;
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
        } else
            return 'nok';
    }
    
    public static function getDeamonLaunchable() {
        foreach (self::byType('mymodbus') as $eqMymodbus) { // boucle sur les équipements
            if ($eqMymodbus->getIsEnable()) {
                foreach ($eqMymodbus->getCmd('info') as $cmd) {
                    // Au moins une commande enregistrée, donc la configuration est validée par preSave()
                    return 'ok';
                }
            }
        }
        return 'nok';
    }
    
    public static function getCallbackUrl() {
        $protocol = config::byKey('internalProtocol', 'core', 'http://');
        $protocol = config::byKey('internalPort', 'core', 80);
        $comp = trim(config::byKey('internalComplement', 'core', ''), '/');
        if ($comp !== '') $comp .= '/';
        $callback = $prot.'localhost:' . $protocol . '/' . $comp . 'plugins/mymodbus/core/php/jeemymodbus.php';
        if ((file_exists('/.dockerenv') || config::byKey('forceDocker', __CLASS__, '0')) && config::byKey('urlOverrideEnable', __CLASS__, '0') == '1')
			$callback = config::byKey('urlOverrideValue', __CLASS__, $callback);
        return $callback;
    }
}

class mymodbusCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */
    
    public function execute($command = array()) {
        
        log::add('mymodbus', 'debug', '**************** execute *****: ' . json_encode($command));
        
        $cmdFormat = $this->getConfiguration('cmdFormat');
        
        if (strstr($cmdFormat, '8') || $cmdFormat == 'blob' || in_array($this->getSubType(), array('color', 'select')))
            return;
        
        $eqMymodbus = $this->getEqLogic();
        
        $write_cmd = array();
        $write_cmd['eqId'] = $eqMymodbus->getId();
        $write_cmd['cmdId'] = $this->getId();
        
        if (in_array($this->getSubtype(), array('other', 'message'))) {
            if (isset($command['message']))
                $write_cmd['cmdWriteValue'] = $command['message'];
            else
                $write_cmd['cmdWriteValue'] = $this->getConfiguration('cmdWriteValue');
        } elseif ($this->getSubtype() == 'slider') {
            $write_cmd['cmdWriteValue'] = 0;
            if (strstr($cmdFormat, 'int'))
                $write_cmd['cmdWriteValue'] = intval($command['slider']);
            else if (strstr($cmdFormat, 'float'))
                $write_cmd['cmdWriteValue'] = floatval($command['slider']);
        }
        
        $message = array();
        $message['CMD'] = 'write';
        $message['write_cmd'] = $write_cmd;
        mymodbus::sendToDaemon($message);
    }

//    public function postInsert() {}
//    public function postRemove() {}
//    public function postSave() {}
    
    // Fonction exécutée automatiquement avant la sauvegarde de la commande (création ou mise à jour)
    // La levée d'une exception invalide la sauvegarde
    public function preSave() {
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
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('L\'adresse esclave doit être un nombre.</br>\'0\' si pas de bus série.', __FILE__));
        if ($this->getType() == 'info' && $cmdFrequency == '') {
            $cmdFrequency = '1';
            $this->setConfiguration('cmdFrequency', $cmdFrequency);
        }
        if ($cmdFctModbus == 'fromBlob') {
            $cmdSourceBlob = $this->getConfiguration('cmdSourceBlob');
            if ($cmdFormat == 'blob')
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('On ne peut pas extraire une plage de registres d\'une plage de registres.', __FILE__));
            $blobs = $this->getBlobs($this->getSubType() == 'binary');
            $ok = false;
            foreach ($blobs as $blobName) {
                if (strstr($cmdSourceBlob, $blobName)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok)
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('La plage de registre doit exister et la plage de registres et la commande doivent être du même sous-type.', __FILE__));
        }
        if ($this->getType() == 'info' && !is_numeric($cmdFrequency))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('La configuration \'Lecture 1x sur\' doit être un nombre.', __FILE__));
        if (!is_numeric($cmdAddress) && $cmdFormat != 'string' && $cmdFormat != 'blob' && !strstr($cmdFormat, 'sp-sf'))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('L\'adresse Modbus doit être un nombre.', __FILE__));
        if ($cmdFormat == 'string' && !preg_match('/\d+\s*\[\s*\d+\s*\]/', $cmdAddress))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('L\'adresse Modbus d\'une chaine de caractère doit être de la forme</br>adresse[longueur]', __FILE__));
        if ($cmdFormat == 'blob' && !preg_match('/\d+\s*\[\s*\d+\s*\]/', $cmdAddress))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('L\'adresse Modbus d\'une plage de registres doit être de la forme</br>adresse[longueur]', __FILE__));
        if (strstr($cmdFormat, 'sp-sf') && !preg_match('/\d+\s*sf\s*\d+/i', $cmdAddress))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('L\'adresse Modbus d\'un scale factor doit être de la forme&nbsp;:</br><i>adresse_valeur </i>sf<i> adresse_scale_factor</i>', __FILE__));
        if ($cmdOption != '' && (!strstr($cmdOption, '#value#') || strstr($cmdOption, ';')))
            throw new Exception($this->getName() . '&nbsp;:</br>' . __('Le paramètre \'Option\' doit contenir \'#value#\' et aucun \';\'.', __FILE__));
        if ($this->getType() == 'action') {
            if ($cmdFctModbus == '6' && (strstr($cmdFormat, '32') || strstr($cmdFormat, '64')))
                throw new Exception($this->getName() . '&nbsp;:</br>' . __('La fonction "[0x06] Write register" ne permet pas d\'écrire une variable de cette longueur.', __FILE__));
            if (strstr($cmdFormat, '8') || $cmdFormat == 'blob' || $cmdFctModbus == 'fromBlob' || in_array($this->getSubType(), array('color', 'select')))
                log::add('mymodbus', 'warning', $this->getName() . '&nbsp;:</br>' . __('L\'écriture sera ignorée.', __FILE__));
        }
        //log::add('mymodbus', 'debug', 'Validation de la configuration pour la commande *' . $this->getName() . '* : OK');
    }
    
    public function getBlobs($binarySubType=false) {
        $eqMymodbus = $this->getEqLogic();
        $blobs = array();
        foreach ($eqMymodbus->getCmd('info') as $cmd) {
            if ($cmd->getConfiguration('cmdFormat') == 'blob') {
                if ($binarySubType && $cmd->getSubType() == 'binary' or !$binarySubType && $cmd->getSubType() != 'binary')
                    $blobs[ $cmd->getId()] = $cmd->getName();
            }
        }
        return $blobs;
    }


    /*     * **********************Getteur Setteur*************************** */
}