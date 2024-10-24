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



    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */
    public static function cron() {
            if (!self::deamon_info()) {
                self::deamon_start();
            }
    }

    public static function deamon_start(){
		//self::deamon_stop();
		log::add('mymodbus', 'info', 'on en est là');
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		
    	$eqLogics = eqLogic::byType('mymodbus');
		foreach ($eqLogics as $mymodbus) {
			if ($mymodbus->getIsEnable()) {	
		    	$mymodbus_ip = $mymodbus->getConfiguration('addr');
				$mymodbus_port = $mymodbus->getConfiguration('port');
				$mymodbus_unit = $mymodbus->getConfiguration('unit');
				$mymodbus_keepopen = $mymodbus->getConfiguration('keepopen');
				if($mymodbus_port==""){
					$mymodbus_port=502;
				}
				if($mymodbus_unit==""){
					$mymodbus_unit=1;
				}
				if($mymodbus_keepopen==""){
					$mymodbus_keepopen=0;
				}
		    	$mymodbus_polling = $mymodbus->getConfiguration('polling');
		    	$request='-h '.$mymodbus_ip.' -p '.$mymodbus_port.' --unit_id='.$mymodbus_unit.' --polling='.$mymodbus_polling.' --keepopen='.$mymodbus_keepopen;
		        log::add('mymodbus', 'info', 'Lancement du démon modbus'.$request);
		        $mymodbus_path = realpath(dirname(__FILE__) . '/../../ressources');
				foreach ($mymodbus->getCmd('info') as $cmd) {
					if($cmd->getConfiguration('type')=='coils'){
						//log::add('mymodbus', 'info', 'coil trouvé :'.$cmd->getConfiguration('location'));
						$coils[]=$cmd->getConfiguration('location');
					}
					if($cmd->getConfiguration('type')=='discrete_inputs'){
						$discrete_inputs[]=$cmd->getConfiguration('location');
					}
					if($cmd->getConfiguration('type')=='holding_registers'){
						$holding_registers[]=$cmd->getConfiguration('location');
						log::add('mymodbus', 'info', 'holding_registers :'.$cmd->getConfiguration('location'));
					}
					if($cmd->getConfiguration('type')=='input_registers'){
						$input_registers[]=$cmd->getConfiguration('location');
					}
				}
				if($coils){
					$request.=' --coils='.implode(',',$coils);
				}
				if($discrete_inputs){
					$request.=' --dis='.implode(',',$discrete_inputs);
				}
				if($holding_registers){
					$request.=' --hrs='.implode(',',$holding_registers);
				}
				if($input_registers){
					$request.=' --irs='.implode(',',$input_registers);
				}
		        $cmd = 'nice -n 19 /usr/bin/python ' . $mymodbus_path . '/demon.py ' . $request;
		        log::add('mymodbus', 'info', 'Lancement démon modbus : ' . $cmd);
		        $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('mymodbus') . ' 2>&1 &');
		        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
		            log::add('mymodbus', 'error', $result);
		            return false;
		        }
		        sleep(2);
		        if (!self::deamon_info()) {
		            sleep(10);
		            if (!self::deamon_info()) {
		                log::add('mymodbus', 'error', 'Impossible de lancer le démon Modbus', 'unableStartDeamon');
		                return false;
		            }
		        }
		        message::removeAll('mymodbus', 'unableStartDeamon');
		        log::add('mymodbus', 'info', 'Démon modbus lancé');
		        }
		}
    }

	
    public static function deamon_info() {
		$return = array();
		$return['state'] = 'nok';
	    $return['launchable'] = 'ok';

		
		$result = exec("ps -eo pid,command | grep 'demon.py' | grep -v grep | awk '{print $1}'");
		if ($result == 0) {
            
			$return['state'] = 'nok';
			
        } else {
		     $return['state'] = 'ok';
		}		
		return $return;
		
    }

    public static function deamon_stop() {
	
		$nbpid = exec("ps -eo pid,command | grep 'demon.py' | grep -v grep | awk '{print $1}'| wc -l");
		log::add('mymodbus', 'info', 'valeur de nbpiddébut'.$nbpid);
		While ($nbpid > 0) {	
		  $nbpid = exec("ps -eo pid,command | grep 'demon.py' | grep -v grep | awk '{print $1}'| wc -l");
		  log::add('mymodbus', 'info', 'valeur de nbpid'.$nbpid);
		  self::Kill_Process();  
		  
		}
		log::add('mymodbus', 'info', 'Arret des deamons');
		
		
    }
    public static function dependancy_info() {
    $return = array();
    $return['state'] = 'ok';
	if (exec(system::getCmdSudo() . system::get('cmd_check') . '-E "python3\-setuptools" | wc -l') == 0) $return['state'] = 'nok';
	if (exec(system::getCmdSudo() . 'pip list | grep -E "pyModbusTCP" | wc -l') == 0) $return['state'] = 'nok';
	if (exec(system::getCmdSudo() . 'pip3 list | grep -E "pyserial" | wc -l') == 0) $return['state'] = 'nok';
	log::add('mymodbus', 'info', 'valeur de return'.$return['state']);
	if ($return['state'] == 'nok') message::add('mymodbus_dep', __('Si les dépendances sont/restent NOK, veuillez mettre à jour votre système linux, puis relancer l\'installation des dépendances générales. Merci', __FILE__));
    return $return;
    }
    public static function dependancy_install()
	{
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../ressources/install.sh /tmp/dependances_MyModbus_en_cours', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}
    public static function Kill_Process() {
		
		$pid = exec("ps -eo pid,command | grep 'demon.py' | grep -v grep | awk '{print $1}'");
        exec('kill ' . $pid);
        $check = self::deamon_info();
        $retry = 0;
        //while ($check) {
           // $check = self::deamon_info();
            //$retry++;
            //if ($retry > 10) {
                //$check = false;
            //} else {
                //sleep(1);
            //}
        //}
        //exec('kill -9 ' . $pid);
        //$check = self::deamon_info();
        //$retry = 0;
        //while ($check) {
            //$check = self::deamon_info();
            //$retry++;
            //if ($retry > 10) {
                //$check = false;
            //} else {
                //sleep(1);
            //}
        //}
		//$retry = 0;
	}	


    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        
    }

    public function postInsert() {
          if(1==2){  	
    	for ($i = 0; $i <= 12; $i++) {
    		$mymodbusCmd = new mymodbusCmd();
	        $mymodbusCmd->setName(__('DI'.$i, __FILE__));
	        $mymodbusCmd->setEqLogic_id($this->id);
			$mymodbusCmd->setConfiguration('type', 'DI');
			$mymodbusCmd->setConfiguration('id', 'DI'.$i);
	        $mymodbusCmd->setUnite('');
	        $mymodbusCmd->setType('info');
	        $mymodbusCmd->setSubType('binary');
			$mymodbusCmd->save();
		}
		
		for ($i = 0; $i <= 6; $i++) {
    		$mymodbusCmd = new mymodbusCmd();
	        $mymodbusCmd->setName(__('Valeur DO'.$i, __FILE__));
	        $mymodbusCmd->setEqLogic_id($this->id);
			$mymodbusCmd->setConfiguration('type', 'DO');
			$mymodbusCmd->setConfiguration('id', 'DO'.$i);
	        $mymodbusCmd->setUnite('');
	        $mymodbusCmd->setType('info');
	        $mymodbusCmd->setSubType('binary');
			$mymodbusCmd->save();
		}
		
		for ($i = 0; $i <= 6; $i++) {
    		$mymodbusCmd = new mymodbusCmd();
	        $mymodbusCmd->setName(__('DO'.$i.' ON', __FILE__));
	        $mymodbusCmd->setEqLogic_id($this->id);
			$mymodbusCmd->setConfiguration('type', 'DO');
			$mymodbusCmd->setConfiguration('id', 'DO'.$i);
			$mymodbusCmd->setConfiguration('request', '1');
	        $mymodbusCmd->setType('action');
	        $mymodbusCmd->setSubType('other');
			$mymodbusCmd->save();
		}
		
        for ($i = 0; $i <= 6; $i++) {
    		$mymodbusCmd = new mymodbusCmd();
	        $mymodbusCmd->setName(__('DO'.$i.' OFF', __FILE__));
	        $mymodbusCmd->setEqLogic_id($this->id);
			$mymodbusCmd->setConfiguration('type', 'DO');
			$mymodbusCmd->setConfiguration('id', 'DO'.$i);
			$mymodbusCmd->setConfiguration('request', '0');
	        $mymodbusCmd->setType('action');
	        $mymodbusCmd->setSubType('other');
			$mymodbusCmd->save();
		}
        
        }  
    }    

    public function preSave() {
		
        if ($this->getConfiguration('request') == '') {
            //throw new Exception(__('La requete ne peut etre vide',__FILE__));
        }    
    }

    public function postSave() {
    self::deamon_stop();
		sleep(1);
		$deamonRunning = self::deamon_info();
        if ($deamonRunning['state'] != 'ok') {
            self::deamon_start();
        }    
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
        
    }

	
    public function preRemove() {
        
    }

    public function postRemove() {
        self::deamon_stop();
		sleep(1);
		$deamonRunning = self::deamon_info();
        if ($deamonRunning['state'] != 'ok') {
            self::deamon_start();
        }
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
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

    public function execute($_options = null) {
		

		log::add('mymodbus', 'info', 'Debut de l action');	
    	$mymodbus = $this->getEqLogic();
        $mymodbus_ip = $mymodbus->getConfiguration('addr');
		$mymodbus_port = $mymodbus->getConfiguration('port');
		$mymodbus_location = $this->getConfiguration('location');
		$mymodbus_path = realpath(dirname(__FILE__) . '/../../ressources');
		$response = true;
        
		log::add('mymodbus', 'info', 'Debut de l action 2:'.$mymodbus_ip);
		if ($this->type == 'action') {
			$value="";
		try {
			if($this->getConfiguration('type')=='coils'){
				$type_input='--wsc=';
				$value=$this->getConfiguration('request');
				$return_value=$this->getConfiguration('parameters');
			}else if($this->getConfiguration('type')=='holding_registers'){
				$type_input='--wsr=';
				switch ($this->subType) {
                    case 'message':
						$value = urlencode(str_replace('#message#', $_options['message'], $this->getConfiguration('request')));
                        break;
                    case 'slider':
						$value = str_replace('#slider#', $_options['slider'], $this->getConfiguration('request'));
                        break;
                    default:
						$value=$this->getConfiguration('request');
						if (!is_numeric($value)) {
							$value=cmd::cmdToValue($value);		
						}	
						$return_value=$this->getConfiguration('parameters');
                        break;
                }
			}else{
				return;
			}	
			log::add('mymodbus', 'info', 'Debut de l action '.'/usr/bin/python ' . $mymodbus_path . '/mymodbus_write.py -h '.$mymodbus_ip.' -p '.$mymodbus_port.' ' . $type_input . ''.$mymodbus_location.' --value='.$value.' 2>&1');
			$result = shell_exec('/usr/bin/python ' . $mymodbus_path . '/mymodbus_write.py -h '.$mymodbus_ip.' -p '.$mymodbus_port.' ' . $type_input . ''.$mymodbus_location.' --value='.$value.' 2>&1');
			if($return_value<>""){
				sleep(1);
				log::add('mymodbus', 'info', 'Debut de l action '.'/usr/bin/python ' . $mymodbus_path . '/mymodbus_write.py -h '.$mymodbus_ip.' -p '.$mymodbus_port.' ' . $type_input . ''.$mymodbus_location.' --value='.$return_value.' 2>&1');
				$result = shell_exec('/usr/bin/python ' . $mymodbus_path . '/mymodbus_write.py -h '.$mymodbus_ip.' -p '.$mymodbus_port.' ' . $type_input . ''.$mymodbus_location.' --value='.$return_value.' 2>&1');
			}
			return true;
		} catch (Exception $e)  {
		    // 404
		    log::add('mymodbus', 'error', 'valeur '.$this->getConfiguration('id').': ' . $e->getMessage());
		    return false;
		}
		}else{
			return $this->getValue();
		}
		
    }

    /*     * **********************Getteur Setteur*************************** */
}


