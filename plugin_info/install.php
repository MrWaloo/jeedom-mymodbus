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

function mydeltree($_dir) {
	if (!is_dir($_dir)) {
		return;
	}

	// On utilise scandir avec le drapeau SCANDIR_SORT_DESCENDING pour parcourir les éléments du plus profond au moins profond
	foreach (scandir($_dir, SCANDIR_SORT_DESCENDING) as $element) {
		if ($element === '.' || $element === '..') {
			continue; // On ignore les répertoires . et ..
		}
		$file_or_dir = "$_dir/$element";
		if (is_dir($file_or_dir)) {
			mydeltree($file_or_dir);
		} else {
			if (!unlink(realpath($file_or_dir))) {
				// Gestion des erreurs en cas d'échec de la suppression
				throw new RuntimeException("Impossible de supprimer le fichier $file_or_dir");
			}
		}
	}

	// On supprime le répertoire vide
	if (!rmdir($_dir)) {
		throw new RuntimeException("Impossible de supprimer le répertoire $_dir");
	}
}

function delete_unused_files() {
	$dir = realpath(__DIR__ . '/..') . '/';
	$files = [
		'core/php/mymodbus.inc.php',
		'desktop/images/adam_icon.png',
		'desktop/images/crouzet_m3_icon.png',
		'desktop/images/logo_icon.png',
		'desktop/images/rtu_icon.png',
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
			unlink(realpath($dir . $file));
		}
	}

	$directories = [
		'ressources',
	];
	foreach($directories as $directory) {
		if (is_dir($dir . $directory)) {
			mydeltree($dir . $directory);
		}
	}
}

function mymodbus_update() {
	$pluginId = basename(realpath(__DIR__ . '/..'));
	log::add($pluginId, 'info', 'mymodbus_update');

	// Remove old cron jobs
	do {
		$cron = cron::byClassAndFunction($pluginId, 'cronDaily');
		if (is_object($cron)) {
			$cron->remove(true);
		} else {
			break;
		}
	} while (true);

	// Save all eqLogics and their commands
	// This is necessary to update the configuration of the plugin
	$eqLogics = eqLogic::byType($pluginId);
	foreach ($eqLogics as $eqLogic) {
		foreach ($eqLogic->getCmd() as $cmdMymodbus) { // loop over the commands of the eqLogic
			$cmdMymodbus->save();
		}
		$eqLogic->save();
	}

	delete_unused_files();

}

function mymodbus_install() {
	$pluginId = basename(realpath(__DIR__ . '/..'));
	log::add($pluginId, 'info', 'mymodbus_install');

	delete_unused_files();
}

/*
		function mymodbus_remove() {}
 */


?>
