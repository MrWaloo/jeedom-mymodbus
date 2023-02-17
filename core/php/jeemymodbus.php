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
if (!is_array($result))
    die();

if (isset($result['state'])) {
    // TODO
    //log::add('mymodbus', 'debug', 'jeemymodbus.php: state: *' . $result['state'] . '*');
    
} elseif (isset($result['values'])) {
    //log::add('mymodbus', 'debug', 'jeemymodbus.php: values: *' . $result['values'] . '*');
    foreach ($result['values'] as $cmd_id => $new_value) {
        $cmd = cmd::byid($cmd_id);
        //$old_value = $cmd->execCmd();
        log::add('mymodbus', 'info', 'Mise à jour cmd ' . $cmd->getName() . ' -> new value: ' . $new_value, 'config');
        
        $eqlogic = $cmd->getEqLogic();
        $eqlogic->checkAndUpdateCmd($cmd, $new_value);
    }
} else {
    log::add('mymodbus', 'error', 'jeemodbus.php: unknown message received from daemon');
}
