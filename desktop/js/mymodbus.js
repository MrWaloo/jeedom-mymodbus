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

// *********** Namespace
MyModbus_ext = {}

// Send ajax request to MyModbus plugin
MyModbus_ext.callPluginAjax = function(_params) {
	domUtils.ajax({
		async: _params.async == undefined ? true : _params.async,
		global: false,
		type: "POST",
		url: "plugins/mymodbus/core/ajax/mymodbus.ajax.php",
		data: _params.data,
		dataType: 'json',
		error: function (request, status, error) {
			domUtils.handleAjaxError(request, status, error);
		},
		success: function (data) {
			if (data.state != 'ok') {
				jeedomUtils.showAlert({message: data.result, level: 'danger'});
			}
			else {
				if (typeof _params.success === 'function') {
					_params.success(data.result);
				}
			}
		}
	});
}

MyModbus_ext.getEqId = function() {
	const element = document.querySelector('.eqLogicAttr[data-l1key="id"]');
	return element ? element.value : null;
}

MyModbus_ext.cmdIDStyle = '';

// *********** Evénements de la page du plugin

document.querySelector('.eqLogicThumbnailContainer').addEventListener('click', function(event) {
	if (event.target.closest('#bt_addMymodbusEq')) {
		let prompt_message = '<label class="control-label">{{Nom du nouvel équipement :}}</label>';
		prompt_message += '<input class="promptAttr" autocomplete="off" type="text" data-l1key="name"><br>';
		prompt_message += '<label class="control-label">{{Utiliser un template :}}';
		prompt_message += '	<select class="promptAttr" data-l1key="template">';
		prompt_message += '	 <option value="">{{Aucun}}</option>';
		MyModbus_ext.callPluginAjax({
			data: {
				action: "getTemplateList",
			},
			async: false,
			error: function(error) {},
			success: function (dataresult) {
				for (var i in dataresult) {
					prompt_message += '  <option value="' + dataresult[i][0] + '">' + dataresult[i][0] + '</option>';
				}
			}
		});
		prompt_message += '	</select>';
		prompt_message += '</label>';
		jeeDialog.prompt({
			title: '{{Ajouter un nouvel équipement MyModbus}}',
			message: prompt_message,
			inputType: false,
			callback: function(result) {
				if (typeof result === 'object' && result !== null && result.name != '') {
					jeedom.eqLogic.save({
						type: eqType,
						eqLogics: [{
							name: result.name
						}],
						error: function(error) {
							jeedomUtils.showAlert({
								message: error.message,
								level: 'danger'
							})
						},
						success: function(savedEq) {
							var success = false;
							if (result.template != '') {
								MyModbus_ext.callPluginAjax({
									data: {
										action: 'applyTemplate',
										id: savedEq.id,
										templateName : result.template,
										keepCmd: false
									},
									async: false,
									success: function () {
										success = true;
									}
								});
							}
							if (result.template != '' && success || result.template == '') {
								var vars = getUrlVars();
								var url = 'index.php?';
								for (var i in vars) {
									if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull') {
										url += i + '=' + vars[i].replace('#', '') + '&';
									}
								}
								jeeFrontEnd.modifyWithoutSave = false;
								modifyWithoutSave = jeeFrontEnd.modifyWithoutSave;
								url += 'id=' + savedEq.id + '&saveSuccessFull=1';
								jeedomUtils.loadPage(url);
							}
						}
					});
				}
			}
		});
		return;
	}

	if (event.target.closest('#bt_healthmymodbus')) {
		jeeDialog.dialog({
			id: 'modal_healthmymodbus',
			title: '{{Santé MyModbus}}',
			height: '85%',
			contentUrl: 'index.php?v=d&plugin=' + eqType + '&modal=health'
		});
		return;
	}

	if (event.target.closest('#bt_templatesMymodbus')) {
		jeeDialog.dialog({
			id: 'modal_templatesMymodbus',
			title: '{{Gestion des templates d\'équipement MyModbus}}',
			height: '85%',
			contentUrl: 'index.php?v=d&plugin=' + eqType + '&modal=templates'
		});
		return;
	}

	if (event.target.closest('#bt_move_cmd')) {
		jeeDialog.dialog({
			id: 'modal_move_cmd',
			title: '{{Déplacement des commandes MyModbus}}',
			height: '85%',
			contentUrl: 'index.php?v=d&plugin=' + eqType + '&modal=move_cmd'
		});
		return;
	}
});

// *********** Evénements de la page d'édition d'un équipement

