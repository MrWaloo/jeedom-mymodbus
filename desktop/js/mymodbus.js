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

// *********** Evénements de la page du plugin

$('.eqLogicAction[data-action=bt_addMymodbusEq]').off('click').on('click', function() {
  let dialog_message = '<label class="control-label">{{Nom du nouvel équipement :}}</label>';
  dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="addMymodbusEqName"><br><br>';
  dialog_message += '<label class="control-label">{{Utiliser un template :}}</label>';
  dialog_message += '<select class="bootbox-input bootbox-input-select form-control" id="addMymodbusTplSelector">';
  dialog_message += '</select>';
  bootbox.confirm({
    title: "{{Ajouter un nouvel équipement MyModbus}}",
    message: dialog_message,
    callback: function (result) {
      if (result) {
        var eqName = $('#addMymodbusEqName').value();
        if (eqName === undefined || eqName == null || eqName === '' || eqName == false) {
          $.fn.showAlert({message: "{{Le nom de l'équipement ne peut pas être vide !}}", level: 'warning'});
          return false;
        }
        var eqTemplate = $('#addMymodbusTplSelector').val();
        jeedom.eqLogic.save({
          type: 'mymodbus',
          eqLogics: [ {name: eqName} ],
          error: function (error) {
            $.fn.showAlert({message: error.message, level: 'danger'});
          },
          success: function(savedEq) {
            if (eqTemplate != '') {
              mymodbus.callPluginAjax({
                data: {
                  action: "applyTemplate",
                  id: savedEq.id,
                  templateName : eqTemplate,
                  keepCmd: false
                },
                success: function () {
                  var vars = getUrlVars();
                  var url = 'index.php?';
                  for (var i in vars) {
                    if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull') {
                      url += i + '=' + vars[i].replace('#', '') + '&';
                    }
                  }
                  modifyWithoutSave = false;
                  url += 'id=' + savedEq.id + '&saveSuccessFull=1';
                  jeedomUtils.loadPage(url);
                }
              });
            }
            if (eqTemplate == '') {
              var vars = getUrlVars();
              var url = 'index.php?';
              for (var i in vars) {
                if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull') {
                  url += i + '=' + vars[i].replace('#', '') + '&';
                }
              }
              modifyWithoutSave = false;
              url += 'id=' + savedEq.id + '&saveSuccessFull=1';
              jeedomUtils.loadPage(url);
            }
          }
        });
      }
    }
  });
  mymodbus.callPluginAjax({
    data: {
      action: "getTemplateList",
    },
    error: function(error) {},
    success: function (dataresult) {
      opts = '<option value="">{{Aucun}}</option>';
      for (var i in dataresult)
        opts += '<option value="' + dataresult[i][0] + '">' + dataresult[i][0] + '</option>';
      $('#addMymodbusTplSelector').html(opts);
    }
  });
});

$('#bt_healthmymodbus').on('click', function () {
  $('#md_modal').dialog({title: "{{Santé mymodbus}}"});
  $('#md_modal').load('index.php?v=d&plugin=mymodbus&modal=health').dialog('open');
});

$('#bt_templatesMymodbus').on('click', function () {
  $('#md_modal').dialog({title: "{{Gestion des templates d'équipement MyMobus}}"});
  $('#md_modal').load('index.php?v=d&plugin=mymodbus&modal=templates').dialog('open');
});

$('.eqLogicAction[data-action=bt_docSpecific]').on('click', function () {
  window.open('https://bebel27a.github.io/jeedom-mymobdus.github.io/fr_FR/');
});

// *********** Evénements de la page de l'édition d'un équipement

$('.eqLogicAction[data-action=createTemplate]').off('click').on('click', function () {
  bootbox.prompt({
    title: "{{Nom du nouveau template ?}}",
    callback: function (result) {
      if (result !== null) {
        mymodbus.callPluginAjax({
          data: {
            action: "createTemplate",
            id: mymodbus.getEqId(),
            name : result
          }
        });
      }
    }
  });
});

