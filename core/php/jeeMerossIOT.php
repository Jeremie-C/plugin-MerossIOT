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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'MerossIOT')) {
    echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
    die();
}

$result = json_decode(file_get_contents("php://input"), true);
$response = array('success' => true);
log::add('MerossIOT', 'debug', print_r($result, true));
$action = $result['action'];

if( $action == 'online' ) {
    log::add('MerossIOT', 'debug', 'Traitement de '.$action);
    $eqLogic = eqLogic::byLogicalId($result['uuid'], 'MerossIOT');
    if( is_object($eqLogic) ) {
        if( $result['status'] == 'online' ) {
            $eqLogic->setStatus('warning', 0);
            $eqLogic->setConfiguration('online', '1');
            $eqLogic->save();
        } else {
            $eqLogic->setStatus('warning', 1);
            $eqLogic->setConfiguration('ip', '');
            $eqLogic->setConfiguration('online', '0');
            $eqLogic->save();
            $humanName = $eqLogic->getHumanName();
            message::add('MerossIOT', $humanName.' '.__('semble manquant, il a été désactivé.', __FILE__));
        }
    } else {
        $uuid = $result['uuid'];
        message::add('MerossIOT', __('Nouvel équipement Meross disponible: Merci de lancer une synchronisation.', __FILE__));
    }
} elseif( $action == 'switch' ) {
    log::add('MerossIOT', 'debug', 'Traitement de '.$action);
    $eqLogic = eqLogic::byLogicalId($result['uuid'], 'MerossIOT');
    if( is_object($eqLogic) ) {
        $eqLogic->checkAndUpdateCmd("onoff_".$result['channel'], $result['status']);
    }
} elseif( $action == 'door') {
    log::add('MerossIOT', 'debug', 'Traitement de '.$action);
    $eqLogic = eqLogic::byLogicalId($result['uuid'], 'MerossIOT');
    if (is_object($eqLogic)) {
        if( $result['status'] == 'open' ) {
            $eqLogic->checkAndUpdateCmd("onoff_".$result['channel'], 0);
        } else {
            $eqLogic->checkAndUpdateCmd("onoff_".$result['channel'], 1);
        }
    }
} elseif( $action == 'bulb' ) {
    log::add('MerossIOT', 'debug', __('Traitement de ', __FILE__).$action);
    $eqLogic = eqLogic::byLogicalId($result['uuid'], 'MerossIOT');
    $data = $result['status'];
    if( is_object($eqLogic) ) {
        $eqLogic->checkAndUpdateCmd("lumival", $data['luminance']);
        log::add('MerossIOT', 'debug', 'Luminance: '.$data['luminance']);
        $eqLogic->checkAndUpdateCmd("tempval", $data['temperature']);
        log::add('MerossIOT', 'debug', 'Temperature: '.$data['temperature']);
        $eqLogic->checkAndUpdateCmd("rgbval", '#'.substr('000000'.dechex($data['rgb']),-6));
        log::add('MerossIOT', 'debug', 'RGB: '.'#'.substr('000000'.dechex($data['rgb']),-6));
        # Light Mode
        if( $data['capacity'] == 1 || $data['capacity'] == 5 ) {
            $eqLogic->checkAndUpdateCmd("capacity", __('Couleur', __FILE__));
        } else {
            $eqLogic->checkAndUpdateCmd("capacity", __('Blanc', __FILE__));
        }
        log::add('MerossIOT', 'debug', 'Capacity: '.$data['capacity']);
    }
} elseif( $action == 'electricity' ) {
    log::add('MerossIOT', 'debug', __('Traitement de ', __FILE__).$action);
    foreach( $result['values'] as $uuid=>$data ) {
        $eqLogic = eqLogic::byLogicalId($uuid, 'MerossIOT');
        if( is_object($eqLogic) ) {
            $eqLogic->checkAndUpdateCmd("power", $data['power']);
            $eqLogic->checkAndUpdateCmd("current", $data['current']);
            $eqLogic->checkAndUpdateCmd("tension", $data['voltage']);
        }
    }
} elseif( $action == 'hlight' ) {
    log::add('MerossIOT', 'debug', __('Traitement de ', __FILE__).$action);
    $eqLogic = eqLogic::byLogicalId($result['uuid'], 'MerossIOT');
    if( is_object($eqLogic) ) {
        $eqLogic->checkAndUpdateCmd("onoff_".$result['channel'], $result['status']);
        log::add('MerossIOT', 'debug', 'Light: '.$result['status']);
        $eqLogic->checkAndUpdateCmd("lumival", $result['luminance']);
        log::add('MerossIOT', 'debug', 'Luminance: '.$result['luminance']);
        $eqLogic->checkAndUpdateCmd("rgbval", '#'.substr('000000'.dechex($result['rgb']),-6));
        log::add('MerossIOT', 'debug', 'RGB: '.'#'.substr('000000'.dechex($result['rgb']),-6));
    }
} elseif( $action == 'connect' ) {
    log::add('MerossIOT', 'info', 'CONNECT: '.$result['status']);
} elseif( $action == 'bind' ) {
    log::add('MerossIOT', 'info', $result['uuid'].' Bind: '.$result['data']);
} elseif( $action == 'unbind' ) {
    log::add('MerossIOT', 'info', $result['uuid'].' Unbind');
}
echo json_encode($response);
