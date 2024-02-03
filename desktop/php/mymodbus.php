<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('mymodbus');
sendVarToJS('eqType', $plugin->getId());
include_file('desktop', 'mymodbus.functions', 'js', 'mymodbus');
$eqLogics = eqLogic::byType($plugin->getId());

?>

<div class="row row-overflow">
  <!-- Page d'accueil du plugin -->
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
    <!-- Boutons de gestion du plugin -->
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction logoPrimary" data-action="bt_addMymodbusEq">
        <i class="fas fa-plus-circle"></i>
        <br>
        <span>{{Ajouter}}</span>
      </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
        <i class="fas fa-wrench" style="color:#0F9DE8;"></i>
        <br>
        <span>{{Configuration}}</span>
      </div>
      <div class="cursor logoSecondary" id="bt_healthmymodbus">
        <i class="fas fa-medkit" style="color:#0F9DE8;"></i>
        <br/>
        <span>{{Santé}}</span>
      </div>
      <div class="cursor logoSecondary" id="bt_templatesMymodbus">
        <i class="fas fa-cubes" style="color:#0F9DE8;"></i>
        <br/>
        <span>{{Templates}}</span>
      </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="bt_docSpecific" >
        <i class="fas fa-book" style="color:#0F9DE8;"></i>
        <br>
        <span>{{Documentation}}</span>
      </div>
    </div>
    <legend><i class="fas fa-table"></i> {{Mes équipements}}</legend>
    <?php
    if (count($eqLogics) == 0) {
      echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement MyModbus trouvé, cliquer sur "Ajouter" pour commencer}}</div>';
    } else {
      // Champ de recherche
      echo '<div class="input-group" style="margin:5px;">';
      echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
      echo '<div class="input-group-btn">';
      echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
      echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
      echo '</div>';
      echo '</div>';
      // Liste des équipements du plugin
      echo '<div class="eqLogicThumbnailContainer">';
      foreach ($eqLogics as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
        echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
        $alternateImg = $eqLogic->getConfiguration('eqProtocol');
        if (file_exists(dirname(__FILE__) . '/../../desktop/images/' . $alternateImg .'_icon.png')) {
          echo '<img class="lazy" src="plugins/mymodbus/desktop/images/' . $alternateImg .'_icon.png"/>';
        } else {	
          echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
        }
        echo '<br>';
        echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
        echo '<span class="hiddenAsCard displayTableRight hidden">';
        echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
        echo '</span>';
        echo '</div>';
      }
      echo '</div>';
    }
    ?>
  </div> <!-- /.eqLogicThumbnailDisplay -->

  <!-- Page de présentation de l'équipement -->
  <div class="col-xs-12 eqLogic" style="display:none;" id="eqLogic">
    <div class="input-group pull-right" style="display:inline-flex">
      <span class="input-group-btn">
        <a class="btn btn-primary btn-sm eqLogicAction roundedLeft tooltips" data-action="createTemplate" title="{{Créer Template}}"><i class="fas fa-cubes"></i></a>
        <a class="btn btn-warning btn-sm eqLogicAction tooltips" data-action="applyTemplate" title="{{Appliquer Template}}"><i class="fas fa-share"></i></a>
        <!-- DEBUG Export à supprimer !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! -->
        <a class="btn btn-warning btn-sm eqLogicAction tooltips" data-action="export" title="{{TEST Exporter TEST}}"><i class="fas fa-share"></i></a>
        <!-- DEBUG Export à supprimer !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! -->
        <a class="btn btn-primary btn-sm bt_showExpressionTest tooltips" title="{{Expression}}"><i class="fas fa-check"></i></a>
        <a class="btn btn-default btn-sm eqLogicAction tooltips" data-action="configure" title="{{Configuration avancée}}"><i class="fas fa-cogs"></i></a>
        <a class="btn btn-default btn-sm eqLogicAction tooltips" data-action="copy" title="{{Dupliquer}}"><i class="fas fa-copy"></i></a>
        <a class="btn btn-success btn-sm eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
        <a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
      </span>
    </div>
    <!-- Onglets -->
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
      <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
      <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
    </ul>
    <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x:hidden;">
      <!-- Onglet de configuration de l'équipement -->
      <div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <form class="form-horizontal">
          <fieldset>
            
            <!-- Affichage de la configuration de l'équipement -->
            <div id='div_MyModbusEqlogic'></div>

          </fieldset>
        </form>
      </div><!-- /.tabpanel #eqlogictab-->
      
      <!-- Onglet des commandes de l'équipement -->
      <div role="tabpanel" class="tab-pane" id="commandtab">
        <div class="input-group pull-right" style="display:inline-flex;margin-top:5px;">
          <span class="input-group-btn">
            <a class="btn btn-warning btn-sm roundedRight" id="bt_add_command_top"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}} </a>
          </span>
        </div>
        <br/><br/>
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:100px;width:300px;">{{Nom}}</th>
                <th style="min-width:80px;">{{Valeur}}</th>
                <th style="width:100px;">{{Type}}</th>
                <th style="min-width:80px;width:130px;">{{Adresse esclave}}
                  <sup><i class="fas fa-question-circle tooltips" title="{{'0' si pas de bus série}}"></i></sup>
                </th>
                <th style="width:230px;">{{Fonction Modbus}}</th>
                <th style="min-width:120px;width:320px;">{{Adresse Modbus}}</th>
                <th>{{Paramètres}}</th>
                <th style="min-width:300px;width:310px;">{{Options}}</th>
                <th style="width:15px;">&nbsp;</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
        <div class="input-group pull-right" style="display:inline-flex;margin-top:5px;">
          <span class="input-group-btn">
            <a class="btn btn-warning btn-sm roundedRight" id="bt_add_command_bottom"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}} </a>
          </span>
        </div>
      </div><!-- /.tabpanel #commandtab-->
    </div><!-- /.tab-content -->
  </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<script>

$('#div_MyModbusEqlogic').load('index.php?v=d&plugin=mymodbus&modal=eqConfig');

</script>

<?php
include_file('desktop', 'mymodbus', 'js', 'mymodbus');
include_file('core', 'plugin.template', 'js');
?>