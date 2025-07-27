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
			<legend><i class="fa fa-wrench"></i> <?= __("Equipement :", __FILE__) ?></legend>
			<div class="form-group">
				<label class="col-sm-6 control-label"><?= __("Nom de l'équipement", __FILE__) ?></label>
				<div class="col-sm-6">
					<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;" />
					<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="<?= __("Nom de l'équipement", __FILE__) ?>"<?= $disabled ?>/>
				</div>
			</div>
			<?php
			if (!$_is_template) {
			?>
			<div class="form-group">
				<label class="col-sm-6 control-label"><?= __("Objet parent", __FILE__) ?></label>
				<div class="col-sm-6">
					<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
						<option value=""><?= __("Aucun", __FILE__) ?></option>
						<?php
						foreach ((jeeObject::buildTree(null, false)) as $object) {
							echo '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
						}
						?>
					</select>
				</div>
			</div>
			<?php
			}
			?>
			<div class="form-group eqCategories">
				<label class="col-sm-6 control-label"><?= __("Catégorie", __FILE__) ?></label>
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
				<label class="col-sm-6 control-label"><?= __("Options", __FILE__) ?></label>
				<div class="col-sm-6">
					<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked<?= $disabled ?>/><?= __("Activé", __FILE__) ?></label>
					<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked<?= $disabled ?>/><?= __("Visible", __FILE__) ?></label>
				</div>
			</div>
			
			<!-- *********************************** -->
			<legend><i class="fa fa-list-alt"></i> <?= __("Configuration :", __FILE__) ?></legend>
			<div class="form-group">
				<label class="col-sm-6 control-label"><?= __("Protocole de connexion", __FILE__) ?></label>
				<div class="col-sm-6">
					<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqProtocol"<?= $disabled ?>>
						<option disabled selected value>-- <?= __("Choisir un protocole de connexion", __FILE__) ?> --</option>
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
			<!-- Paramètres propres au protocole -->
			<div id="div_protocolParameters">
				<div class="form-group nonShared noRegTest">
					<label class="col-sm-6 control-label"><?= __("Mode de rafraîchissement", __FILE__) ?></label>
					<div class="col-sm-6">
						<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRefreshMode"<?= $disabled ?>>
							<option disabled selected value>-- <?= __("Selectionnez un mode", __FILE__) ?> --</option>
							<option value="polling"><?= __("Polling", __FILE__) ?></option>
							<option value="cyclic"><?= __("Cyclique", __FILE__) ?></option>
							<option value="on_event"><?= __("Sur événement", __FILE__) ?></option>
						</select>
					</div>
				</div>
				<div class="form-group nonShared noRegTest" id="eqPolling">
					<label class="col-sm-6 control-label"><?= __("Polling (s)", __FILE__) ?>
						<sup><i class="fas fa-question-circle tooltips" title="<?= __("En mode Polling: raffraichissement des valeurs toutes les n secondes, minimum 1", __FILE__) ?>"></i></sup>
					</label>
					<div class="col-sm-6">
						<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqPolling" placeholder="60"<?= $disabled ?>/>
					</div>
				</div>
				<div class="form-group nonShared">
					<label class="col-sm-6 control-label"><?= __("Timeout (s)", __FILE__) ?>
						<sup><i class="fas fa-question-circle tooltips" title="<?= __("Temps maximum d'attente de réponse à une requête", __FILE__) ?>"></i></sup>
					</label>
					<div class="col-sm-6">
						<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqTimeout" placeholder="60"<?= $disabled ?>/>
					</div>
				</div>
				<div class="form-group nonShared">
					<label class="col-sm-6 control-label"><?= __("Nombre de tentatives en cas d'erreur", __FILE__) ?></label>
					<div class="col-sm-6">
						<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRetries" placeholder="3"<?= $disabled ?>/>
					</div>
				</div>
				<div class="form-group nonShared">
					<label class="col-sm-6 control-label"><?= __("Temps entre 2 requêtes de lecture (s)", __FILE__) ?>
						<sup><i class="fas fa-question-circle tooltips" title="<?= __("Egalement le temps aloué à la vérification de l'envoi d'une commande action par Jeedom", __FILE__) ?>"></i></sup>
					</label>
					<div class="col-sm-6">
						<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqWriteCmdCheckTimeout" placeholder="1"<?= $disabled ?>/>
					</div>
				</div>
				<div class="form-group nonShared">
					<label class="col-sm-6 control-label"><?= __("Temps d'attente après la connexion (s)", __FILE__) ?></label>
					<div class="col-sm-6">
						<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqFirstDelay" placeholder="0"<?= $disabled ?>/>
					</div>
				</div>
				<div class="form-group nonShared">
					<label class="col-sm-6 control-label"><?= __("Temps d'attente après une erreur de lecture (s)", __FILE__) ?></label>
					<div class="col-sm-6">
						<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqErrorDelay" placeholder="0"<?= $disabled ?>/>
					</div>
				</div>
				<?php
				self::show_network_config();
				self::show_serial_config();
				?>
				<div class="form-group nonShared">
					<label class="col-sm-6 control-label"><?= __("Equipement destiné à tester l'existence des registres", __FILE__) ?></label>
					<div class="col-sm-6">
						<label class="checkbox-inline">
							<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRegTest"<?= $disabled ?>/>
							<div class="danger">
								<i class="fas fa-exclamation-triangle"></i>
								<?= __("ATTENTION : lors de la sauvegarde de l'équipement, si l'équipement est activé, toutes les commandes seront supprimées sans demande de confirmation", __FILE__) ?>
							</div>
						</label>
					</div>
				</div>

				<div id="div_RegTestParameters" hidden>
					<div class="form-group nonShared">
						<label class="col-sm-6 control-label"><?= __("Premier registre à tester", __FILE__) ?></label>
						<div class="col-sm-6">
							<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRegTestFirst"<?= $disabled ?>/>
						</div>
					</div>
					<div class="form-group nonShared">
						<label class="col-sm-6 control-label"><?= __("Dernier registre à tester", __FILE__) ?></label>
						<div class="col-sm-6">
							<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRegTestLast"<?= $disabled ?>/>
						</div>
					</div>
					<div class="form-group nonShared">
						<label class="col-sm-6 control-label"><?= __("ID du serveur", __FILE__) ?></label>
						<div class="col-sm-6">
							<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRegTestSlave"<?= $disabled ?>/>
						</div>
					</div>
					<div class="form-group nonShared">
						<label class="col-sm-6 control-label"><?= __("Fonction à utiliser", __FILE__) ?></label>
						<div class="col-sm-6">
							<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRegTestFunction"<?= $disabled ?>>
								<option disabled selected value>-- <?= __("Selectionnez une fonction", __FILE__) ?> --</option>
								<option value="1">[0x01] Read coils</option>
								<option value="2">[0x02] Read discrete inputs</option>
								<option value="3">[0x03] Read holding registers</option>
								<option value="4">[0x04] Read input registers</option>
							</select>
						</div>
					</div>
					<div class="form-group nonShared">
						<label class="col-sm-6 control-label"><?= __("Format des registres", __FILE__) ?></label>
						<div class="col-sm-6">
							<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqRegTestFormat"<?= $disabled ?>>
								<option class="formatTestBin" value="bits">bit (0 / 1)</option>
								<optgroup class="formatTestNum" label="16 bits">
									<option class="formatTestNum" value="h">int16 (-32 768 ... 32 767)</option>
									<option class="formatTestNum" value="H">uint16 (0 ... 65 535)</option>
								</optgroup>
								<optgroup class="formatTestNum" label="32 bits (<?= __("2 registres", __FILE__) ?>)">
									<option class="formatTestNum" value="i">int32 (-2 147 483 648 ... 2 147 483 647)</option>
									<option class="formatTestNum" value="I">uint32 (0 ... 4 294 967 295)</option>
									<option class="formatTestNum" value="f">float32 (Real 32bit)</option>
								</optgroup>
								<optgroup class="formatTestNum" label="64 bits (<?= __("4 registres", __FILE__) ?>)">
									<option class="formatTestNum" value="q">int64 (-9e18 ... 9e18)</option>
									<option class="formatTestNum" value="Q">uint64 (0 ... 18e18)</option>
									<option class="formatTestNum" value="d">float64 (Real 64bit)</option>
								</optgroup>
								<option class="formatTestNum" value="s"><?= __("Chaine de caractères", __FILE__) ?></option>
							</select>
						</div>
					</div>
					<div class="form-group nonShared">
						<label class="col-sm-6 control-label"><?= __("Inverser les octets", __FILE__) ?></label>
						<div class="col-sm-6">
							<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="eqRegTestInvertBytes"<?= $disabled ?>/>
						</div>
					</div>
					<div class="form-group nonShared">
						<label class="col-sm-6 control-label"><?= __("Inverser les mots", __FILE__) ?></label>
						<div class="col-sm-6">
							<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="eqRegTestInvertWords"<?= $disabled ?>/>
						</div>
					</div>
					<div class="form-group nonShared">
						<label class="col-sm-6 control-label"><?= __("Inverser les double-mots", __FILE__) ?></label>
						<div class="col-sm-6">
							<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="eqRegTestInvertDWords"<?= $disabled ?>/>
						</div>
					</div>
				</div>

			</div> <!-- div_protocolParameters -->
		</div>

		<div class="<?= $colSmClass ?>">
			<legend><i class="fas fa-info"></i><?= __("Informations", __FILE__) ?></legend>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= __("Notes", __FILE__) ?></label>
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
			<label class="col-sm-6 control-label"><?= __("Utilisation de l'interface de l'équipement", __FILE__) ?></label>
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
			<label class="col-sm-6 control-label"><?= __("Adresse IP", __FILE__) ?></label>
			<div class="col-sm-6">
				<input type="text" class="eqLogicAttr form-control networkConfig" data-l1key="configuration" data-l2key="eqAddr" placeholder="192.168.1.55"/>
			</div>
		</div>

		<div class="form-group networkConfig" hidden>
			<label class="col-sm-6 control-label"><?= __("Port", __FILE__) ?></label>
			<div class="col-sm-6">
				<input type="number" class="eqLogicAttr form-control networkConfig" data-l1key="configuration" data-l2key="eqPortNetwork" placeholder="502"/>
			</div>
		</div>
		<?php
	}

	static function show_serial_config() {
		?>
		<div class="form-group serialConfig" hidden>
			<label class="col-sm-6 control-label"><?= __("Interface", __FILE__) ?></label>
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
			<label class="col-sm-6 control-label"><?= __("Méthode de transport", __FILE__) ?></label>
			<div class="col-sm-6">
				<select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialMethod">
					<option value="rtu"><?= __("RTU", __FILE__) ?></option>
					<option value="ascii"><?= __("ASCII", __FILE__) ?></option>
				</select>
			</div>
		</div>

		<div class="form-group serialConfig" hidden>
			<label class="col-sm-6 control-label"><?= __("Vitesse de transmission", __FILE__) ?></label>
			<div class="col-sm-6">
				<select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialBaudrate">
					<option disabled selected value>-- <?= __("Selectionnez une valeur", __FILE__) ?> --</option>
					<option value="300">300 <?= __("bauds", __FILE__) ?></option>
					<option value="600">600 <?= __("bauds", __FILE__) ?></option>
					<option value="1200">1200 <?= __("bauds", __FILE__) ?></option>
					<option value="2400">2400 <?= __("bauds", __FILE__) ?></option>
					<option value="4800">4800 <?= __("bauds", __FILE__) ?></option>
					<option value="9600">9600 <?= __("bauds", __FILE__) ?></option>
					<option value="14400">14400 <?= __("bauds", __FILE__) ?></option>
					<option value="19200">19200 <?= __("bauds", __FILE__) ?></option>
					<option value="38400">38400 <?= __("bauds", __FILE__) ?></option>
					<option value="56000">56000 <?= __("bauds", __FILE__) ?></option>
					<option value="57600">57600 <?= __("bauds", __FILE__) ?></option>
					<option value="115200">115200 <?= __("bauds", __FILE__) ?></option>
					<option value="128000">128000 <?= __("bauds", __FILE__) ?></option>
					<option value="230400">230400 <?= __("bauds", __FILE__) ?></option>
					<option value="256000">256000 <?= __("bauds", __FILE__) ?></option>
				</select>
			</div>
		</div>

		<div class="form-group serialConfig" hidden>
			<label class="col-sm-6 control-label"><?= __("Nombre de bits par octet", __FILE__) ?></label>
			<div class="col-sm-6">
				<select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialBytesize">
					<option disabled selected value>-- <?= __("Selectionnez une valeur", __FILE__) ?> --</option>
					<option value="7">7</option>
					<option value="8">8</option>
				</select>
			</div>
		</div>

		<div class="form-group serialConfig" hidden>
			<label class="col-sm-6 control-label"><?= __("Parité", __FILE__) ?></label>
			<div class="col-sm-6">
				<select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialParity">
					<option disabled selected value>-- <?= __("Selectionnez une valeur", __FILE__) ?> --</option>
					<option value="E"><?= __("Paire", __FILE__) ?></option>
					<option value="O"><?= __("Impaire", __FILE__) ?></option>
					<option value="N"><?= __("Aucune", __FILE__) ?></option>
				</select>
			</div>
		</div>

		<div class="form-group serialConfig" hidden>
			<label class="col-sm-6 control-label"><?= __("Bits de stop", __FILE__) ?></label>
			<div class="col-sm-6">
				<select class="eqLogicAttr form-control serialConfig" data-l1key="configuration" data-l2key="eqSerialStopbits">
					<option disabled selected value>-- <?= __("Selectionnez une valeur", __FILE__) ?> --</option>
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