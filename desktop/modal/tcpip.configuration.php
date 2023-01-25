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
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<div class="form-group">
	<div class="col-sm-3 form-group">
		<img class="img_device" src="plugins/mymodbus/desktop/images/tcpip_icon.png" style="margin-left:20px;height : 100px;" />
	</div>
</div>
<div class="form-group">
    <label class="col-sm-3 control-label">{{Adresse IP}}</label>
        <div class="col-sm-3">
            <input type="text" id="addr" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="addr" placeholder="{{Adresse IP}}"/>
        </div>
</div>
<div class="form-group">
    <label class="col-sm-3 control-label">{{Port}}</label>
        <div class="col-sm-3">
            <input type="text" id="port" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="port" placeholder="{{502}}"/>
        </div>
</div>
<div class="form-group">
    <label class="col-sm-3 control-label">{{Unit ID}}</label>
        <div class="col-sm-3">
            <input type="text" id="port" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="unit" placeholder="{{1}}"/>
        </div>
</div>	
<div class="form-group">
    <label class="col-sm-3 control-label">{{Polling en secondes}}</label>
        <div class="col-sm-3">
            <input type="text" id="addr" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="polling" placeholder="{{Polling en secondes}}"/>
        </div>
</div>
<div class="form-group">
	<label class="col-sm-3 control-label">{{Garder la connexion active}}</label>
		<div class="col-sm-9">
			<label class="checkbox-inline"><input type="checkbox" id="keepopen" class="eqLogicAttr" data-l1key="configuration" data-l2key="keepopen"/>{{Activer}}</label>
	    </div>
</div>