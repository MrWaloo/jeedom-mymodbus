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

<div style="display: none;" id="md_mymodbusTemplate"></div>
<div class="col-lg-3 col-md-3 col-sm-3" id="div_listmymodbus" style="z-index:999">
  <div class="bs-sidebar nav nav-list bs-sidenav">
    <div class="form-group">
      <span class="btn btn-default btn-file" style="width:100%;">
        <i class="fas fa-upload"></i> {{Importer un template depuis un fichier}}<input id="bt_jmqttTemplateUp" type="file" name="file" accept=".json" data-url="plugins/jMQTT/core/ajax/jMQTT.ajax.php?action=fileupload&amp;dir=template" style="display : inline-block;width:100%;">
      </span>
    </div>
    <legend>{{Templates existants}}</legend>
    <ul id="ul_mymyodbusTemplateList" class="nav nav-list bs-sidenav"></ul>
  </div>
</div>
<div class="col-lg-9 col-md-9 col-sm-9" id="div_listmymodbusTemplate" style="display:none;">
  <form class="form-horizontal">
    <!--<legend><i class="fas fa-home"></i> {{Général}}</legend>-->
    <a class="btn btn-sm btn-primary" id="bt_mymodbusTemplateDownload"><i class="fas fa-cloud-download-alt"></i> {{Télécharger}}</a>
    <!--<a class='btn btn-sm btn-success pull-right' id='bt_jmqttTemplateApply'><i class="far fa-check-circle"></i> {{Appliquer}}</a>-->
    <a class="btn btn-sm btn-danger" id="bt_mymodbusTemplateDelete"><i class="fas fa-times"></i> {{Supprimer}}</a>
    <br />
    <!--<div id='div_jmqttTemplateParams'></div>-->
    <!--<legend><i class="fas fa-tools"></i> {{Détails}}</legend>-->
      <!--<legend><i class="fas fa-tachometer-alt"></i> {{Equipement}}</legend>
      <div id='div_mymodbusTemplateEqlogic'></div>
      <br />-->
      <legend><i class="fas fa-list-alt"></i> {{Aperçu des commandes}}</legend>
      <table id="table_mymodbusTemplateCmds" class="table tree table-bordered table-condensed table-striped">
          <thead>
              <tr>
                  <th style="width:250px;">{{Nom}}</th>
                  <th style="width:60px;">{{Sous-Type}}</th>
                  <th style="width:300px;">{{Topic}}</th>
                  <th style="width:300px;">{{Valeur}}</th>
                  <th style="width:1px;">{{Unité}}</th>
                  <th style="width:150px;">{{Paramètres}}</th>
              </tr>
          </thead>
          <tbody>
          </tbody>
      </table>
  </form>
