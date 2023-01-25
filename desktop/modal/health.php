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

if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}
$eqLogics = mymodbus::byType('mymodbus');
?>

<table class="table table-condensed tablesorter" id="table_healthmymodbus">
	<thead>
		<tr>
			<th>{{Nom}}</th>
  			<th>{{Id}}</th>
			<th>{{Protocol}}</th>
			<th>{{Unit Id}}</th>
			<th>{{Adresse IP}}</th>
  			<th>{{Port}}</th>
			<th>{{Statut}}</th>
			<th>{{Démon}}</th>
			<th>{{Dernière communication}}</th>
			<th>{{Date création}}</th>
		</tr>
	</thead>
	<tbody>
	 <?php
foreach ($eqLogics as $eqLogic) {
	echo '<tr><td><a href="' . $eqLogic->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqLogic->getHumanName(true) . '</a></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getId() . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getConfiguration('protocol') . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getConfiguration('unit') . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getConfiguration('addr') . '</span></td>';
    echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getConfiguration('port') . '</span></td>';
	$status = '<span class="label label-success" style="font-size : 1em; cursor : default;">{{OK}}</span>';
	if ($eqLogic->getIsEnable()==0) {
		$status = '<span class="label label-danger" style="font-size : 1em; cursor : default;">{{NOK}}</span>';
	}
	echo '<td>' . $status . '</td>';
	//$result = '<span class="label label-success" style="font-size : 1em; cursor : default;">{{OK}}</span>';
	$result = exec("ps -eo pid,command | grep 'eqid={$eqLogic->getId()}' | grep -v grep | awk '{print $1}' | wc -l");
	if ($result==0) {
		$result = '<span class="label label-danger" style="font-size : 1em; cursor : default;">{{NOK}}</span>';
	} else {$result = '<span class="label label-success" style="font-size : 1em; cursor : default;">{{OK}}</span>';
	}
	echo '<td>' . $result . '</td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getStatus('lastCommunication') . '</span></td>';
	echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqLogic->getConfiguration('createtime') . '</span></td></tr>';
}
?>
	</tbody>
</table>