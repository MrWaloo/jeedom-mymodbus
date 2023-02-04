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
$("#table_mymodbusFilters").sortable({
    axis: "y",
    cursor: "move",
    items: ".filter",
    placeholder: "ui-state-highlight",
    tolerance: "intersect",
    forcePlaceholderSize: true
});
/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */
$('.eqLogicAction[data-action=bt_docSpecific]').on('click', function () {
    window.open('https://bebel27a.github.io/jeedom-mymobdus.github.io/fr_FR/');
});
$('.pluginAction[data-action=openLink]').on('click', function () {
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
//$('#bt_templatesmymodbus').on('click', function () {
//    $('#md_modal').dialog({title: "{{Gestion des templates d'équipements mymobus}}"});
//    $('#md_modal').load('index.php?v=d&plugin=mymodbus&modal=templates').dialog('open');
//});

function prePrintEqLogic() {
    // unlink the event from the protocol dropdown list
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqProtocol]').off();
}

function printEqLogic(_eqLogic) {
    $.showLoading();
    // unlink the event from the protocol dropdown list
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqProtocol]').off();
    if (isset(_eqLogic.configuration) && isset(_eqLogic.configuration.eqProtocol)) {
        // load the form from the corresponding modal php file
        $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=configuration.' + _eqLogic.configuration.eqProtocol, function () {
            // load values
            $('body').setValues(_eqLogic, '.eqLogicAttr');
            // unlink and bind the event on change: load form from the corresponding modal php file
            $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqProtocol]').off().on('change', function () {
                $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=configuration.' + $(this).val());
            });
            modifyWithoutSave = false;
        });
    } else {
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqProtocol]').on('change', function () {
            $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=configuration.' + $(this).val());
        });
    }
    $.hideLoading();
}

// Génère la liste déroulante de choix du bit dans deux octets
var bitSelect = 
            '               <div class="col-xs-4">' +
            '                   <select class="conditionAttr form-control" data-l1key="operande">' +
            '                       <optgroup label="{{Premier Octet}}">';
for (let i = 0; i < 16; i++) {
    if (i == 8) bitSelect +=
            '                       </optgroup>' +
            '                       <optgroup label="{{Second Octet}}">';
    bitSelect += '                           <option value="' + 2**i + '">Bit ' + i % 8 + '</option>';
}
bitSelect += 
            '                       </optgroup>' +
            '                   </select>' +
            '               </div>';

$("#table_cmd").delegate(".infParamFiltre", 'click', function () {
    var el = $(this);
    var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=request]');
    var message = '<div class="row">  ' +
            '   <div class="col-md-12"> ' +
            '       <form class="form-horizontal" onsubmit="return false;"> ' +
            '           <div class="form-group"> ' +
            '               <label class="col-xs-5 control-label">{{Filtrer sur :}}</label>' +
                            bitSelect +
            '           </div>' +
            '       </form>' +
            '   </div>' +
            '</div>';
    bootbox.dialog({
        title: "{{Ajout d'un filtre}}",
        message: message,
        buttons: {
            "{{Ne rien mettre}}": {
                className: "btn-default",
                callback: function () {
                    return;
                }
            },
            success: {
                label: "{{Valider}}",
                className: "btn-primary",
                callback: function () {
                    var condition = ' & ' + $('.conditionAttr[data-l1key=operande]').value();
                    calcul.atCaret('insert', condition);
                }
            },
        }
    });
});

