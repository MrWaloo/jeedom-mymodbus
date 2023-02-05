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
    
    public static $SOCKET_PORT = '55502';

    /*     * ***********************Methode static*************************** */
  
    //* Fonction exécutée automatiquement toutes les minutes par Jeedom
    // TODO
    public static function cron() {
        //SI Restart des Démons
        if (config::byKey('ActiveRestart', 'mymodbus', true)) {
            $deamonInfo = self::deamon_info();
            $deamonsRunning = self::health();
            $deamonsRunning = $deamonsRunning[0];  // peut importe l'index  0 
            
            // Si Healt Nok et que le demon principal est OK alors Restart 
            if (($deamonsRunning['result'] == 'NOK') and ($deamonInfo['state'] == 'ok')) {
                log::add('mymodbus', 'info', 'restart by Health');
                self::deamon_stop();
                sleep(2);
                self::deamon_start();
            }
        }
    }

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

    //Fonction exécutée automatiquement tous les jours par Jeedom
    public static function cronDaily() {
//        foreach (self::byType('mymodbus') as $mymodbus) {//parcours tous les équipements du plugin mymodbus
//            if ($mymodbus->getIsEnable() == 1) {//vérifie que l'équipement est actif
//                $cmd = $mymodbus->getCmd(null, 'ntp');//retourne la commande 'ntp' si elle existe
//                if (!is_object($cmd)) {//Si la commande n'existe pas
//                    continue; //continue la boucle
//                }
//                $cmd->execCmd(); // la commande existe on la lance
//                log::add('mymodbus', 'info', 'mise à jour heure ');
//            }
//        }
    }
    
    // TODO: à adapter en fonction des paramètres nécessaires au démon (ressources/mymodbusd/mymodbusd.py)
    // log avec mymodbusd
    public static function deamon_start() {
        // Always stop first.
        self::deamon_stop();
        
        // Pas de démarrage si aucune commande n'est configurée
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration du demon', __FILE__));
        }
        
        $completeConfig = self::getCompleteConfiguration();
        
        $daemonLoglevel = escapeshellarg('debug'); // DEBUG 'info'
        $daemonApikey = escapeshellarg(jeedom::getApiKey(__CLASS__));
        $daemonCallback = escapeshellarg(self::getCallbackUrl());
        $daemonConfig = escapeshellarg(json_encode($completeConfig));
        
        //log::add('mymodbus', 'debug', 'deamon_start socketport *' . json_encode(self::$SOCKET_PORT) . '*');
        log::add('mymodbus', 'debug', 'deamon_start API-key *' . $daemonApikey . '*');
        log::add('mymodbus', 'debug', 'deamon_start callbackURL *' . $daemonCallback . '*');
        log::add('mymodbus', 'debug', 'deamon_start config *' . $daemonConfig . '*');
        
        $request = ' --loglevel ' . $daemonLoglevel . ' --apikey ' . $daemonApikey . ' --callback ' . $daemonCallback . ' --config ' . $daemonConfig;
        
        $mymodbus_path = realpath(dirname(__FILE__) . '/../../ressources/mymodbusd');
        $cmd = 'nice -n 19 /usr/bin/python3 ' . $mymodbus_path . '/mymodbusd.py' . $request;
        log::add('mymodbus', 'info', 'Lancement du démon mymodbus : ' . $cmd);                
//        $result = exec($cmd . ' >> ' . log::getPathToLog('mymodbus') . ' 2>&1 &');
//        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
//            log::add('mymodbus', 'error', $result);
//            return false;
//        }


