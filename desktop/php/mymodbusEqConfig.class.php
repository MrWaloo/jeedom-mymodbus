<?php

class mymodbusEqConfig {

  public static function show($_is_template = false) {
    $colSmClass = 'col-sm-6';
    $disabled = '';
    if ($_is_template) {
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
      if (!$_is_template) {
      ?>
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
      
      <!-- *********************************** -->
      <legend><i class="fa fa-list-alt"></i> {{Configuration :}}</legend>
      <div class="form-group">
        <label class="col-sm-4 control-label">{{Protocol de connexion}}</label>
        <div class="col-sm-6">
          <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqProtocol"<?= $disabled ?>>
            <option disabled selected value>-- {{Choisir un protocol de connexion}} --</option>
            <?php
            foreach (mymodbus::supportedProtocols() as $protocol) {
              $prot_name = $protocol === 'shared_from' ? __("Interface d'un autre équipement", __FILE__) : $protocol;
              echo '<option value="' . $protocol . '">' . $prot_name . '</option>';
            }
            ?>
          </select>
        </div>
      </div>
      <div id="div_sharedInterface">
        <?php
        self::show_shared_interface();
        ?>
      </div>
      <!-- Paramètres propres au protocol -->
      <div id="div_protocolParameters">
        <div class="form-group nonShared">
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
        <div class="form-group nonShared" id="eqPolling">
          <label class="col-sm-4 control-label">{{Polling (s)}}
            <sup><i class="fas fa-question-circle tooltips" title="{{En mode Polling: raffraichissement des valeurs toutes les n secondes, minimum 1}}"></i></sup>
          </label>
          <div class="col-sm-6">
            <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqPolling" placeholder="60"<?= $disabled ?>/>
          </div>
        </div>
        <div class="form-group nonShared" id="eqTimeout">
          <label class="col-sm-4 control-label">{{Timeout (s)}}
            <sup><i class="fas fa-question-circle tooltips" title="{{Temps maximum d'attente de réponse à une requête}}"></i></sup>
          </label>
          <div class="col-sm-6">
            <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqTimeout" placeholder="60"<?= $disabled ?>/>
          </div>
        </div>
        <div class="form-group nonShared">
          <label class="col-sm-4 control-label">{{Nombre de tentatives en cas d'erreur}}</label>
          <div class="col-sm-6">
            <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRetries" placeholder="3"<?= $disabled ?>/>
          </div>
        </div>
        <div class="form-group nonShared">
          <label class="col-sm-4 control-label">{{Temps entre 2 requêtes de lecture (s)}}
            <sup><i class="fas fa-question-circle tooltips" title="{{Egalement le temps aloué à la vérification de l'envoi d'une commande action par Jeedom}}"></i></sup>
          </label>
          <div class="col-sm-6">
            <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqWriteCmdCheckTimeout" placeholder="1"<?= $disabled ?>/>
          </div>
        </div>
        <div class="form-group nonShared">
          <label class="col-sm-4 control-label">{{Temps d'attente après la connexion (s)}}</label>
          <div class="col-sm-6">
            <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqFirstDelay" placeholder="0"<?= $disabled ?>/>
          </div>
        </div>
        <div class="form-group nonShared">
          <label class="col-sm-4 control-label">{{Temps d'attente après une erreur de lecture (s)}}</label>
          <div class="col-sm-6">
            <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqErrorDelay" placeholder="0"<?= $disabled ?>/>
          </div>
        </div>
        <?php
        self::show_network_config();
        self::show_serial_config();
        ?>
      </div>
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
    <?php
  }

  static function show_shared_interface() {
    ?>
    <div class="form-group sharedInterface">
      <label class="col-sm-4 control-label">{{Utilisation de l'interface de l'équipement}}</label>
      <div class="col-sm-6">
        <select id="sharedInterface" class="eqLogicAttr form-control sharedInterface" data-toggle="tooltip" data-placement="top" data-html="true" data-l1key="configuration" data-l2key="eqInterfaceFromEqId">
        <?php
          foreach (mymodbus::getSharedInterfaces() as $eqId => $eq_name) {
            echo '<option title="' . $eq_name . '" value="' . $eqId . '">' . $eq_name . '</option>';
          }
          ?>
        </select>
      </div>
    </div>
    <?php
  }

  static function show_network_config() {
    ?>
    <div class="form-group networkConfig" hidden>
      <label class="col-sm-4 control-label">{{Adresse IP}}</label>
      <div class="col-sm-6">
        <input type="text" class="eqLogicAttr form-control networkConfig" data-l1key="configuration" data-l2key="eqAddr" placeholder="192.168.1.55"/>
      </div>
    </div>

    <div class="form-group networkConfig" hidden>
      <label class="col-sm-4 control-label">{{Port}}</label>
      <div class="col-sm-6">
        <input type="text" class="eqLogicAttr form-control networkConfig" data-l1key="configuration" data-l2key="eqPortNetwork" placeholder="502"/>
      </div>
    </div>
    <?php
  }

  static function show_serial_config() {
    ?>
    <div class="form-group serialConfig" hidden>
      <label class="col-sm-4 control-label">{{Interface}}</label>
      <div class="col-sm-6">
        <select class="eqLogicAttr form-control serialConfig" data-toggle="tooltip" data-placement="top" data-html="true" data-l1key="configuration" data-l2key="eqPortSerial">
          <?php
          foreach (mymodbus::getTtyInterfaces() as $key => $value) {
            echo '<option title="' . $value . '" value="' . $value . '">' . $key . '</option>';
          }
          ?>
        </select>
      </div>
    </div>

    <div class="form-group serialConfig" hidden>
      <label class="col-sm-4 control-label">{{Méthode de transport}}</label>
      <div class="col-sm-6">
        <select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialMethod">
          <option value="rtu">{{RTU}}</option>
          <option value="ascii">{{ASCII}}</option>
        </select>
      </div>
    </div>

    <div class="form-group serialConfig" hidden>
      <label class="col-sm-4 control-label">{{Vitesse de transmission}}</label>
      <div class="col-sm-6">
        <select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialBaudrate">
          <option disabled selected value>-- {{Selectionnez une valeur}} --</option>
          <option value="300">300 {{bauds}}</option>
          <option value="600">600 {{bauds}}</option>
          <option value="1200">1200 {{bauds}}</option>
          <option value="2400">2400 {{bauds}}</option>
          <option value="4800">4800 {{bauds}}</option>
          <option value="9600">9600 {{bauds}}</option>
          <option value="14400">14400 {{bauds}}</option>
          <option value="19200">19200 {{bauds}}</option>
          <option value="38400">38400 {{bauds}}</option>
          <option value="56000">56000 {{bauds}}</option>
          <option value="57600">57600 {{bauds}}</option>
          <option value="115200">115200 {{bauds}}</option>
          <option value="128000">128000 {{bauds}}</option>
          <option value="230400">230400 {{bauds}}</option>
          <option value="256000">256000 {{bauds}}</option>
        </select>
      </div>
    </div>

    <div class="form-group serialConfig" hidden>
      <label class="col-sm-4 control-label">{{Nombre de bits par octet}}</label>
      <div class="col-sm-6">
        <select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialBytesize">
          <option disabled selected value>-- {{Selectionnez une valeur}} --</option>
          <option value="7">7</option>
          <option value="8">8</option>
        </select>
      </div>
    </div>

    <div class="form-group serialConfig" hidden>
      <label class="col-sm-4 control-label">{{Parité}}</label>
      <div class="col-sm-6">
        <select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialParity">
          <option disabled selected value>-- {{Selectionnez une valeur}} --</option>
          <option value="E">{{Paire}}</option>
          <option value="O">{{Impaire}}</option>
          <option value="N">{{Aucune}}</option>
        </select>
      </div>
    </div>

    <div class="form-group serialConfig" hidden>
      <label class="col-sm-4 control-label">{{Bits de stop}}</label>
      <div class="col-sm-6">
        <select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialStopbits">
          <option disabled selected value>-- {{Selectionnez une valeur}} --</option>
          <option value="0">0</option>
          <option value="1">1</option>
          <option value="2">2</option>
        </select>
      </div>
    </div>
    <?php
  }
}

?>