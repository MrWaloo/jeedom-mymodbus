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

require_once __DIR__ . '/../../../core/php/core.inc.php';

function delete_unused_files() {
  $dir = realpath(__DIR__ . '/..') . '/';
  $files = [
    'core/php/mymodbus.inc.php',
    'desktop/images/adam_icon.png',
    'desktop/images/crouzet_m3_icon.png',
    'desktop/images/logo_icon.png',
    'desktop/images/rtu_icon.png',
    'desktop/images/rtuovertcp_icon.png',
    'desktop/images/tcpip_icon.png',
    'desktop/images/wago_icon.png',
    'desktop/modal/adam.configuration.php',
    'desktop/modal/crouzet_m3.configuration.php',
    'desktop/modal/logo.configuration.php',
    'desktop/modal/rtu.configuration.php',
    'desktop/modal/rtuovertcp.configuration.php',
    'desktop/modal/tcpip.configuration.php',
    'desktop/modal/wago.configuration.php',
    'desktop/modal/configuration.serial.php',
    'desktop/modal/configuration.tcp.php',
    'desktop/modal/configuration.udp.php',
    'desktop/modal/eqConfig.php',
    'desktop/modal/eqConfig_serial.php',
    'desktop/modal/eqConfig_tcp.php',
    'desktop/modal/eqConfig_udp.php'
  ];
  foreach($files as $file) {
    if (is_file($dir . $file)) {
      unlink($dir . $file);
    }
  }

  $directories = [
    'ressources',
  ];
  foreach($directories as $directory) {
    if (is_file($dir . $directory)) {
      rmdir($dir . $directory);
    }
  }
}

function mymodbus_update() {

  do {
    $cron = cron::byClassAndFunction('mymodbus', 'cronDaily');
    if (is_object($cron)) {
      $cron->remove(true);
    } else {
      break;
    }
  } while (true);

  delete_unused_files();

}

function mymodbus_install() {
  delete_unused_files();
}

/*
    function mymodbus_remove() {}
 */

?>
