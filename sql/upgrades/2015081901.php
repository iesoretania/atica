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

// Upgrade to database version 2015081901

$updates[] = array(
    'id' => '2017091201',
    'description' => 'Añadido soporte de autenticación externa.'
);

if ($ok && (false === $simulate)) {
    try {
        ORM::get_db()->exec("ALTER TABLE `person` ADD `is_external` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `is_global_administrator`;");
    }
    catch(Exception $e) {
        $ok = false;
    }
    $ok = $ok && setModuleVersion('core', '2017091201');
}
// Volver a obtener la versión del núcleo
$core = getModuleVersion('core');
