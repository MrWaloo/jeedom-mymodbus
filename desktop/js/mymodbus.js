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

$("#table_cmd").delegate(".paramFiltre", 'click', function () {
    var el = $(this);
    var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=cmdWriteValue]');
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
                    console.log('condition: ' + condition)
                }
            },
        }
    });
});

//$("#table_cmd").delegate(".paramValue", 'click', function () {
//    var el = $(this);
//    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
//        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=request]');
//        // définition de la structure du message
//        var message = '<div class="row">  ' +
//            '   <div class="col-md-12"> ' +
//            '       <form class="form-horizontal" onsubmit="return false;"> ' +
//            '           <div class="form-group"> ' +
//            '               <label class="col-xs-5 control-label" >' + result.human + ' {{=>}}</label>' +
//                            bitSelect +
//            '           </div>' +
//            '       </form> ' +
//            '   </div> ' +
//            '</div>';
//        bootbox.dialog({
//            title: "{{Ajout d'un filtre }}",
//            message: message,
//            buttons: {
//                "{{Ne rien mettre}}": {
//                    className: "btn-default",
//                    callback: function () {
//                        calcul.atCaret('insert', result.human);
//                    }
//                },
//                success: {
//                    label: "{{Valider}}",
//                    className: "btn-primary",
//                    callback: function () {
//                        var condition = result.human + ' & ' + $('.conditionAttr[data-l1key=operator]').value();
//                        calcul.atCaret('insert', condition);
//                    }
//                },
//            }
//        });
//    });
//});
//
$("#table_cmd tbody").delegate(".cmdAttr[data-l1key=type]", 'change', function (event) {
    actualise_visible($(this));
});

$("#table_cmd tbody").delegate(".cmdAttr[data-l1key=subType]", 'change', function (event) {
    actualise_visible($(this));
});

$("#table_cmd tbody").delegate(".cmdAttr[data-l1key=configuration][data-l2key=cmdFctModbus]", 'change', function (event) {
    actualise_visible($(this));
});

function actualise_visible(me) {
    var cmdType = $(me).closest('tr').find('.cmdAttr[data-l1key=type]').value();
    var subType = $(me).closest('tr').find('.cmdAttr[data-l1key=subType]').value();
    var cmdFctModbus = $(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=cmdFctModbus]').value();
    
    $(me).closest('tr').find('.formatNum').hide();
    $(me).closest('tr').find('.formatBin').hide();
    $(me).closest('tr').find('.readFunction').hide();
    $(me).closest('tr').find('.writeFunction').hide();
    $(me).closest('tr').find('.readBin').hide();
    $(me).closest('tr').find('.readNum').hide();
    
    if (cmdType == 'info') {
        $(me).closest('tr').find('.readFunction').show();
        if (subType == 'binary') {
            $(me).closest('tr').find('.readBin').show();
            $(me).closest('tr').find('.formatBin').show();
        } else {
            $(me).closest('tr').find('.readNum').show();
            $(me).closest('tr').find('.formatNum').show();
        }
    } else { // action
        $(me).closest('tr').find('.writeFunction').show();
        if (cmdFctModbus == '5' || cmdFctModbus == '15') {
            $(me).closest('tr').find('.formatBin').show();
        } else {
            $(me).closest('tr').find('.formatNum').show();
        }
    }
}

$("#bt_add_command").on('click', function (event) {
    addCmdToTable({});
    modifyWithoutSave = true;
});

