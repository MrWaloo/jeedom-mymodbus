
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


$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */
$('.eqLogicAction[data-action=bt_docSpecific]').on('click', function () {
    window.open('https://bebel27a.github.io/jeedom-mymobdus.github.io/fr_FR/');
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
            initCheckBox();
            $('.eqLogicAttr[data-l1key=configuration][data-l2key=protocol]').off().on('change', function () {
                $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=' + $(this).val() + '.configuration',function(){
                    initCheckBox();
                });
            });
            modifyWithoutSave = false;
            $.hideLoading();
        });
    } else {
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=protocol]').on('change', function () {
            $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=' + $(this).val() + '.configuration',function(){
                initCheckBox();
            });
        });
        $.hideLoading();
    }
}
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="name">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name"></td>';
    tr += '<td class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType();
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span></td>';
    tr += '<td><select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="type"><option value="coils">Coil</option><option value="discrete_inputs">Discrete Input</option><option value="holding_registers">Holding Register</option><option value="input_registers">Input Register</option></select></td>'
    tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="location"></td>';
    tr += '<td ><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" />';
    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="parameters" rows="1" style="margin-top : 5px;" placeholder="{{Valeur de retour}}" ></textarea>';
    tr += '</td>';
    tr += '<td>';
    tr += '<input class="cmdAttr form-control tooltips input-sm" data-l1key="unite"  style="width : 100px;" placeholder="Unité" title="{{Unité}}">';
    tr += '<input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="margin-top : 5px;">';
    tr += '<input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="margin-top : 5px;">';
    tr += '</td>';
    tr += '<td>';
	if (is_numeric(_cmd.id)) {
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure" title="{{Configuration de la commande}}""><i class="fas fa-cogs"></i></a> ';
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
		tr += ' <a class="btn btn-default btn-xs cmdAction" data-action="copy" title="Dupliquer"><i class="far fa-clone"></i></a> ';
    }
	tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr " data-l1key="isHistorized" data-size="mini" />{{Historiser}}</label></span> ';
	tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
	tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
        $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}