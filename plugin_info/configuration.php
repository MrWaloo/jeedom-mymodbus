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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="col-sm-6">
      <legend><i class="icon loisir-darth"></i>{{Gestion du démon}}</legend>
      <div class="form-group expertModeVisible">
        <label class="col-sm-8 control-label">{{Port du socket interne (pour éviter un conflit avec un autre plugin)&nbsp;:}}
          <i class="fas fa-question-circle tooltips" title="{{55502 par défaut si non précisé}}"></i>
        </label>
        <div class="col-sm-4">
          <input class="configKey form-control" data-l1key="socketport" placeholder="55502"/>
        </div>
      </div>
      <legend><i class="icon loisir-darth"></i>{{Interfaces série personnalisées}}</legend>
      <div class="form-group expertModeVisible">
        <label class="col-sm-8 control-label">{{Liste des interfaces personnalisées&nbsp;:}}
          <i class="fas fa-question-circle tooltips" title="{{séparées par des ';'}}"></i>
        </label>
        <div class="col-sm-4">
          <input class="configKey form-control" data-l1key="interfaces"/>
        </div>
      </div>
    </div>
    <div class="col-sm-6">
      <legend><i class="icon loisir-darth"></i>{{Suppression du répertoire pyenv}}</legend>
      <label class="col-sm-2 control-label">&nbsp;
        <i class="fas fa-question-circle tooltips" title="{{L'installation des dépendances doit être relancée après la suppression}}"></i>
      </label>
      <div class="col-sm-10">
        <a class="btn btn-danger" id="bt_RemovePyenv" style="width:150px;"><i class="fas fa-trash"></i> {{Supprimer pyenv}}</a>
      </div>
    </div>
  </fieldset>
</form>

<script>
  $('#bt_RemovePyenv').on('click', function() { // bouton sauvegarde des modifs mode de log
    bootbox.confirm('{{Êtes-vous sûr de vouloir supprimer pyenv ?}}', function(result) {
      if (!result)
        return;
      $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "plugins/mymodbus/core/ajax/mymodbus.ajax.php", // url du fichier php
        data: {
          action: "RemovePyenv"
        },
        dataType: 'json',
        error: function (request, status, error) {
          handleAjaxError(request, status, error);
        },
        success: function (data) { // si l'appel a bien fonctionné
          if (data.state != 'ok') {
            $.fn.showAlert({message: data.result, level: 'danger'});
            return;
          }
          $.fn.showAlert({message: '{{Pyenv a bien été supprimé, relancez l\'installation des dépendances}}', level: 'success'});
        }
      });
    });
  });
  $('#bt_savePluginLogConfig').on('click', function() { // bouton sauvegarde des modifs mode de log
    $.ajax({// fonction permettant de faire de l'ajax
      type: "POST", // methode de transmission des données au fichier php
      url: "plugins/mymodbus/core/ajax/mymodbus.ajax.php", // url du fichier php
      data: {
        action: "changeLogLevel",
        level: $('#div_plugin_log').getValues('.configKey')[0]
      },
      dataType: 'json',
      error: function (request, status, error) {
        handleAjaxError(request, status, error);
      },
      success: function (data) { // si l'appel a bien fonctionné
        if (data.state != 'ok') {
          $.fn.showAlert({message: data.result, level: 'danger'});
          return;
        }
        $.fn.showAlert({message: '{{Changement réussi, inutile de redémarrer le démon}}', level: 'success'});
      }
    });
  });
</script>