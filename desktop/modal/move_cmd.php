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
  $eqId = $eqLogic->getId();
  if ($protocol === "shared_from") {
    if (!array_key_exists($eqId, $eqLogicSrc)) {
      $eqLogicSrc[] = $eqId;
    }
    $src_id = $eqLogic->getConfiguration('eqInterfaceFromEqId');
    if (!array_key_exists($src_id, $eqLogicSrc)) {
      $eqLogicSrc[] = $src_id;
    }
  }
}

?>

<form class="form-horizontal">
  <div class="col-sm-12" id="div_form_move" style="height:100%">
    <div class="col-sm-6" id="div_Source" style="height:100%">
      <?php
      if (count($eqLogicSrc) > 0) {
        echo '<fieldset>';
        echo '  <legend><i class="fa fa-list-alt"></i> {{Source :}}</legend>';
        echo '  <div class="form-group">';
        echo '    <label class="col-sm-4 control-label">{{Equipement source}}</label>';
        echo '    <div class="col-sm-8">';
        echo '      <select id="sel_source" class="form-control">';
        echo '        <option disabled selected value>-- {{Selectionnez un équipement source}} --</option>';
        foreach ($eqLogicSrc as $src_id) {
          $eqLogic = eqLogic::byId($src_id);
          echo '        <option value="' . $src_id . '">' . $eqLogic->getName() . '</option>';
        }
        echo '      </select>';
        echo '    </div>';
        echo '    </br>';
        echo '    <div class="col-sm-12" id="div_ListCmd"></div>';
        echo '  </div>';
        echo '</fieldset>';
      } else {
        echo __('Aucun équipement ne partage sa configuration de connexion', __FILE__);
      }
      ?>
    </div>

    <div class="col-sm-6" id="div_Destination" style="height:100%">
    </div>

    <div class="col-sm-12" id="div_move_button" style="height:100%">
    </div>
    
  </div>

</form>

<script>

function sel_source_change(event) {
  // Affichage des commandes déplaçables
  jeedom.eqLogic.getCmd({
    id: $(this).val(),
    //async: false,
    success: function(cmds) {
      if (cmds.length == 0) {
        return;
      }
      let movable_cmds = {};
      let blob = {};
      for (var i in cmds) {
        if (cmds[i].logicalId == '') {
          if (cmds[i].configuration['cmdFctModbus'] != 'fromBlob') {
            movable_cmds[cmds[i].id] = cmds[i].name;
          } else {
            let blob_src = null;
            if ('cmdSourceBlobNum' in cmds[i].configuration) {
              blob_src = cmds[i].configuration['cmdSourceBlobNum'];
            } else if ('cmdSourceBlobBin' in cmds[i].configuration) {
              blob_src = cmds[i].configuration['cmdSourceBlobBin'];
            }
            if (blob_src !== null) {
              if (!(blob_src in blob)) {
                blob[blob_src] = [];
              }
              blob[blob_src].push(cmds[i].name)
            }
          }
        }
      }
      let html = '';
      for (var cmd_id in movable_cmds) {
        html += '<div class="form-group">';
        html += '  <label class="checkbos-inline">';
        html += '    <input type="checkbox" value="' + cmd_id + '"></input>';
        html += '      ' + movable_cmds[cmd_id];
        html += '  </label>';
        if (cmd_id in blob) {
          html += '  (' + blob[cmd_id].join(' / ') + ')';
        }
        html += '</div>';
      }
      let div_ListCmd = document.getElementById("div_ListCmd");
      div_ListCmd.innerHTML = html;
    }
  });

  // Sauvegarde de l'eqLogic sélectionné comme source
  let scr_eqLogic = null;
  jeedom.eqLogic.byId({
    id: $(this).val(),
    async: false,
    success: function(_eqLogic) {
      scr_eqLogic = _eqLogic;
    }
  });

  // Génération de la liste des destinations possibles
  jeedom.eqLogic.byType({
    type: 'mymodbus',
    async: false,
    success: function(eqLogics) {
      let html = '';
      if (eqLogics.length == 0) {
        html += '<legend><i class="fa fa-list-alt"></i> {{Destination :}}</legend>';
        html += '{{Aucune destination possible}}';
      } else {
        html += '<fieldset>';
        html += '  <legend><i class="fa fa-list-alt"></i> {{Destination :}}</legend>';
        html += '  <label class="col-sm-4 control-label">{{Equipement destination}}</label>';
        html += '  <div class="form-group">';
        html += '    <div class="col-sm-8">';
        html += '      <select id="sel_destination" class="form-control">';
        html += '        <option disabled selected value>-- {{Selectionnez un équipement destination}} --</option>';
        for (var i in eqLogics) {
          if (scr_eqLogic.id != eqLogics[i].id) {
            if (
              ('eqInterfaceFromEqId' in eqLogics[i].configuration && eqLogics[i].configuration['eqInterfaceFromEqId'] === scr_eqLogic.id)
              || ('eqInterfaceFromEqId' in scr_eqLogic.configuration && scr_eqLogic.configuration['eqInterfaceFromEqId'] === eqLogics[i].id)
            ) {
              html += '        <option value="' + eqLogics[i].id + '">' + eqLogics[i].name + '</option>';
            }
          }
        }
        html += '      </select>';
        html += '    </div>';
        html += '  </div>';
        html += '</fieldset>';
      }
      let div_Destination = document.getElementById("div_Destination");
      div_Destination.innerHTML = html;
      let div_move_button = document.getElementById("div_move_button");
      div_move_button.innerHTML = '';

      const sel_destination = document.getElementById('sel_destination');
      sel_destination.addEventListener('change', sel_destination_change);
    }
  });
  
}

function sel_destination_change(event) {
  let html = '</br></br>';
  html += '<fieldset>';
  html += '  <legend><i class="fa fa-cog"></i> {{Déplacer :}}</legend>';
  html += '  <div class="form-group">';
  html += '    <div class="col-sm-2">';
  html += '    </div>';
  html += '    <div class="col-sm-10">';
  html += '      <a class="btn btn-sm btn-primary" id="bt_move_cmds"><i class="fas fa-arrow-right"></i> {{Déplacer}}</a>';
  html += '    </div>';
  html += '  </div>';
  html += '</fieldset>';
  let div_move_button = document.getElementById("div_move_button");
  div_move_button.innerHTML = html;

  const bt_move_cmds = document.getElementById('bt_move_cmds');
  bt_move_cmds.addEventListener('click', bt_move_cmds_click);
}

function bt_move_cmds_click(event) {
  const sel_source = document.getElementById('sel_source');
  const sel_destination = document.getElementById('sel_destination');
  console.log('source', sel_source.value, 'destination', sel_destination.value);
}

const sel_source = document.getElementById('sel_source');
sel_source.addEventListener('change', sel_source_change);

</script>