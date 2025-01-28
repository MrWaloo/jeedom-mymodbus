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

if (isset($result['values'])) {
  $names = '';
  $sharedEqs = null;
  $conv = [
    'cycle_time'  => 'refresh time',
    'cycle_ok'    => 'cycle ok',
    'polling'     => 'polling'
  ];
  foreach ($result['values'] as $cmd_id => $new_value) {
    #log::add('mymodbus', 'debug', 'jeemymodbus.php: Traitement cmd_id = ' . $cmd_id . ' -> new value: ' . sprintf("%d", $new_value));
    
    if (is_null($sharedEqs) && isset($new_value['eqId'])) { // Déterminé qu'une seule fois
      $sharedEqs = [];
      foreach (mymodbus::byType('mymodbus') as $eqMymodbus) { // boucle sur les équipements
        if ($eqMymodbus->getIsEnable()
        && $eqMymodbus->getConfiguration('eqProtocol') === 'shared_from'
        && $eqMymodbus->getConfiguration('eqInterfaceFromEqId') === $new_value['eqId']) {
          $sharedEqs[] = $eqMymodbus->getId();
        }
      }
    }

    if (in_array($cmd_id, array_keys($conv))) {
      $eqlogic = mymodbus::byId($new_value['eqId']);
      $cmd = mymodbusCmd::byEqLogicIdAndLogicalId($new_value['eqId'], $conv[$cmd_id]);
      $new_value = $new_value['value'];
      if (is_float($new_value)) {
        $new_value = number_format($new_value, 3);
      }
      
    } elseif (is_numeric($cmd_id)) {
      $cmd = mymodbusCmd::byid($cmd_id);
      if (is_object($cmd)) {
        $eqlogic = $cmd->getEqLogic();
        //$old_value = $cmd->execCmd();
        
        $cmdOption = $cmd->getConfiguration('cmdOption');
        //log::add('mymodbus', 'debug', 'jeemymodbus.php: ' . $cmd->getName() . ' ' . sprintf('cmdOption = +%s+', $cmdOption));
        // Only if the option is valid and cannot be malicious code
        if (strstr($cmdOption, '#value#') && !strstr($cmdOption, ';')) {
          try {
            $eval = str_replace('#value#', sprintf("%s", $new_value), $cmdOption);
            //log::add('mymodbus', 'debug', 'jeemymodbus.php: ' . $cmd->getName() . ' ' . sprintf('eval = +%s+', $eval));
            $new_value = eval('return ' . $eval . ';');
            //log::add('mymodbus', 'debug', 'jeemymodbus.php: ' . $cmd->getName() . ' ' . sprintf('new_value = +%s+', $new_value));
          } catch (Throwable $t) {
            log::add('mymodbus', 'error', 'jeemymodbus.php: ' . $cmd->getName() . ' ' . __('Calcul non effectué. Erreur lors du calcul : ' . $t, __FILE__));
          }
        }
      }
    }

    if (is_object($cmd)) {
      $cmd_name =$cmd->getName();
      log::add('mymodbus', 'debug', "jeemymodbus.php: Mise à jour cmd '$cmd_name' -> new value: '$new_value'");
      $eqlogic->checkAndUpdateCmd($cmd, $new_value);
      if (in_array($cmd_id, array_keys($conv)) && !is_null($sharedEqs) && $sharedEqs != []) {
        foreach ($sharedEqs as $sharedEqId) {
          $shared_cmd = mymodbusCmd::byEqLogicIdAndLogicalId($sharedEqId, $conv[$cmd_id]);
          if (is_object($shared_cmd)) {
            $shared_eqlogic = mymodbus::byId($sharedEqId);
            $shared_eqlogic->checkAndUpdateCmd($shared_cmd, $new_value);
          }
        }
      }
      #$names .= ' \'' . $cmd->getName() . '\'';
    } else {
      log::add('mymodbus', 'debug', "'jeemymodbus.php: Mise à jour cmd_id '$cmd_id' impossible -> new value: '$new_value'");
    }
  }
  #log::add('mymodbus', 'debug', 'jeemymodbus.php: Mise à jour des commandes info :' . $names);

} elseif (isset($result['test'])) {
  
} else {
  log::add('mymodbus', 'error', 'jeemymodbus.php: unknown message received from daemon');
}

?>