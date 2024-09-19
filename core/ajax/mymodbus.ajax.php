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

try {
  require_once __DIR__ . '/../../../../core/php/core.inc.php';
  require_once __DIR__ . '/../class/mymodbusConst.class.php';
  include_file('core', 'authentification', 'php');

  if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
  }
  
  /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
  *  Autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
  */
  ajax::init(array('fileupload'));
  
  /* ---------------------------
  * getTemplateList
  */
  if (init('action') == 'getTemplateList') {
    ajax::success(mymodbus::templateList());
  }

  /* ---------------------------
  * getTemplateByFile
  */
  if (init('action') == 'getTemplateByFile') {
    ajax::success(mymodbus::templateByFile(init('file')));
  }

  /* ---------------------------
  * deleteTemplateByFile
  */
  if (init('action') == 'deleteTemplateByFile') {
    if (!mymodbus::deleteTemplateByFile(init('file'))) {
      throw new Exception(__('Impossible de supprimer le fichier', __FILE__));
    }
    ajax::success(true);
  }
  
  /* ---------------------------
  * applyTemplate
  */
  if (init('action') == 'applyTemplate') {
    $eqpt = mymodbus::byId(init('id'));
    if (!is_object($eqpt) || $eqpt->getEqType_name() != mymodbus::class) {
      throw new Exception(sprintf(__("Pas d'équipement MyModbus avec l'id %s", __FILE__), init('id')));
    }
    $template = mymodbus::templateByName(init('templateName'));
    $eqpt->applyATemplate($template, init('keepCmd'));
    ajax::success();
  }

  /* ---------------------------
  * createTemplate
  */
  if (init('action') == 'createTemplate') {
    $eqpt = mymodbus::byId(init('id'));
    if (!is_object($eqpt) || $eqpt->getEqType_name() != mymodbus::class) {
      throw new Exception(sprintf(__("Pas d'équipement MyModbus avec l'id %s", __FILE__), init('id')));
    }
    $eqpt->createTemplate(init('name'));
    ajax::success();
  }

  /* ---------------------------
  * fileupload
  */
  if (init('action') == 'fileupload') {
    if (!isset($_FILES['file'])) {
      throw new Exception(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
    }

    $uploaddir = realpath(__DIR__ . '/../../') . mymodbusConst::PATH_TEMPLATES_USER;
    log::add('mymodbus', 'info', sprintf(__("uploaddir = '%s'", __FILE__), $uploaddir));
    $allowed_ext = '.json';
    $max_size = 500*1024; // 500KB
    
    if (filesize($_FILES['file']['tmp_name']) > $max_size) {
      throw new Exception(sprintf(__('Le fichier est trop gros (maximum %s)', __FILE__), sizeFormat($max_size)));
    }
    
    $fname = $_FILES['file']['name'];
    $extension = strtolower(strrchr($fname, '.'));
    if ($extension != $allowed_ext) {
      throw new Exception(sprintf(__("L'extension de fichier '%s' n'est pas autorisée", __FILE__), $extension));
    }
    if (!file_exists($uploaddir)) {
      if (!mkdir($uploaddir, 0775, true)) {
        throw new Exception(__('Impossible de créer le répertoire de téléversement :', __FILE__) . ' ' . $uploaddir);
      }
    }
    if (!file_exists($uploaddir)) {
      throw new Exception(__('Répertoire de téléversement non trouvé :', __FILE__) . ' ' . $uploaddir);
    }
    
    if (file_exists($uploaddir . '/' . $fname)) {
      throw new Exception(__('Impossible de téléverser le fichier car il existe déjà. Par sécurité, il faut supprimer le fichier existant avant de le remplacer.', __FILE__));
    }
    
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploaddir . '/' . $fname)) {
      throw new Exception(__('Impossible de déplacer le fichier temporaire', __FILE__));
    }
    
    if (!file_exists($uploaddir . '/' . $fname)) {
      throw new Exception(__('Impossible de téléverser le fichier (limite du serveur web ?)', __FILE__));
    }
    
    // After template file imported
    [$templateKey, $templateValue] = mymodbus::templateRead($uploaddir . '/' . $fname);
    mymodbus::deleteTemplateByFile($uploaddir . '/' . $fname);
    mymodbus::saveTemplateToFile($templateKey, $templateValue);
    log::add('mymodbus', 'info', sprintf(__("Template '%s' correctement téléversée", __FILE__), $fname));
    ajax::success($fname);
  }
  
  throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
  /* * *********Catch exeption*************** */
}
catch (Exception $e) {
  ajax::error(displayException($e), $e->getCode());
}

