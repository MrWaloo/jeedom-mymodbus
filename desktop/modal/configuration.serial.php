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
<!-- 
<div class="form-group">
  <div class="col-sm-4 form-group">
    <img class="img_device" src="plugins/mymodbus/desktop/images/serial_icon.png" style="margin-left:20px;height : 100px;" />
  </div>
</div>
-->

<div class="form-group">
  <label class="col-sm-4 control-label">{{Interface}}</label>
  <div class="col-sm-6">
    <select class="eqLogicAttr form-control" data-toggle="tooltip" data-placement="top" data-html="true" data-l1key="configuration" data-l2key="eqSerialInterface">
      <?php
      foreach (mymodbus::getTtyInterfaces() as $key => $value) {
        echo '<option title="' . $value . '" value="' . $value . '">' . $key . '</option>';
      }
      ?>
    </select>
  </div>
</div>

<div class="form-group">
  <label class="col-sm-4 control-label">{{Méthode de transport}}</label>
  <div class="col-sm-6">
    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqSerialMethod">
      <option value="rtu">{{RTU}}</option>
      <option value="ascii">{{ASCII}}</option>
      <option value="binary">{{Binaire}}</option>
    </select>
  </div>
</div>

<div class="form-group">
  <label class="col-sm-4 control-label">{{Vitesse de transmission}}</label>
  <div class="col-sm-6">
    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqSerialBaudrate">
      <option disabled selected value>-- {{Selectionnez une valeur}} --</option>
      <option value="300">300</option>
      <option value="600">600</option>
      <option value="1200">1200</option>
      <option value="2400">2400</option>
      <option value="4800">4800</option>
      <option value="9600">9600</option>
      <option value="14400">14400</option>
      <option value="19200">19200</option>
      <option value="38400">38400</option>
      <option value="56000">56000</option>
      <option value="57600">57600</option>
      <option value="115200">115200</option>
      <option value="128000">128000</option>
      <option value="230400">230400</option>
      <option value="256000">256000</option>
    </select>
  </div>
</div>

<div class="form-group">
  <label class="col-sm-4 control-label">{{Nombre de bit par octet}}</label>
  <div class="col-sm-6">
    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqSerialBytesize">
      <option disabled selected value>-- {{Selectionnez une valeur}} --</option>
      <option value="7">7</option>
      <option value="8">8</option>
    </select>
  </div>
</div>

<div class="form-group">
  <label class="col-sm-4 control-label">{{Parité}}</label>
  <div class="col-sm-6">
    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqSerialParity">
      <option disabled selected value>-- {{Selectionnez une valeur}} --</option>
      <option value="E">{{Paire}}</option>
      <option value="O">{{Impaire}}</option>
      <option value="N">{{Aucune}}</option>
    </select>
  </div>
</div>

<div class="form-group">
  <label class="col-sm-4 control-label">{{Bits de stop}}</label>
  <div class="col-sm-6">
    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqSerialStopbits">
      <option disabled selected value>-- {{Selectionnez une valeur}} --</option>
      <option value="0">0</option>
      <option value="1">1</option>
      <option value="2">2</option>
    </select>
  </div>
</div>



