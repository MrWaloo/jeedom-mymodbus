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
require_once __DIR__ . '/../php/mymodbusEqConfig.class.php';
?>

<div class="col-lg-2 col-md-2 col-sm-2" style="height:100%">
  <div class="bs-sidebar nav nav-list bs-sidenav" style="height:calc(100%);overflow:auto;overflow-x:hidden;">
    <div class="form-group">
      <span class="btn btn-default btn-file" style="width:100%;">
        <i class="fas fa-upload"></i> {{Importer un template depuis un fichier}}<input id="bt_MyModbusTemplateUp" type="file" name="file" accept=".json" data-url="plugins/mymodbus/core/ajax/mymodbus.ajax.php?action=fileupload" style="display : inline-block;width:100%;">
      </span>
    </div>
    <legend>{{Templates existants}}</legend>
    <ul id="ul_MyModbusTemplateList" class="nav nav-list bs-sidenav"></ul>
  </div>
</div>
<div class="col-lg-10 col-md-10 col-sm-10" id="div_MyModbusTemplate" style="display:none;height:100%">
  <form class="form-horizontal" style="height:calc(100%);overflow:auto;overflow-x:hidden;">
    <a class="btn btn-sm btn-primary" id="bt_MyModbusTemplateDownload" hidden><i class="fas fa-cloud-download-alt"></i> {{Télécharger}}</a>
    <a class="btn btn-sm btn-danger" id="bt_MyModbusTemplateDelete"><i class="fas fa-times"></i> {{Supprimer}}</a>
    <br>
    <legend><i class="fas fa-tachometer-alt"></i> {{Aperçu de l'équipement}}</legend>
    <div id='div_MyModbusTemplateEqlogic'>
      <?php
        mymodbusEqConfig::show(true);
      ?>
    </div>
    <br>
    <legend><i class="fas fa-list-alt"></i> {{Aperçu des commandes}}</legend>
    <table id="table_MyModbusTemplateCmds" class="table tree table-bordered table-condensed table-striped">
      <thead>
        <tr>
          <th style="min-width:60px;">{{Nom}}</th>
          <th style="width:60px;">{{Type}}</th>
          <th style="width:80px;">{{Adresse esclave}}</th>
          <th style="min-width:230px;width:230px">{{Fonction Modbus}}</th>
          <th style="min-width:200px;width:200px;">{{Adresse Modbus}}</th>
          <th>{{Paramètres}}</th>
          <th style="min-width:130px;width:130px;">{{Options}}</th>
        </tr>
      </thead>
      <tbody>
      </tbody>
    </table>
  </form>
</div>

<script>

$('#bt_MyModbusTemplateUp').fileupload({
  dataType: 'json',
  replaceFileInput: false,
  done: function (e, data) {
    if (data.result.state != 'ok') {
      $.fn.showAlert({message: data.result.result, level: 'danger'});
    } else {
      $.fn.showAlert({message: 'Template ajouté avec succès', level: 'success'});
      refreshMymodbusTemplateList();
    }
    $('#bt_MyModbusTemplateUp').val(null);
  }
});

function refreshMymodbusTemplateList() {
  mymodbus.callPluginAjax({
    data: {
      action: "getTemplateList"
    },
    error: function(error) {
      $.fn.showAlert({message: error.message, level: 'danger'})
    },
    success: function (dataresult) {
      $('#div_MyModbusTemplate').hide();
      $('#ul_MyModbusTemplateList').empty();
      li = ''
      for (var i in dataresult) {
        li += "<li class='cursor li_mymodbusTemplate' data-name='" + dataresult[i][0] + "' data-file='" + dataresult[i][1] + "'><a>" + dataresult[i][0] + "</a></li>";
      }
      $('#ul_MyModbusTemplateList').html(li);
    }
  });
}
refreshMymodbusTemplateList();

$('#ul_MyModbusTemplateList').on('click', '.li_mymodbusTemplate', function(event) {
  $('#bt_MyModbusTemplateDownload').show();
  $('#div_MyModbusTemplate').hide();
  $('#ul_MyModbusTemplateList .li_mymodbusTemplate').removeClass('active');
  $('#table_MyModbusTemplateCmds tbody').empty();
  $(this).addClass('active');
  if ($('#ul_MyModbusTemplateList li.active').attr('data-name').startsWith('[User] ')) {
    $('#bt_MyModbusTemplateDelete').show();
  } else {
    $('#bt_MyModbusTemplateDelete').hide();
  }
  
  mymodbus.callPluginAjax({
    data: {
      action: "getTemplateByFile",
      file: $(this).attr('data-file')
    },
    error: function(error) {
      $.fn.showAlert({message: error.message, level: 'danger'});
    },
    success: function (data) {
      // Gestion de l'équipement
      $('#div_MyModbusTemplate').show();
      $('#div_MyModbusTemplate').setValues(data, '.eqLogicAttr');
      var show_network = (data.configuration.eqProtocol !== 'serial');
      const networkConfig = $('#div_protocolParameters .networkConfig');
      const serialConfig = $('#div_protocolParameters .serialConfig');
      if (show_network) {
        networkConfig.show();
        serialConfig.hide();
      } else {
        networkConfig.hide();
        serialConfig.show();
      }
      networkConfig.prop('disabled', true);
      serialConfig.prop('disabled', true);

      // Configuration des commandes
      for (let _cmd of data['commands']) {
        if (isset(_cmd.configuration) && !isset(_cmd.logicalId)) {
          var tr = getTrfromCmd(_cmd, true);
          $('#table_MyModbusTemplateCmds tbody').append(tr);

          var tr = $('#table_MyModbusTemplateCmds tbody tr:last');
          
          if (_cmd.type == 'action' && isset(_cmd.value)) {
            option = '<option>' + _cmd.value + '</option>';
            tr.find('.cmdAttr[data-l1key=value]').append(option);
          }

          tr.setValues(_cmd, '.cmdAttr');
          jeedom.cmd.changeType(tr, init(_cmd.subType));
          tr.find('.cmdAttr[data-l1key=type]').prop('disabled', true);
          tr.find('.cmdAttr[data-l1key=subType]').prop('disabled', true);

          if (isset(_cmd.configuration.cmdFctModbus) && _cmd.configuration.cmdFctModbus == 'fromBlob') {
            let cmdSourceBlob = '';
            if (_cmd.subType == 'binary') {
              cmdSourceBlob = _cmd.configuration.cmdSourceBlobBin;
            } else {
              cmdSourceBlob = _cmd.configuration.cmdSourceBlobNum;
            }
            if (cmdSourceBlob.slice(0, 2) == '#[' && cmdSourceBlob.slice(-2) == ']#') {
              cmdSourceBlob = cmdSourceBlob.slice(2, -2);
            } else {
              cmdSourceBlob = '{{**Erreur format**}}';
            }
            cmdSourceBlob = '<option>' + cmdSourceBlob + '</option>';

            if (_cmd.subType == 'binary') {
              tr.find('.cmdAttr[data-l1key=configuration][data-l2key=cmdSourceBlobBin]').append(cmdSourceBlob);
            } else {
              tr.find('.cmdAttr[data-l1key=configuration][data-l2key=cmdSourceBlobNum]').append(cmdSourceBlob);
            }
          }
          actualise_visible($(tr.find('.cmdAttr[data-l1key=type]')), 'first call', true);
        }
      }
      $('#div_MyModbusTemplate').children(0).scrollTop(0);
    }
  });
});

$('#bt_MyModbusTemplateDownload').on('click', function() {
  filename = $('#ul_MyModbusTemplateList li.active').attr('data-file');
  dataname = $('#ul_MyModbusTemplateList li.active').attr('data-name');
  if (dataname == undefined) {
    $.fn.showAlert({message: "{{Sélectionnez d'abord un template}}", level: 'danger'});
    return;
  }
  window.open('core/php/downloadFile.php?pathfile=' + filename, "_blank", null);
});

$('#bt_MyModbusTemplateDelete').on('click', function() {
  filename = $('#ul_MyModbusTemplateList li.active').attr('data-file');
  dataname = $('#ul_MyModbusTemplateList li.active').attr('data-name');
  if (dataname == undefined) {
    $.fn.showAlert({message: "{{Sélectionnez d'abord un template}}", level: 'danger'});
    return;
  }
  bootbox.confirm('{{Êtes-vous sûr de vouloir supprimer ce template :}}' + ' \'' + dataname + '\' ?', function(result) {
    if (result) {
      mymodbus.callPluginAjax({
        data: {
          action: "deleteTemplateByFile",
          file: filename,
        },
        error: function(error) {
          $.fn.showAlert({message: error.message, level: 'danger'});
        },
        success: function(data) {
          if (data) {
            $.fn.showAlert({message: '{{Template supprimé.}}', level: 'success'});
          } else
            $.fn.showAlert({message: '{{Ce template ne peut pas être supprimé.}}', level: 'danger'});
          refreshMymodbusTemplateList();
        }
      });
    }
  });
});

</script>