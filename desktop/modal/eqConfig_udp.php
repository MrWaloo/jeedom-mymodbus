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

$disabled = '';
if (init('template') !== '') {
  $disabled = ' disabled';
}
?>
<!-- 
<div class="form-group">
  <div class="col-sm-4 form-group">
    <img class="img_device" src="plugins/mymodbus/desktop/images/udp_icon.png" style="margin-left:20px;height : 100px;" />
  </div>
</div>
-->

<div class="form-group">
  <label class="col-sm-4 control-label">{{Adresse IP}}</label>
  <div class="col-sm-6">
    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqUdpAddr" placeholder="{{192.168.1.55}}"<?= $disabled ?>/>
  </div>
</div>

<div class="form-group">
  <label class="col-sm-4 control-label">{{Port}}</label>
  <div class="col-sm-6">
    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqUdpPort" placeholder="{{502}}"<?= $disabled ?>/>
  </div>
</div>