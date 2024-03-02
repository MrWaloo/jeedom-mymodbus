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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function install_pyenv() {
  $myModbusId = 'mymodbus';
  $myModbusUpdate = update::byLogicalId($myModbusId);
  $myModbusVersion = $myModbusUpdate->getConfiguration('version');

  $pluginId = 'pyenv';
  $update = update::byLogicalId($pluginId);
  if (!is_object($update)) {
    $update = new update();
    $update->setLogicalId($pluginId);
  }
  $update->setSource('market');
  $update->setConfiguration('version', $myModbusVersion);
  $update->save();
  $update->doUpdate();
  $plugin = plugin::byId($pluginId);
  if (!is_object($plugin)) {
    log::add('mymodbus', 'error', sprintf(__("** Installation ** : plugin non trouvé : %s", __FILE__), $pluginId));
    die();
  }
  $plugin->setIsEnable(1);
  $plugin->dependancy_install();
  log::add('mymodbus', 'info', sprintf(__("** Installation ** : installation terminée : %s", __FILE__), $pluginId));
}

function mymodbus_update() {

  do {
    $cron = cron::byClassAndFunction('mymodbus', 'cronDaily');
    if (is_object($cron))
      $cron->remove(true);
  else
    break;
  } while (true);

  install_pyenv();

}

function mymodbus_install() {
  install_pyenv();
}

/*
        
        
    function mymodbus_remove() {}
 */

?>