document.querySelector('#eqLogicActions').addEventListener('click', function(event) {
	if (event.target.closest('.eqLogicAction[data-action="createTemplate"]')) {
		jeeDialog.prompt(
			'{{Nom du nouveau template ?}}',
			function (result) {
				if (result !== null) {
					MyModbus_ext.callPluginAjax({
						data: {
							action: 'createTemplate',
							id: MyModbus_ext.getEqId(),
							name : result
						}
					});
				}
			}
		);
		return;
	}
	
	if (event.target.closest('.eqLogicAction[data-action="applyTemplate"]')) {
		MyModbus_ext.callPluginAjax({
			data: {
				action: 'getTemplateList',
			},
			async: false,
			success: function (dataresult) {
				let prompt_message = '<label class="control-label">{{Choisissez un template :}}</label>';
				prompt_message += '	<select class="promptAttr" data-l1key="template">';
				
				for(var i in dataresult) {
					prompt_message += '<option value="' + dataresult[i][0] + '">' + dataresult[i][0] + '</option>';
				}
				prompt_message += '</select>'; // <br>
				
				prompt_message += '<label class="control-label">{{Que voulez-vous faire des commandes existantes ?}}</label> ';
				prompt_message += '	<select class="promptAttr" data-l1key="optionKeepCmd">';
				prompt_message += '  <option value="0">{{Les supprimer d\'abord}}</option>';
				prompt_message += '  <option value="1">{{Les conserver / Mettre à jour}}</option>';
				prompt_message += '</select>';

				jeeDialog.prompt({
					title: '{{Appliquer un Template}}',
					message: prompt_message,
					inputType: false,
					callback: function(result) {
						if (result !== null) {
							MyModbus_ext.callPluginAjax({
								data: {
									action: 'applyTemplate',
									id: MyModbus_ext.getEqId(),
									templateName: result.template,
									keepCmd: result.optionKeepCmd
								},
								success: function (dataresult) {
									window.location.reload();
								}
							});
						}
					}
				});
			}
		});
		return;
	}
});

function handleProtocolChange(event) {
	const value = event.target.value;

	const show_shared = (value === 'shared_from');
	const show_network = (value !== 'serial');
	const sharedInterface = document.getElementById('div_sharedInterface');
	const protocolParameters = document.getElementById('div_protocolParameters');
	const networkConfig = document.querySelector('#div_protocolParameters .networkConfig');
	const serialConfig = document.querySelector('#div_protocolParameters .serialConfig');
	if (show_shared) {
		sharedInterface.seen();
		protocolParameters.unseen();
	} else {
		sharedInterface.unseen();
		protocolParameters.seen();
		if (show_network) {
			networkConfig.seen();
			serialConfig.unseen();
		} else {
			networkConfig.unseen();
			serialConfig.seen();
		}
	}
}

function handleeqUniqueIdChange(event) {
	const value = event.target.value;
	MyModbus_ext.cmdIDStyle = value !== '' ? 'display:none;' : '';

	// Mettre à jour le style de tous les champs ID des commandes
	const cmdDevIDElements = document.querySelectorAll('.cmdAttr[data-l1key="configuration"][data-l2key="cmdDevID"]');
	cmdDevIDElements.forEach(function(cmdDevIDEl) {
		if (MyModbus_ext.cmdIDStyle == '') {
			cmdDevIDEl.seen();
		} else {
			cmdDevIDEl.value = '';
			cmdDevIDEl.unseen();
		}
	});
}

function printEqLogic(_eqLogic) {
	
	// Afficher la partie variable de la configuration de l'équipement en fonction du protocole choisi
	$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqProtocol]').off().on('change', function () {
		if ($(this).val() != '' && !is_null($(this).val())) {
			var show_shared = ($(this).val() === 'shared_from');
			var show_network = ($(this).val() !== 'serial');
			const sharedInterface = $('#div_sharedInterface');
			const protocolParameters = $('#div_protocolParameters');
			const networkConfig = $('#div_protocolParameters .networkConfig');
			const serialConfig = $('#div_protocolParameters .serialConfig');
			if (show_shared) {
				sharedInterface.show();
				protocolParameters.hide();
			} else {
				sharedInterface.hide();
				protocolParameters.show();
				if (show_network) {
					networkConfig.show();
					serialConfig.hide();
				} else {
					networkConfig.hide();
					serialConfig.show();
				}
			}
		}
	});

	const selectElement = document.getElementById('sharedInterface');
	for (let i = 0; i < selectElement.options.length; i++) {
		if (selectElement.options[i].value === _eqLogic.id) {
			selectElement.options[i].classList.add('hidden');
		} else {
			selectElement.options[i].classList.remove('hidden');
		}
	}

	const div_eqlogicId = document.getElementById('eqlogicId_in_tab');
	let html_eqId = '<i class="fas fa-tachometer-alt"></i> {{Equipement}} (ID: ' + _eqLogic.id.toString() + ')';
	div_eqlogicId.innerHTML = html_eqId;

	// load values
	$('#eqLogic').setValues(_eqLogic, '.eqLogicAttr');
}

$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqRefreshMode]').off().on('change', function () {
	eqConfig_visibility();
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqRegTest]').off().on('change', function () {
	eqConfig_visibility();
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqOneDevID]').off().on('change', function () {
	eqConfig_visibility();
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqRegTestFunction]').off().on('change', function () {
	eqConfig_visibility();
});

