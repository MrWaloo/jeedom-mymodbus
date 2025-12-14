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

require_once __DIR__ . '/../../../../core/php/core.inc.php';

if (!jeedom::apiAccess(init('apikey'), 'mymodbus')) {
	echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
	die();
}
if (init('test') != '') {
	log::add('mymodbus', 'debug', 'jeemymodbus.php: Premier message de test reçu');
	mymodbus::sendNewConfig();
	echo 'OK';
	die();
}
$input = json_decode(file_get_contents("php://input"), true);
log::add('mymodbus', 'debug', 'jeemymodbus.php: $input *' . json_encode($input) . '* type: ' . gettype($input));
if (!is_array($input)) {
	die();
}

if (isset($input['values'])) {
	$names = '';
	$sharedEqs = null;
	$conv = [
		'cycle_time'	=> 'refresh time',
		'cycle_ok'		=> 'cycle ok',
		'polling'			=> 'polling'
	];
	//log::add('mymodbus', 'debug', 'jeemymodbus.php: ******* VALUES ******* ' . sprintf("input['values'] = *'%s'*", var_export($input['values'], true)));
	foreach ($input['values'] as $cmd_id => $new_value) {
		//log::add('mymodbus', 'debug', 'jeemymodbus.php: Traitement cmd_id = ' . $cmd_id . ' -> new value: ' . sprintf("%s", var_export($new_value, true)) . ' // cmd_id is numeric = ' . (is_numeric($cmd_id) ? 'true' : 'false'));
		
		if (is_null($sharedEqs)) { // Déterminé qu'une seule fois
			$sharedEqs = [];
			foreach (mymodbus::byType('mymodbus') as $eqMymodbus) { // boucle sur les équipements
				if ($eqMymodbus->getIsEnable()
				&& $eqMymodbus->getConfiguration('eqProtocol') === 'shared_from') {
					$fromEqId = $eqMymodbus->getConfiguration('eqInterfaceFromEqId');
					if (!array_key_exists($fromEqId, $sharedEqs)) {
						$sharedEqs[$fromEqId] = [];
					}
					$sharedEqs[$fromEqId][] = $eqMymodbus->getId();
				}
			}
		}

		$new_cmd_value = null;

		if (in_array($cmd_id, array_keys($conv))) {
			$cmd = mymodbusCmd::byEqLogicIdAndLogicalId($new_value['eqId'], $conv[$cmd_id]);
			$new_cmd_value = $new_value['value'];
			
		} elseif (is_numeric($cmd_id)) {
			$cmd = mymodbusCmd::byid($cmd_id);
			if (is_object($cmd)) {
				$new_cmd_value = $new_value;
				//$old_value = $cmd->execCmd();
				
				$cmdOption = $cmd->getConfiguration('cmdOption');
				//log::add('mymodbus', 'debug', 'jeemymodbus.php: ' . $cmd->getName() . ' ' . sprintf('cmdOption = +%s+', $cmdOption));
				// Only if the option is valid and cannot be malicious code
				if (strstr($cmdOption, '#value#') && !strstr($cmdOption, ';') && !strstr($cmdOption, 'include') && !strstr($cmdOption, 'require')) {
					try {
						$eval = str_replace('#value#', sprintf("%s", $new_value), $cmdOption);
						//log::add('mymodbus', 'debug', 'jeemymodbus.php: ' . $cmd->getName() . ' ' . sprintf('eval = +%s+', $eval));
						$new_cmd_value = eval('return ' . $eval . ';');
						//log::add('mymodbus', 'debug', 'jeemymodbus.php: ' . $cmd->getName() . ' ' . sprintf('new_value = +%s+', $new_value_value));
					} catch (Throwable $t) {
						log::add('mymodbus', 'error', 'jeemymodbus.php: ' . $cmd->getName() . ' ' . __('Calcul non effectué. Erreur lors du calcul : ' . $t, __FILE__));
					}
				}
			}
		}
		if (is_float($new_cmd_value)) {
			$new_cmd_value = number_format($new_cmd_value, 5, '.', '');
		}

		if (is_object($cmd) && !is_null($new_cmd_value)) {
			$cmd_name = $cmd->getName();
			log::add('mymodbus', 'debug', "jeemymodbus.php: Mise à jour cmd '$cmd_name' -> new value: '$new_cmd_value'");
			$cmd->event($new_cmd_value);

			if (in_array($cmd_id, array_keys($conv)) && !is_null($sharedEqs) && array_key_exists($new_value['eqId'], $sharedEqs)) {
				foreach ($sharedEqs[$new_value['eqId']] as $sharedEqId) {
					$shared_cmd = mymodbusCmd::byEqLogicIdAndLogicalId($sharedEqId, $conv[$cmd_id]);
					if (is_object($shared_cmd)) {
						$shared_cmd->event($new_cmd_value);
					}
				}
			}
			#$names .= ' \'' . $cmd->getName() . '\'';
		} else {
			log::add('mymodbus', 'debug', "jeemymodbus.php: Mise à jour cmd_id '$cmd_id' impossible -> new value: '" . sprintf("%s", var_export($new_value, true)) . "'");
		}
	}
	#log::add('mymodbus', 'debug', 'jeemymodbus.php: Mise à jour des commandes info :' . $names);

} elseif (isset($input['RegTest'])) {
	foreach ($input['RegTest'] as $eqLogic_id => $results) {
//		log::add('mymodbus', 'debug', "jeemymodbus.php: ***DEBUG*** \$eqLogic_id '$eqLogic_id'...");
		$eqLogic = mymodbus::byId($eqLogic_id);
		if (is_object($eqLogic)) {
			$eq_name = $eqLogic->getName();
			log::add('mymodbus', 'debug', "jeemymodbus.php: Mise à jour équipement de test '$eq_name'...");
			foreach ($results as $address => $new_value) {
//				log::add('mymodbus', 'debug', "jeemymodbus.php: ***DEBUG*** \$address '$address'...");
//				log::add('mymodbus', 'debug', "jeemymodbus.php: ***DEBUG*** \$new_value '$new_value'...");
				$cmd = mymodbusCmd::byEqLogicIdAndLogicalId($eqLogic_id, 'RegTest_' . $address);
				if (is_object($cmd)) {
					$cmd_name =$cmd->getName();
					log::add('mymodbus', 'debug', "jeemymodbus.php: Mise à jour cmd '$cmd_name' -> new value: '$new_value'");
					$cmd->event($new_value);
				}
			}
		}
	}

} else {
	log::add('mymodbus', 'error', 'jeemymodbus.php: unknown message received from daemon');
}

?>