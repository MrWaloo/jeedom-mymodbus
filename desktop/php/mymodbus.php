<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('mymodbus');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

?>

<div class="row row-overflow">
    <!-- Page d'accueil du plugin -->
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
        <!-- Boutons de gestion du plugin -->
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoPrimary" data-action="add">
                <i class="fas fa-plus-circle"style="font-size:6em;color:#0F9DE8;"></i>
                <br>
                <span>{{Ajouter}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                <i class="fas fa-wrench"style="font-size:6em;color:#0F9DE8;"></i>
                <br>
                <span>{{Configuration}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" data-action="bt_docSpecific" >
                <i class="fas fa-book"style="font-size:6em;color:#0F9DE8;"></i>
                <br>
                <span>{{Documentation}}</span>
            </div>
            <!--
            <div class="cursor pluginAction" data-action="openLink" data-location="https://community.jeedom.com/t/plugin-<?=$plugin->getId()?>/9395" >
                <i class="fas fa-comments" style="font-size:6em;color:#0F9DE8;"></i>
                <br>
                <span>{{Commmunity}}</span>
            </div>
            -->
            <div class="cursor logoSecondary" id="bt_healthmymodbus">
                <i class="fas fa-medkit"style="font-size:6em;color:#0F9DE8;"></i>
                <br/>
                <span>{{Santé}}</span>
            </div>
            <div class="cursor logoSecondary" id="bt_templatesMymodbus">
                <i class="fas fa-cubes"style="font-size:6em;color:#0F9DE8;"></i>
                <br/>
                <span>{{Templates}}</span>
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
                <!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
                <a class="btn btn-primary btn-sm bt_showExpressionTest roundedLeft"><i class="fas fa-check"></i> {{Expression}}
                </a><a <a class="btn btn-default btn-sm eqLogicAction" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}
                </a><a class="btn btn-default btn-sm eqLogicAction" data-action="copy"><i class="fas fa-copy"></i> {{Dupliquer}}
                </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
                </a><a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
                </a>
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
                        <!-- Partie gauche de l'onglet "Equipement" -->
                        <div class="col-lg-6">
                            <legend><i class="fa fa-wrench"></i> {{Equipement :}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
                                <div class="col-sm-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;" />
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Objet parent}}</label>
                                <div class="col-sm-6">
                                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                        <option value="">{{Aucun}}</option>
                                        <?php
                                        foreach ((jeeObject::buildTree(null, false)) as $object)
                                            echo '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Catégorie}}</label>
                                <div class="col-sm-6">
                                    <?php
                                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                        echo '<label class="checkbox-inline">';
                                        echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" >' . $value['name'];
                                        echo '</label>';
                                    }
                                    ?>
                               </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Options}}</label>
                                <div class="col-sm-6">
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
                                </div>
                            </div>
                            
                            <!--   ***********************************  -->
                            <legend><i class="fa fa-list-alt"></i> {{Configuration :}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Protocol de connexion}}</label>
                                <div class="col-sm-6">
                                    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqProtocol">
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
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="eqKeepopen"/>{{Garder la connexion ouverte}}</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Mode de rafraîchissement}}</label>
                                <div class="col-sm-6">
                                    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRefreshMode">
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
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqPolling" placeholder="60"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Timeout pour vérification d'une commande action}}
                                    <sup><i class="fas fa-question-circle tooltips" title="{{Temps aloué à la vérification de l'envoi d'une commande action par Jeedom, minimum 0.1}}"></i></sup>
                                </label>
                                <div class="col-sm-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqWriteCmdCheckTimeout" placeholder="1"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Temps entre la connexion et la première requête}}</label>
                                <div class="col-sm-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqFirstDelay" placeholder="0"/>
                                </div>
                            </div>
                            
                            <!-- Paramètres propres au protocol "desktop/modal/configuration.*.php" -->
                            <div id="div_protocolParameters"></div>
                            
                        </div>
                        
                        <!-- Partie basse ou droite de l'onglet "Équipement" -->
                        <div class="col-lg-6">
                            <legend><i class="fas fa-info"></i>{{Informations}}</legend>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">{{Notes}}</label>
                                <div class="col-sm-8">
                                    <textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div><!-- /.tabpanel #eqlogictab-->
            
            <!-- Onglet des commandes de l'équipement -->
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <div class="input-group pull-right" style="display:inline-flex;margin-top:5px;">
                    <span class="input-group-btn">
                        <a class="btn btn-warning btn-sm roundedRight" id="bt_add_command"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}} </a>
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
            </div><!-- /.tabpanel #commandtab-->
        </div><!-- /.tab-content -->
    </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<?php
include_file('desktop', 'mymodbus', 'js', 'mymodbus');
include_file('core', 'plugin.template', 'js');
?>