function eqConfig_visibility() {
	// Met à jour la visibilité des éléments en fonction des sélections
	let $eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
	let $eqRefreshMode = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqRefreshMode]').value();
	let $eqRegTest = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqRegTest]').value();
	let $eqOneDevID = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqOneDevID]').value();
	let $eqRegTestFunction = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqRegTestFunction]').value();

	if ($eqRegTest == '0') {
		$('#div_RegTestParameters').hide();
		$('.btn_add_command').show();
		$('.noRegTest').show();
	} else {
		$('#div_RegTestParameters').show();
		$('.btn_add_command').hide();
		$('.noRegTest').hide();
	}

	if ($eqLogicId != '') {
		jeedom.eqLogic.byId({
			id: $eqLogicId,
			async: false,
			success: function(eqLogic) {
				if (eqLogic.configuration.eqProtocol === 'shared_from') {
					jeedom.eqLogic.byId({
						id: eqLogic.configuration.eqInterfaceFromEqId,
						async: false,
						success: function(eqLogic2) {
							if (eqLogic2.configuration.eqOneDevID == '1') {
								$('.colDevID').hide();
							} else {
								$('.colDevID').show();
							}
						}
					});
				}
			}
		});
	}

	if ($eqOneDevID == '1') {
		if ($eqLogicId != '') {
			$('.colDevID').hide();
		}
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqDevID]').prop('disabled', false);
	} else {
		if ($eqLogicId != '') {
			$('.colDevID').show();
		}
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqDevID]').prop('disabled', true);
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqDevID]').prop('value', '');
	}

	if ($eqRegTestFunction != '' && !is_null($eqRegTestFunction)) {
		var show_num = ($eqRegTestFunction === '3' || $eqRegTestFunction === '4');
		if (show_num) {
			$('.formatTestBin').hide();
			$('.formatTestNum').show();
		} else {
			$('.formatTestBin').show();
			$('.formatTestNum').hide();
		}
		var eqRegTestFormat = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqRegTestFormat]');
		selectFirstVisible(eqRegTestFormat);
	}

	if ($eqRefreshMode == 'polling') {
		$('#eqPolling').show();
	} else {
		$('#eqPolling').hide();
	}
}

// Génère la liste déroulante de choix du bit dans deux octets
var bitSelect = 
			'				<div class="col-xs-4">' +
			'					<select class="conditionAttr form-control" data-l1key="operande">' +
			'						<optgroup label="{{Premier Octet}}">';
for (let i = 0; i < 16; i++) {
	if (i == 8) {
		bitSelect +=
			'						</optgroup>' +
			'						<optgroup label="{{Second Octet}}">';
	}
	bitSelect += '							<option value="' + 2**i + '">Bit ' + i % 8 + '</option>';
}
bitSelect += 
			'						</optgroup>' +
			'					</select>' +
			'				</div>';

$("#table_cmd").delegate(".paramFiltre", 'click', function () {
	var el = $(this);
	var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=cmdOption]');
	var message = '<div class="row">	' +
			'	<div class="col-md-12"> ' +
			'		<form class="form-horizontal" onsubmit="return false;"> ' +
			'			<div class="form-group"> ' +
			'				<label class="col-xs-5 control-label">{{Filtrer sur :}}</label>' +
			bitSelect +
			'			</div>' +
			'		</form>' +
			'	</div>' +
			'</div>';
	bootbox.dialog({
		title: "{{Ajout d'un filtre}}",
		message: message,
		buttons: {
			"{{Annuler}}": {
				className: "btn-default",
				callback: function () {
					return;
				}
			},
			success: {
				label: "{{Valider}}",
				className: "btn-primary",
				callback: function () {
					var condition = '#value# & ' + $('.conditionAttr[data-l1key=operande]').value();
					calcul.atCaret('insert', condition);
				}
			},
		}
	});
});

$("#table_cmd").delegate(".listEquipementInfo", 'click', function () {
	var el = $(this);
	jeedom.cmd.getSelectModal({ cmd: { type: 'info' } }, function (result) {
		var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']');
		calcul.atCaret('insert', result.human);
	})
})

