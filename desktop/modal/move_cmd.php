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

?>

<form class="form-horizontal">
  <div class="col-sm-12" id="div_form_move" style="height:100%">
    <div class="col-sm-6" id="div_Source" style="height:100%">
    </div>
    <div class="col-sm-6" id="div_Destination" style="height:100%">
    </div>
    <div class="col-sm-12" id="div_move_button" style="height:100%">
    </div>
  </div>
</form>

<script>

function fill_sel_source() {
  let pluginId = 'mymodbus';
  let eqLogicSrc = [];
  jeedom.eqLogic.byType({
    type: pluginId,
    async: false,
    noCache: true,
    success: function(eqLogics) {
      for (var eqLogic of eqLogics) {
        let protocol = eqLogic.configuration['eqProtocol'];
        let eqId = eqLogic.id;
        if (protocol == "shared_from") {
          if (!eqLogicSrc.includes(eqId)) {
            eqLogicSrc.push(eqId);
          }
          let source_id = eqLogic.configuration['eqInterfaceFromEqId'];
          if (!eqLogicSrc.includes(source_id)) {
            eqLogicSrc.push(source_id);
          }
        }
      }
    }
  });
  let html = '';
  if (eqLogicSrc.length > 0) {
    html += '<fieldset>';
    html += '  <legend><i class="fa fa-list-alt"></i> {{Source :}}</legend>';
    html += '  <div class="form-group">';
    html += '    <label class="col-sm-4 control-label">{{Equipement source}}</label>';
    html += '    <div class="col-sm-8">';
    html += '      <select id="sel_source" class="form-control">';
    html += '        <option disabled selected value>-- {{Selectionnez un équipement source}} --</option>';
    for (var eqLogic_id of eqLogicSrc) {
      jeedom.eqLogic.byId({
        id: eqLogic_id,
        async: false,
        success: function(eqLogic) {
          html += '        <option value="' + eqLogic.id + '">' + eqLogic.name + '</option>';
        }
      });
    }
    html += '      </select>';
    html += '    </div>';
    html += '    </br>';
    html += '    <div class="col-sm-12" id="div_ListCmd"></div>';
    html += '  </div>';
    html += '</fieldset>';
  } else {
    html += '{{Aucun équipement ne partage sa configuration de connexion}}';
  }
  let div_Source = document.getElementById('div_Source');
  div_Source.innerHTML = html;
  let sel_source = document.getElementById('sel_source');
  if (sel_source) {
    sel_source.addEventListener('change', sel_source_change);
  }
}

function sel_source_change(event) {
  // Affichage des commandes déplaçables
  jeedom.eqLogic.getCmd({
    id: event.target.value,
    async: false,
    noCache: true,
    success: function(cmds) {
      if (cmds.length == 0) {
        return;
      }
      let movable_cmds = {};
      let blob = {};
      for (var cmd of cmds) {
        if (cmd.logicalId == '') {
          if (cmd.configuration['cmdFctModbus'] != 'fromBlob') {
            movable_cmds[cmd.id] = cmd.name;
          } else {
            let blob_src = null;
            if ('cmdSourceBlobNum' in cmd.configuration) {
              blob_src = cmd.configuration['cmdSourceBlobNum'];
            } else if ('cmdSourceBlobBin' in cmd.configuration) {
              blob_src = cmd.configuration['cmdSourceBlobBin'];
            }
            if (blob_src !== null) {
              if (!(blob_src in blob)) {
                blob[blob_src] = [];
              }
              blob[blob_src].push(cmd.name)
            }
          }
        }
      }
      let html = '';
      for (var cmd_id in movable_cmds) {
        html += '<div class="form-group">';
        html += '  <input type="checkbox" value="' + cmd_id + '"></input>';
        html += '  <label class="checkbos-inline">' + movable_cmds[cmd_id] + '</label>';
        if (cmd_id in blob) {
          html += ' (' + blob[cmd_id].join(' / ') + ')';
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
    id: event.target.value,
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
        for (var eqLogic of eqLogics) {
          if (scr_eqLogic.id != eqLogic.id) {
            if (
              ('eqInterfaceFromEqId' in eqLogic.configuration && eqLogic.configuration['eqInterfaceFromEqId'] === scr_eqLogic.id)
              || ('eqInterfaceFromEqId' in scr_eqLogic.configuration && scr_eqLogic.configuration['eqInterfaceFromEqId'] === eqLogic.id)
              || ('eqInterfaceFromEqId' in eqLogic.configuration && 'eqInterfaceFromEqId' in scr_eqLogic.configuration && eqLogic.configuration['eqInterfaceFromEqId'] === scr_eqLogic.configuration['eqInterfaceFromEqId'])
            ) {
              html += '        <option value="' + eqLogic.id + '">' + eqLogic.name + '</option>';
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
  //console.log('source', sel_source.value, 'destination', sel_destination.value); // DEBUG
  const checkboxes = document.querySelectorAll('#div_ListCmd input[type="checkbox"]');
  const checkedCheckboxes = Array.from(checkboxes).filter(checkbox => checkbox.checked);
  const checkedValues = checkedCheckboxes.map(checkbox => checkbox.value);
  //console.log(checkedValues); // DEBUG

  let html = '</br></br>';
  mymodbus.callPluginAjax({
    async: false,
    data: {
      action: "moveCommands",
      source: sel_source.value,
      destination: sel_destination.value,
      cmdIds: checkedValues,
    },
    error: function(error) {
      html += '{{Erreur lors du déplacement}}';
    },
    success: function (result) {
      html += '{{Déplacement effectué}}';
    }
  });
  let div_move_button = document.getElementById("div_move_button");
  div_move_button.innerHTML = html;
  let div_Source = document.getElementById("div_Source");
  div_Source.innerHTML = '';
  let div_Destination = document.getElementById("div_Destination");
  div_Destination.innerHTML = '';
}

fill_sel_source();

</script>