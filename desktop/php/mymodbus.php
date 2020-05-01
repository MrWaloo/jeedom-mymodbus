<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('mymodbus');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

$deamonRunning = mymodbus::deamon_info();
    if ($deamonRunning['state'] != 'ok') {
        echo '<div class="alert alert-danger">ATTENTION LE DEMON MYMODBUS NE TOURNE PAS , Avant de le lancer il faut toujours avoir un équipement MyModbus de céer ! </div>';
    }
	
?>

<div class="row row-overflow">
   <div class="col-xs-12 eqLogicThumbnailDisplay">
  <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
  <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction logoPrimary" data-action="add">
        <i class="fas fa-plus-circle"style="font-size : 6em;color:#337aff;"></i>
        <br>
        <span>{{Ajouter}}</span>
    </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
      <i class="fas fa-wrench"style="font-size : 6em;color:#337aff;"></i>
    <br>
    <span>{{Configuration}}</span>
  </div>
  <div class="cursor eqLogicAction logoSecondary" data-action="bt_docSpecific" >
		<i class="fas fa-book"style="font-size : 6em;color:#337aff;"></i>
 		<br>
		<span>{{Documentation}}</span>
		</div>
  <div class="cursor logoSecondary" id="bt_healthmymodbus">
				<i class="fas fa-medkit"style="font-size : 6em;color:#337aff;"></i>
				<br/>
				<span>{{Santé}}</span>
			</div>
  </div>
  <legend><i class="fas fa-table"></i> {{Mes équipements}}</legend>
	   <input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
<div class="eqLogicThumbnailContainer">
    <?php
foreach ($eqLogics as $eqLogic) {
	$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
	echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
	$alternateImg = $eqLogic->getConfiguration('protocol');
	if (file_exists(dirname(__FILE__) . '/../../ressources/images/' . $alternateImg ._icon . '.png')) {
		echo '<img class="lazy" src="plugins/mymodbus/ressources/images/' . $alternateImg ._icon . '.png"/>';
	} else {	
	echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
	}
	echo '<br>';
	echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
	echo '</div>';
}
?>
</div>
</div>

<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex">
			<span class="input-group-btn">
				<a class="btn btn-primary btn-sm bt_showNoteManagement roundedLeft"><i class="fas fa-file"></i> {{Notes}}</a><a class="btn btn-primary btn-sm bt_showExpressionTest roundedLeft"><i class="fas fa-check"></i> {{Expression}}</a><a <a class="btn btn-default btn-sm eqLogicAction" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}</a><a class="btn btn-default btn-sm eqLogicAction" data-action="copy"><i class="fas fa-copy"></i> {{Dupliquer}}</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a><a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
			</span>
		</div>
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
    <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
	<li role="presentation"><a href="#filtrestab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-cog"></i> {{Logic}}</a></li>
  </ul>
  <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
    <div role="tabpanel" class="tab-pane active" id="eqlogictab">
      <br/>
    <form class="form-horizontal">
	<legend><i class="fa fa-wrench"></i> {{Equipement :}}</legend>
        <fieldset>
            <div class="form-group">
                <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
                <div class="col-sm-3">
                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label" >{{Objet parent}}</label>
                <div class="col-sm-3">
                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                        <option value="">{{Aucun}}</option>
                        <?php
foreach (jeeObject::all() as $object) {
	echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
}
?>
                   </select>
               </div>
           </div>
	   <div class="form-group">
                <label class="col-sm-3 control-label">{{Catégorie}}</label>
                <div class="col-sm-9">
                 <?php
                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                    echo '<label class="checkbox-inline">';
                    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                    echo '</label>';
                    }
                  ?>
               </div>
           </div>
	<div class="form-group">
		<label class="col-sm-3 control-label"></label>
		<div class="col-sm-9">
			<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
			<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
		</div>
		<legend><i class="fa fa-list-alt"></i> {{Configuration :}}</legend>
		<!--   ***********************************  -->
	</div>
	   <div class="form-group">
         <label class="col-sm-3 control-label">{{Mode de connection}}</label>
            <div class="col-sm-3">
                <select id="mode" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="protocol">
					<option disabled selected value>-- {{Choisir un mode de connection}} --</option>
					<?php
foreach (mymodbus::supportedProtocol() as $protocol) {
    echo '<option value="' . $protocol . '">' . $protocol . '</option>';
  }
?>
				</select>
               </div>
           </div>
       </fieldset>
<div>
      
        <fieldset>
		<div id="div_protocolParameters"></div>
        </fieldset>
    </form>
</div>
</div>
      <div role="tabpanel" class="tab-pane" id="commandtab">
<a class="btn btn-default btn-sm pull-right" id="bt_add_Info" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une info}}</a>
<a class="btn btn-default btn-sm  pull-right" id="bt_add_Action" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une action}}</a><br/><br/>
<table id="table_cmd" class="table table-bordered table-condensed">
    <thead>
        <tr>
            <!--<th>{{Nom}}</th><th>{{Type}}</th><th>{{Action}}</th> -->
			<th style="width: 200px;">{{Nom}}</th>
            <th style="width: 100px;">{{Type}}</th>
            <th style="width: 150px;">{{Type E/S}}</th>
            <th style="width: 100px;">{{Adresse}}</th>
            <th>{{Parametre(s)}}</th>
			<th style="width: 100px;">{{Options}}</th>
			<th>{{Configuration}}</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>

</div>
<div role="tabpanel" class="tab-pane" id="filtrestab">
					<br/>
					<fieldset>
						<form class="form-horizontal">
							<legend><i class="fa fa-list-alt"></i> {{Filtres}}
								<a class="btn btn-default btn-xs pull-right" style="margin-right:15px;" id="bt_addFiltres"><i class="fas fa-plus"></i> {{Ajouter}}</a>
							</legend>
							<table class="table table-condensed" id="table_mymodbusFilters">
								<thead>
									<tr>
										<th style="width: 50px;"> ID</th>
										<th style="width: 230px;">{{Nom}}</th>
										<th style="width: 110px;">{{Sous-Type}}</th>
										<th>{{Valeur}}</th>
									</tr>
								</thead>
								<tbody>
									
								</tbody>
							</table>
						</fieldset>
					</form>
					<br/>
					<div class="alert alert-info">{{Dans cet onglet vous allez définir les filtres pour vos registres d'entrées : <br>
						- Exemple_1.
						}}
					</div>
</div>

</div>
</div>

<?php include_file('desktop', 'mymodbus', 'js', 'mymodbus');?>
<?php include_file('core', 'plugin.template', 'js');?>