$('.eqLogicAction[data-action=applyTemplate]').off('click').on('click', function () {
  mymodbus.callPluginAjax({
    data: {
      action: "getTemplateList",
    },
    success: function (dataresult) {
      var dialog_message = '<label class="control-label">{{Choisissez un template :}}</label> ';
      dialog_message += '<select class="bootbox-input bootbox-input-select form-control" id="applyTemplateSelector">';
      for(var i in dataresult)
        dialog_message += '<option value="'+dataresult[i][0]+'">'+dataresult[i][0]+'</option>';
      dialog_message += '</select><br/>';

      dialog_message += '<label class="control-label">{{Que voulez-vous faire des commandes existantes ?}}</label> ';
      dialog_message += '<div class="radio"><label><input type="radio" name="applyTemplateCommand" value="1" checked="checked">{{Les conserver / Mettre à jour}}</label></div>';
      dialog_message += '<div class="radio"><label><input type="radio" name="applyTemplateCommand" value="0">' + "{{Les supprimer d'abord}}" + '</label></div>';

      bootbox.confirm({
        title: '{{Appliquer un Template}}',
        message: dialog_message,
        callback: function (result){ if (result) {
          mymodbus.callPluginAjax({
            data: {
              action: "applyTemplate",
              id: mymodbus.getEqId(),
              templateName : $("#applyTemplateSelector").val(),
              keepCmd: $("[name='applyTemplateCommand']:checked").val()
            },
            success: function (dataresult) {
              $('.eqLogicDisplayCard[data-eqLogic_id=' + mymodbus.getEqId() + ']').click();
            }
          });
        }}
      });
    }
  });
});

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
});

function printEqLogic(_eqLogic) {
  //console.log('eqLogic : ' + init(JSON.stringify(_eqLogic)));
  if (isset(_eqLogic.configuration.protocol) && !isset(_eqLogic.configuration.eqProtocol)) {
    if (_eqLogic.configuration.protocol == 'rtu') {
      _eqLogic.configuration.eqProtocol = 'serial';
      _eqLogic.configuration.eqSerialMethod = 'rtu';
      if (isset(_eqLogic.configuration.port)) {
        _eqLogic.configuration.eqSerialInterface = _eqLogic.configuration.port;
        delete _eqLogic.configuration.port;
      }
      if (isset(_eqLogic.configuration.baudrate)) {
        _eqLogic.configuration.eqSerialBaudrate = _eqLogic.configuration.baudrate;
        delete _eqLogic.configuration.baudrate;
      }
      if (isset(_eqLogic.configuration.parity)) {
        _eqLogic.configuration.eqSerialParity = _eqLogic.configuration.parity;
        delete _eqLogic.configuration.parity;
      }
      if (isset(_eqLogic.configuration.bytesize)) {
        _eqLogic.configuration.eqSerialBytesize = _eqLogic.configuration.bytesize;
        delete _eqLogic.configuration.bytesize;
      }
      if (isset(_eqLogic.configuration.stopbits)) {
        _eqLogic.configuration.eqSerialStopbits = _eqLogic.configuration.stopbits;
        delete _eqLogic.configuration.stopbits;
      }
    } else {
      _eqLogic.configuration.eqProtocol = 'tcp';
      if (_eqLogic.configuration.protocol == 'rtuovertcp')
        _eqLogic.configuration.eqTcpRtu = 1;
      if (isset(_eqLogic.configuration.addr)) {
        _eqLogic.configuration.eqTcpAddr = _eqLogic.configuration.addr;
        delete _eqLogic.configuration.addr;
      }
      if (isset(_eqLogic.configuration.port)) {
        _eqLogic.configuration.eqTcpPort = _eqLogic.configuration.port;
        delete _eqLogic.configuration.port;
      }
    }
    delete _eqLogic.configuration.protocol;
  }
  if (isset(_eqLogic.configuration.polling) && !isset(_eqLogic.configuration.eqPolling)) {
    _eqLogic.configuration.eqPolling = _eqLogic.configuration.polling;
    delete _eqLogic.configuration.polling;
  }
  if (isset(_eqLogic.configuration.keepopen) && !isset(_eqLogic.configuration.eqKeepopen)) {
    _eqLogic.configuration.eqKeepopen = _eqLogic.configuration.keepopen;
    delete _eqLogic.configuration.keepopen;
  }
  // Define the default configuration's value
  if (!isset(_eqLogic.configuration.eqRefreshMode) || _eqLogic.configuration.eqRefreshMode == '')
    _eqLogic.configuration.eqRefreshMode = 'polling';
  if (!isset(_eqLogic.configuration.eqPolling) || _eqLogic.configuration.eqPolling == '')
    _eqLogic.configuration.eqPolling = '5';
  if (!isset(_eqLogic.configuration.eqFirstDelay) || _eqLogic.configuration.eqFirstDelay == '')
    _eqLogic.configuration.eqFirstDelay = '0';
  if (!isset(_eqLogic.configuration.eqWriteCmdCheckTimeout) || _eqLogic.configuration.eqWriteCmdCheckTimeout == '')
    _eqLogic.configuration.eqWriteCmdCheckTimeout = '1';
  
  // Afficher la partie variable de la configuration de l'équipement en fonction du protocole choisi
  $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqProtocol]').off().on('change', function () {
    if ($(this).val() != '' && !is_null($(this).val())) {
      $('#div_protocolParameters').load('index.php?v=d&plugin=mymodbus&modal=eqConfig_' + $(this).val(), function () {
        $('#div_protocolParameters').setValues(_eqLogic, '.eqLogicAttr');
      });
    }
  });
  // load values
  $('#eqLogic').setValues(_eqLogic, '.eqLogicAttr');
}

