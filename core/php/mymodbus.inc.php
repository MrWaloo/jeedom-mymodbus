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
/*
 * Non obligatoire mais peut être utilisé si vous voulez charger en même temps que votre
 * plugin des librairies externes (ne pas oublier d'adapter plugin_info/info.xml).
 * C'est ici que je gére l'affichage de retour, des valeurs du plugin 
 * 
 */


if (isset($argv)) {
    foreach ($argv as $arg) {
        $argList = explode('=', $arg);
        if (isset($argList[0]) && isset($argList[1])) {
            $_GET[$argList[0]] = $argList[1];
			//log::add('mymodbus', 'debug', 'argv : ' . $argList[1]);
			
        }
    }
}
$message = '';
foreach ($_GET as $key => $value) {
    $message .= $key . '=>' . $value . ' ';
}
$values=str_replace('[', '', $_GET['values']);
$values=str_replace(']', '', $values);
$values=str_replace('True', 1, $values);
$values=str_replace('False', 0, $values);
$values=str_replace(' ', '', $values);
//log::add('mymodbus', 'event', 'tableau 1: ' . $values);
$values_arr=explode(',', $values);
$values_inputs=str_replace('[', '', $_GET['inputs']);
$values_inputs=str_replace(']', '', $values_inputs);
$values_inputs=str_replace(' ', '', $values_inputs);
log::add('mymodbus', 'debug', 'Evenement : ' . $message);

if($values_inputs<>""){
	$values_inputs_arr=explode(',', trim($values_inputs));
	if(count($values_inputs_arr)==count($values_arr)){
		$arr_values=array_combine($values_inputs_arr,$values_arr);
		//log::add('mymodbus', 'event', 'tableau 2: ' . json_encode($arr_values));
		//log::add('mymodbus', 'debug', 'inputarr : ' .json_encode($arr_values));
		//$mymodbus_all = eqLogic::byTypeAndSearhConfiguration('mymodbus',$_GET['add']); // mettre à jour par l'id et non l'ip
		//$mymodbus_all = eqLogic::fullById($_GET['eqid']);
		$mymodbus = eqLogic::byid($_GET['eqid']);
		//log::add('mymodbus', 'debug',$_GET['eqid'], 'config');
		//log::add('mymodbus', 'debug', 'get : ' .json_encode($_GET));
		//foreach ($_GET['eqid'] as $id) $EqLogics[] = eqLogic::byId($id);
		
		foreach ($mymodbus->getCmd('info') as $cmd) {
			if ($cmd->getConfiguration('type') == $_GET['type'] && isset($arr_values[$cmd->getConfiguration('location')])){
				$old_value=$cmd->getValue();
				$new_value=$arr_values[$cmd->getConfiguration('location')];
				if($old_value<>$new_value){	
					log::add('mymodbus', 'info', 'mise à jour : '.' Add =>'.$_GET['add'].' Unit => '.$_GET['unit'] .' '.$_GET['type'] .'=> '.$cmd->getConfiguration('location').' -> old value:'.$old_value.' new value:'.$new_value, 'config');
					if($new_value <= $cmd->getConfiguration('maxValue', $new_value) && $new_value >= $cmd->getConfiguration('minValue', $new_value)){
						$cmd->event($new_value);
						$cmd->setValue($new_value);
						$cmd->save();
					}
				}
			}
		}
	}
}