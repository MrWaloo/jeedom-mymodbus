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
$values_arr=explode(',', $values);
$values_inputs=str_replace('[', '', $_GET['inputs']);
$values_inputs=str_replace(']', '', $values_inputs);
$values_inputs=str_replace(' ', '', $values_inputs);
log::add('mymodbus', 'debug', 'Evenement : ' . $message);
#log::add('mymodbus', 'debug', 'inputs : ' . $values_inputs);

if($values_inputs<>""){
    $values_inputs_arr=explode(',', trim($values_inputs));
    if(count($values_inputs_arr)==count($values_arr)){
        $arr_values=array_combine($values_inputs_arr,$values_arr);
        #log::add('mymodbus', 'debug', 'tableau 2: ' . json_encode($arr_values));
        $mymodbus = eqLogic::byid($_GET['eqid']);
        foreach ($mymodbus->getCmd('info') as $cmd) {
            #log::add('mymodbus', 'debug', 'prob : '.$cmd->getConfiguration('type'));
            #log::add('mymodbus', 'debug', 'prob1 : '.$_GET['type']);
            if ($cmd->getConfiguration('type') == $_GET['type'] && isset($arr_values[$cmd->getConfiguration('location')])){
                #log::add('mymodbus', 'debug', 'ici');
                #log::add('mymodbus', 'debug', 'tableau 2: ' . json_encode($arr_values));
                $old_value=$cmd->getValue();
                $cache_value=$cmd->getCache();
                #log::add('mymodbus', 'debug', 'oldvalue : ' . $old_value);
                $new_value=$arr_values[$cmd->getConfiguration('location')];
                #log::add('mymodbus', 'debug', 'newvalue : ' . $new_value);
                $Options=$cmd->getConfiguration('request');
                if (is_numeric($new_value)) {  // evite le calcul sur un none
                    $new_value = $new_value.$Options;
                    $new_value=jeedom::evaluateExpression($new_value);
                }
                #if($old_value<>$new_value){  modif pour comparer des floats
                #if($old_value<=>$new_value){
                if(($old_value<=>$new_value)|| empty($cache_value)){
                    log::add('mymodbus', 'info', 'mise à jour : '.' Add =>'.$_GET['add'].' Unit => '.$_GET['unit'] .' '.$_GET['type'] .'=> '.$cmd->getConfiguration('location').' -> old value:'.$old_value.' new value:'.$new_value, 'config');
                    $cmd->event($new_value);
                    $cmd->setValue($new_value);
                    $cmd->save();
                }
            }
        }
    }
}
