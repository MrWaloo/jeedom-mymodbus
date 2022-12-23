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

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
});
$("#table_mymodbusFilters").sortable({axis: "y", cursor: "move", items: ".filter", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */
$('.eqLogicAction[data-action=bt_docSpecific]').on('click', function () {
    window.open('https://bebel27a.github.io/jeedom-mymobdus.github.io/fr_FR/');
});
$('.pluginAction[data-action=openLink]').on('click',function(){
    window.open($(this).attr("data-location"), "_blank", null);
});
$('#bt_healthmymodbus').on('click', function () {
    $('#md_modal').dialog({title: "{{Santé mymodbus}}"});
    $('#md_modal').load('index.php?v=d&plugin=mymodbus&modal=health').dialog('open');
});
$('.bt_showExpressionTest').off('click').on('click', function () {
  $('#md_modal').dialog({title: "{{Testeur d'expression}}"});
  $("#md_modal").load('index.php?v=d&modal=expression.test').dialog('open');
});
$('.bt_showNoteManagement').off('click').on('click', function () {
  $('#md_modal').dialog({title: "{{Notes}}"});
  $("#md_modal").load('index.php?v=d&modal=note.manager').dialog('open');
});
$('#bt_templatesmymodbus').on('click', function () {
    $('#md_modal').dialog({title: "{{Gestion des templates d'équipements mymobus}}"});
    $('#md_modal').load('index.php?v=d&plugin=mymodbus&modal=templates').dialog('open');
});

 function prePrintEqLogic() {
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=protocol]').off();
}

function  printEqLogic(_eqLogic) {
    $.showLoading();
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=protocol]').off();
    if (isset(_eqLogic.configuration) && isset(_eqLogic.configuration.protocol)) {
        $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=' + _eqLogic.configuration.protocol + '.configuration', function () {
            $('body').setValues(_eqLogic, '.eqLogicAttr');
            //initCheckBox();
            $('.eqLogicAttr[data-l1key=configuration][data-l2key=protocol]').off().on('change', function () {
                $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=' + $(this).val() + '.configuration',function(){
                    //initCheckBox();
                });
            });
            modifyWithoutSave = false;
            $.hideLoading();
        });
    } else {
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=protocol]').on('change', function () {
            $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=' + $(this).val() + '.configuration',function(){
                //initCheckBox();
            });
        });
        $.hideLoading();
    }
}

//$("#table_cmd").delegate(".listEquipementInfo", 'click', function() {
//    var el = $(this);
//    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
//        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=request]');
//        calcul.atCaret('insert', result.human);
 //   });
//});
//$("#table_cmd").delegate(".listEquipementInfo1", 'click', function () {
//  var el = $(this);
//  jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
//    var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=parameters]');
//    calcul.atCaret('insert', result.human);
//  });
//});
$("#table_cmd").delegate(".listEquipementInfo", 'click', function () {
	var el = $(this);
	jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
		var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=request]');
		// définition de la structure du message 
		var message = '<div class="row">  ' +
			'<div class="col-md-12"> ' +
			'<form class="form-horizontal" onsubmit="return false;"> ' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >'+result.human+' {{=>}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="operator">' +
			'                    <option value="&">{{AND}}</option>' +
			'                  <option value="|">{{OR}}</option>' +
			'                  <option value="^">{{XOR}}</option>' +
			'            </select>' +
			'       </div>' +
			'      <div class="col-xs-4">' +
			'               <select class="conditionAttr form-control" data-l1key="operande">' +
			'					<optgroup label="Premier Octet">' +
			'                   <option value="1">{{Bit 1}}</option>' +
			'                  	<option value="2">{{Bit 2}}</option>' +
			'                  	<option value="4">{{Bit 3}}</option>' +	
			'                  	<option value="8">{{Bit 4}}</option>' +
			'                  	<option value="16">{{Bit 5}}</option>' +
			'                  	<option value="32">{{Bit 6}}</option>' +
			'                  	<option value="64">{{Bit 7}}</option>' +
			'                  	<option value="128">{{Bit 8}}</option>' +
			'					</optgroup>' +
			'					<optgroup label="Second Octet">' +
			'					<option value="256">{{Bit 1}}</option>' +
			'                  	<option value="512">{{Bit 2}}</option>' +
			'                  	<option value="1024">{{Bit 3}}</option>' +	
			'                  	<option value="2048">{{Bit 4}}</option>' +
			'                  	<option value="4096">{{Bit 5}}</option>' +
			'                  	<option value="8192">{{Bit 6}}</option>' +
			'                  	<option value="16384">{{Bit 7}}</option>' +
			'                  	<option value="32768">{{Bit 8}}</option>' +
			'					</optgroup>' +
			'            	</select>' +			
			'    </div>' +
			'</div>' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >{{Ensuite}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="next">' +
			'                    <option value="">rien</option>' +
			'            </select>' +
			'       </div>' +
			'</div>' +
			'</div> </div>' +
			'</form> </div>  </div>';
		bootbox.dialog({
			title: "{{Ajout d'un filtre }}",
			message: message,
			buttons: {
				"Ne rien mettre": {
					className: "btn-default",
					callback: function () {
						calcul.atCaret('insert', result.human);
					}
				},
				success: {
					label: "Valider",
					className: "btn-primary",
					callback: function () {
    					var condition = result.human;
						condition += ' ' + $('.conditionAttr[data-l1key=operator]').value();
						condition += ' ' + $('.conditionAttr[data-l1key=operande]').value();
						condition += ' ' + $('.conditionAttr[data-l1key=next]').value()+' ';
						calcul.atCaret('insert', condition);
						if($('.conditionAttr[data-l1key=next]').value() != ''){
							el.click();
						}
					}
				},
			}
		});
	});
});

