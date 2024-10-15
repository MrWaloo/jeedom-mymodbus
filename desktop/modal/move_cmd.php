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
$pluginId = 'mymodbus';
$eqLogics = eqLogic::byType($pluginId);
$eqLogicSrc = [];

foreach ($eqLogics as $eqLogic) {
  $protocol = $eqLogic->getConfiguration('eqProtocol', '');
  if ($protocol === "shared_from") {
    $src_id = $eqLogic->getConfiguration('eqInterfaceFromEqId');
    if (!array_key_exists($src_id, $eqLogicSrc)) {
      $eqLogicSrc[$src_id] = [];
    }
    $eqId = $eqLogic->getId();
    $eqLogicSrc[$src_id][] = $eqLogic->getId();
  }
}

?>

<div class="col-sm-8" id="div_Source" style="height:100%">
  <?php
  if (count($eqLogicSrc) > 0) {
    echo '<form class="form-horizontal">';
    echo '  <fieldset>';
    echo '    <div class="form-group">';
    echo '      <label class="col-sm-4 control-label">{{Equipement source}}</label>';
    echo '      <div class="col-sm-8">';
    echo '        <select id="sel_source" class="form-control">';
    echo '          <option disabled selected value>-- {{Selectionnez un équipement source}} --</option>';
    foreach ($eqLogicSrc as $src_id => $dest_ids) {
      $eqLogic = eqLogic::byId($src_id);
      echo '          <option value="' . $src_id . '">' . $eqLogic->getName() . '</option>';
    }
    echo '        </select>';
    echo '      </div>';
    echo '      </br>';
    echo '      <div class="col-sm-8" id="div_ListCmd"></div>';
    echo '    </div>';
    echo '  </fieldset>';
    echo '</form>';
  } else {
    echo __('Aucun équipement ne partage sa configuration de connexion', __FILE__);
  }
  ?>
</div>

<div class="col-sm-4" id="div_Destination" style="height:100%">
  
</div>

<script>

$('#sel_source').off().on('change', function () {
  jeedom.eqLogic.getCmd({
    id: $(this).val(),
    async: false,
    success: function(cmds) {
      var html = '';
      for (var i in cmds) {
        if (cmds[i].logicalId == '') {
          html += '    <div class="form-group">';
          html += '      <label class="checkbos-inline">';
          html += '        <input type="checkbox" value="' + cmds[i].id + '"></input>';
          html += '          ' + cmds[i].name;
          html += '      </label>';
          html += '    </div>';
        }
      }
      let div_ListCmd = document.getElementById("div_ListCmd");
      div_ListCmd.innerHTML = html;
    }
  })
});

</script>