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

class gammu extends eqLogic {

  public static function health() {
    $return = array();
    $pid = trim( shell_exec ('ps ax | grep "gammu" | grep -v "grep" | wc -l') );
    if ($pid != '' && $pid != '0') {
      $service = true;
    } else {
      $service = false;
    }
    $return[] = array(
      'test' => __('Gammu', __FILE__),
      'result' => ($service) ? __('OK', __FILE__) : __('NOK', __FILE__),
      'advice' => ($service) ? '' : __('Indique si le service gammu est démarré', __FILE__),
      'state' => $service,
    );
    return $return;
  }

  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'gammu_dep';
    $cmd = "dpkg -l | grep gammu";
    exec($cmd, $output, $return_var);
    if ($output[0] != "") {
      $return['state'] = 'ok';
    } else {
      $return['state'] = 'nok';
    }
    return $return;
  }

  public static function dependancy_install() {
    $cmd = 'sudo apt-get -y install gammu gammu-smsd python-gammu >> ' . log::getPathToLog('gammu_dep') . ' 2>&1 &';
    exec($cmd);
  }

  public static function configuration() {
    if (config::byKey('pin', 'gammu') == '' || config::byKey('nodeGateway', 'gammu') == '') {
    	log::add('gammu', 'error', 'Configuration plugin non remplie, impossible de configurer gammu');
    	die();
    } else {
    	log::add('gammu', 'debug', 'Configuration gammu');
    }
    $install_path = dirname(__FILE__) . '/../../resources';
    if (!config::byKey('internalPort')) {
      $url = 'http://127.0.0.1' . config::byKey('internalComplement') . '/plugins/gammu/core/api/jeeGammu.php?apikey=' . config::byKey('api');
    } else {
      $url = 'http://127.0.0.1:' . config::byKey('internalPort') . config::byKey('internalComplement') . '/plugins/gammu/core/api/jeeGammu.php?apikey=' . config::byKey('api');
    }
    $usbGateway = jeedom::getUsbMapping(config::byKey('nodeGateway', 'gammu'));
    $cmd = 'sudo /bin/bash ' . $install_path . '/install.sh ' . $install_path . ' ' . $usbGateway . ' ' . config::byKey('pin', 'gammu') . ' ' . $url;
    exec($cmd);
    log::add('gammu', 'debug', $cmd);

    $i = 1;
    foreach (eqLogic::byType('gammu', true) as $gammu) {
      $phone = $gammu->getConfiguration('phone');
      if ($phone != '+') { $phone='+'.$phone; }
      $line = 'number' . $i . ' = ' . $phone;
      $cmd = 'echo "' . $line . '" | sudo tee --append /etc/gammu-smsdrc';
      exec($cmd);
      $i++;
    }

    exec('sudo service gammu-smsd restart');
  }

  public function preUpdate() {
    if ($this->getConfiguration('phone') == '') {
      throw new Exception(__('Le téléphone ne peut être vide',__FILE__));
    }
  }

  public function preSave() {
    $id = str_replace('+','',$this->getConfiguration('phone'));
    $this->setLogicalId($id);
  }

  public function postUpdate() {
    gammu::configuration();
  }

  public function postSave() {
    $text = $this->getCmd(null, 'text');
		if (!is_object($text)) {
			$text = new gammuCmd();
			$text->setLogicalId('text');
			$text->setIsVisible(0);
			$text->setName(__('Message', __FILE__));
		}
		$text->setType('info');
		$text->setSubType('string');
		$text->setEqLogic_id($this->getId());
		$text->save();

		$sender = $this->getCmd(null, 'send');
		if (!is_object($sender)) {
			$sender = new gammuCmd();
			$sender->setLogicalId('send');
			$sender->setIsVisible(1);
			$sender->setName(__('Envoi', __FILE__));
		}
		$sender->setType('action');
		$sender->setSubType('message');
		$sender->setEqLogic_id($this->getId());
		$sender->save();

  }

}

class gammuCmd extends cmd {

  public function preSave() {
		if ($this->getSubtype() == 'message') {
			$this->setDisplay('title_disable', 1);
		}
	}

	public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    $phone = $eqLogic->getConfiguration('phone');
    switch ($this->getType()) {
      case 'info' :
      return $this->getConfiguration('value');
      break;

      case 'action' :
      	putenv ( ' LANG=fr_FR.UTF-8 ' );
      $reply = $_options['message'];
      $len=strlen($reply);
      $reply = str_replace('/n',PHP_EOL, $reply);
      $reply = '"'.$reply.'"';
      if ($phone != '+') { $phone='+'.$phone; }
      $cmd='sudo gammu-smsd-inject TEXT ' . $phone . ' -unicode -len ' .$len. " -text ".$reply;
      log::add('gammu', 'debug', 'SMS send : ' . $cmd);
      exec($cmd);
      break;
    }
	}

}

?>
