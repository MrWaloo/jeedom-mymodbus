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

require_once __DIR__  . '/../../../../core/php/core.inc.php';

if (!jeedom::apiAccess(init('apikey'), 'mymodbus')) {
  echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
  die();
}
if (init('test') != '') {
  log::add('mymodbus', 'debug', 'jeemymodbus.php: Premier message de test reçu');
  echo 'OK';
  die();
}
$result = json_decode(file_get_contents("php://input"), true);
log::add('mymodbus', 'debug', 'jeemymodbus.php: $result *' . json_encode($result) . '* type: ' . gettype($result));
if (!is_array($result)) {
  die();
}

if (isset($result['heartbeat_request'])) {
  $message = array();
  $message['CMD'] = 'heartbeat_answer';
  $message['answer'] = $result['heartbeat_request'];
  mymodbus::sendToDaemon($message);
  
} elseif (isset($result['getConfig'])) {
  mymodbus::sendNewConfig();
  
} elseif (isset($result['values'])) {
  $names = '';
  foreach ($result['values'] as $cmd_id => $new_value) {
    #log::add('mymodbus', 'debug', 'jeemymodbus.php: Traitement cmd_id = ' . $cmd_id . ' -> new value: ' . sprintf("%d", $new_value));
    
    if (is_numeric($cmd_id)) {
      $cmd = mymodbusCmd::byid($cmd_id);
      $eqlogic = $cmd->getEqLogic();
      //$old_value = $cmd->execCmd();
      
      $cmdOption = $cmd->getConfiguration('cmdOption');
      // Only if the option is valid and cannot be malicious code
      if (strstr($cmdOption, '#value#') && !strstr($cmdOption, ';')) {
        try {
          $eval = str_replace('#value#', '$new_value', $cmdOption);
          $new_value = eval('return ' . $eval . ';');
        } catch (Throwable $t) {
          log::add('mymodbus', 'error', 'jeemymodbus.php: ' . $cmd->getName() . __('Calcul non effectué. Erreur lors du calcul : ' . $t, __FILE__));
        }
      }

    } elseif ($cmd_id === 'cycle_time') {
      $eqlogic = mymodbus::byId($new_value['eqId']);
      $cmd = mymodbusCmd::byEqLogicIdAndLogicalId($new_value['eqId'], 'refresh time');
      $new_value = number_format($new_value['value'], 3);
      
    } elseif ($cmd_id === 'cycle_ok') {
      $eqlogic = mymodbus::byId($new_value['eqId']);
      $cmd = mymodbusCmd::byEqLogicIdAndLogicalId($new_value['eqId'], 'cycle ok');
      $new_value = $new_value['value'];
    }
    
    log::add('mymodbus', 'debug', 'jeemymodbus.php: Mise à jour cmd ' . $cmd->getName() . ' -> new value: ' . $new_value, 'config');
    
    $eqlogic->checkAndUpdateCmd($cmd, $new_value);
    
    $names .= ' \'' . $cmd->getName() . '\'';
  }
  #log::add('mymodbus', 'debug', 'jeemymodbus.php: Mise à jour des commandes info :' . $names);
} else {
  log::add('mymodbus', 'error', 'jeemymodbus.php: unknown message received from daemon');
}

?>