$('.eqLogicAttr[data-l1key=configuration][data-l2key=eqRefreshMode]').off().on('change', function () {
  if ($(this).val() == 'polling') {
    $('#eqPolling').show();
  } else {
    $('#eqPolling').hide();
  }
});

// Génère la liste déroulante de choix du bit dans deux octets
var bitSelect = 
      '         <div class="col-xs-4">' +
      '           <select class="conditionAttr form-control" data-l1key="operande">' +
      '             <optgroup label="{{Premier Octet}}">';
for (let i = 0; i < 16; i++) {
  if (i == 8) bitSelect +=
      '             </optgroup>' +
      '             <optgroup label="{{Second Octet}}">';
  bitSelect += '               <option value="' + 2**i + '">Bit ' + i % 8 + '</option>';
}
bitSelect += 
      '             </optgroup>' +
      '           </select>' +
      '         </div>';

$("#table_cmd").delegate(".paramFiltre", 'click', function () {
  var el = $(this);
  var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=cmdOption]');
  var message = '<div class="row">  ' +
      '   <div class="col-md-12"> ' +
      '     <form class="form-horizontal" onsubmit="return false;"> ' +
      '       <div class="form-group"> ' +
      '         <label class="col-xs-5 control-label">{{Filtrer sur :}}</label>' +
      bitSelect +
      '       </div>' +
      '     </form>' +
      '   </div>' +
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
          if (cmds[i].subType !== 'binary')
            resultNum += '<option value="' + cmds[i].id + '">' + cmds[i].name + '</option>';
        }
      }
      if (typeof(_params.success) == 'function')
        _params.success(resultBin, resultNum);
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
        if (cmds[i].type === 'info' && cmds[i].configuration.cmdFormat != 'blob' && cmds[i].logicalId === '')
          result += '<option value="' + cmds[i].id + '">' + cmds[i].name + '</option>';
      }
      if (typeof(_params.success) == 'function')
        _params.success(result);
    }
  });
}

