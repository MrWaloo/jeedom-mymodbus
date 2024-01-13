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
//$eqLogics = mymodbus::byType('mymodbus');
?>

<div class="col-lg-3 col-md-3 col-sm-3" id="div_listMyModbus" style="height:100%">
  <div class="bs-sidebar nav nav-list bs-sidenav" style="height:calc(100%);overflow:auto;overflow-x:hidden;">
    <div class="form-group">
      <span class="btn btn-default btn-file" style="width:100%;">
        <i class="fas fa-upload"></i> {{Importer un template depuis un fichier}}<input id="bt_MyModbusTemplateUp" type="file" name="file" accept=".json" data-url="plugins/mymodbus/core/ajax/mymodbus.ajax.php?action=fileupload&amp;dir=template" style="display : inline-block;width:100%;">
      </span>
    </div>
    <legend>{{Templates existants}}</legend>
    <ul id="ul_MyModbusTemplateList" class="nav nav-list bs-sidenav"></ul>
  </div>
</div>
<div class="col-lg-9 col-md-9 col-sm-9" id="div_listMyModbusTemplate" style="display:none;height:100%">
  <form class="form-horizontal" style="height:calc(100%);overflow:auto;overflow-x:hidden;">
    <a class="btn btn-sm btn-primary" id="bt_MyModbusTemplateDownload"><i class="fas fa-cloud-download-alt"></i> {{Télécharger}}</a>
    <!--<a class='btn btn-sm btn-success pull-right' id='bt_MyModbusTemplateApply'><i class="far fa-check-circle"></i> {{Appliquer}}</a>-->
    <a class="btn btn-sm btn-danger" id="bt_MyModbusTemplateDelete"><i class="fas fa-times"></i> {{Supprimer}}</a>
    <br/>
    <legend><i class="fas fa-tachometer-alt"></i> {{Aperçu de l'équipement}}</legend>
    <div id='div_MyModbusTemplateEqlogic'></div>
    <legend><i class="fas fa-list-alt"></i> {{Aperçu des commandes}}</legend>
    <table id="table_MyModbusTemplateCmds" class="table tree table-bordered table-condensed table-striped">
      <thead>
        <tr>
          <th style="width:0px;">{{ID}}</th>
          <th style="min-width:60px;">{{Nom}}</th>
          <th style="width:60px;">{{Type}}</th>
          <th style="min-width:60px;">{{Adresse esclave}}</th>
          <th style="min-width:2300px;">{{Fonction Modbus}}</th>
          <th style="min-width:120px;width:320px;">{{Adresse Modbus}}</th>
          <th>{{Paramètres}}</th>
          <th style="min-width:95px;width:95px;">{{Options}}</th>
        </tr>
      </thead>
      <tbody>
      </tbody>
    </table>
  </form>
</div>

<script>

function refreshMymodbusTemplateList() {
  mymodbus.callPluginAjax({
    data: {
      action: "getTemplateList"
    },
    error: function(error) {
      $.fn.showAlert({message: error.message, level: 'danger'})
    },
    success: function (dataresult) {
      $('#div_listMyModbusTemplate').hide();
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

</script>

<?php

?>