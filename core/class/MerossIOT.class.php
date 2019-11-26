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
require_once dirname(__FILE__).'/../../../../core/php/core.inc.php';

class MerossIOT extends eqLogic {
    /*
     * Fonction exécutée automatiquement par Jeedom
     */
    public static function cron10() {
        log::add('MerossIOT', 'debug', __('Mise à jour des consommations des équipements depuis le Cloud Meross', __FILE__));
        $results = self::callMeross('syncMerossConso');
        foreach( $results['result'] as $uuid=>$data ) {
            $eqLogic = MerossIOT::byLogicalId($uuid, 'MerossIOT');
            if( is_object($eqLogic) ) {
                $eqLogic->checkAndUpdateCmd("conso_totale", $data['conso_totale']);
            }
        }
        log::add('MerossIOT', 'debug', __('Mise à jour des consommations terminées.', __FILE__));
    }
    /**
     * Call the meross Python daemon.
     * @param  string $action Action calling.
     * @param  string $args   Other arguments.
     * @return array  Result of the callMeross.
     */
    public static function callMeross($action, $args = '') {
        log::add('MerossIOT', 'debug', 'callMeross ' . print_r($action, true) . ' ' .print_r($args, true));
        $apikey = jeedom::getApiKey('MerossIOT');
        $sock = 'unix://' . jeedom::getTmpFolder('MerossIOT') . '/daemon.sock';
        $fp = stream_socket_client($sock, $errno, $errstr);
        $result = '';
        if ($fp) {
            $query = [ 'action' => $action, 'args' => $args, 'apikey' => $apikey ];
            fwrite($fp, json_encode($query));
            while (!feof($fp)) {
                $result .= fgets($fp, 1024);
            }
            fclose($fp);
        }
        $result = (is_json($result)) ? json_decode($result, true) : $result;
        log::add('MerossIOT', 'debug', 'result callMeross '.print_r($result, true));
        return $result;
    }
    /**
     * Sync all meross devices.
     * @return none
     */
    public static function syncMeross() {
        log::add('MerossIOT', 'debug', __('Synchronisation des équipements depuis le Cloud Meross', __FILE__));
        $results = self::callMeross('syncMeross');
        foreach( $results['result'] as $key=>$device ) {
            self::syncOneMeross($device);
        }
        log::add('MerossIOT', 'debug', __('syncMeross: synchronisation terminée.', __FILE__));
    }
    /**
     * Sync one meross devices.
     * @return none
     */
    public static function syncOneMeross($device) {
        $key = $device['uuid'];
        $eqLogic = self::byLogicalId($key, 'MerossIOT');
        # Création ou Update
        if (!is_object($eqLogic)) {
            log::add('MerossIOT', 'debug', __('syncMeross: Ajout de ', __FILE__) . $device["name"] . ' - ' . $key);
            $eqLogic = new MerossIOT();
            $eqLogic->setName($device['name']);
            $eqLogic->setEqType_name('MerossIOT');
            $eqLogic->setLogicalId($key);
            if ($device['type'] != '') {
                $eqLogic->setConfiguration('type', $device['type']);
            }
            if ($device['famille'] != '') {
                $eqLogic->setConfiguration('famille', $device['famille']);
            }
            if ($device['online'] != '') {
                $eqLogic->setConfiguration('online', $device['online']);
            } else {
                $eqLogic->setConfiguration('online', '0');
            }
        } else {
            log::add('MerossIOT', 'debug', __('syncMeross: Mise à jour de ', __FILE__) . $device["name"] . ' - ' . $key);
            if ($device['online'] != '') {
                $eqLogic->setConfiguration('online', $device['online']);
            } else {
                $eqLogic->setConfiguration('online', '0');
            }
        }
        # Si online, on continue
        log::add('MerossIOT', 'debug',  __('syncMeross: En ligne : ', __FILE__) . $device["online"] . ' - ' . $key);
        if( $device['online'] ) {
            if ($device['ip'] != '') {
                $eqLogic->setConfiguration('ip', $device['ip']);
            }
            if ($device['mac'] != '') {
                $eqLogic->setConfiguration('mac', $device['mac']);
            }
            $eqLogic->setIsEnable(1);
            $eqLogic->save();
            # Les Commandes
            self::updateEqLogicCmds($eqLogic, $device);
            self::updateEqLogicVals($eqLogic, $device['values']);
        } else {
            $eqLogic->setIsEnable(0);
            $eqLogic->save();
            $humanName = $eqLogic->getHumanName();
            message::add('MerossIOT', $humanName.' '.__('semble manquant, il a été désactivé.', __FILE__));
        }
    }
    /**
     * Update Values.
     * @return none
     */
    public static function updateEqLogicVals($_eqLogic, $values) {
        # Valeurs
        log::add('MerossIOT', 'debug', 'updateEqLogicVals: Update eqLogic values');
        foreach ($values as $key => $value) {
            if( $key == 'switch' ) {
                foreach( $value as $id=>$state ) {
                    $_eqLogic->checkAndUpdateCmd('onoff_'.$id, $state);
                }
            } else {
                if( $key == "capacity" ) {
                    if( $value == 1 || $value == 5 ) {
                        $value = __('Couleur', __FILE__);
                    } else {
                        $value = __('Blanc', __FILE__);
                    }
                }
                if( $key == "rgbval" ) {
                    $value = '#'.substr('000000'.dechex($value),-6);
                }
                $_eqLogic->checkAndUpdateCmd($key, $value);
            }
        }
    }
    /**
     * Sync one meross devices.
     * @return none
     */
    public static function updateEqLogicCmds($_eqLogic, $_device) {
        log::add('MerossIOT', 'debug', 'updateEqLogicCmds: Update eqLogic commands');
        $i = 0;
        $order = 1;
        $familly = $_device['famille'];
        # Switch
        $nb_switch = count($_device['onoff']);
        foreach ($_device['onoff'] as $key=>$value) {
            if(  $i==0 && $nb_switch>1 ) {
                # All On
                $cmd = $_eqLogic->getCmd(null, 'on_'.$i);
                if (!is_object($cmd)) {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=on_'.$i);
                    $cmd = new MerossIOTCmd();
                    $cmd->setIsVisible(1);
                    $cmd->setName($value.' '.__('Marche', __FILE__));
                    $cmd->setLogicalId('on_'.$i);
                    $cmd->setEqLogic_id($_eqLogic->getId());
                } else {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=on_'.$i);
                }
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setOrder($order);
                $cmd->save();
                $order++;
                # All off
                $cmd = $_eqLogic->getCmd(null, 'off_'.$i);
                if (!is_object($cmd)) {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=off_'.$i);
                    $cmd = new MerossIOTCmd();
                    $cmd->setIsVisible(1);
                    $cmd->setName($value.' '.__('Arrêt', __FILE__));
                    $cmd->setLogicalId('off_'.$i);
                    $cmd->setEqLogic_id($_eqLogic->getId());
                } else {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=off_'.$i);
                }
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setOrder($order);
                $cmd->save();
                $order++;
                $i++;
            } else {
                # status
                $cmd = $_eqLogic->getCmd(null, 'onoff_'.$i);
                if (!is_object($cmd)) {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=onoff_'.$i);
                    $cmd = new MerossIOTCmd();
                    $cmd->setIsVisible(0);
                    $cmd->setIsHistorized(0);
                    $cmd->setLogicalId('onoff_'.$i);
                    $cmd->setEqLogic_id($_eqLogic->getId());
                } else {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=onoff_'.$i);
                }
                $cmd->setName($value);
                $cmd->setType('info');
                $cmd->setSubType('binary');
                if( $familly == 'GenericGarageDoorOpener' ) {
                    $cmd->setGeneric_type('GARAGE_STATE');
                } elseif( $familly == 'GenericBulb' ) {
                    $cmd->setGeneric_type('LIGHT_STATE');
                } else {
                    $cmd->setGeneric_type('ENERGY_STATE');
                }
                $cmd->setOrder($order);
                $cmd->save();
                $status_id = $cmd->getId();
                $order++;
                # off
                $cmd = $_eqLogic->getCmd(null, 'off_'.$i);
                if (!is_object($cmd)) {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=off_'.$i);
                    $cmd = new MerossIOTCmd();
                    $cmd->setIsVisible(1);
                    $cmd->setName(__('Arrêt', __FILE__).' '.$i);
                    $cmd->setLogicalId('off_'.$i);
                    $cmd->setEqLogic_id($_eqLogic->getId());
                } else {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=off_'.$i);
                }
                $cmd->setType('action');
                $cmd->setSubType('other');
                if( $familly == 'GenericGarageDoorOpener' ) {
                    $cmd->setTemplate('dashboard', 'garage');
                    $cmd->setTemplate('mobile', 'garage');
                    $cmd->setGeneric_type('GB_OPEN');
                } elseif( $familly == 'GenericBulb' ) {
                    $cmd->setTemplate('dashboard', 'light');
                    $cmd->setTemplate('mobile', 'light');
                    $cmd->setGeneric_type('LIGHT_OFF');
                } else {
                    $cmd->setTemplate('dashboard', 'prise');
                    $cmd->setTemplate('mobile', 'prise');
                    $cmd->setGeneric_type('ENERGY_OFF');
                }
                $cmd->setOrder($order);
                $cmd->save();
                $cmd->setValue($status_id);
                $cmd->save();
                $order++;
                # on
                $cmd = $_eqLogic->getCmd(null, 'on_'.$i);
                if (!is_object($cmd)) {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=on_'.$i);
                    $cmd = new MerossIOTCmd();
                    $cmd->setIsVisible(1);
                    $cmd->setName(__('Marche', __FILE__).' '.$i);
                    $cmd->setLogicalId('on_'.$i);
                    $cmd->setEqLogic_id($_eqLogic->getId());
                } else {
                    log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=on_'.$i);
                }
                $cmd->setType('action');
                $cmd->setSubType('other');
                if( $familly == 'GenericGarageDoorOpener' ) {
                    $cmd->setTemplate('dashboard', 'garage');
                    $cmd->setTemplate('mobile', 'garage');
                    $cmd->setGeneric_type('GB_CLOSE');
                } elseif( $familly == 'GenericBulb' ) {
                    $cmd->setTemplate('dashboard', 'light');
                    $cmd->setTemplate('mobile', 'light');
                    $cmd->setGeneric_type('LIGHT_ON');
                } else {
                    $cmd->setTemplate('dashboard', 'prise');
                    $cmd->setTemplate('mobile', 'prise');
                    $cmd->setGeneric_type('ENERGY_ON');
                }
                $cmd->setOrder($order);
                $cmd->save();
                $cmd->setValue($status_id);
                $cmd->save();
                $order++;
                $i++;
            }
        }
        # Refresh
        $cmd = $_eqLogic->getCmd(null, 'refresh');
        if (!is_object($cmd)) {
            log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=refresh');
            $cmd = new MerossIOTCmd();
            $cmd->setName('Refresh');
            $cmd->setIsVisible(1);
            $cmd->setLogicalId('refresh');
            $cmd->setEqLogic_id($_eqLogic->getId());
        } else {
            log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=refresh');
        }
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setGeneric_type('DONT');
        $cmd->setConfiguration('switch', 'read');
        $cmd->setOrder($order);
        $cmd->save();
        $order++;
        # Electicité
        if( $_device['elec'] ) {
            # Puissance
            $cmd = $_eqLogic->getCmd(null, 'power');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=power');
                $cmd = new MerossIOTCmd();
                $cmd->setName(__('Puissance', __FILE__));
                $cmd->setIsVisible(1);
                $cmd->setIsHistorized(1);
                $cmd->setEventOnly(1);
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setLogicalId('power');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=power');
            }
            $cmd->setType('info');
            $cmd->setSubType('numeric');
            $cmd->setGeneric_type('POWER');
            $cmd->setConfiguration('minValue', 0);
            $cmd->setConfiguration('maxValue', 4000);
            $cmd->setUnite('W');
            $cmd->setOrder($order);
            $cmd->save();
            $order++;
            # Courant
            $cmd = $_eqLogic->getCmd(null, 'current');
            if (!is_object($cmd)) {
                log::add('merossiot', 'debug', 'syncMeross: - Add cmd=current');
                $cmd = new MerossIOTCmd();
                $cmd->setName(__('Courant', __FILE__));
                $cmd->setIsVisible(1);
                $cmd->setIsHistorized(1);
                $cmd->setEventOnly(1);
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setLogicalId('current');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=current');
            }
            $cmd->setType('info');
            $cmd->setSubType('numeric');
            $cmd->setGeneric_type('GENERIC_INFO');
            $cmd->setConfiguration('minValue', 0);
            $cmd->setConfiguration('maxValue', 17);
            $cmd->setUnite('A');
            $cmd->setOrder($order);
            $cmd->save();
            $order++;
            # Tension
            $cmd = $_eqLogic->getCmd(null, 'tension');
            if (!is_object($cmd)) {
                log::add('merossiot', 'debug', 'syncMeross: - Add cmd=tension');
                $cmd = new MerossIOTCmd();
                $cmd->setName(__('Tension', __FILE__));
                $cmd->setIsVisible(1);
                $cmd->setIsHistorized(1);
                $cmd->setEventOnly(1);
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setLogicalId('tension');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=tension');
            }
            $cmd->setType('info');
            $cmd->setSubType('numeric');
            $cmd->setGeneric_type('VOLTAGE');
            $cmd->setConfiguration('minValue', 0);
            $cmd->setConfiguration('maxValue', 250);
            $cmd->setUnite('V');
            $cmd->setOrder($order);
            $cmd->save();
            $order++;
        }
        # Consommation
        if( $_device['conso'] ) {
            # Ce Jour
            $cmd = $_eqLogic->getCmd(null, 'conso_totale');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=conso_totale');
                $cmd = new MerossIOTCmd();
                $cmd->setName(__('Consommation', __FILE__));
                $cmd->setIsVisible(1);
                $cmd->setIsHistorized(1);
                $cmd->setEventOnly(1);
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setLogicalId('conso_totale');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=conso_totale');
            }
            $cmd->setType('info');
            $cmd->setSubType('numeric');
            $cmd->setGeneric_type('CONSUMPTION');
            $cmd->setUnite('kWh');
            $cmd->setOrder($order);
            $cmd->save();
            $order++;
        }
        # Lampe - Luminosité
        if( $_device['lumin'] ) {
            # Luminance information
            $cmd = $_eqLogic->getCmd(null, 'lumival');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=lumival');
                $cmd = new MerossIOTCmd();
                $cmd->setName('lumi');
                $cmd->setIsVisible(0);
                $cmd->setIsHistorized(0);
                $cmd->setLogicalId('lumival');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=lumival');
            }
            $cmd->setType('info');
            $cmd->setSubType('numeric');
            $cmd->setGeneric_type('LIGHT_STATE');
            $cmd->setConfiguration('minValue', 1);
            $cmd->setConfiguration('maxValue', 100);
            $cmd->setUnite('%');
            $cmd->setOrder($order);
            $cmd->save();
            $order++;
            $status_id =  $cmd->getId();
            # Luminance setter
            $cmd = $_eqLogic->getCmd(null, 'lumiset');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=lumiset');
                $cmd = new MerossIOTCmd();
                $cmd->setName(__('Luminosité', __FILE__));
                $cmd->setIsVisible(1);
                $cmd->setIsHistorized(0);
                $cmd->setLogicalId('lumiset');
                $cmd->setTemplate('dashboard', 'light');
                $cmd->setTemplate('mobile', 'light');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=lumiset');
            }
            $cmd->setType('action');
            $cmd->setSubType('slider');
            $cmd->setGeneric_type('LIGHT_SLIDER');
            $cmd->setConfiguration('minValue', 1);
            $cmd->setConfiguration('maxValue', 100);
            $cmd->setOrder($order);
            $cmd->save();
            $cmd->setValue($status_id);
            $cmd->save();
            $order++;
        }
        if( $_device['tempe'] ) {
            # Temperature information
            $cmd = $_eqLogic->getCmd(null, 'tempval');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=tempval');
                $cmd = new MerossIOTCmd();
                $cmd->setName('temp');
                $cmd->setIsVisible(0);
                $cmd->setIsHistorized(0);
                $cmd->setLogicalId('tempval');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=tempval');
            }
            $cmd->setType('info');
            $cmd->setSubType('numeric');
            $cmd->setGeneric_type('LIGHT_COLOR_TEMP');
            $cmd->setConfiguration('minValue', 1);
            $cmd->setConfiguration('maxValue', 100);
            $cmd->setOrder($order);
            $cmd->save();
            $order++;
            $status_id =  $cmd->getId();
            # Temperature setter
            $cmd = $_eqLogic->getCmd(null, 'tempset');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=tempset');
                $cmd = new MerossIOTCmd();
                $cmd->setName(__('Température', __FILE__));
                $cmd->setIsVisible(1);
                $cmd->setIsHistorized(0);
                $cmd->setLogicalId('tempset');
                $cmd->setTemplate('dashboard', 'light');
                $cmd->setTemplate('mobile', 'light');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=tempset');
            }
            $cmd->setType('action');
            $cmd->setSubType('slider');
            $cmd->setGeneric_type('LIGHT_SET_COLOR_TEMP');
            $cmd->setConfiguration('minValue', 1);
            $cmd->setConfiguration('maxValue', 100);
            $cmd->setOrder($order);
            $cmd->save();
            $cmd->setValue($status_id);
            $cmd->save();
            $order++;
        }
        if( $_device['isrgb'] ) {
            # Color information
            $cmd = $_eqLogic->getCmd(null, 'rgbval');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=rgbval');
                $cmd = new MerossIOTCmd();
                $cmd->setName('rgb');
                $cmd->setIsVisible(0);
                $cmd->setIsHistorized(0);
                $cmd->setLogicalId('rgbval');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('merossiot', 'debug', 'syncMeross: - Update cmd=rgbval');
            }
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setGeneric_type('LIGHT_COLOR');
            $cmd->setOrder($order);
            $cmd->save();
            $order++;
            $status_id = $cmd->getId();
            # Color setter
            $cmd = $_eqLogic->getCmd(null, 'rgbset');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=rgbset');
                $cmd = new MerossIOTCmd();
                $cmd->setName(__('Couleur', __FILE__));
                $cmd->setIsVisible(1);
                $cmd->setIsHistorized(0);
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setLogicalId('rgbset');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('MerossIOT', 'debug', 'syncMeross: - Update cmd=rgbset');
            }
            $cmd->setType('action');
            $cmd->setSubType('color');
            $cmd->setGeneric_type('LIGHT_SET_COLOR');
            $cmd->setOrder($order);
            $cmd->save();
            $cmd->setValue($status_id);
            $cmd->save();
            $order++;
        }
        # Light Mode
        if( $_device['tempe'] && $_device['isrgb'] ) {
            # information
            $cmd = $_eqLogic->getCmd(null, 'capacity');
            if (!is_object($cmd)) {
                log::add('MerossIOT', 'debug', 'syncMeross: - Add cmd=capacity');
                $cmd = new MerossIOTCmd();
                $cmd->setName(__('Mode', __FILE__));
                $cmd->setIsVisible(1);
                $cmd->setIsHistorized(0);
                $cmd->setEventOnly(1);
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setLogicalId('capacity');
                $cmd->setEqLogic_id($_eqLogic->getId());
            } else {
                log::add('merossiot', 'debug', 'syncMeross: - Update cmd=capacity');
            }
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setGeneric_type('GENERIC_INFO');
            $cmd->setOrder($order);
            $cmd->save();
        }
        log::add('MerossIOT', 'debug', 'updateEqLogicCmdVal: Update eqLogic informations Completed');
    }
    /**
     * Effacer tous les EqLogic
     * @return none
     */
    public function deleteAll()
    {
        log::add('MerossIOT','debug','***** DELETE ALL *****');
        $eqLogics = eqLogic::byType('MerossIOT');
        foreach ($eqLogics as $eqLogic) {
            $eqLogic->remove();
        }
        return array(true, 'OK');
    }
    /**
     * Get dependancy information
     * @return array Python3 command return.
     */
    public static function dependancy_info() {
        $return = [
            'state' => 'nok',
            'log' => 'MerossIOT_update',
            'progress_file' => jeedom::getTmpFolder('MerossIOT') . '/dependance'
        ];
        $meross_version = trim(file_get_contents(dirname(__FILE__) . '/../../resources/meross-iot_version.txt'));
        $cmd = "/usr/bin/python3 -c 'from distutils.version import LooseVersion;import pkg_resources,meross_iot,sys;" .
            "sys.exit(LooseVersion(pkg_resources.get_distribution(\"meross_iot\").version)<LooseVersion(\"".$meross_version."\"))' 2>&1";
        exec($cmd, $output, $return_var);
        if ($return_var == 0) {
            $return['state'] = 'ok';
        }
        return $return;
    }
    /**
     * Install dependancies.
     * @return array Shell script command return.
     */
    public static function dependancy_install() {
        log::remove(__CLASS__ . '_update');
        return [
            'script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('MerossIOT') . '/dependance',
            'log' => log::getPathToLog(__CLASS__ . '_update')
        ];
    }
    /**
     * Start python daemon.
     * @return array Shell command return.
     */
    public static function deamon_start() {
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        $user = config::byKey('MerossUSR', 'MerossIOT');
        $pswd = config::byKey('MerossPWD', 'MerossIOT');
        $updp = config::byKey('MerossUPD', 'MerossIOT');
        $merossiot_path = realpath(dirname(__FILE__) . '/../../resources');
        $callback = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/MerossIOT/core/php/jeeMerossIOT.php';

        $cmd = '/usr/bin/python3 ' . $merossiot_path . '/MerossIOTd/MerossIOTd.py';
        $cmd.= ' --muser '.$user;
        $cmd.= ' --mpswd '.$pswd;
        $cmd.= ' --mupdp '.$updp;
        $cmd.= ' --callback '.$callback;
        $cmd.= ' --apikey '.jeedom::getApiKey('MerossIOT');
        $cmd.= ' --loglevel '.log::convertLogLevel(log::getLogLevel('MerossIOT'));
        $cmd.= ' --pid '.jeedom::getTmpFolder('MerossIOT') . '/daemon.pid';
        $cmd.= ' --socket '.jeedom::getTmpFolder('MerossIOT') . '/daemon.sock';

        $log = str_replace($pswd, 'xxx', str_replace($user, 'xxx', $cmd));
        log::add('MerossIOT','info',__('Lancement démon meross :', __FILE__).' '.$log);
        $result = exec($cmd . ' >> ' . log::getPathToLog('MerossIOT') . ' 2>&1 &');
        $i = 0;
        while ($i < 10) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i++;
        }
        if ($i >= 10) {
            log::add('MerossIOT', 'error', __('Impossible de lancer le démon meross, vérifiez le log', __FILE__), 'unableStartDeamon');
            return false;
        }
        message::removeAll('MerossIOT', 'unableStartDeamon');
        log::add('MerossIOT','info',__('Démon meross lancé.', __FILE__));
        return true;
    }
    /**
     * Stop python daemon.
     * @return array Shell command return.
     */
    public static function deamon_stop() {
        $pid_file = jeedom::getTmpFolder('MerossIOT') . '/daemon.pid';
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            system::kill($pid);
        }
        $i = 0;
        while ($i < 5) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'nok') {
                break;
            }
            sleep(1);
            $i++;
        }
        if ($i >= 5) {
            log::add('MerossIOT', 'error', __('Impossible de stopper le démon meross, tuons le', __FILE__));
            system::kill('MerossIOTd.py');
        }
    }
    /**
     * Return information (status) about daemon.
     * @return array Shell command return.
     */
    public static function deamon_info() {
        $pid_file = jeedom::getTmpFolder('MerossIOT') . '/daemon.pid';
        $return = ['state' => 'nok'];

        if (file_exists($pid_file)) {
            if (@posix_getsid(trim(file_get_contents($pid_file)))) {
                $return['state'] = 'ok';
            } else {
                shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
            }
        }
        $return['launchable'] = 'ok';

        if (self::dependancy_info()['state'] == 'nok') {
            $cache = cache::byKey('dependancy' . 'MerossIOT');
            $cache->remove();
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Veuillez (ré-)installer les dépendances', __FILE__);
        }
        return $return;
    }
}