function actualise_visible(me, source, _template = false) {
  if (source !== 'first call')
    modifyWithoutSave = true;
  //var cmdName = $(me).closest('tr').find('.cmdAttr[data-l1key=name]').value();
  //console.log(cmdName + ' *-*-*-*-*-*-*-*-*-* ' + source);
  var cmdLogicalId = $(me).closest('tr').find('.cmdAttr[data-l1key=logicalId]').value();
  var cmdType = $(me).closest('tr').find('.cmdAttr[data-l1key=type]').value();
  var subType = $(me).closest('tr').find('.cmdAttr[data-l1key=subType]').value();
  var cmdFctModbusEl = $(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=cmdFctModbus]');
  var cmdFctModbus = $(cmdFctModbusEl).value();
  var cmdFormatEl = $(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=cmdFormat]');
  var cmdFormat = $(cmdFormatEl).value();
  
  $(me).closest('tr').find('.formatNum').hide();
  $(me).closest('tr').find('.formatBin').hide();
  $(me).closest('tr').find('.FctBlobBin').hide();
  $(me).closest('tr').find('.FctBlobNum').hide();
  $(me).closest('tr').find('.notFctBlob').hide();
  $(me).closest('tr').find('.notFormatBlob').hide();
  $(me).closest('tr').find('.readFunction').hide();
  $(me).closest('tr').find('.writeFunction').hide();
  $(me).closest('tr').find('.readBin').hide();
  $(me).closest('tr').find('.readNum').hide();
  $(me).closest('tr').find('.withSlave').hide();
  $(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=listValue]').hide();
  
  if (_template || cmdLogicalId == '') { // without a logicalId
    if (cmdFctModbus != 'fromBlob')
      $(me).closest('tr').find('.withSlave').show();
    
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
          $(me).closest('tr').find('.formatNum').hide(); // HIDE !
        } else {
          $(me).closest('tr').find('.FctBlobNum').show();
        }
      }
      if (cmdFormat != 'blob')
        $(me).closest('tr').find('.notFormatBlob').show();
      
    } else { // action
      $(me).closest('tr').find('.writeFunction').show();
      if (cmdFctModbus == '5' || cmdFctModbus == '15') {
        $(me).closest('tr').find('.formatBin').show();
      } else {
        $(me).closest('tr').find('.formatNum').show();
      }
      if (subType == 'select')
        $(me).closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=listValue]').show();
      $(me).closest('tr').find('.notFormatBlob').show();
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
    if (option.is(':selected') && !visible)
      wrongSelection = true;
    if (visible && firstVisibleOption === null)
      firstVisibleOption = option.value();
  });
  if (wrongSelection)
    $(selectEl).val(firstVisibleOption).change();
}