$("#table_cmd").delegate(".actParamValue", 'click', function () {
    var el = $(this);
    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=request]');
        // définition de la structure du message
        var message = '<div class="row">  ' +
            '   <div class="col-md-12"> ' +
            '       <form class="form-horizontal" onsubmit="return false;"> ' +
            '           <div class="form-group"> ' +
            '               <label class="col-xs-5 control-label" >' + result.human + ' {{=>}}</label>' +
                            bitSelect +
            '           </div>' +
            '       </form> ' +
            '   </div> ' +
            '</div>';
        bootbox.dialog({
            title: "{{Ajout d'un filtre }}",
            message: message,
            buttons: {
                "{{Ne rien mettre}}": {
                    className: "btn-default",
                    callback: function () {
                        calcul.atCaret('insert', result.human);
                    }
                },
                success: {
                    label: "{{Valider}}",
                    className: "btn-primary",
                    callback: function () {
                        var condition = result.human + ' & ' + $('.conditionAttr[data-l1key=operator]').value();
                        calcul.atCaret('insert', condition);
                    }
                },
            }
        });
    });
});

$("#table_cmd").delegate(".actParamRet", 'click', function () {
    var el = $(this);
    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=parameters]');
        // définition de la structure du message
        var message = '<div class="row">  ' +
            '   <div class="col-md-12"> ' +
            '       <form class="form-horizontal" onsubmit="return false;"> ' +
            '           <div class="form-group"> ' +
            '               <label class="col-xs-5 control-label" >' + result.human + ' {{=>}}</label>' +
                            bitSelect +
            '           </div>' +
            '       </form>' +
            '   </div> ' +
            '</div>';
        bootbox.dialog({
            title: "{{Ajout d'un filtre }}",
            message: message,
            buttons: {
                "{{Ne rien mettre}}": {
                    className: "btn-default",
                    callback: function () {
                        calcul.atCaret('insert', result.human);
                    }
                },
                success: {
                    label: "{{Valider}}",
                    className: "btn-primary",
                    callback: function () {
                        var condition = result.human + ' & ' + $('.conditionAttr[data-l1key=operator]').value();
                        calcul.atCaret('insert', condition);
                    }
                },
            }
        });
    });
});

$("#bt_add_InfoBin").on('click', function (event) {
    addCmdToTable({type: 'info', mymodbusType: 'bin'});
    modifyWithoutSave = true;
});

$("#bt_add_InfoNum").on('click', function (event) {
    addCmdToTable({type: 'info', mymodbusType: 'num'});
    modifyWithoutSave = true;
});

$("#bt_add_ActionBin").on('click', function (event) {
    addCmdToTable({type: 'action', mymodbusType: 'bin'});
    modifyWithoutSave = true;
});

$("#bt_add_ActionNum").on('click', function (event) {
    addCmdToTable({type: 'action', mymodbusType: 'num'});
    modifyWithoutSave = true;
});

