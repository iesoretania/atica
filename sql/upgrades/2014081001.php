<?php

/*  ATICA - Web application for supporting Quality Management Systems
  Copyright (C) 2009-2015: Luis-Ramón López López

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see [http://www.gnu.org/licenses/]. */

// Upgrade to database version 2014081001

$updates[] = array(
    'id' => '2014081001',
    'description' => 'Soporte para forzar las fechas de entrega'
);

if ($ok && (false === $simulate)) {
    $query = false;
    try {
        $query = ORM::get_db()->exec("ALTER TABLE `event` ADD `force_period` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `period_description`, ADD `grace_period` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `force_period`;");
    }
    catch(Exception $e) {
        $ok = false;
        $query = false;
    }
    if ($query) {
        $ok = setModuleVersion('core', '2014081001');
    }
}
// Volver a obtener la versión del núcleo
$core = getModuleVersion('core');
