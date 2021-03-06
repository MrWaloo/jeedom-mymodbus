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
		<img class="img_device" src="plugins/mymodbus/ressources/images/rtu_icon.png" style="margin-left:20px;height : 100px;" />
	</div>
</div>

<div class="form-group">
        <label class="col-sm-3 control-label">{{Port série}}</label>
        <div class="col-sm-3">
			<select class="eqLogicAttr form-control"  data-l1key="configuration" data-l2key="port">
				<option value="none">{{Aucun}}</option>
				<optgroup label="Ports disponibles">
					<?php
                    foreach (jeedom::getUsbMapping('', true) as $name => $value){
						echo '<option value="' . $value . '">' . $name . ' (' . $value . ')</option>';
                    }
                    ?>
				</optgroup>
			</select>
        </div>
    </div>
<div class="form-group">
    <label class="col-sm-3 control-label">{{Vitesse}}</label>
        <div class="col-sm-3">
            <select id="baudrate" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="baudrate">
                <option value="9600">9600</option>
                <option value="19200">19200</option>
                <option value="38400">38400</option>
                <option value="57600">57600</option>
				<option value="128000">76800</option>
                <option value="115200">115200</option>
            </select>
        </div>
</div>

<div class="form-group">
    <label class="col-sm-3 control-label">{{Parité}}</label>
        <div class="col-sm-3">
            <select id="parity" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="parity">
                        <option value="none">{{Aucune}}</option>
                        <!--option value="even">{{Paire}}</option-->
                        <!--option value="odd">{{Impaire}}</option-->
            </select>
        </div>
</div>

<div class="form-group">
    <label class="col-sm-3 control-label">{{Taille de l'octet}}</label>
        <div class="col-sm-3">
	        <select id="bytesize" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="bytesize">
	            <!--option value="7">{{7 Data Bits}}</option-->
	            <option value="8">{{8 Data Bits}}</option>
	        </select>
	    </div>
</div>

<div class="form-group">
    <label class="col-sm-3 control-label">{{Bit de fin}}</label>
        <div class="col-sm-3">
            <select id="stopbits" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="stopbits">
	            <option value="1">{{1 Stop Bit}}</option>
	            <!--option value="2">{{2 Stop Bit}}</option-->
	        </select>
        </div>
</div>

<div class="form-group">
        <label class="col-sm-3 control-label">{{Unit ID}}</label>
			<div class="col-sm-3">
				<input type="text" id="port" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="unit" placeholder="{{Unit ID}}"/>
			</div>
</div>	
       
<div class="form-group">
        <label class="col-sm-3 control-label">{{Polling en secondes}}</label>
			<div class="col-sm-3">
				<input type="text" id="addr" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="polling" placeholder="{{Polling en secondes}}"/>
			</div>
</div>

<div class="form-group">
		<label class="col-sm-5 control-label"></label>
			   <legend><i class="fa fa-list-alt"></i> {{Matériel :}}</legend>
		<!--   ***********************************  -->
</div>