function addCmdToTable(_cmd) {
    var prefix = init(_cmd.type).toString().substr(0, 3); // 'inf' for info or 'act' for action
    
    // Structure minimum de _cmd
    if (!isset(_cmd))
        var _cmd = {configuration: {}};
    if (!isset(_cmd.configuration))
        _cmd.configuration = {};
    
    // Configuration par défaut en cas d'ajout
    if (!isset(_cmd.id)) {
        if (prefix == 'inf') {
            if (init(_cmd.mymodbusType) == 'bin') {
                _cmd.subType = 'binary';
                _cmd.configuration.infFctModbus = '1';
                _cmd.configuration.infFormat = 'bit';
            } else {
                _cmd.subType = 'numeric';
                _cmd.configuration.infFctModbus = '3';
                _cmd.configuration.infFormat = 'int16';
            }
        } else if (prefix == 'act') {
            _cmd.subType = 'other';
            if (init(_cmd.mymodbusType) == 'bin') {
                _cmd.configuration.actFctModbus = '5';
                _cmd.configuration.actFormat = 'bit';
            } else {
                _cmd.configuration.actFctModbus = '6';
                _cmd.configuration.actFormat = 'int16';
            }
        }
    }
    
    // Type de variable de la commande
    if (prefix == 'inf' && !isset(_cmd.mymodbusType)) {
        if (_cmd.subType == 'binary')
            _cmd.mymodbusType = 'bin';
        else
            _cmd.mymodbusType = 'num';
    } else if (prefix == 'act' && !isset(_cmd.mymodbusType)) {
        if (_cmd.configuration.actFormat.toString().substr(0, 3) == 'bit')
            _cmd.mymodbusType = 'bin';
        else
            _cmd.mymodbusType = 'num';
    }
    
    //console.log('init(_cmd): ', init(_cmd)); // DEBUG
    
    // Commande info ou action
    if (prefix == 'inf' || prefix == 'act') {
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
        // Nom
        tr += ' <td class="name">';
        tr += '     <input class="cmdAttr form-control input-sm" data-l1key="id" disabled style="display:none;">';
        tr += '     <input class="cmdAttr form-control input-sm" data-l1key="name">';
        tr += ' </td>';
        // Type
        tr += ' <td>';
        tr += '     <input class="cmdAttr form-control type input-sm" data-l1key="type" value="' + init(_cmd.type) + '" disabled style="margin-bottom:5px;" />';
        tr += '     <span class="subType" subType="' + init(_cmd.subType) + '"></span>';
        tr += ' </td>';
        // Fonction modbus / format de donnée
        tr += ' <td>';
        tr += '     <div class="input-group" style="margin-bottom:5px;">';
        tr += '         <div class="col-sm-12">';
        tr += '             <select class="cmdAttr form-control input-sm" style="width:300px;" data-l1key="configuration" data-l2key="' + prefix + 'FctModbus">';
        if (prefix == 'inf') {
            if (init(_cmd.mymodbusType) == 'bin') {
                tr += '                 <option value="1">[0x01] Read coils ({{Binaire}} / bit)</option>';
                tr += '                 <option value="2">[0x02] Read discrete inputs ({{Binaire}} / bit)</option>';
            } else {
                tr += '                 <option value="3">[0x03] Read holding registers ({{Numérique}})</option>';
                tr += '                 <option value="4">[0x04] Read input registers ({{Numérique}})</option>';
            }
        } else if (prefix == 'act') {
            if (init(_cmd.mymodbusType) == 'bin') {
                tr += '                 <option value="5">[0x05] Write single coil ({{Binaire}} / bit)</option>';
                tr += '                 <option value="15">[0x0F] Write coils ({{Binaire}} / bit)</option>';
            } else {
                tr += '                 <option value="6">[0x06] Write register ({{Numérique}})</option>';
                tr += '                 <option value="16">[0x10] Write registers ({{Numérique}})</option>';
            }
        }
        tr += '             </select>';
        tr += '         </div>';
        tr += '     </div>';
        tr += '     <div class="input-group">';
        tr += '         <div class="col-sm-12">';
        tr += '             <select class="cmdAttr form-control input-sm" style="width:300px;" data-l1key="configuration" data-l2key="' + prefix + 'Format">';
        if (init(_cmd.mymodbusType) == 'bin') {
            tr += '                 <option value="bit">bit (0 .. 1)</option>';
            tr += '                 <option value="bit-inv">{{bit inversé}} (1 .. 0)</option>';
        } else {
            tr += '                 <optgroup label="8 bits">';
            tr += '                     <option value="int8-lsb">int8 LSB (-128 ... 127)</option>';
            tr += '                     <option value="int8-msb">int8 MSB (-128 ... 127)</option>';
            tr += '                     <option value="uint8-lsb">uint8 LSB (0 ... 255)</option>';
            tr += '                     <option value="uint8-msb">uint8 MSB (0 ... 255)</option>';
            tr += '                 </optgroup>';
            tr += '                 <optgroup label="16 bits">';
            tr += '                     <option value="int16">int16 (-32 768 ... 32 768)</option>';
            tr += '                     <option value="uint16">uint16 (0 ... 65 535)</option>';
            tr += '                     <option value="float16">float16 (Real 16bit)</option>';
            tr += '                 </optgroup>';
            tr += '                 <optgroup label="32 bits ({{2 registres}})">';
            tr += '                     <option value="int32">int32 (-2 147 483 648 ... 2 147 483 647)</option>';
            tr += '                     <option value="uint32">uint32 (0 ... 4 294 967 296)</option>';
            tr += '                     <option value="float32">float32 (Real 32bit)</option>';
            tr += '                 </optgroup>';
            tr += '                 <optgroup label="64 bits ({{4 registres}})">';
            tr += '                     <option value="int64">int64 (-9e18 ... 9e18)</option>';
            tr += '                     <option value="uint64">uint64 (0 ... 18e18)</option>';
            tr += '                     <option value="float64">float64 (Real 64bit)</option>';
            tr += '                 </optgroup>';
        }
        tr += '             </select>';
        tr += '         </div>';
        tr += '     </div>';
        tr += ' </td>';
        // Adresse
        tr += ' <td><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="' + prefix + 'Addr"></td>';
        // Paramètre
        tr += ' <td>';
        if (prefix == 'inf') {
            tr += '     <div class="input-group" style="margin-bottom : 5px;">';
            tr += '         <input class="cmdAttr form-control input-sm roundedLeft" data-l1key="configuration" data-l2key="request" placeholder="{{Option}}" />';
            tr += '         <span class="input-group-btn">';
            tr += '             <a class="btn btn-default btn-sm cursor infParamFiltre roundedRight" data-input="configuration"><i class="fa fa-list-alt"></i></a>';
            tr += '         </span>';
            tr += '     </div>';
        } else if (prefix == 'act') {
            tr += '     <div class="input-group" style="margin-bottom:5px;">';
            tr += '         <input class="cmdAttr form-control input-sm roundedLeft" data-l1key="configuration" data-l2key="request" placeholder="{{Valeur}}"/>';
            tr += '         <span class="input-group-btn">';
            tr += '             <a class="btn btn-default btn-sm cursor actParamValue roundedRight" data-input="configuration"><i class="fa fa-list-alt "></i></a>';
            tr += '         </span>';
            tr += '     </div>';
            tr += '     <div class="input-group">';
            tr += '         <input class="cmdAttr form-control input-sm roundedLeft" data-l1key="configuration" data-l2key="parameters" placeholder="{{Valeur de retour }}" />';
            tr += '         <span class="input-group-btn">';
            tr += '             <a class="btn btn-default btn-sm cursor actParamRet roundedRight" data-input="configuration"><i class="fa fa-list-alt "></i></a>';
            tr += '         </span>';
            tr += '     </div>';
        }
        tr += ' </td>';  
        // Options
        tr += ' <td>';
        tr += '     <input class="cmdAttr form-control tooltips input-sm" data-l1key="unite" style="width:100px;" placeholder="Unité" title="{{Unité}}">';
        tr += '     <input class="cmdAttr form-control tooltips input-sm expertModeVisible" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="margin-top:5px;">';
        tr += '     <input class="cmdAttr form-control tooltips input-sm expertModeVisible" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="margin-top:5px;">';
        tr += ' </td>';
        // Configuration commande
        tr += ' <td>';
        if (is_numeric(_cmd.id)) {
            tr += '     <a class="btn btn-default btn-xs cmdAction" data-action="configure" title="{{Configuration de la commande}}""><i class="fas fa-cogs"></i></a>';
            tr += '     <a class="btn btn-default btn-xs cmdAction" data-action="test"title="Tester"><i class="fas fa-rss"></i> </a>';
            tr += '     <a class="btn btn-default btn-xs cmdAction" data-action="copy" title="Dupliquer"><i class="far fa-clone"></i></a>';
        }
        tr += '     <span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" data-size="mini"/>{{Historiser}}</label></span>';
        tr += '     <span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span>';
        tr += '     <i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer}}"></i>';
        tr += ' </td>';
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