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
    'id' => '2015081901',
    'description' => 'Solución al fallo que impedía eliminar entregas completas. Tampoco desaparecen los eventos asociados a una carpeta borrada.'
);

if ($ok && (false === $simulate)) {
    $query = false;
    try {
        $query = ORM::get_db()->exec("ALTER TABLE `delivery` DROP FOREIGN KEY `delivery_current_revision_id_fk`;");
        $query = ORM::get_db()->exec("ALTER TABLE `delivery` ADD  CONSTRAINT `delivery_current_revision_id_fk` FOREIGN KEY (`current_revision_id`) REFERENCES `atica`.`revision`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
        $query = ORM::get_db()->exec("ALTER TABLE `event` DROP FOREIGN KEY `event_ibfk_1`;");
        $query = ORM::get_db()->exec("ALTER TABLE `event` ADD  CONSTRAINT `event_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `atica`.`folder`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
    }
    catch(Exception $e) {
        $ok = false;
        $query = false;
    }
    if ($query) {
        $ok = setModuleVersion('core', '2015081901');
    }
}
// Volver a obtener la versión del núcleo
$core = getModuleVersion('core');