$("#table_cmd").delegate(".listEquipementInfo1", 'click', function () {
	var el = $(this);
	jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
		var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=parameters]');
		// définition de la structure du message 
		var message = '<div class="row">  ' +
			'<div class="col-md-12"> ' +
			'<form class="form-horizontal" onsubmit="return false;"> ' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >'+result.human+' {{=>}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="operator">' +
			'                    <option value="&">{{AND}}</option>' +
			'                  <option value="|">{{OR}}</option>' +
			'                  <option value="^">{{XOR}}</option>' +
			'            </select>' +
			'       </div>' +
			'      <div class="col-xs-4">' +
			'               <select class="conditionAttr form-control" data-l1key="operande">' +
			'					<optgroup label="Premier Octet">' +
			'                   <option value="1">{{Bit 1}}</option>' +
			'                  	<option value="2">{{Bit 2}}</option>' +
			'                  	<option value="4">{{Bit 3}}</option>' +	
			'                  	<option value="8">{{Bit 4}}</option>' +
			'                  	<option value="16">{{Bit 5}}</option>' +
			'                  	<option value="32">{{Bit 6}}</option>' +
			'                  	<option value="64">{{Bit 7}}</option>' +
			'                  	<option value="128">{{Bit 8}}</option>' +
			'					</optgroup>' +
			'					<optgroup label="Second Octet">' +
			'					<option value="256">{{Bit 1}}</option>' +
			'                  	<option value="512">{{Bit 2}}</option>' +
			'                  	<option value="1024">{{Bit 3}}</option>' +	
			'                  	<option value="2048">{{Bit 4}}</option>' +
			'                  	<option value="4096">{{Bit 5}}</option>' +
			'                  	<option value="8192">{{Bit 6}}</option>' +
			'                  	<option value="16384">{{Bit 7}}</option>' +
			'                  	<option value="32768">{{Bit 8}}</option>' +
			'					</optgroup>' +
			'            	</select>' +			
			'    </div>' +
			'</div>' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >{{Ensuite}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="next">' +
			'                    <option value="">rien</option>' +
		//	'                  <option value="OU">{{ou}}</option>' +
			'            </select>' +
			'       </div>' +
			'</div>' +
			'</div> </div>' +
			'</form> </div>  </div>';
		bootbox.dialog({
			title: "{{Ajout d'un filtre }}",
			message: message,
			buttons: {
				"Ne rien mettre": {
					className: "btn-default",
					callback: function () {
						calcul.atCaret('insert', result.human);
					}
				},
				success: {
					label: "Valider",
					className: "btn-primary",
					callback: function () {
    					var condition = result.human;
						condition += ' ' + $('.conditionAttr[data-l1key=operator]').value();
						condition += ' ' + $('.conditionAttr[data-l1key=operande]').value();
						condition += ' ' + $('.conditionAttr[data-l1key=next]').value()+' ';
						calcul.atCaret('insert', condition);
						if($('.conditionAttr[data-l1key=next]').value() != ''){
							el.click();
						}
					}
				},
			}
		});
	});
});

