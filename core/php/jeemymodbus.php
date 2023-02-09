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
    log::add('mymodbus', 'debug', 'Premier message de test reçu');
    echo 'OK'; // Michel: ligne obligatoire pour que le test du démon soit OK ?
    die();
}
$result = json_decode(file_get_contents("php://input"), true);
log::add('mymodbus', 'debug', '**** jeemymodbus.php **** vivant *' . $result . '*');
if (!is_array($result)) {
    die();
}
log::add('mymodbus', 'debug', '**** jeemymodbus.php **** vivant 3');

// TODO: 
if (isset($result['state'])) {
    log::add('mymodbus', 'debug', '**** jeemymodbus.php **** state: *' . $result['state'] . '*');
} elseif (isset($result['key2'])) {
    // TODO ...
} else {
    log::add('mymodbus', 'error', 'unknown message received from daemon');
}