function addCmdToTable(_cmd) {
    // Minimal structure for _cmd
    if (!isset(_cmd))
        var _cmd = {configuration: {}};
    if (!isset(_cmd.configuration))
        _cmd.configuration = {};
    // Default value for new added commands
    if (!isset(_cmd.id)) {
        _cmd.configuration.cmdSlave = '0';
        _cmd.configuration.cmdFctModbus = '3';
        _cmd.configuration.cmdFormat = 'int16';
    }
    
    // Command info or action
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    // Nom
    tr += ' <td class="name">';
    tr += '     <input class="cmdAttr form-control input-sm" data-l1key="id" disabled style="display:none;">';
    tr += '     <input class="cmdAttr form-control input-sm" data-l1key="name">';
    tr += ' </td>';
    // Type
    tr += ' <td>';
    tr += '     <span class="type" id="' + init(_cmd.type) + '" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '     <span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += ' </td>';
    // Adresse esclave
    tr += ' <td><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="cmdSlave"></td>';
    // Modbus function / Data format
    tr += ' <td>';
    tr += '     <div class="input-group" style="margin-bottom:5px;">';
    tr += '         <select class="cmdAttr form-control input-sm" style="width:300px;" data-l1key="configuration" data-l2key="cmdFctModbus">';
    tr += '             <option class="readBin" value="1">[0x01] Read coils</option>';
    tr += '             <option class="readBin" value="2">[0x02] Read discrete inputs</option>';
    tr += '             <option class="readNum" value="3">[0x03] Read holding registers</option>';
    tr += '             <option class="readNum" value="4">[0x04] Read input registers</option>';
    tr += '             <option class="writeFunction" value="5">[0x05] Write single coil</option>';
    tr += '             <option class="writeFunction" value="15">[0x0F] Write coils</option>';
    tr += '             <option class="writeFunction" value="6">[0x06] Write register</option>';
    tr += '             <option class="writeFunction" value="16">[0x10] Write registers</option>';
    tr += '         </select>';
    tr += '     </div>';
    tr += '     <div class="input-group">';
    tr += '         <select class="cmdAttr form-control input-sm" style="width:300px;" data-l1key="configuration" data-l2key="cmdFormat">';
    tr += '             <option class="formatBin" value="bit">bit (0 .. 1)</option>';
    tr += '             <option class="formatBin" value="bit-inv">{{bit inversé}} (1 .. 0)</option>';
    tr += '             <optgroup class="formatNum" label="8 bits">';
    tr += '                 <option class="formatNum" value="int8-lsb">int8 LSB (-128 ... 127)</option>';
    tr += '                 <option class="formatNum" value="int8-msb">int8 MSB (-128 ... 127)</option>';
    tr += '                 <option class="formatNum" value="uint8-lsb">uint8 LSB (0 ... 255)</option>';
    tr += '                 <option class="formatNum" value="uint8-msb">uint8 MSB (0 ... 255)</option>';
    tr += '             </optgroup>';
    tr += '             <optgroup class="formatNum" label="16 bits">';
    tr += '                 <option class="formatNum" value="int16">int16 (-32 768 ... 32 768)</option>';
    tr += '                 <option class="formatNum" value="uint16">uint16 (0 ... 65 535)</option>';
    tr += '                 <option class="formatNum" value="float16">float16 (Real 16bit)</option>';
    tr += '             </optgroup>';
    tr += '             <optgroup class="formatNum" label="32 bits ({{2 registres}})">';
    tr += '                 <option class="formatNum" value="int32">int32 (-2 147 483 648 ... 2 147 483 647)</option>';
    tr += '                 <option class="formatNum" value="uint32">uint32 (0 ... 4 294 967 296)</option>';
    tr += '                 <option class="formatNum" value="float32">float32 (Real 32bit)</option>';
    tr += '             </optgroup>';
    tr += '             <optgroup class="formatNum" label="64 bits ({{4 registres}})">';
    tr += '                 <option class="formatNum" value="int64">int64 (-9e18 ... 9e18)</option>';
    tr += '                 <option class="formatNum" value="uint64">uint64 (0 ... 18e18)</option>';
    tr += '                 <option class="formatNum" value="float64">float64 (Real 64bit)</option>';
    tr += '             </optgroup>';
    tr += '             <option class="formatNum" class="" value="string">{{Chaine de caractères}}</option>';
    tr += '             <optgroup class="formatNum" label="{{Spécial}}">';
    tr += '                 <option class="formatNum" value="int16sp-sf">{{SunSpec scale factor int16}}</option>';
    tr += '                 <option class="formatNum" value="uint16sp-sf">{{SunSpec scale factor uint16}}</option>';
    tr += '                 <option class="formatNum" value="uint32sp-sf">{{SunSpec scale factor uint32}}</option>';
    tr += '             </optgroup>';
    tr += '         </select>';
    tr += '     </div>';
    tr += ' </td>';
    // Adresse modbus
    tr += ' <td>';
    tr += '     <input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="cmdAddress">';
    tr += '     <label class="checkbox-inline">';
    tr += '         <input type="checkbox" class="cmdAttr checkbox-inline tooltips" title="{{\'Little endian\' si coché}}" data-l1key="configuration" data-l2key="cmdInvertBytes"/>{{Inverser octets}}';
    tr += '     </label></br>';
    tr += '     <label class="checkbox-inline">';
    tr += '         <input type="checkbox" class="cmdAttr checkbox-inline tooltips" title="{{\'Little endian\' si coché}}" data-l1key="configuration" data-l2key="cmdInvertWords"/>{{Inverser mots}}</label></br>';
    tr += '     </label></br>';
    tr += ' </td>';
    // Paramètre
    tr += ' <td>';
    tr += '     <div class="input-group">';
    tr += '         <input class="cmdAttr form-control input-sm roundedLeft readFunction" data-l1key="configuration" data-l2key="cmdOption" placeholder="{{Option}}" />';
    tr += '         <span class="input-group-btn">';
    tr += '             <a class="btn btn-default btn-sm cursor paramFiltre roundedRight readFunction" data-input="configuration"><i class="fa fa-list-alt"></i></a>';
    tr += '         </span>';
    tr += '     </div>';
    tr += '     <div class="input-group">';
    tr += '         <input class="cmdAttr form-control input-sm roundedLeft writeFunction" style="width:100%;" data-l1key="configuration" data-l2key="cmdWriteValue" placeholder="{{Valeur}}"/>';
    //tr += '         <span class="input-group-btn">';
    //tr += '             <a class="btn btn-default btn-sm cursor paramValue roundedRight writeFunction" data-input="configuration"><i class="fa fa-list-alt "></i></a>';
    //tr += '         </span>';
    tr += '     </div>';
    tr += ' </td>';
    // Etat
    tr += ' <td>';
    tr += '     <span class="cmdAttr" data-l1key="htmlstate"></span>';
    tr += ' </td>';        
    // Options
    tr += ' <td>';
    if (is_numeric(_cmd.id)) {
        tr += '     <a class="btn btn-default btn-xs cmdAction" data-action="configure" title="{{Configuration de la commande}}""><i class="fas fa-cogs"></i></a>';
        tr += '     <a class="btn btn-default btn-xs cmdAction" data-action="test" title="Tester"><i class="fas fa-rss"></i> </a>';
        tr += '     <a class="btn btn-default btn-xs cmdAction" data-action="copy" title="Dupliquer"><i class="far fa-clone"></i></a>';
    }
    tr += '     <label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label>';
    tr += '     <label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" data-size="mini"/>{{Historiser}}</label>';
    tr += '     <div style="margin-top:7px;">';
    tr += '         <input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;">';
    tr += '         <input class="tooltips cmdAttr form-control input-sm expertModeVisible" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;">';
    tr += '         <input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;">';
    tr += '     </div>';
    tr += ' </td>';
    // Delete button
    tr += ' <td>';
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
            //tr.find('.cmdAttr[data-l1key=configuration][data-l2key=updateCmdId]').append(result);
            tr.setValues(_cmd, '.cmdAttr');
            jeedom.cmd.changeType(tr, init(_cmd.subType));
        }
    });
}