</div>


 <script>
 /*
$('#bt_mymodbusTemplateUp').fileupload({
    dataType: 'json',
    replaceFileInput: false,
    done: function (e, data) {
        if (data.result.state != 'ok') {
            $('#md_mymobusTemplate').showAlert({message: data.result.result, level: 'danger'});
        } else {
            $('#md_mymodbusTemplate').showAlert({message: 'Template ajouté avec succès', level: 'success'});
            refreshMymodbusTemplateList()
        }
        $('#bt_mymodbusTemplateUp').val(null);
    }
});

function refreshMymodbusTemplateList() {
    callPluginAjax({
        data: {
            action: "getTemplateList",
        },
        error: function(error) {
            $('#md_mymodbusTemplate').showAlert({message: error.message, level: 'danger'})
        },
        success: function (dataresult) {
            $('#div_listmymodbusTemplate').hide()
            $('#ul_mymodbusTemplateList').empty()
            li = ''
            for (var i in dataresult) {
                li += "<li class='cursor li_mymodbusTemplate' data-name='" + dataresult[i][0] + "' data-file='" + dataresult[i][1] + "'><a>" + dataresult[i][0] + "</a></li>"
            }
            $('#ul_mymodbusTemplateList').html(li)
        }
    });
}
refreshMymodbusTemplateList()

$('#ul_mymodbusTemplateList').on({
	'click': function(event) {
		$('#div_listMymodbusTemplate').hide()
		$('#ul_mymodbusTemplateList .li_mymodbusTemplate').removeClass('active')
		$('#table_mymodbusTemplateCmds tbody').empty()
		$(this).addClass('active')
		if ($('#ul_mymodbusTemplateList li.active').attr('data-name').startsWith('[Perso]'))
			$('#bt_mymodbusTemplateDelete').show()
		else
			$('#bt_mymodbusTemplateDelete').hide()
		callPluginAjax({
			data: {
				action: "getTemplateByFile",
				file: $(this).attr('data-file')
			},
			error: function(error) {
				$('#md_mymodbusTemplate').showAlert({message: error.message, level: 'danger'})
			},
			success: function (data) {
				$('#div_listmymodbusTemplate').show()



				for (var i in data['commands']) {
					_cmd = data['commands'][i]
					if (!isset(_cmd))
						var _cmd = {configuration: {}};
					if (!isset(_cmd.configuration))
						_cmd.configuration = {};
					if (!isset(_cmd.tree_id)) {
						//looking for all tree-id, keep part before the first dot, convert to Int
						var root_tree_ids = $('[tree-id]').map((pos,e) => parseInt(e.getAttribute("tree-id").split('.')[0]))
						//if some tree-id has been found
						if (root_tree_ids.length > 0)
							_cmd.tree_id = (Math.max.apply(null, root_tree_ids) + 1).toString(); //use the highest one plus one
						else
							_cmd.tree_id = '1'; // else this is the first one
					}
					if (init(_cmd.type) == 'info') {
						var tr = '<tr class="cmd" tree-id="' + _cmd.tree_id + '" style="height: 88px!important;">';
						tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="name" disabled style="margin-bottom: 33px;">';
						tr += '</td><td>';
						tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom:5px;width:120px;" />';
						tr += '<select style="width : 120px;margin-top : 5px;" class="cmdAttr form-control input-sm" disabled data-l1key="subType">';
						if (init(_cmd.subType) == 'numeric') tr += '<option value="numeric">Numérique</option>';
						if (init(_cmd.subType) == 'binary')  tr += '<option value="binary">Binaire</option>';
						if (init(_cmd.subType) == 'string')  tr += '<option value="string">Autre</option>';
						tr += '</select></td><td>';
						tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" disabled style="resize:none!important;height:65px;" placeholder="{{Topic}}"></textarea>';
						tr += '</td><td>';
						tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="resize:none!important;height:65px;" disabled></textarea>';
						tr += '</td><td>';
						tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" disabled title="{{Min}}" style="width:50px;display:inline-block;">';
						tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" disabled title="{{Max}}" style="width:50px;display:inline-block;">';
						tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" disabled title="{{Unité}}" style="width:50px;display:inline-block;margin-right:5px;">';
						tr += '</td><td>';
						if (init(_cmd.subType) == 'numeric')
							tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" disabled data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
						tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" disabled data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
						if (init(_cmd.subType) == 'binary')
							tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" disabled data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span> ';
						tr += '</td></tr>';
						$('#table_jmqttTemplateCmds tbody').append(tr);
						$('#table_jmqttTemplateCmds [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
					}
					if (init(_cmd.type) == 'action') {
						var tr = '<tr class="cmd" tree-id="' +  _cmd.tree_id + '" style="height: 88px!important;">';
						tr += '<td>';
						tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" disabled>';
						tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="margin-top:5px;margin-right:10px;" disabled><option>' + init(_cmd.value, 'Aucune') + '</option></select>';
						tr += '</td>';
						tr += '<td>';
						tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom:5px;width:120px;" />';
						tr += '<select style="width : 120px;margin-top : 5px;" class="cmdAttr form-control input-sm" disabled data-l1key="subType">';
						if (init(_cmd.subType) == 'other')   tr += '<option value="other">Défaut</option>';
						if (init(_cmd.subType) == 'slider')  tr += '<option value="slider">Curseur</option>';
						if (init(_cmd.subType) == 'message') tr += '<option value="message">Message</option>';
						if (init(_cmd.subType) == 'color')   tr += '<option value="color">Couleur</option>';
						if (init(_cmd.subType) == 'select')  tr += '<option value="select">Liste</option>';
						tr += '</select></td>';
						tr += '<td>';
						tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" style="resize:none!important;height:65px;" disabled placeholder="{{Topic}}"></textarea>';
						tr += '</td><td>';
						tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="resize:none!important;height:65px;" disabled placeholder="{{Valeur}}"></textarea>';
						tr += '</td><td>';
						if (init(_cmd.subType) == 'slider') {
							tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" disabled style="width:50px;display:inline-block;">';
							tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" disabled style="width:50px;display:inline-block;">';
						}
						if (init(_cmd.subType) == 'select')
							tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de valeur|texte séparé par ;}}" disabled title="{{Liste}}">';
						tr += '</td><td>';
						tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" disabled checked/>{{Afficher}}</label></span><br> ';
						tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" disabled data-l2key="retain"/>{{Retain}}</label></span><br> ';
						tr += '<span class="checkbox-inline">{{Qos}}: <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" disabled data-l2key="Qos" placeholder="{{Qos}}" title="{{Qos}}" style="width:50px;display:inline-block;"></span> ';
						tr += '</td></tr>';
						$('#table_mymodbusTemplateCmds tbody').append(tr);
						$('#table_mymyodbusTemplateCmds [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
					}
				}
			}
		});
	}
}, '.li_mymodbusTemplate')

$('#bt_mymodbusTemplateDelete').on('click', function() {
  if ($('#ul_mymodbusTemplateList li.active').attr('data-file') == undefined) {
    $('#md_jmqttTemplate').showAlert({message: 'Vous devez d\'abord sélectionner un template', level: 'danger'})
    return
  }
  bootbox.confirm('{{Êtes-vous sûr de vouloir supprimer ce template ?}}', function(result) {
    if (result) {
      callPluginAjax({
        data: {
            action: "deleteTemplateByFile",
            file: $('#ul_mymodbusTemplateList li.active').attr('data-file'),
        },
        error: function(error) {
            $('#md_mymodbusTemplate').showAlert({message: error.message, level: 'danger'})
        },
        success: function(data) {
            if (data) {
                $('#md_mymodbusTemplate').showAlert({message: 'Suppression du template réussie.', level: 'success'})
                refreshJmqttTemplateList()
            } else
                $('#md_mymodbusTemplate').showAlert({message: 'Ce template ne peut pas être supprimé.', level: 'danger'})
        }
      })
    }
  })
})

$('#bt_mymodbusTemplateDownload').on('click',function() {
	if ($('#ul_mymodbusTemplateList li.active').attr('data-file') == undefined) {
		$('#md_mymodbusTemplate').showAlert({message: 'Vous devez d\'abord sélectionner un template', level: 'danger'})
		return
	}
	window.open('core/php/downloadFile.php?pathfile=' + $('#ul_mymodbusTemplateList li.active').attr('data-file'), "_blank", null)
})
*/
</script>
