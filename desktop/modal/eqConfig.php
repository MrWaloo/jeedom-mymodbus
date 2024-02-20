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

$colSmClass = 'col-sm-6';
$disabled = '';
if (init('template') !== '') {
  $colSmClass = 'col-sm-12';
  $disabled = ' disabled';
}
?>

<!-- Partie gauche de l'onglet "Equipement" -->
<div class="<?= $colSmClass ?>">
  <legend><i class="fa fa-wrench"></i> {{Equipement :}}</legend>
  <div class="form-group">
    <label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
    <div class="col-sm-6">
      <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;" />
      <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"<?= $disabled ?>/>
    </div>
  </div>
  <?php
  if (init('template') === '') {
  ?>
  <div class="form-group">
    <label class="col-sm-4 control-label">{{Objet parent}}</label>
    <div class="col-sm-6">
      <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id"<?= $disabled ?>>
        <option value="">{{Aucun}}</option>
        <?php
        foreach ((jeeObject::buildTree(null, false)) as $object)
          echo '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
        ?>
      </select>
    </div>
  </div>
  <?php
  }
  ?>
  <div class="form-group eqCategories">
    <label class="col-sm-4 control-label">{{Catégorie}}</label>
    <div class="col-sm-6">
      <?php
      foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
        echo '<label class="checkbox-inline">';
        echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '"' . $disabled . '>' . $value['name'];
        echo '</label>';
      }
      ?>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-4 control-label">{{Options}}</label>
    <div class="col-sm-6">
      <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked<?= $disabled ?>/>{{Activer}}</label>
      <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked<?= $disabled ?>/>{{Visible}}</label>
    </div>
  </div>
  
  <!--   ***********************************  -->
  <legend><i class="fa fa-list-alt"></i> {{Configuration :}}</legend>
  <div class="form-group">
    <label class="col-sm-4 control-label">{{Protocol de connexion}}</label>
    <div class="col-sm-6">
      <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqProtocol"<?= $disabled ?>>
        <option disabled selected value>-- {{Choisir un protocol de connexion}} --</option>
        <?php
        foreach (mymodbus::supportedProtocols() as $protocol)
          echo '<option value="' . $protocol . '">' . $protocol . '</option>';
        ?>
      </select>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-4 control-label"></label>
    <div class="col-sm-6">
      <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="eqKeepopen"<?= $disabled ?>/>{{Garder la connexion ouverte}}</label>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-4 control-label">{{Mode de rafraîchissement}}</label>
    <div class="col-sm-6">
      <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRefreshMode"<?= $disabled ?>>
        <option disabled selected value>-- {{Selectionnez un mode}} --</option>
        <option value="polling">{{Polling}}</option>
        <option value="cyclic">{{Cyclique}}</option>
          <option value="on_event">{{Sur événement}}</option>
      </select>
    </div>
  </div>
  <div class="form-group" id="eqPolling">
    <label class="col-sm-4 control-label">{{Polling en secondes}}
      <sup><i class="fas fa-question-circle tooltips" title="{{En mode Polling: raffraichissement des valeurs toutes les n secondes, minimum 1}}"></i></sup>
    </label>
    <div class="col-sm-6">
      <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqPolling" placeholder="60"<?= $disabled ?>/>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-4 control-label">{{Timeout pour vérification d'une commande action}}
      <sup><i class="fas fa-question-circle tooltips" title="{{Temps aloué à la vérification de l'envoi d'une commande action par Jeedom, minimum 0.1}}"></i></sup>
    </label>
    <div class="col-sm-6">
      <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqWriteCmdCheckTimeout" placeholder="1"<?= $disabled ?>/>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-4 control-label">{{Temps entre la connexion et la première requête}}</label>
    <div class="col-sm-6">
      <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqFirstDelay" placeholder="0"<?= $disabled ?>/>
    </div>
  </div>
  <!-- Paramètres propres au protocol "desktop/modal/eqConfig_*.php" -->
  <?php
  if (init('template') === '') {
  ?>
  <div id="div_protocolParameters"></div>
  <?php
  } else {
  ?>
  <div id="div_protocolTmplParameters"></div>
  <?php
  }
  ?>
</div>

<div class="<?= $colSmClass ?>">
  <legend><i class="fas fa-info"></i>{{Informations}}</legend>
  <div class="form-group">
    <label class="col-sm-2 control-label">{{Notes}}</label>
    <div class="col-sm-8">
      <textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"<?= $disabled ?>></textarea>
    </div>
  </div>
</div>