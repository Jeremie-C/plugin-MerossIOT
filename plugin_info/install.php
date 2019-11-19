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
/**
 * Jeedom plugin installation function.
 */
function MerossIOT_install()
{
    message::removeAll('MerossIOT');
    message::add('MerossIOT', '{{Installation du plugin MerossIOT terminée}}''.', null, null);
}
/**
 * Jeedom plugin update function.
 */
function MerossIOT_update()
{
    log::add('MerossIOT', 'debug', 'MerossIOT_update');
    $daemonInfo = MerossIOT::deamon_info();
    if( $daemonInfo['state'] == 'ok' ) {
        MerossIOT::deamon_stop();
    }
    $cache = cache::byKey('dependancy' . 'MerossIOT');
    $cache->remove();
    MerossIOT::dependancy_install();
    message::removeAll('MerossIOT');
    message::add('MerossIOT', '{{Mise à jour du plugin MerossIOT terminée}}''.', null, null);
    MerossIOT::deamon_start();
}
/**
 * Jeedom plugin remove function.
 */
function MerossIOT_remove()
{
    log::add('MerossIOT', 'debug', 'MerossIOT_remove');
    $daemonInfo = MerossIOT::deamon_info();
    if( $daemonInfo['state'] == 'ok' ) {
        MerossIOT::deamon_stop();
    }
    message::removeAll('MerossIOT');
    message::add('MerossIOT', '{{Désinstallation du plugin MerossIOT terminée}}''.', null, null);
}