//        foreach (self::byType('mymodbus') as $mymodbus) { // boucle sur les équipements
//            if ($mymodbus->getIsEnable() == 1) {
//                $mymodbus_ip = $mymodbus->getConfiguration('addr');
//                $mymodbus_id = $mymodbus->getId(); // récupére l'id
//                $mymodbus_port = $mymodbus->getConfiguration('port');
//                $mymodbus_unit = $mymodbus->getConfiguration('unit');
//                $mymodbus_protocol = $mymodbus->getConfiguration('protocol');
//                $mymodbus_keepopen = $mymodbus->getConfiguration('keepopen');
//                $mymodbus_baudrate = $mymodbus->getConfiguration('baudrate');
//                // Equipement commun tcpip
//                if ($mymodbus_protocol== "wago" || $mymodbus_protocol== "crouzet_m3" || $mymodbus_protocol== "adam" || $mymodbus_protocol== "logo"  ) {
//                    $mymodbus_protocold="tcpip";
//                    if ($mymodbus_ip == "") {
//                        throw new Exception(__('La requete adresse ip ne peut etre vide',__FILE__).$mymodbus_ip);
//                    }
//                } else {
//                    $mymodbus_protocold = $mymodbus_protocol;
//                }
//                if ($mymodbus_port=="") {
//                    throw new Exception(__('La requetes port ne peut etre vide',__FILE__));
//                }
//                if ($mymodbus_unit=="") {
//                    $mymodbus_unit=1;
//                }
//                if ($mymodbus_keepopen=="") {
//                    $mymodbus_keepopen=0;
//                }
//                $mymodbus_polling = $mymodbus->getConfiguration('polling');
//                if ($mymodbus_polling == ""  ) {
//                    throw new Exception(__('La requetes polling ne peut etre vide',__FILE__));
//                }
//                //explod
//                if ($mymodbus_protocold== "rtu" ) {
//                    $request='--host='.$mymodbus_ip.' --port='.$mymodbus_port.' --unid='.$mymodbus_unit.' --polling='.$mymodbus_polling.' --protocol='.$mymodbus_protocold.' --eqid='.$mymodbus_id.' --baudrate='.$mymodbus_baudrate ;
//                } else {
//                    $request='--host='.$mymodbus_ip.' --port='.$mymodbus_port.' --unid='.$mymodbus_unit.' --polling='.$mymodbus_polling.' --keepopen='.$mymodbus_keepopen.' --protocol='.$mymodbus_protocold.' --eqid='.$mymodbus_id ;
//                }
//                foreach ($mymodbus->getCmd('info') as $cmd) {
//                    if ($cmd->getConfiguration('type')=='coils') {
//                        $coils[]=$cmd->getConfiguration('location');
//                        log::add('mymodbus', 'info', 'coils trouvées :'.$cmd->getConfiguration('location'));
//                    }
//                    if ($cmd->getConfiguration('type')=='discrete_inputs') {
//                        $discrete_inputs[]=$cmd->getConfiguration('location');
//                    }
//                    if ($cmd->getConfiguration('type')=='holding_registers') {
//                        $holding_registers[] =$cmd->getConfiguration('location');
//                        log::add('mymodbus', 'info', 'holding_registers trouvées :'.$cmd->getConfiguration('location'));
//                    }
//                    if ($cmd->getConfiguration('type')=='input_registers') {
//                        $input_registers[]=$cmd->getConfiguration('location');
//                    }
//                    if ($cmd->getConfiguration('type')=='sign') {
//                        $sign[]=$cmd->getConfiguration('location');
//                        log::add('mymodbus', 'info', 'holding_Signed trouvées :'.$cmd->getConfiguration('location'));
//                    }
//                    if ($cmd->getConfiguration('type')=='virg') {
//                        $virg[]=$cmd->getConfiguration('location');
//                        log::add('mymodbus', 'info', 'holding_float trouvées :'.$cmd->getConfiguration('location'));
//                    }
//                    if ($cmd->getConfiguration('type')=='swapi32') {
//                        $swapi32[]=$cmd->getConfiguration('location');
//                        log::add('mymodbus', 'info', 'imput_register_swap_32 trouvées :'.$cmd->getConfiguration('location'));
//                    }
//                }
//                if ($coils) {
//                    $request.=' --coils='.implode(',',$coils);
//                }
//                if ($discrete_inputs) {
//                    $request.=' --dis='.implode(',',$discrete_inputs);
//                }
//                if ($holding_registers) {
//                    $request.=' --hrs='.implode(',',$holding_registers);
//                }
//                if ($input_registers) {
//                    $request.=' --irs='.implode(',',$input_registers);
//                }
//                if ($sign) {
//                    $request.=' --sign='.implode(',',$sign);
//                }
//                if ($virg) {
//                    $request.=' --virg='.implode(',',$virg);
//                }
//                if ($swapi32) {
//                    $request.=' --swapi32='.implode(',',$swapi32);
//                }
//                $mymodbus_path = realpath(dirname(__FILE__) . '/../../ressources/mymodbusd');
//                $cmd = 'nice -n 19 /usr/bin/python3 ' . $mymodbus_path . '/mymodbusd.py ' . $request;
//                log::add('mymodbus', 'info', 'Lancement du démon mymodbus : ' . $cmd);                
//                $result = exec($cmd . ' >> ' . log::getPathToLog('mymodbus') . ' 2>&1 &');
//                if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
//                    log::add('mymodbus', 'error', $result);
//                    return false;
//                }
//
//                $holding_registers = array();
//                $coils = array();
//                $discrete_inputs = array();
//                $input_registers = array();
//                $sign = array();
//                $virg = array();
//                $swapi32 = array();
//                
//                sleep(2);
//                if (!self::deamon_info()) {
//                    sleep(10);
//                    if (!self::deamon_info()) {
//                        log::add('mymodbus', 'error', 'Impossible de lancer le démon Modbus', 'unableStartDeamon');
//                        return false;
//                    }
//                }
//                message::removeAll('mymodbus', 'unableStartDeamon');
//            }
//        }
    }

    public static function health() {
        $return = array();
        $return['test'] = __('Etat(s) démon(s)', __FILE__);
        $return['result'] ='OK';
        $return['advice'] = '';
        $return['state'] = true;

        foreach (self::byType('mymodbus') as $eqLogic) {
            if ($eqLogic->getIsEnable() == 0) continue;
            // vérifie si l'eq à un démon qui tourne 
            $result = exec("ps -eo pid,command | grep 'eqid={$eqLogic->getId()}' | grep -v grep | awk '{print $1}' | wc -l");
            if ($result == 0) {
                $return['state'] = false;
                $return['result'] = 'NOK';
                $return['advice'] = __('Au moins un démon ne tourne pas ! Voir la page santé dans la configuration de MyModbus.', __FILE__);    
                break;

            } else {
                $return['state'] = true;
            }
        }
        return array($return);
    }

    // Information du démon
    public static function deamon_info() {
        $return = array();
        $return['state'] = 'nok';
        $return['launchable'] = 'nok';
        
        // State
        $result = exec("ps -eo pid,command | grep 'mymodbusd.py' | grep -v grep | awk '{print $1}'"); // TODO
        $return['state'] = ($result == 0)? 'nok': 'ok';
        
        // Launchable
        foreach (self::byType('mymodbus') as $mymodbus) { // boucle sur les équipements
            if ($mymodbus->getIsEnable() == 1) {
                foreach ($mymodbus->getCmd('info') as $cmd) {
                    // Au moins une commande enregistrée, donc la configuration est validée par preSave()
                    $return['launchable'] = 'ok';
                    break 2;
                }
            }
        }
        
        log::add('mymodbus', 'debug', 'deamon_info *' . $mymodbus->getName() . '* = ' . json_encode($return));
        return $return;
    }
    
    // TODO
    public static function deamon_stop() {
        log::add('mymodbusd', 'info', 'Arrêt des démons');
        $pid = exec("ps -eo pid,command | grep 'mymodbusd.py' | grep -v grep | awk '{print $1}'| wc -l");
        While ($pid > 0) {
            $pid = exec("ps -eo pid,command | grep 'mymodbusd.py' | grep -v grep | awk '{print $1}'| wc -l");
            system::kill($pid);
        }
        system::kill('mymodbusd.py');
        sleep(1);
        log::add('mymodbus', 'info', 'Démons arrêtés');
    }
    
    // TODO
    public static function sendToDaemon($params) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] != 'ok') {
            throw new Exception("Le démon n'est pas démarré");
        }
        $params['apikey'] = jeedom::getApiKey(__CLASS__);
        $payLoad = json_encode($params);
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, '55502'));
        socket_write($socket, $payLoad, strlen($payLoad));
        socket_close($socket);
    }

    public static function ntp_crouzet_m3() {
        
    }
    
    // Michel: OK
    // Supported protocols are in desktop/modal/configuration.[protocol].php
    public static function supportedProtocols() {
        $return = array();
        foreach (glob(dirname(__FILE__) . '/../../desktop/modal/configuration.*.php') as $file) {
            $return[] = substr(basename($file), strlen('configuration.'), strlen('.php') * -1);
        }
        return $return;
    }

    // Michel: OK
    public static function dependancy_info() {
        $return = array();
        $return['log'] = log::getPathToLog(__CLASS__ . '_update');
        $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependency';
        if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependency')) {
            $return['state'] = 'in_progress';
        } else {
            if (exec(system::getCmdSudo() . system::get('cmd_check') . '-Ec "python3\-pip"') < 1) {
                $return['state'] = 'nok';
            } elseif (exec(system::getCmdSudo() . 'python3 -m pip list | grep -Ewc "pymodbus|pyserial|six|serial|pyudev"') < 5) {
                $return['state'] = 'nok';
            } else {
                $return['state'] = 'ok';
            }
        }
        return $return;
    }

    // Michel: OK
    public static function dependancy_install() {
        log::remove(__CLASS__ . '_update');
        return array('script' => dirname(__FILE__) . '/../../ressources/install_#stype#.sh ' . jeedom::getTmpFolder('mymodbus') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

    /*     * *********************Méthodes d'instance************************* */

    // Fonction exécutée automatiquement avant la suppression de l'équipement
    public function preRemove() {
        self::deamon_stop();
    }

    // Fonction exécutée automatiquement après la suppression de l'équipement
    public function postRemove() {
        sleep(2);
        $deamonRunning = self::deamon_info();
        if ($deamonRunning['state'] != 'ok') {
            self::deamon_start();
        }
    }
    
    // Fonction exécutée automatiquement avant la sauvegarde de l'équipement (création ou mise à jour)
    // La levée d'une exception invalide la sauvegarde
    public function preSave() {
        $configKeys = array();
        foreach ($this->getConfiguration() as $key => $value) {
            $configKeys[] = $key;
            //log::add('mymodbus', 'debug', 'eqLogic Configuration *' . $key . '* : *' . $value . '*');
        }
        if (!in_array('eqProtocol', $configKeys) and !in_array('eqKeepopen', $configKeys) and
                !in_array('eqPolling', $configKeys) and !in_array('eqWordEndianess', $configKeys) and
                !in_array('eqDWordEndianess', $configKeys)) {
            //log::add('mymodbus', 'debug', 'Sans doute un nouvel équipement...');
            return True;
        }
        if (!in_array('eqProtocol', $configKeys) or !in_array('eqKeepopen', $configKeys) or
                !in_array('eqPolling', $configKeys) or !in_array('eqWordEndianess', $configKeys) or
                !in_array('eqDWordEndianess', $configKeys)) {
            throw new Exception(__('Veuillez définir la configuration de base de l\'équipement', __FILE__));
        }
        $eqProtocol = $this->getConfiguration('eqProtocol');
        $eqPolling = $this->getConfiguration('eqPolling');
        $eqWordEndianess = $this->getConfiguration('eqWordEndianess');
        $eqDWordEndianess = $this->getConfiguration('eqDWordEndianess');
        if (!in_array($eqProtocol, self::supportedProtocols())) {
            throw new Exception(__('Le protocol n\'est pas défini correctement.', __FILE__));
        }
        if (!is_numeric($eqPolling)) {
            throw new Exception(__('Le paramètre "Pooling" doit être un nombre.', __FILE__));
        }
        if (!in_array($eqWordEndianess, array('>', '<'))) {
            throw new Exception(__('L\'ordre des BYTE n\'est pas défini correctement.', __FILE__));
        }
        if (!in_array($eqDWordEndianess, array('>', '<'))) {
            throw new Exception(__('L\'ordre des WORD n\'est pas défini correctement.', __FILE__));
        }
        
        if ($eqProtocol == 'tcp') {
            // Vérification du paramétrage d'une connexion TCP
            if (!in_array('eqTcpAddr', $configKeys) and !in_array('eqTcpPort', $configKeys) and
                    !in_array('eqTcpRtu', $configKeys)) {
                throw new Exception(__('Veuillez définir la configuration TCP de l\'équipement', __FILE__));
            }
            $eqTcpAddr = $this->getConfiguration('eqTcpAddr');
            $eqTcpPort = $this->getConfiguration('eqTcpPort');
            if (!filter_var($eqTcpAddr, FILTER_VALIDATE_IP)) {
                throw new Exception(__('L\'adresse IP n\'est pas valide', __FILE__));
            }
            if (!is_numeric($eqTcpPort)) {
                throw new Exception(__('Le port doit être un nombre.', __FILE__));
            }
            
        } elseif ($eqProtocol == 'serial') {
            // Vérification du paramétrage d'une connexion série
            if (!in_array('eqSerialAddr', $configKeys) and !in_array('eqSerialMethod', $configKeys) and
                    !in_array('eqSerialBaudrate', $configKeys) and !in_array('eqSerialBytesize', $configKeys) and
                    !in_array('eqSerialParity', $configKeys) and !in_array('eqSerialStopbits', $configKeys)) {
                throw new Exception(__('Veuillez définir la configuration série de l\'équipement', __FILE__));
            }
            $eqSerialAddr = $this->getConfiguration('eqSerialAddr');
            $eqSerialMethod = $this->getConfiguration('eqSerialMethod');
            $eqSerialBaudrate = $this->getConfiguration('eqSerialBaudrate');
            $eqSerialBytesize = $this->getConfiguration('eqSerialBytesize');
            $eqSerialParity = $this->getConfiguration('eqSerialParity');
            $eqSerialStopbits = $this->getConfiguration('eqSerialStopbits');
            if (!is_numeric($eqSerialAddr)) {
                throw new Exception(__('L\'adresse modbus doit être un nombre.', __FILE__));
            }
            if (!in_array($eqSerialMethod, array('rtu', 'ascii'))) {
                throw new Exception(__('La méthode de transport n\'est pas défini correctement.', __FILE__));
            }
            if (!is_numeric($eqSerialBaudrate)) {
                throw new Exception(__('La vitesse de transmission modbus doit être un nombre.', __FILE__));
            }
            if (!in_array($eqSerialBytesize, array('7', '8'))) {
                throw new Exception(__('Le nombre de bits par octet n\'est pas défini correctement.', __FILE__));
            }
            if (!in_array($eqSerialParity, array('E', 'O', 'N'))) {
                throw new Exception(__('La parité n\'est pas définie correctement.', __FILE__));
            }
            if (!in_array($eqSerialStopbits, array('0', '1', '2'))) {
                throw new Exception(__('Le nombre de bits de stop n\'est pas défini correctement.', __FILE__));
            }
        }
        //log::add('mymodbus', 'debug', 'Validation de la configuration pour l\'équipement *' . $this->getName() . '* : OK');
    }

    // Fonction exécutée automatiquement après la sauvegarde de l'équipement (création ou mise à jour)
    public function postSave() {
//        foreach (self::byType('mymodbus') as $mymodbus) { // boucle sur les équipements
//            $mymodbus_mheure = $mymodbus->getConfiguration('mheure');
//            $mymodbus_auto_cmd = $mymodbus->getConfiguration('auto_cmd');
//
//            if ($mymodbus_mheure== 1) {
//                $ntp = $mymodbus->getCmd(null, 'ntp');
//                if (!is_object($ntp)) {
//                    log::add('mymodbus', 'info', 'Ajout cmd synchro heure');
//                    $ntp = new mymodbusCmd();
//                    $ntp->setName(__('Synchro_Heure', __FILE__));
//                }
//                $ntp->setLogicalId('ntp');
//                $ntp->setEqLogic_id($mymodbus->getId());
//                $ntp->setConfiguration('type', 'holding_registers');
//                $ntp->setConfiguration('request', '30');
//                $ntp->setConfiguration('location', '33');
//                $ntp->setType('action');
//                $ntp->setSubType('other');
//                $ntp->setIsVisible(0);
//                $ntp->save();
//
//            } else {
//                $ntp = $mymodbus->getCmd(null, 'ntp');
//                if (is_object($ntp)) {
//                    $ntp->remove();
//                    log::add('mymodbus', 'info', 'suppression cmd synchro heure');
//                }
//            }
//        }
    }

    public function postAjax() {
        self::deamon_stop();
        sleep(2);
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
            $eqConfig = array();
            $eqConfig['id'] = $eqMymodbus->getId();
            foreach ($eqMymodbus->getConfiguration() as $key => $value) {
                $eqConfig[$key] = $value;
            }
            $eqConfig['cmds'] = array();
            foreach ($eqMymodbus->getCmd('info') as $cmdMymodbus) { // boucle sur les commandes info
                $cmdConfig = array();
                $cmdConfig['id'] = $cmdMymodbus->getId();
                foreach ($cmdMymodbus->getConfiguration() as $key => $value) {
                    $cmdConfig[$key] = $value;
                }
                $eqConfig['cmds'][] = $cmdConfig;
            }
            $completeConfig[] = $eqConfig;
        }
        log::add('mymodbus', 'debug', 'eqLogic mymodbus getCompleteConfiguration: ' . json_encode($completeConfig));
        return $completeConfig;
    }
    
    public static function getCallbackUrl() {
        $prot = config::byKey('internalProtocol', 'core', 'http://');
        $port = config::byKey('internalPort', 'core', 80);
        $comp = trim(config::byKey('internalComplement', 'core', ''), '/');
        if ($comp !== '') $comp .= '/';
        $callback = $prot.'localhost:'.$port.'/'.$comp.'plugins/mymodbus/core/php/jeemymodbus.php';
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

    // TODO: à adapter en fonction des paramètres nécessaires à la commande action
    public function execute($_options = array()) {
        $mymodbus = $this->getEqLogic();
        $mymodbus_ip = $mymodbus->getConfiguration('addr');
        $mymodbus_port = $mymodbus->getConfiguration('port');
        $mymodbus_unit = $mymodbus->getConfiguration('unit');
        $mymodbus_location = $this->getConfiguration('location');
        $mymodbus_protocol = $mymodbus->getConfiguration('protocol');
        $mymodbus_baudrate = $mymodbus->getConfiguration('baudrate');
        $mymodbus_path = realpath(dirname(__FILE__) . '/../../ressources');
        $response = true;
        if ($mymodbus_unit=="") {
            $mymodbus_unit=1;
        }
        if ($this->type == 'action') {
            $value="";
            
            if ($mymodbus_protocol!= "rtu") {
                $mymodbus_baudrate=0; // Michel: ça n'a aucun sens...
            }
            
            if ($mymodbus_protocol== "wago" || $mymodbus_protocol== "crouzet_m3" || $mymodbus_protocol== "adam" || $mymodbus_protocol== "logo"  ) {
                $mymodbus_protocol="tcpip";
            }

            try {
                if ($this->getConfiguration('type')=='coils') {
                    $type_input='--wsc=';
                    $value=$this->getConfiguration('request');
                    $return_value=$this->getConfiguration('parameters');
                    
                } else if ($this->getConfiguration('type')=='holding_registers') {
                    $type_input='--whr=';
                    
                } else if ($this->getConfiguration('type')=='Write_Multiple_Holding') {
                    $type_input='--wmhr=';
                    
                } else {
                    return;
                }
                
                switch ($this->subType) {
                    case 'message':
                        $value = urlencode(str_replace('#message#', $_options['message'], $this->getConfiguration('request')));
                        break;
                    case 'slider':
                        $value = str_replace('#slider#', $_options['slider'], $this->getConfiguration('request'));
                        if (!is_numeric($value)) {
                            $value=jeedom::evaluateExpression($value);
                        }
                        break;
                    default:
                        $value=$this->getConfiguration('request');
                        if (!is_numeric($value)) {
                            $value=jeedom::evaluateExpression($value);
                        }
                        $return_value=$this->getConfiguration('parameters');
                        if (!is_numeric($return_value)) {
                            $return_value=jeedom::evaluateExpression($return_value);
                        }
                        break;
                }
                log::add('mymodbus', 'info', 'Debut de l action '.'/usr/bin/python3 ' . $mymodbus_path . '/mymodbus_write.py --host='.$mymodbus_ip.' --protocol='.$mymodbus_protocol.' --port='.$mymodbus_port.' --baudrate='.$mymodbus_baudrate.' --unid='.$mymodbus_unit.' ' . $type_input . ''.$mymodbus_location.' --value='.$value.' 2>&1');
                $result = shell_exec('/usr/bin/python3 ' . $mymodbus_path . '/mymodbus_write.py --host='.$mymodbus_ip.' --protocol='.$mymodbus_protocol.' --port='.$mymodbus_port.' --baudrate='.$mymodbus_baudrate.' --unid='.$mymodbus_unit.' ' . $type_input . ''.$mymodbus_location.' --value='.$value.' 2>&1');
                if ($return_value<>"") {
                    sleep(1);
                    log::add('mymodbus', 'info', 'Debut de l action retour'.'/usr/bin/python3 ' . $mymodbus_path . '/mymodbus_write.py --host='.$mymodbus_ip.' --protocol='.$mymodbus_protocol.' --port='.$mymodbus_port.' --baudrate='.$mymodbus_baudrate.' --unid='.$mymodbus_unit.' ' . $type_input . ''.$mymodbus_location.' --value='.$return_value.' 2>&1');
                    $result = shell_exec('/usr/bin/python3 ' . $mymodbus_path . '/mymodbus_write.py --host='.$mymodbus_ip.' --protocol='.$mymodbus_protocol.' --port='.$mymodbus_port.' --baudrate='.$mymodbus_baudrate.' --unid='.$mymodbus_unit.' ' . $type_input . ''.$mymodbus_location.' --value='.$return_value.' 2>&1');
                }
                return true;
            } catch (Exception $e) {
                // 404
                log::add('mymodbus', 'error', 'valeur '.$this->getConfiguration('id').': ' . $e->getMessage());
                return false;
            }
            
        } else {
            return $this->getValue();
        }
    }

//    public function postInsert() { }
//    public function postRemove() { }
//    public function postSave() {}
    
    // Fonction exécutée automatiquement avant la sauvegarde de la commande (création ou mise à jour)
    // La levée d'une exception invalide la sauvegarde
    public function preSave() {
        $prefix = substr($this->type, 0, 3);
        $Address = $this->getConfiguration($prefix . 'Addr');
        if (!is_numeric($Address)) {
            throw new Exception(__('L\'adresse doit être un nombre.', __FILE__));
        }
        //log::add('mymodbus', 'debug', 'Validation de la configuration pour la commande *' . $this->getName() . '* : OK');
    }


    /*     * **********************Getteur Setteur*************************** */
    //$this->formatValue(str_replace('"','',jeedom::evaluateExpression($this->getConfiguration('calcul'))));
}