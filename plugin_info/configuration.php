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
        <legend><i class="icon loisir-darth"></i> {{Gestion des démons}}</legend>
        <div class="form-group expertModeVisible">
            <label class="col-lg-4 control-label">{{Port socket interne (doit être identique sur l esclaves)}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="socketport" placeholder="{{Futur Beta Mymodbus}}"/>
            </div>
        </div>
        <div class="form-group" >
            <label class="col-lg-4 control-label">{{Activer les logs séparés des démons locaux}} <i class="fas fa-question-circle tooltips" title="{{Nécéssite un redémarrage des démons}}"></i></label>
            <div class="col-lg-3">
                <input type="checkbox"disabled="disabled" class="configKey " data-l1key="ActiveDemonLog" />
            </div>
        </div>
        <div class="form-group" >
            <label class="col-lg-4 control-label">{{Activer le redémarrage des démons locaux (t/mn) }} <i class="fas fa-question-circle tooltips" title="{{Nécéssite un redémarrage des démons}}"></i></label>
            <div class="col-lg-3">
                <input type="checkbox" class="configKey " data-l1key="ActiveRestart" />
            </div>
        </div>
    </fieldset>
</form>