function getTrfromCmd(_cmd, _template = false) {
  let formDisabled = (_template) ? ' disabled' : '';
  // id
  let dataCmdId = (!_template) ? 'data-cmd_id="' + init(_cmd.id) : '';
  let tr = '<tr class="cmd" ' + dataCmdId + '">';
  if (!_template) {
    tr += ' <td class="hidden-xs">'
    tr += '   <span class="cmdAttr" data-l1key="id" disabled></span>'
    tr += '   <span class="cmdAttr" data-l1key="logicalId" hidden></span>'
    tr += ' </td>'
  }
  // Nom
  tr += ' <td class="name">';
  tr += '   <input class="cmdAttr form-control input-sm" data-l1key="name"' + formDisabled + '>';
  tr += '   <select class="cmdAttr form-control input-sm" data-l1key="value" style="display : none;margin-top : 5px;" title="{{Commande info liée}}"' + formDisabled + '>';
  tr += '     <option value="">Aucune</option>';
  tr += '   </select>';
  tr += ' </td>';
  // Valeur
  if (!_template) {
    tr += ' <td>';
    tr += '   <span class="cmdAttr" data-l1key="htmlstate"></span>';
    tr += ' </td>';
  }
  // Type
  tr += ' <td>';
  tr += '   <div class="input-group">';
  tr += '     <span class="type" id="' + init(_cmd.type) + '" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
  tr += '     <span class="subType" subType="' + init(_cmd.subType) + '"></span>';
  tr += '   </div>';
  tr += ' </td>';
  // Adresse esclave
  tr += ' <td><input class="cmdAttr form-control input-sm withSlave" data-l1key="configuration" data-l2key="cmdSlave"' + formDisabled + '></td>';
  // Modbus function / Data format
  tr += ' <td>';
  tr += '   <div class="input-group" style="margin-bottom:5px;">';
  tr += '     <select class="cmdAttr form-control input-sm" style="width:230px;" data-l1key="configuration" data-l2key="cmdFctModbus"' + formDisabled + '>';
  tr += '       <option class="readBin" value="1">[0x01] Read coils</option>';
  tr += '       <option class="readBin" value="2">[0x02] Read discrete inputs</option>';
  tr += '       <option class="readNum" value="3">[0x03] Read holding registers</option>';
  tr += '       <option class="readNum" value="4">[0x04] Read input registers</option>';
  tr += '       <option class="writeFunction" value="5">[0x05] Write single coil</option>';
  tr += '       <option class="writeFunction" value="15">[0x0F] Write coils</option>';
  tr += '       <option class="writeFunction" value="6">[0x06] Write register</option>';
  tr += '       <option class="writeFunction" value="16">[0x10] Write registers</option>';
  tr += '       <option class="readFunction" value="fromBlob">{{Depuis une plage de registres}}</option>';
  tr += '     </select>';
  tr += '   </div>';
  tr += '   <div class="input-group">';
  tr += '     <select class="cmdAttr form-control input-sm" style="width:230px;" data-l1key="configuration" data-l2key="cmdFormat"' + formDisabled + '>';
  tr += '       <option class="formatBin" value="bit">bit (0 .. 1)</option>';
  tr += '       <option class="formatBin" value="bit-inv">{{bit inversé}} (1 .. 0)</option>';
  tr += '       <optgroup class="formatNum" label="8 bits">';
  tr += '         <option class="formatNum" value="int8-lsb">int8 LSB (-128 ... 127)</option>';
  tr += '         <option class="formatNum" value="int8-msb">int8 MSB (-128 ... 127)</option>';
  tr += '         <option class="formatNum" value="uint8-lsb">uint8 LSB (0 ... 255)</option>';
  tr += '         <option class="formatNum" value="uint8-msb">uint8 MSB (0 ... 255)</option>';
  tr += '       </optgroup>';
  tr += '       <optgroup class="formatNum" label="16 bits">';
  tr += '         <option class="formatNum" value="int16">int16 (-32 768 ... 32 768)</option>';
  tr += '         <option class="formatNum" value="uint16">uint16 (0 ... 65 535)</option>';
  tr += '         <option class="formatNum" value="float16">float16 (Real 16bit)</option>';
  tr += '       </optgroup>';
  tr += '       <optgroup class="formatNum" label="32 bits ({{2 registres}})">';
  tr += '         <option class="formatNum" value="int32">int32 (-2 147 483 648 ... 2 147 483 647)</option>';
  tr += '         <option class="formatNum" value="uint32">uint32 (0 ... 4 294 967 296)</option>';
  tr += '         <option class="formatNum" value="float32">float32 (Real 32bit)</option>';
  tr += '       </optgroup>';
  tr += '       <optgroup class="formatNum" label="64 bits ({{4 registres}})">';
  tr += '         <option class="formatNum" value="int64">int64 (-9e18 ... 9e18)</option>';
  tr += '         <option class="formatNum" value="uint64">uint64 (0 ... 18e18)</option>';
  tr += '         <option class="formatNum" value="float64">float64 (Real 64bit)</option>';
  tr += '       </optgroup>';
  tr += '       <option class="formatNum" value="string">{{Chaine de caractères}}</option>';
  tr += '       <option class="notFctBlob" value="blob">{{Plage de registres}}</option>';
  tr += '       <optgroup class="formatNum" label="{{Spécial}}">';
  tr += '         <option class="formatNum" value="int16sp-sf">{{SunSpec scale factor int16}}</option>';
  tr += '         <option class="formatNum" value="uint16sp-sf">{{SunSpec scale factor uint16}}</option>';
  tr += '         <option class="formatNum" value="uint32sp-sf">{{SunSpec scale factor uint32}}</option>';
  tr += '       </optgroup>';
  tr += '     </select>';
  tr += '   </div>';
  tr += ' </td>';
  // Adresse Modbus
  tr += ' <td>';
  tr += '   <div class="input-group" style="margin-bottom:5px;">';
  tr += '     <select class="cmdAttr form-control input-sm FctBlobBin" style="width:100%;" data-l1key="configuration" data-l2key="cmdSourceBlobBin"' + formDisabled + '>';
  tr += '     </select>';
  tr += '     <select class="cmdAttr form-control input-sm FctBlobNum" style="width:100%;" data-l1key="configuration" data-l2key="cmdSourceBlobNum"' + formDisabled + '>';
  tr += '     </select>';
  tr += '   <input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="cmdAddress"' + formDisabled + '/>';
  tr += '   <label class="checkbox-inline notFormatBlob">';
  tr += '     <input type="checkbox" class="cmdAttr checkbox-inline tooltips" title="{{\'Little endian\' si coché}}" data-l1key="configuration" data-l2key="cmdInvertBytes"' + formDisabled + '/>{{Inverser octets}}';
  tr += '   </label></br>';
  tr += '   <label class="checkbox-inline notFormatBlob">';
  tr += '     <input type="checkbox" class="cmdAttr checkbox-inline tooltips" title="{{\'Little endian\' si coché}}" data-l1key="configuration" data-l2key="cmdInvertWords"' + formDisabled + '/>{{Inverser mots}}';
  tr += '   </label></br>';
  tr += '   </div>';
  tr += ' </td>';
  // Paramètre
  tr += ' <td>';
  tr += '   <div class="input-group">';
  tr += '     <input class="cmdAttr form-control input-sm roundedLeft readFunction" data-l1key="configuration" data-l2key="cmdOption" placeholder="{{Option}}"' + formDisabled + '/>';
  if (!_template) {
    tr += '     <span class="input-group-btn">';
    tr += '       <a class="btn btn-default btn-sm cursor paramFiltre roundedRight readFunction" data-input="configuration"><i class="fa fa-list-alt"></i></a>';
    tr += '     </span>';
  }
  tr += '   </div>';
  tr += '   <div class="input-group notFctBlob">';
  tr += '     <label class="label">{{Lecture 1x sur&nbsp;:}}&nbsp;';
  tr += '       <input class="cmdAttr form-inline input-sm" style="width:70px;" data-l1key="configuration" data-l2key="cmdFrequency" placeholder="{{1 par défaut}}"' + formDisabled + '/>';
  tr += '     </label>';
  tr += '   </div>';
  tr += '   <div class="input-group" style="width:100%;">';
  tr += '     <input class="cmdAttr form-control input-sm roundedLeft writeFunction" data-l1key="configuration" data-l2key="cmdWriteValue" placeholder="{{Valeur}}"' + formDisabled + '/>';
  if (!_template) {
    tr += '     <span class="input-group-btn">'
    tr += '       <a class="btn btn-default btn-sm listEquipementInfo roundedRight writeFunction" data-input="cmdWriteValue"><i class="fas fa-list-alt"></i></a>'
    tr += '     </span>'
  }
  tr += '   </div>';
  tr += ' </td>';    
  // Options
  tr += ' <td>';
  if (is_numeric(!_template && _cmd.id)) {
    tr += '   <a class="btn btn-default btn-xs cmdAction" data-action="configure" title="{{Configuration de la commande}}""><i class="fas fa-cogs"></i></a>';
    tr += '   <a class="btn btn-default btn-xs cmdAction" data-action="test" title="{{Tester}}"><i class="fas fa-rss"></i></a>';
    tr += '   <a class="btn btn-default btn-xs cmdAction" data-action="copy" title="{{Dupliquer}}"><i class="far fa-clone"></i></a>';
  }
  tr += '   <label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked' + formDisabled + '/>{{Afficher}}</label>';
  tr += '   <label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" data-size="mini"' + formDisabled + '/>{{Historiser}}</label>';
  tr += '   <div class="input-group" style="margin-top:7px;">';
  tr += '     <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;"' + formDisabled + '/>';
  tr += '     <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;"' + formDisabled + '/>';
  tr += '     <input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;max-width:100px;display:inline-block;margin-right:2px;"' + formDisabled + '/>';
  tr += '     <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de \'valeur|texte\' séparés par \';\'}}" title="{{Liste}}" style="min-width:280px;width:290px;margin-right:2px;"' + formDisabled + '>';
  tr += '   </div>';
  tr += ' </td>';
  // Delete button
  if (!_template) {
    tr += ' <td>';
    tr += '   <div class="input-group">';
    tr += '     <i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer}}"></i>';
    tr += '   </div>';
    tr += ' </td>';
  }
  tr += '</tr>';

  return tr;
}