class MerossIOTCmd extends cmd {
    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic();
        $action = $this->getLogicalId();
        log::add('MerossIOT', 'debug', $eqLogic->getLogicalId().' = action: '. $action.' - params '.json_encode($_options) );
        $execute = false;
        // Handle actions like on_x off_x
        $splitAction = explode("_", $action);
        $action = $splitAction[0];
        $channel = $splitAction[1];
        switch ($action) {
            case "on":
                $res = MerossIOT::callMeross('setOn', [$eqLogic->getLogicalId(), $splitAction[1]]);
                log::add('MerossIOT', 'debug', 'setOn: '.json_encode($res['result']));
                break;
            case "off":
                $res = MerossIOT::callMeross('setOff', [$eqLogic->getLogicalId(), $splitAction[1]]);
                log::add('MerossIOT', 'debug', 'setOff: '.json_encode($res['result']));
                break;
            case "lumiset":
                $res = MerossIOT::callMeross('setLumi', [$eqLogic->getLogicalId(), $_options['slider']]);
                log::add('MerossIOT', 'debug', 'setLumi '.$_options['slider'].': '.$res['result']);
                break;
            case "tempset":
                $cmd = $eqLogic->getCmd(null, 'lumival');
                $lumi = $cmd->execCmd();
                $res = MerossIOT::callMeross('setTemp', [$eqLogic->getLogicalId(), $_options['slider'], $lumi]);
                log::add('MerossIOT', 'debug', 'setTemp '.$_options['slider'].': '.$res['result']);
                break;
            case "rgbset":
                $cmd = $eqLogic->getCmd(null, 'lumival');
                $lumi = $cmd->execCmd();
                $rgb = hexdec($_options['color']);
                $res = MerossIOT::callMeross('setRGB', [$eqLogic->getLogicalId(), $rgb, $lumi]);
                log::add('MerossIOT', 'debug', 'setRGB '.$_options['color'].' ('.$rgb.'): '.$res['result']);
                break;
            case "refresh":
                $res = MerossIOT::callMeross('syncDevice', [$eqLogic->getLogicalId()]);
                MerossIOT::syncOneMeross($res['result']);
                log::add('MerossIOT', 'debug', 'refresh: '.json_encode($res['result']));
                break;
            default:
                log::add('MerossIOT','debug','action: Action='.$action.' '.__('non implementée.', __FILE__));
                break;
        }
    }
}
