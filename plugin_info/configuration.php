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
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Email}}</label>
            <div class="col-lg-2">
                <input type="email" class="configKey form-control" data-l1key="MerossUSR" placeholder="{{Email de votre compte Meross}}" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Mot de passe}}</label>
            <div class="col-lg-2">
                <input type="password" class="configKey form-control" data-l1key="MerossPWD" placeholder="{{Mot de passe Meross}}" />
            </div>
        </div>
       <div class="form-group">
            <label class="col-lg-4 control-label">{{Cycle mise à jour puissance (secondes)}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="MerossUPD" placeholder="30" />
            </div>
        </div>
    </fieldset>
</form>