$("#table_cmd").delegate(".listEquipementInfo2", 'click', function () {
	var el = $(this);
	var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=request]');
	var message = '<div class="row">  ' +
			'<div class="col-md-12"> ' +
			'<form class="form-horizontal" onsubmit="return false;"> ' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >{{=>}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="operator">' +
			'                    <option value="&">{{AND}}</option>' +
			'                  <option value="|">{{OR}}</option>' +
			'                  <option value="^">{{XOR}}</option>' +
			'            </select>' +
			'       </div>' +
			'      <div class="col-xs-4">' +
			'               <select class="conditionAttr form-control" data-l1key="operande">' +
			'					<optgroup label="Premier Octet">' +
			'                   <option value="1">{{Bit 1}}</option>' +
			'                  	<option value="2">{{Bit 2}}</option>' +
			'                  	<option value="4">{{Bit 3}}</option>' +	
			'                  	<option value="8">{{Bit 4}}</option>' +
			'                  	<option value="16">{{Bit 5}}</option>' +
			'                  	<option value="32">{{Bit 6}}</option>' +
			'                  	<option value="64">{{Bit 7}}</option>' +
			'                  	<option value="128">{{Bit 8}}</option>' +
			'					</optgroup>' +
			'					<optgroup label="Second Octet">' +
			'					<option value="256">{{Bit 1}}</option>' +
			'                  	<option value="512">{{Bit 2}}</option>' +
			'                  	<option value="1024">{{Bit 3}}</option>' +	
			'                  	<option value="2048">{{Bit 4}}</option>' +
			'                  	<option value="4096">{{Bit 5}}</option>' +
			'                  	<option value="8192">{{Bit 6}}</option>' +
			'                  	<option value="16384">{{Bit 7}}</option>' +
			'                  	<option value="32768">{{Bit 8}}</option>' +
			'					</optgroup>' +
			'            	</select>' +			
			'    </div>' +
			'</div>' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >{{Ensuite}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="next">' +
			'                    <option value="">rien</option>' +
		//	'                  <option value="OU">{{ou}}</option>' +
			'            </select>' +
			'       </div>' +
			'</div>' +
			'</div> </div>' +
			'</form> </div>  </div>';
		bootbox.dialog({
			title: "{{Ajout d'un filtre }}",
			message: message,
			buttons: {
				"Ne rien mettre": {
					className: "btn-default",
					callback: function () {
						return;
					}
				},
				success: {
					label: "Valider",
					className: "btn-primary",
					callback: function () {
    					var condition = '';
						condition += ' ' + $('.conditionAttr[data-l1key=operator]').value();
						condition += ' ' + $('.conditionAttr[data-l1key=operande]').value();
						condition += ' ' + $('.conditionAttr[data-l1key=next]').value()+' ';
						calcul.atCaret('insert', condition);
						if($('.conditionAttr[data-l1key=next]').value() != ''){
							el.click();
						}
					}
				},
			}
		});
	});

$("#bt_add_Action").on('click', function (event) {
  addCmdToTable({type: 'action'});
  modifyWithoutSave = true;
});

$("#bt_add_Info").on('click', function (event) {
  addCmdToTable({type: 'info'});
  modifyWithoutSave = true;
});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }

  	if (init(_cmd.type) == 'info') {
    	var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    	tr += '<td class="name">';
    	tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
    	tr += '<input class="cmdAttr form-control input-sm" data-l1key="name"></td>';
    	//tr += '<td class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType();
      	tr += '<td>';
    	tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom : 5px;" />';
   		tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    	tr += '</td>';
    	tr += '<td>';
		tr += '<div class="input-group" style="margin-bottom : 5px;">';
    	tr += '<select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="type"><option value="coils">Coil</option><option value="discrete_inputs">Discrete Input</option><option value="holding_registers">Holding Register</option><option value="input_registers">Input Register</option><option value="sign">Register Int</option><option value="virg">Register Float</option><option value="swapi32">Eastron Input</option></select>';
		tr += '</div>';
		tr += '<div class="input-group">';
		tr += '<select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="datatype"><option value="bitsize_16">16 bits</option><option value="bitsize_32">32 bits</option></select>';
		tr += '</div>';
		tr += '</td>';
		tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="location"></td>';
		tr += '<td>';
		tr += '<div class="input-group" style="margin-bottom : 5px;">';
		tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="configuration" data-l2key="request" placeholder="{{Option}}" />';
    	tr += '<span class="input-group-btn">';
    	tr += '<a class="btn btn-default btn-sm cursor listEquipementInfo2 roundedRight" data-input="configuration"><i class="fa fa-list-alt "></i></a>';
		tr += '</span>';
		tr += '</div>';
    	tr += '</td>';  
		tr += '<td>';
    	tr += '<input class="cmdAttr form-control tooltips input-sm" data-l1key="unite"  style="width : 100px;" placeholder="Unité" title="{{Unité}}">';
    	tr += '<input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="margin-top : 5px;">';
    	tr += '<input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="margin-top : 5px;">';
    	tr += '</td>';
    	tr += '<td>';
		if (is_numeric(_cmd.id)) {
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure" title="{{Configuration de la commande}}""><i class="fas fa-cogs"></i></a> ';
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"title="Tester"><i class="fas fa-rss"></i> </a>';
			tr += ' <a class="btn btn-default btn-xs cmdAction" data-action="copy" title="Dupliquer"><i class="far fa-clone"></i></a> ';
    	}
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr " data-l1key="isHistorized" data-size="mini"/>{{Historiser}}</label></span> ';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
    	tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span> ';
		tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"title="Supprimer"></i></td>';
    	tr += '</tr>';
    	$('#table_cmd tbody').append(tr);
    	var tr = $('#table_cmd tbody tr:last');
    	jeedom.eqLogic.builSelectCmd({
    	id:  $('.eqLogicAttr[data-l1key=id]').value(),
   		filter: {type: 'info'},
    	error: function (error) {
      	$('#div_alert').showAlert({message: error.message, level: 'danger'});
    	},
    	success: function (result) {
      	tr.find('.cmdAttr[data-l1key=value]').append(result);
      	tr.find('.cmdAttr[data-l1key=configuration][data-l2key=updateCmdId]').append(result);
      	tr.setValues(_cmd, '.cmdAttr');
      	jeedom.cmd.changeType(tr, init(_cmd.subType));
    	}
  		});
    }
    if (init(_cmd.type) == 'action') {
      	var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    	tr += '<td class="name">';
    	tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
    	tr += '<input class="cmdAttr form-control input-sm" data-l1key="name"></td>';
    	//tr += '<td class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType();
    	//tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span></td>';
      	tr += '<td>';
    	tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom : 5px;" />';
   		tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    	tr += '</td>';
    	tr += '<td><select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="type"><option value="coils">Coil</option><option value="discrete_inputs">Discrete Input</option><option value="holding_registers">Holding Register</option><option value="Write_Multiple_Holding">Write Multiple Holding</option><option value="input_registers">Input Register</option></select></td>'
    	tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="location"></td>';
		tr += '<td>';
    	tr += '<div class="input-group" style="margin-bottom : 5px;">';
    	tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="configuration" data-l2key="request" placeholder="{{Valeur}}"/>';
    	tr += '<span class="input-group-btn">';
    	tr += '<a class="btn btn-default btn-sm cursor listEquipementInfo roundedRight" data-input="configuration"><i class="fa fa-list-alt "></i></a>';
    	tr += '</span>';
    	tr += '</div>';
    	tr += '<div class="input-group">';
    	tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="configuration" data-l2key="parameters" placeholder="{{Valeur de retour }}" />';
    	tr += '<span class="input-group-btn">';
    	tr += '<a class="btn btn-default btn-sm cursor listEquipementInfo1 roundedRight" data-input="configuration"><i class="fa fa-list-alt "></i></a>';
    	tr += '</span>';
    	tr += '</div>';
    	tr += '</td>';  
		tr += '<td>';
    	tr += '<input class="cmdAttr form-control tooltips input-sm" data-l1key="unite"  style="width : 100px;" placeholder="Unité" title="{{Unité}}">';
    	tr += '<input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="margin-top : 5px;">';
    	tr += '<input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="margin-top : 5px;">';
    	tr += '</td>';
    	tr += '<td>';
		if (is_numeric(_cmd.id)) {
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure" title="{{Configuration de la commande}}""><i class="fas fa-cogs"></i></a> ';
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"title="Tester"><i class="fas fa-rss"></i> </a>';
			tr += ' <a class="btn btn-default btn-xs cmdAction" data-action="copy" title="Dupliquer"><i class="far fa-clone"></i></a> ';
    	}
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr " data-l1key="isHistorized" data-size="mini" />{{Historiser}}</label></span> ';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
    	tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span> ';
		tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"title="Supprimer"></i></td>';
    	tr += '</tr>';
    	$('#table_cmd tbody').append(tr);
    	var tr = $('#table_cmd tbody tr:last');
    	jeedom.eqLogic.builSelectCmd({
    	id:  $('.eqLogicAttr[data-l1key=id]').value(),
   		filter: {type: 'info'},
    	error: function (error) {
      	$('#div_alert').showAlert({message: error.message, level: 'danger'});
    	},
    	success: function (result) {
      	tr.find('.cmdAttr[data-l1key=value]').append(result);
      	tr.find('.cmdAttr[data-l1key=configuration][data-l2key=updateCmdId]').append(result);
      	tr.setValues(_cmd, '.cmdAttr');
      	jeedom.cmd.changeType(tr, init(_cmd.subType));
    	}
  		});
    }
}