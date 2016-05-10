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
header('Content-type: application/json');
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (init('apikey') != config::byKey('api') || config::byKey('api') == '') {
	connection::failed();
	echo 'Clef API non valide, vous n\'etes pas autorisé à effectuer cette action (jeeApi)';
	die();
}



$phone = init('phone');
$text = init('text');
$eqLogic = eqLogic::byLogicalId($phone, 'gammu');
if (!is_object($eqLogic)) {
	echo json_encode(array('text' => __('Id inconnu : ', __FILE__) . init('id')));
	die();
}

log::add('gammu', 'debug', 'recu : ' . $text . ' pour ' . $phone);

$parameters = array();
$username = $eqLogic->getConfiguration('user','none');
if ($username != 'none') {
	$user = user::byLogin($username);
	if (is_object($user)) {
		$parameters['profile'] = $username;
	}
}

$cmd = $eqLogic->getCmd(null, 'send');
if ($cmd->getConfiguration('storeVariable', 'none') != 'none') {
	$dataStore = new dataStore();
	$dataStore->setType('scenario');
	$dataStore->setKey($cmd->getConfiguration('storeVariable', 'none'));
	$dataStore->setValue($text);
	$dataStore->setLink_id(-1);
	$dataStore->save();
	$cmd->setConfiguration('storeVariable', 'none');
	$cmd->save();
	echo json_encode(array('text' => ''));
	die();
}

$cmd_text = $eqLogic->getCmd(null, 'text');
$cmd_text->event($text);
$cmd_text->setConfiguration('value',$text);
$cmd_text->save();

if ($eqLogic->getConfiguration('interact') == 1) {
	putenv ( ' LANG=fr_FR.UTF-8 ' );
 	$reply = interactQuery::tryToReply($text, $parameters);
	$len=strlen($reply);
	$reply = str_replace('/n',PHP_EOL, $reply);
	$reply = '"'.$reply.'"';
	$cmd='sudo gammu-smsd-inject TEXT ' . $phone . ' -unicode -len ' .$len. ' -text '.$reply;
  	log::add('gammu', 'debug', 'SMS send : ' . $cmd);
  	exec($cmd);
}

return true;

?>
