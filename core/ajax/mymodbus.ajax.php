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
  require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
  require_once dirname(__FILE__) . '/../class/mymodbusConst.class.php';
  include_file('core', 'authentification', 'php');

  if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
  }
  
  /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
  *  Autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
  */
  ajax::init(array('fileupload'));

  /* ---------------------------
  * changeLogLevel
  */
  if (init('action') == 'changeLogLevel') {
    $level = init('level');
    $level_config = $level['log::level::mymodbus'];
    foreach ($level_config as $log_level => $value) {
      if ($value == '1')
        ajax::success(mymodbus::changeLogLevel($log_level));
    }
  }
  
  /* ---------------------------
  * getTemplateList
  */
  if (init('action') == 'getTemplateList') {
    ajax::success(mymodbus::templateList());
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
  * getTemplateByFile
  */
  if (init('action') == 'getTemplateByFile') {
    ajax::success(mymodbus::templateByFile(init('file')));
  }

  /* ---------------------------
  * fileupload TODO !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  */
  if (init('action') == 'fileupload') {
    if (!isset($_FILES['file'])) {
      throw new Exception(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
    }
    if (init('dir') == 'template') {
      $uploaddir = realpath(__DIR__ . '/../../' . mymodbusConst::PATH_TEMPLATES_PERSO);
      $allowed_ext = '.json';
      $max_size = 500*1024; // 500KB
    } elseif (init('dir') == 'backup') {
      $uploaddir = realpath(__DIR__ . '/../../' . mymodbusConst::PATH_BACKUP);
      $allowed_ext = '.tgz';
      $max_size = 100*1024*1024; // 100MB
    } else {
      throw new Exception(__('Téléversement invalide', __FILE__));
    }
    if (filesize($_FILES['file']['tmp_name']) > $max_size) {
      throw new Exception(sprintf(__('Le fichier est trop gros (maximum %s)', __FILE__), sizeFormat($max_size)));
    }
    $extension = strtolower(strrchr($_FILES['file']['name'], '.'));
    if ($extension != $allowed_ext)
      throw new Exception(sprintf(__("L'extension de fichier '%s' n'est pas autorisée", __FILE__), $extension));
    if (!file_exists($uploaddir)) {
      mkdir($uploaddir);
    }
    if (!file_exists($uploaddir)) {
      throw new Exception(__('Répertoire de téléversement non trouvé :', __FILE__) . ' ' . $uploaddir);
    }
    $fname = $_FILES['file']['name'];
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
    if (init('dir') == 'template') {
      // TODO: définir une fonction 
      // Adapt template for the topic in configuration
      //jMQTT::moveTopicToConfigurationByFile($fname);
      //jMQTT::logger('info', sprintf(__("Template %s correctement téléversée", __FILE__), $fname));
      ajax::success($fname);
    }
    elseif (init('dir') == 'backup') {
      $backup_dir = realpath(__DIR__ . '/../../' . mymodbusConst::PATH_BACKUP);
      $files = ls($backup_dir, '*.tgz', false, array('files', 'quiet'));
      sort($files);
      $backups = array();
      foreach ($files as $backup)
        $backups[] = array('name' => $backup, 'size' => sizeFormat(filesize($backup_dir.'/'.$backup)));
        // TODO !!!!!!!!!!!!!!!!!!!!!!!!!!
        //jMQTT::logger('info', sprintf(__("Sauvegarde %s correctement téléversée", __FILE__), $fname));
      ajax::success($backups);
    }
  }
  
  throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
  /* * *********Catch exeption*************** */
}
catch (Exception $e) {
  ajax::error(displayException($e), $e->getCode());
}

