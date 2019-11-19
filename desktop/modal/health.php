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
if (!isConnect('admin')) {
    throw new \Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('MerossIOT');
$eqLogics = MerossIOT::byType('MerossIOT');
?>
<table class="table table-condensed tablesorter" id="table_healthmeross">
    <thead>
    <tr>
        <th>{{Image}}</th>
        <th>{{Nom}}</th>
        <th>{{ID}}</th>
        <th>{{Modèle}}</th>
        <th>{{IP}}</th>
        <th>{{Online}}</th>
        <th>{{Date création}}</th>
        <th>{{Date dernière communication}}</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach( $eqLogics as $eqLogic ) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'opacity:0.3';
        if( file_exists(dirname(__FILE__) . '/../../docs/images/' . $eqLogic->getConfiguration('type') . '.png')) {
            $image = '<img src="plugins/MerossIOT/docs/images/' . $eqLogic->getConfiguration('type') . '.png" height="55" width="55" style="' . $opacity . '" />';
        } else {
            $image = '<img src="' . $plugin->getPathImgIcon() . '" height="55" width="55" style="' . $opacity . '" />';
        }
        $status = '<span class="label label-success" style="font-size : 1em;cursor:default;">{{OK}}</span>';
        if( $eqLogic->getConfiguration('online') != 1 ) {
            $status = '<span class="label label-danger" style="font-size : 1em;cursor:default;">{{NOK}}</span>';
        }
        echo '<tr>';
        echo '<td>' . $image . '</td><td><a href="' . $eqLogic->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqLogic->getHumanName(true) . '</a></td>';
        echo '<td><span class="label label-info" style="font-size : 1em;">' . $eqLogic->getId() . '</span></td>';
        echo '<td><span class="label label-info" style="font-size : 1em;">' . $eqLogic->getConfiguration('type') . '</span></td>';
        echo '<td><span class="label label-info" style="font-size : 1em;">' . $eqLogic->getConfiguration('ip') . '</span></td>';
        echo '<td>' . $status . '</td>';
        echo '<td><span class="label label-info" style="font-size : 1em;">' . $eqLogic->getConfiguration('createtime') . '</span></td>';
        echo '<td><span class="label label-info" style="font-size : 1em;cursor:default;">' . $eqLogic->getStatus('lastCommunication') . '</span></td>';
        echo '</tr>';
    }
    ?>
    </tbody>
</table>
