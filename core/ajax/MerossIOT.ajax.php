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
   // Ajoute le fichier du core qui se charge d'inclure tous les fichiers nécessaires
   require_once __DIR__ . '/../../../../core/php/core.inc.php';
   // Ajoute le fichier de gestion des authentifications
   include_file('core', 'authentification', 'php');
   // Test si l'utilisateur est connecté
   if (!isConnect('admin')) {
       // Lève un exception si l'utilisateur n'est pas connecté avec les bons droits
       throw new \Exception(__('401 - Accès non autorisé', __FILE__));
   }
   // Initialise la gestion des requêtes Ajax
   ajax::init();
   // SYNC
   if (init('action') == 'syncMeross') {
       MerossIOT::syncMeross(false);
       ajax::success();
   }
   // Delete
   if (init('action') == 'deleteAll') {
       MerossIOT::deleteAll();
       ajax::success();
   }
   // Lève une exception si la requête n'a pas été traitée avec succès (Appel de la fonction ajax::success());
   throw new \Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
 } catch (\Exception $e) {
   // Affiche l'exception levé à l'utilisateur
   ajax::error(displayExeption($e), $e->getCode());
 }