// inspired from jeedom.eqLogic.buildSelectCmd
listSourceBlobs = function(_params) {
	jeedom.eqLogic.getCmd({
		id: _params.id,
		async: false,
		success: function(cmds) {
			var resultBin = '';
			var resultNum = '';
			for (var i in cmds) {
				if (cmds[i].configuration.cmdFormat === 'blob') {
					resultBin += '<option value="' + cmds[i].id + '">' + cmds[i].name + '</option>';
					if (cmds[i].subType !== 'binary') {
						resultNum += '<option value="' + cmds[i].id + '">' + cmds[i].name + '</option>';
					}
				}
			}
			if (typeof(_params.success) == 'function') {
				_params.success(resultBin, resultNum);
			}
		}
	});
}
listSourceValues = function(_params) {
	jeedom.eqLogic.getCmd({
		id: _params.id,
		async: false,
		success: function(cmds) {
			var result = '';
			for (var i in cmds) {
				if (cmds[i].type === 'info' && cmds[i].configuration.cmdFormat != 'blob' && cmds[i].logicalId === '') {
					result += '<option value="' + cmds[i].id + '">' + cmds[i].name + '</option>';
				}
			}
			if (typeof(_params.success) == 'function') {
				_params.success(result);
			}
		}
	});
}

function actualise_visible(me, source, _template = false) {
	if (source !== 'first call') {
		modifyWithoutSave = true;
	}
	//var cmdName = $(me).closest('tr').find('.cmdAttr[data-l1key=name]').value();
	//console.log(cmdName + ' *-*-*-*-*-*-*-*-*-* ' + source);
	var cmdLogicalId = $(me).closest('tr').find('.cmdAttr[data-l1key=logicalId]').value();
	var cmdType = $(me).closest('tr').find('.cmdAttr[data-l1key=type]').value();
	var subType = $(me).closest('tr').find('.cmdAttr[data-l1key=subType]').value();
	var cmdFctModbusEl = $(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=cmdFctModbus]');
	var cmdFctModbus = $(cmdFctModbusEl).value();
	var cmdFormatEl = $(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=cmdFormat]');
	var cmdFormat = $(cmdFormatEl).value();
	var show_invertSetting = false;
	
	$(me).closest('tr').find('.formatNum').hide();
	$(me).closest('tr').find('.formatBin').hide();
	$(me).closest('tr').find('.FctBlobBin').hide();
	$(me).closest('tr').find('.FctBlobNum').hide();
	$(me).closest('tr').find('.notFctBlob').hide();
	$(me).closest('tr').find('.invertSetting').hide();
	$(me).closest('tr').find('.readFunction').hide();
	$(me).closest('tr').find('.writeFunction').hide();
	$(me).closest('tr').find('.readBin').hide();
	$(me).closest('tr').find('.readNum').hide();
	$(me).closest('tr').find('.withDevID').hide();
	$(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=listValue]').hide();
	
	if (_template || cmdLogicalId == '') { // without a logicalId
		if (cmdFctModbus != 'fromBlob') {
			$(me).closest('tr').find('.withDevID').show();
		}
		
		if (cmdType == 'info') {
			$(me).closest('tr').find('.readFunction').show();
			if (subType == 'binary') {
				$(me).closest('tr').find('.readBin').show();
				$(me).closest('tr').find('.readNum').show();
				if (cmdFctModbus == '1' || cmdFctModbus == '2') {
					$(me).closest('tr').find('.formatBin').show();
				} else {
					$(me).closest('tr').find('.formatNum').show();
				}
			} else {
				$(me).closest('tr').find('.readNum').show();
				$(me).closest('tr').find('.formatNum').show();
			}
			if (cmdFctModbus != 'fromBlob') {
				$(me).closest('tr').find('.notFctBlob').show();
			} else {
				if (subType == 'binary') {
					$(me).closest('tr').find('.FctBlobBin').show();
					$(me).closest('tr').find('.formatBin').show();
					$(me).closest('tr').find('.formatNum').show();
				} else {
					$(me).closest('tr').find('.FctBlobNum').show();
				}
			}
			if (cmdFormat != 'blob') {
				show_invertSetting = true;
			}
			
		} else { // action
			$(me).closest('tr').find('.writeFunction').show();
			if (cmdFctModbus == '5' || cmdFctModbus == '15') {
				$(me).closest('tr').find('.formatBin').show();
			} else {
				$(me).closest('tr').find('.formatNum').show();
			}
			if (subType == 'select') {
				$(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=listValue]').show();
			}
			show_invertSetting = true;
		}

		if (show_invertSetting) {
			$(me).closest('tr').find('.invertSetting').show();
			var format64bit = ['q', 'Q', 'd', 's', 'blob'];
			var format32bit = format64bit.concat(['i', 'I', 'f', 'i_sf', 'I_sf']);
			if (format32bit.includes(cmdFormat)) {
				$(me).closest('tr').find('.invertWords').show();
			} else {
				$(me).closest('tr').find('.invertWords').hide();
			}
			if (format64bit.includes(cmdFormat)) {
				$(me).closest('tr').find('.invertDWords').show();
			} else {
				$(me).closest('tr').find('.invertDWords').hide();
			}
		}
		
		selectFirstVisible(cmdFctModbusEl);
		selectFirstVisible(cmdFormatEl);
		
	} else { // with a logicalId
		$(me).closest('tr').find('.input-group').hide();
		$(me).closest('tr').find('.cmdAction[data-action=copy]').hide();
		$(me).closest('tr').find('.cmdAttr[data-l1key=name]').prop('disabled', true);
		
		$(me).closest('tr').find('.cmdAttr[data-l1key=value').hide();
	}
}

