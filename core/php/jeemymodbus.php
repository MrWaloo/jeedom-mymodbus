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
    echo 'OK'; // Michel: ligne obligatoire pour que le test du démon soit OK ?
    die();
}
$result = json_decode(file_get_contents("php://input"), true);
log::add('mymodbus', 'debug', 'jeemymodbus.php: vivant *' . json_encode($result) . '* type: ' . gettype($result));
if (!is_array($result))
    die();

// TODO: 
if (isset($result['state'])) {
    //log::add('mymodbus', 'debug', 'jeemymodbus.php: state: *' . $result['state'] . '*');
    
} elseif (isset($result['values'])) {
    //log::add('mymodbus', 'debug', 'jeemymodbus.php: values: *' . $result['values'] . '*');
    foreach ($result['values'] as $cmd_id => $new_value) {
        $cmd = cmd::byid($cmd_id);
        $old_value = $cmd->getValue();
        $cache_value = $cmd->getCache();
        $Options = $cmd->getConfiguration('request');
        if (is_numeric($new_value)) {  // evite le calcul sur un none
            $new_value = $new_value.$Options;
            $new_value=jeedom::evaluateExpression($new_value);
        }
        if(($old_value<=>$new_value) || empty($cache_value)){
            log::add('mymodbus', 'info', 'jeemymodbus.php: Mise à jour cmd [id] = ' . $cmd_id . ' -> old value:' . $old_value . ' new value:' . $new_value, 'config');
            $cmd->event($new_value);
            $cmd->setValue($new_value);
            $cmd->save();
        }
    }
} else {
    log::add('mymodbus', 'error', 'jeemodbus.php: unknown message received from daemon');
}
