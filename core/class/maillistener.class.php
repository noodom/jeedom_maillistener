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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class maillistener extends eqLogic {
  /*     * *************************Attributs****************************** */
  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'maillistener_install';
    $mailparser = realpath(dirname(__FILE__) . '/../../resources/node_modules/mailparser');
    $imap = realpath(dirname(__FILE__) . '/../../resources/node_modules/imap');
    $return['progress_file'] = '/tmp/maillistener_dep';
    if (is_dir($mailparser) && is_dir($imap)) {
      $return['state'] = 'ok';
    } else {
      $return['state'] = 'nok';
    }
    return $return;
  }

  public static function dependancy_install() {
    $install_path = dirname(__FILE__) . '/../../resources';
    passthru('/bin/bash ' . $install_path . '/nodejs.sh ' . $install_path . ' >> ' . log::getPathToLog('maillistener_install') . ' 2>&1 &');
  }

  public static function deamon_start($_debug = false) {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    log::add('maillistener', 'info', 'Lancement du démon maillistener');

    if (!config::byKey('internalPort')) {
      $url = config::byKey('internalProtocol') . config::byKey('internalAddr') . ':' . config::byKey('internalComplement') . '/core/api/jeeApi.php?api=' . config::byKey('api');
    } else {
      $url = config::byKey('internalProtocol') . config::byKey('internalAddr') . ':' . config::byKey('internalPort') . config::byKey('internalComplement') . '/core/api/jeeApi.php?api=' . config::byKey('api');
    }

    $service_path = realpath(dirname(__FILE__) . '/../../resources');
    $attach_path = $service_path . '/attachments/';

    foreach ($deamon_info['notlaunched'] as $addr) {
      $mail = self::byLogicalId($addr, 'maillistener');
      $cmd = 'nice -n 19 nodejs ' . $service_path . '/maillistener.js ' . $addr . ' ' . $url . ' ' . $mail->getConfiguration('username') . ' ' . $mail->getConfiguration('password') . ' ' . $mail->getConfiguration('server') . ' ' . $mail->getConfiguration('port') . ' ' . $mail->getConfiguration('attach') . ' ' . $attach_path;

      log::add('maillistener', 'debug', 'Lancement démon maillistener : ' . $cmd);
      $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('maillistener_node') . ' 2>&1 &');
      if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
        log::add('maillistener', 'error', $result);
        return false;
      }
    }
    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add('maillistener', 'error', 'Impossible de lancer un démon maillistener, vérifiez le port', 'unableStartDeamon');
      return false;
    }
    message::removeAll('maillistener', 'unableStartDeamon');
    log::add('maillistener', 'info', 'Démons maillistener lancé');
    return true;
  }

  public static function deamon_info() {
    $return = array();
    $return['log'] = 'maillistener_node';
    $return['state'] = 'ok';
    $return['launchable'] = 'ok';
    $return['notlaunched'] = array();
    $return['launched'] = array();
    foreach (eqLogic::byType('maillistener') as $maillistener) {
        if ($maillistener->getIsEnable() == 1 ) {
          $pid = trim( shell_exec ('ps ax | grep "maillistener/resources/maillistener.js '. $maillistener->getConfiguration('addr') . '" | grep -v "grep" | wc -l') );
          if ($pid != '' && $pid != '0') {
            $return['launched'][] = $maillistener->getConfiguration('addr');
          } else {
            $return['state'] = 'nok';
            $return['notlaunched'][] = $maillistener->getConfiguration('addr');
            $return['launchable_message'] = $return['launchable_message'] . ' ' . $maillistener->getConfiguration('addr') . ' non lancé';
          }
          if ($maillistener->getConfiguration('addr') == '') {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Le port de ' . $maillistener->getName() . ' n\'est pas configuré', __FILE__);
          }
        }
    }
    return $return;
  }

  public static function deamon_stop() {
    exec('kill $(ps aux | grep "maillistener/resources/maillistener.js" | awk \'{print $2}\')');
    log::add('maillistener', 'info', 'Arrêt du service maillistener');
    $deamon_info = self::deamon_info();
    if (count($deamon_info['launched']) != 0) {
      sleep(1);
      exec('kill -9 $(ps aux | grep "maillistener/resources/maillistener.js" | awk \'{print $2}\')');
    }
    $deamon_info = self::deamon_info();
    if (count($deamon_info['launched']) != 0) {
      sleep(1);
      exec('sudo kill -9 $(ps aux | grep "maillistener/resources/maillistener.js" | awk \'{print $2}\')');
    }
  }

  public function preUpdate() {
    if ($this->getConfiguration('username') == '') {
      throw new Exception(__('L utilisateur ne peut etre vide',__FILE__));
    }
    if ($this->getConfiguration('server') == '') {
      throw new Exception(__('Le serveur ne peut etre vide',__FILE__));
    }
  }

  public function preSave() {
    $this->setLogicalId($this->getConfiguration('username') . '@' . $this->getConfiguration('server'));
    $this->setConfiguration('addr',$this->getConfiguration('username') . '@' . $this->getConfiguration('server'));
  }

  public function postSave() {
    maillistener::deamon_stop();
  }

  public function postUpdate() {
    $mailCmd = maillistenerCmd::byEqLogicIdAndLogicalId($this->getId(),'from');
    if (!is_object($mailCmd)) {
      $mailCmd = new maillistenerCmd();
      $mailCmd->setEqLogic_id($this->getId());
      $mailCmd->setEqType('maillistener');
      $mailCmd->setIsHistorized(0);
      $mailCmd->setName( 'Expéditeur' );
      $mailCmd->setLogicalId('from');
    }
    $mailCmd->setConfiguration('data', 'from');
    $mailCmd->setType('info');
    $mailCmd->setSubType('string');
    $mailCmd->save();

    $mailCmd = maillistenerCmd::byEqLogicIdAndLogicalId($this->getId(),'subject');
    if (!is_object($mailCmd)) {
      $mailCmd = new maillistenerCmd();
      $mailCmd->setEqLogic_id($this->getId());
      $mailCmd->setEqType('maillistener');
      $mailCmd->setIsHistorized(0);
      $mailCmd->setName( 'Sujet' );
      $mailCmd->setLogicalId('subject');
    }
    $mailCmd->setConfiguration('data', 'subject');
    $mailCmd->setType('info');
    $mailCmd->setSubType('string');
    $mailCmd->save();

    $mailCmd = maillistenerCmd::byEqLogicIdAndLogicalId($this->getId(),'body');
    if (!is_object($mailCmd)) {
      $mailCmd = new maillistenerCmd();
      $mailCmd->setEqLogic_id($this->getId());
      $mailCmd->setEqType('maillistener');
      $mailCmd->setIsHistorized(0);
      $mailCmd->setName( 'Texte' );
      $mailCmd->setLogicalId('body');
    }
    $mailCmd->setConfiguration('data', 'body');
    $mailCmd->setType('info');
    $mailCmd->setSubType('string');
    $mailCmd->save();

    $mailCmd = maillistenerCmd::byEqLogicIdAndLogicalId($this->getId(),'html');
    if (!is_object($mailCmd)) {
      $mailCmd = new maillistenerCmd();
      $mailCmd->setEqLogic_id($this->getId());
      $mailCmd->setEqType('maillistener');
      $mailCmd->setIsHistorized(0);
      $mailCmd->setName( 'HTML' );
      $mailCmd->setLogicalId('html');
    }
    $mailCmd->setConfiguration('data', 'body');
    $mailCmd->setType('info');
    $mailCmd->setSubType('string');
    $mailCmd->save();

    $mailCmd = maillistenerCmd::byEqLogicIdAndLogicalId($this->getId(),'attachment');
    if (!is_object($mailCmd)) {
      $mailCmd = new maillistenerCmd();
      $mailCmd->setEqLogic_id($this->getId());
      $mailCmd->setEqType('maillistener');
      $mailCmd->setIsHistorized(0);
      $mailCmd->setName( 'Pièce Jointe' );
      $mailCmd->setLogicalId('attachment');
    }
    $mailCmd->setConfiguration('data', 'attachment');
    $mailCmd->setType('info');
    $mailCmd->setSubType('string');
    $mailCmd->save();
  }

  public static function mailIncoming() {
    $addr = init('email');
    $from = init('from');
    $subject = init('subject');
    $json = file_get_contents('php://input');
    $body = json_decode($json, true);
    log::add('maillistener', 'debug', 'Mail sur ' . $addr . ' de ' . $from . ' titre ' . $subject . ' message ' . $json);
    $elogic = self::byLogicalId($addr, 'maillistener');
    if (is_object($elogic)) {
      $cmdlogic = maillistenerCmd::byEqLogicIdAndLogicalId($elogic->getId(),'from');
      if (is_object($cmdlogic)) {
        $cmdlogic->setConfiguration('value', $from);
        $cmdlogic->save();
        $cmdlogic->event($from);
      }
      $cmdlogic = maillistenerCmd::byEqLogicIdAndLogicalId($elogic->getId(),'subject');
      if (is_object($cmdlogic)) {
        $cmdlogic->setConfiguration('value', $subject);
        $cmdlogic->save();
        $cmdlogic->event($subject);
      }
      $cmdlogic = maillistenerCmd::byEqLogicIdAndLogicalId($elogic->getId(),'body');
      if (is_object($cmdlogic)) {
        $cmdlogic->setConfiguration('value', $body['body']);
        $cmdlogic->save();
        $cmdlogic->event($body['body']);
      }
      $cmdlogic = maillistenerCmd::byEqLogicIdAndLogicalId($elogic->getId(),'html');
      if (is_object($cmdlogic)) {
        $cmdlogic->setConfiguration('value', $body['html']);
        $cmdlogic->save();
        $cmdlogic->event($body['html']);
      }
    }
  }

  public static function attachment() {
    $addr = init('email');
    $attachment = init('value');
    log::add('maillistener', 'debug', 'Pièce jointe sur ' . $addr);
    $elogic = self::byLogicalId($addr, 'maillistener');
    if (is_object($elogic)) {
      $cmdlogic = maillistenerCmd::byEqLogicIdAndLogicalId($elogic->getId(),'attachment');
      if (is_object($cmdlogic)) {
        $cmdlogic->setConfiguration('value', $attachment);
        $cmdlogic->save();
        $cmdlogic->event($attachment);
      }
    }
  }

  public static function event() {
    log::add('maillistener', 'debug', init('messagetype') . ' ' . init('from') . ' ' . init('subject'));
    $messageType = init('messagetype');
    switch ($messageType) {
      case 'mailIncoming' : self::mailIncoming(); break;
      case 'attachment' : self::attachment(); break;
    }
  }

}

class maillistenerCmd extends cmd {
  public function execute($_options = null) {
    switch ($this->getType()) {
      case 'info' :
      return $this->getConfiguration('value');
      log::add('maillistener', 'debug', 'value ' . $this->getConfiguration('value'));
      break;
    }

  }

}