function selectFirstVisible(selectEl) {
	var firstVisibleOption = null;
	var wrongSelection = false;
	selectEl.find('option').each(function() {
		var option = $(this);
		var visible = option[0].style.display !== "none";
		if (option.is(':selected') && !visible) {
			wrongSelection = true;
		}
		if (visible && firstVisibleOption === null) {
			firstVisibleOption = option.value();
		}
	});
	if (wrongSelection) {
		$(selectEl).val(firstVisibleOption).change();
	}
}

function getTrfromCmd(_cmd, _template = false) {
	let formDisabled = (_template) ? ' disabled' : '';
	// id de la commande
	let dataCmdId = (!_template) ? 'data-cmd_id="' + init(_cmd.id) : '';
	let colDevIDStyle = '';
	if (typeof _cmd.eqLogic_id !== 'undefined' && _cmd.eqLogic_id !== null) {
		jeedom.eqLogic.byId({
			id: _cmd.eqLogic_id,
			async: false,
			success: function(eqLogic) {
				if (eqLogic.configuration.eqProtocol === 'shared_from') {
					jeedom.eqLogic.byId({
						id: eqLogic.configuration.eqInterfaceFromEqId,
						async: false,
						success: function(eqLogic2) {
							if (eqLogic2.configuration.eqOneDevID == '1') {
								colDevIDStyle = ' style="display:none;"';
							}
						}
					});
				} else if (eqLogic.configuration.eqOneDevID == '1') {
					colDevIDStyle = ' style="display:none;"';
				}
			}
		});
	} else {
		colDevIDStyle = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqOneDevID]').value() == '0' ? '' : ' style="display:none;"';
	}
	let tr = '<tr class="cmd" ' + dataCmdId + '">';
	if (!_template) {
		tr += ' <td class="hidden-xs">'
		tr += '	<span class="cmdAttr" data-l1key="id" disabled></span>'
		tr += '	<span class="cmdAttr" data-l1key="logicalId" hidden></span>'
		tr += ' </td>'
	}
	// Nom
	tr += ' <td class="name">';
	tr += '	<input class="cmdAttr form-control input-sm" data-l1key="name"' + formDisabled + '>';
	tr += '	<select class="cmdAttr form-control input-sm" data-l1key="value" style="display : none;margin-top : 5px;" title="{{Commande info liée}}"' + formDisabled + '>';
	tr += '		<option value="">Aucune</option>';
	tr += '	</select>';
	tr += ' </td>';
	// Valeur
	if (!_template) {
		tr += ' <td>';
		tr += '	<span class="cmdAttr" data-l1key="htmlstate"></span>';
		tr += ' </td>';
	}
	// Type
	tr += ' <td>';
	tr += '	<div class="input-group">';
	tr += '		<span class="type" id="' + init(_cmd.type) + '" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
	tr += '		<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
	tr += '	</div>';
	tr += ' </td>';
	// ID du serveur
	tr += ' <td class="colDevID"' + MyModbus_ext.cmdIDStyle + '><input type="number" class="cmdAttr form-control input-sm withDevID" data-l1key="configuration" data-l2key="cmdDevID"' + formDisabled + '></td>';
	// Modbus function / Data format
	tr += ' <td>';
	tr += '	<div class="input-group" style="margin-bottom:5px;">';
	tr += '		<select class="cmdAttr form-control input-sm" style="width:230px;" data-l1key="configuration" data-l2key="cmdFctModbus"' + formDisabled + '>';
	tr += '			<option class="readBin" value="1">[0x01] Read coils</option>';
	tr += '			<option class="readBin" value="2">[0x02] Read discrete inputs</option>';
	tr += '			<option class="readNum" value="3">[0x03] Read holding registers</option>';
	tr += '			<option class="readNum" value="4">[0x04] Read input registers</option>';
	tr += '			<option class="writeFunction" value="5">[0x05] Write single coil</option>';
	tr += '			<option class="writeFunction" value="15">[0x0F] Write coils</option>';
	tr += '			<option class="writeFunction" value="6">[0x06] Write register</option>';
	tr += '			<option class="writeFunction" value="16">[0x10] Write registers</option>';
	tr += '			<option class="readFunction" value="fromBlob">{{Depuis une plage de registres}}</option>';
	tr += '		</select>';
	tr += '	</div>';
	tr += '	<div class="input-group">';
	tr += '		<select class="cmdAttr form-control input-sm" style="width:230px;" data-l1key="configuration" data-l2key="cmdFormat"' + formDisabled + '>';
	tr += '			<option class="formatBin" value="bit">bit (0 / 1)</option>';
	tr += '			<optgroup class="formatNum" label="8 bits">';
	tr += '				<option class="formatNum" value="uint8">uint8 LSB (0 ... 255)</option>';
	tr += '				<option class="formatNum" value="uint8-msb">uint8 MSB (0 ... 255)</option>';
	tr += '			</optgroup>';
	tr += '			<optgroup class="formatNum" label="16 bits">';
	tr += '				<option class="formatNum" value="h">int16 (-32 768 ... 32 767)</option>';
	tr += '				<option class="formatNum" value="H">uint16 (0 ... 65 535)</option>';
	tr += '			</optgroup>';
	tr += '			<optgroup class="formatNum" label="32 bits ({{2 registres}})">';
	tr += '				<option class="formatNum" value="i">int32 (-2 147 483 648 ... 2 147 483 647)</option>';
	tr += '				<option class="formatNum" value="I">uint32 (0 ... 4 294 967 295)</option>';
	tr += '				<option class="formatNum" value="f">float32 (Real 32bit)</option>';
	tr += '			</optgroup>';
	tr += '			<optgroup class="formatNum" label="64 bits ({{4 registres}})">';
	tr += '				<option class="formatNum" value="q">int64 (-9e18 ... 9e18)</option>';
	tr += '				<option class="formatNum" value="Q">uint64 (0 ... 18e18)</option>';
	tr += '				<option class="formatNum" value="d">float64 (Real 64bit)</option>';
	tr += '			</optgroup>';
	tr += '			<option class="formatNum" value="s">{{Chaine de caractères}}</option>';
	tr += '			<option class="notFctBlob" value="blob">{{Plage de registres}}</option>';
	tr += '			<optgroup class="formatNum" label="{{Spécial}}">';
	tr += '				<option class="formatNum" value="h_sf">{{SunSpec scale factor int16}}</option>';
	tr += '				<option class="formatNum" value="H_sf">{{SunSpec scale factor uint16}}</option>';
	tr += '				<option class="formatNum" value="i_sf">{{SunSpec scale factor int32}}</option>';
	tr += '				<option class="formatNum" value="I_sf">{{SunSpec scale factor uint32}}</option>';
	tr += '			</optgroup>';
	tr += '		</select>';
	tr += '	</div>';
	tr += ' </td>';
	// Adresse Modbus
	tr += ' <td>';
	tr += '	<div class="input-group" style="width:100%;">';
	tr += '		<select class="cmdAttr form-control input-sm FctBlobBin" style="width:100%;" data-l1key="configuration" data-l2key="cmdSourceBlobBin"' + formDisabled + '>';
	tr += '		</select>';
	tr += '		<select class="cmdAttr form-control input-sm FctBlobNum" style="width:100%;" data-l1key="configuration" data-l2key="cmdSourceBlobNum"' + formDisabled + '>';
	tr += '		</select>';
	tr += '		<input class="cmdAttr form-control input-sm" style="margin-top:5px;" data-l1key="configuration" data-l2key="cmdAddress"' + formDisabled + '/>';
	tr += '		<label class="checkbox-inline invertSetting">';
	tr += '			<input type="checkbox" class="cmdAttr checkbox-inline tooltips" data-l1key="configuration" data-l2key="cmdInvertBytes"' + formDisabled + '/>{{Inverser octets}}';
	tr += '		</label></br>';
	tr += '		<label class="checkbox-inline invertSetting invertWords">';
	tr += '			<input type="checkbox" class="cmdAttr checkbox-inline tooltips" data-l1key="configuration" data-l2key="cmdInvertWords"' + formDisabled + '/>{{Inverser mots}}';
	tr += '		</label></br>';
	tr += '		<label class="checkbox-inline invertSetting invertDWords">';
	tr += '			<input type="checkbox" class="cmdAttr checkbox-inline tooltips" data-l1key="configuration" data-l2key="cmdInvertDWords"' + formDisabled + '/>{{Inverser double-mots}}';
	tr += '		</label></br>';
	tr += '	</div>';
	tr += ' </td>';
	// Paramètre
	tr += ' <td>';
	tr += '	<div class="input-group">';
	tr += '		<input class="cmdAttr form-control input-sm roundedLeft readFunction" data-l1key="configuration" data-l2key="cmdOption" placeholder="{{Option}}"' + formDisabled + '/>';
	if (!_template) {
		tr += '		<span class="input-group-btn">';
		tr += '			<a class="btn btn-default btn-sm cursor paramFiltre roundedRight readFunction" data-input="configuration"><i class="fa fa-list-alt"></i></a>';
		tr += '		</span>';
	}
	tr += '	</div>';
	tr += '	<div class="input-group notFctBlob">';
	tr += '		<label class="label">{{Lecture 1x sur :}}&nbsp;';
	tr += '			<input type="number" class="cmdAttr form-inline input-sm" style="width:70px;" data-l1key="configuration" data-l2key="cmdFrequency" placeholder="{{1 par défaut}}"' + formDisabled + '/>';
	tr += '		</label>';
	tr += '	</div>';
	tr += '	<div class="input-group" style="width:100%;">';
	tr += '		<input class="cmdAttr form-control input-sm roundedLeft writeFunction" data-l1key="configuration" data-l2key="cmdWriteValue" placeholder="{{Valeur}}"' + formDisabled + '/>';
	if (!_template) {
		tr += '		<span class="input-group-btn">'
		tr += '			<a class="btn btn-default btn-sm listEquipementInfo roundedRight writeFunction" data-input="cmdWriteValue"><i class="fas fa-list-alt"></i></a>'
		tr += '		</span>'
	}
	tr += '	</div>';
	tr += ' </td>';		
	// Options
	tr += ' <td>';
	if (!_template && is_numeric(_cmd.id)) {
		tr += '	<a class="btn btn-default btn-xs cmdAction" data-action="configure" title="{{Configuration de la commande}}""><i class="fas fa-cogs"></i></a>';
		tr += '	<a class="btn btn-default btn-xs cmdAction" data-action="test" title="{{Tester}}"><i class="fas fa-rss"></i></a>';
		tr += '	<a class="btn btn-default btn-xs cmdAction" data-action="copy" title="{{Dupliquer}}"><i class="far fa-clone"></i></a>';
	}
	tr += '	<label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked' + formDisabled + '/>{{Afficher}}</label>';
	tr += '	<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" data-size="mini"' + formDisabled + '/>{{Historiser}}</label>';
	tr += '	<div class="input-group" style="margin-top:7px;">';
	tr += '		<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;" type="number"' + formDisabled + '/>';
	tr += '		<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;" type="number"' + formDisabled + '/>';
	tr += '		<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;"' + formDisabled + '/>';
	tr += '		<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de \'valeur|texte\' séparés par \';\'}}" title="{{Liste}}" style="min-width:280px;width:290px;margin-right:2px;"' + formDisabled + '>';
	tr += '	</div>';
	tr += ' </td>';
	// Delete button
	if (!_template) {
		tr += ' <td>';
		tr += '	<div class="input-group">';
		tr += '		<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer}}"></i>';
		tr += '	</div>';
		tr += ' </td>';
	}
	tr += '</tr>';

	return tr;
}