$("#bt_add_command_top").on('click', function (event) {
  addCmdToTable({});
  modifyWithoutSave = true;
});

$("#bt_add_command_bottom").on('click', function (event) {
  addCmdToTable({});
  modifyWithoutSave = true;
});

function addCmdToTable(_cmd) {
  // Minimal structure for _cmd
  if (!isset(_cmd))
    var _cmd = {configuration: {}};
  if (!isset(_cmd.configuration))
    _cmd.configuration = {};
  // Conversion from the old version of MyModbus
  // ****************************************** info
  if (init(_cmd.type) == 'info') {
    if (isset(_cmd.configuration.location) && !isset(_cmd.configuration.cmdAddress))  {
      _cmd.configuration.cmdAddress = _cmd.configuration.location;
      delete _cmd.configuration.location;
      modifyWithoutSave = true;
    }
    if (isset(_cmd.configuration.request) && !isset(_cmd.configuration.cmdOption))  {
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
        _cmd.configuration.cmdFormat = 'uint16';
      } else if (init(_cmd.configuration.type) == 'input_registers') {
        _cmd.configuration.cmdFctModbus = '4';
        _cmd.configuration.cmdFormat = 'uint16';
      } else if (init(_cmd.configuration.type) == 'sign') {
        _cmd.configuration.cmdFctModbus = '3';
        _cmd.configuration.cmdFormat = 'int16';
      } else if (init(_cmd.configuration.type) == 'virg') {
        _cmd.configuration.cmdFctModbus = '3';
        _cmd.configuration.cmdFormat = 'float32';
      } else if (init(_cmd.configuration.type) == 'swapi32') {
        _cmd.configuration.cmdFctModbus = '4';
        _cmd.configuration.cmdFormat = 'float32';
      }
      
      delete _cmd.configuration.type;
    }
    // was never used
    delete _cmd.configuration.datatype;
    modifyWithoutSave = true;
    
  // ****************************************** action
  } else if (init(_cmd.type) == 'action') {
    if (isset(_cmd.configuration.location) && !isset(_cmd.configuration.cmdAddress))  {
      _cmd.configuration.cmdAddress = _cmd.configuration.location;
      delete _cmd.configuration.location;
      modifyWithoutSave = true;
    }
    if (isset(_cmd.configuration.request) && !isset(_cmd.configuration.cmdWriteValue))  {
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
        _cmd.configuration.cmdFormat = 'uint16';
      } else if (init(_cmd.configuration.type) == 'Write_Multiple_Holding') {
        _cmd.configuration.cmdFctModbus = '16';
        _cmd.configuration.cmdFormat = 'uint16';
      }
      
      delete _cmd.configuration.type;
    }
    // was never used
    delete _cmd.configuration.datatype;
    modifyWithoutSave = true;
  }
  
  // Default value for new added commands
  if (!isset(_cmd.id)) {
    _cmd.configuration.cmdFctModbus = '3';
    _cmd.configuration.cmdFormat = 'int16';
  }
  if (!isset(_cmd.configuration.cmdSlave))
    _cmd.configuration.cmdSlave = '0';
  if (!isset(_cmd.configuration.cmdFrequency))
    _cmd.configuration.cmdFrequency = '1';
  
  //console.log('CMD - ' + init(JSON.stringify(_cmd)));
  
  // The function getTrFromCmd returns the html code for the whole row in the table <tr>...</tr>
  var tr = getTrfromCmd(_cmd);
  $('#table_cmd tbody').append(tr);
  
  var tr = $('#table_cmd tbody tr:last');
  listSourceBlobs({
    id:  mymodbus.getEqId(),
    error: function (error) {
      $('#div_alert').showAlert({message: error.message, level: 'danger'});
    },
    success: function (resultBin, resultNum) {
      tr.find('.cmdAttr[data-l1key=configuration][data-l2key=cmdSourceBlobBin]').append(resultBin);
      tr.find('.cmdAttr[data-l1key=configuration][data-l2key=cmdSourceBlobNum]').append(resultNum);
    }
  });
  
  listSourceValues({
    id:  mymodbus.getEqId(),
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
  
  actualise_visible($(tr.find('.cmdAttr[data-l1key=type]')), 'first call');
}