$("#bt_add_command").on('click', function (event) {
	addCmdToTable({});
	modifyWithoutSave = true;
});

function addCmdToTable(_cmd) {
	// Minimal structure for _cmd
	if (!isset(_cmd)) {
		var _cmd = {configuration: {}};
	}
	if (!isset(_cmd.configuration)) {
		_cmd.configuration = {};
	}
	// Conversion from the old version of MyModbus
	// ****************************************** info
	if (init(_cmd.type) == 'info') {
		if (isset(_cmd.configuration.location) && !isset(_cmd.configuration.cmdAddress)) {
			_cmd.configuration.cmdAddress = _cmd.configuration.location;
			delete _cmd.configuration.location;
			modifyWithoutSave = true;
		}
		if (isset(_cmd.configuration.request) && !isset(_cmd.configuration.cmdOption)) {
			_cmd.configuration.cmdoption = '#value# ' + _cmd.configuration.request;
			delete _cmd.configuration.request;
			modifyWithoutSave = true;
		}
		if (isset(_cmd.configuration.type) && init(_cmd.configuration.type) != '' &&
				!isset(_cmd.configuration.cmdFctModbus) && !isset(_cmd.configuration.cmdFormat)) {
			if (init(_cmd.configuration.type) == 'coils') {
				_cmd.configuration.cmdFctModbus = '1';
				_cmd.configuration.cmdFormat = 'bit';
			} else if (init(_cmd.configuration.type) == 'discrete_inputs') {
				_cmd.configuration.cmdFctModbus = '2';
				_cmd.configuration.cmdFormat = 'bit';
			} else if (init(_cmd.configuration.type) == 'holding_registers') {
				_cmd.configuration.cmdFctModbus = '3';
				_cmd.configuration.cmdFormat = 'H';
			} else if (init(_cmd.configuration.type) == 'input_registers') {
				_cmd.configuration.cmdFctModbus = '4';
				_cmd.configuration.cmdFormat = 'H';
			} else if (init(_cmd.configuration.type) == 'sign') {
				_cmd.configuration.cmdFctModbus = '3';
				_cmd.configuration.cmdFormat = 'h';
			} else if (init(_cmd.configuration.type) == 'virg') {
				_cmd.configuration.cmdFctModbus = '3';
				_cmd.configuration.cmdFormat = 'f';
			} else if (init(_cmd.configuration.type) == 'swapi32') {
				_cmd.configuration.cmdFctModbus = '4';
				_cmd.configuration.cmdFormat = 'f';
			}
			
			delete _cmd.configuration.type;
		}
		// was never used
		delete _cmd.configuration.datatype;
		modifyWithoutSave = true;
		
	// ****************************************** action
	} else if (init(_cmd.type) == 'action') {
		if (isset(_cmd.configuration.location) && !isset(_cmd.configuration.cmdAddress)) {
			_cmd.configuration.cmdAddress = _cmd.configuration.location;
			delete _cmd.configuration.location;
			modifyWithoutSave = true;
		}
		if (isset(_cmd.configuration.request) && !isset(_cmd.configuration.cmdWriteValue)) {
			_cmd.configuration.cmdWriteValue = _cmd.configuration.request;
			delete _cmd.configuration.request;
			modifyWithoutSave = true;
		}
		if (isset(_cmd.configuration.type) && init(_cmd.configuration.type) != '' &&
				!isset(_cmd.configuration.cmdFctModbus) && !isset(_cmd.configuration.cmdFormat)) {
			if (init(_cmd.configuration.type) == 'coils') {
				_cmd.configuration.cmdFctModbus = '5';
				_cmd.configuration.cmdFormat = 'bit';
			} else if (init(_cmd.configuration.type) == 'holding_registers') {
				_cmd.configuration.cmdFctModbus = '6';
				_cmd.configuration.cmdFormat = 'H';
			} else if (init(_cmd.configuration.type) == 'Write_Multiple_Holding') {
				_cmd.configuration.cmdFctModbus = '16';
				_cmd.configuration.cmdFormat = 'H';
			}
			
			delete _cmd.configuration.type;
		}
		// was never used
		delete _cmd.configuration.datatype;
		modifyWithoutSave = true;
	}
	if (isset(_cmd.configuration.cmdFormat)) {
		format_replace = {
			'uint8-lsb':   'uint8',
			'int16':       'h',
			'uint16':      'H',
			'int32':       'i',
			'uint32':      'I',
			'float32':     'f',
			'int64':       'q',
			'uint64':      'Q',
			'float64':     'd',
			'string':      's',
			'int16sp-sf':  'h_sf',
			'uint16sp-sf': 'H_sf',
			'uint32sp-sf': 'I_sf'
		};
		for (let [search, replace] of Object.entries(format_replace)) {
			if (_cmd.configuration.cmdFormat == search) {
				_cmd.configuration.cmdFormat = replace;
				continue;
			}
		}
	}
	
	// Default value for new added commands
	if (!isset(_cmd.id)) {
		_cmd.configuration.cmdFctModbus = '3';
		_cmd.configuration.cmdFormat = 'h';
		_cmd.configuration.cmdFrequency = '1';
		if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=eqOneDevID]').value() === '0') {
			_cmd.configuration.cmdDevID = '1';
		} else {
			_cmd.configuration.cmdDevID = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqDevID]').value();
		}
	}
	
	//console.log('CMD - ' + init(JSON.stringify(_cmd)));
	
	// The function getTrFromCmd returns the html code for the whole row in the table <tr>...</tr>
	var tr = getTrfromCmd(_cmd);
	$('#table_cmd tbody').append(tr);
	
	var tr = $('#table_cmd tbody tr:last');
	listSourceBlobs({
		id:	MyModbus_ext.getEqId(),
		error: function (error) {
			$('#div_alert').showAlert({message: error.message, level: 'danger'});
		},
		success: function (resultBin, resultNum) {
			tr.find('.cmdAttr[data-l1key=configuration][data-l2key=cmdSourceBlobBin]').append(resultBin);
			tr.find('.cmdAttr[data-l1key=configuration][data-l2key=cmdSourceBlobNum]').append(resultNum);
		}
	});
	
	listSourceValues({
		id:	MyModbus_ext.getEqId(),
		error: function (error) {
			$('#div_alert').showAlert({message: error.message, level: 'danger'});
		},
		success: function (result) {
			tr.find('.cmdAttr[data-l1key=value]').append(result);
		}
	});
	
	tr.find('.cmdAttr[data-l1key=type]').off();
	tr.find('.cmdAttr[data-l1key=subType]').off();
	tr.find('.cmdAttr[data-l1key=cmdFctModbus]').off();
	
	tr.setValues(_cmd, '.cmdAttr');
	jeedom.cmd.changeType(tr, init(_cmd.subType));
	
	tr.find('.cmdAttr[data-l1key=type]').on('change', function () {
		actualise_visible($(this), 'type');
	});
	tr.find('.cmdAttr[data-l1key=subType]').on('change', function () {
		actualise_visible($(this), 'subType');
	});
	tr.find('.cmdAttr[data-l1key=configuration][data-l2key=cmdFctModbus]').on('change', function () {
		actualise_visible($(this), 'cmdFctModbus');
	});
	tr.find('.cmdAttr[data-l1key=configuration][data-l2key=cmdFormat]').on('change', function () {
		actualise_visible($(this), 'cmdFormat');
	});
	
	actualise_visible($(tr.find('.cmdAttr[data-l1key=type]')), 'first call');
}
