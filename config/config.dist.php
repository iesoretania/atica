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

ORM::configure(array(
    'connection_string' => 'mysql:host=localhost;dbname=atica',
    'username' => 'root',
    'password' => 'root'
));

ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

$preferences = array(
    'appname' => 'ÁTICA',
    'upload.folder' => "../data/",
    // poner a true si sólo queremos enviar cookies a través de https
    'security.securecookies' => false,
    'salt' => 'v2j4d+-),qh@80q]}6XLqbYrq=)`::HVh9VU9j~jDX?', // ¡¡¡cambiar por favor!!!
    'login.retries' => 5,   // 5 intentos infructuosos de entrar antes del bloqueo
    'login.block' => 5,     // 5 minutos de bloqueo

    // iSéneca web URL
    // Poner a "true" la siguiente opción para activar a nivel global la comprobación externa
    'external.enabled' => false,
    'external.url' => 'https://seneca.juntadeandalucia.es/seneca/jsp/ComprobarUsuarioExt.jsp',
    // El siguiente parámetro evita que se pueda interceptar las credenciales mediante un ataque MITM
    // así que sólo debe ponerse a "false" si existe algún problema con los certificados de la Consejería de Educación
    'external.url.force_security' => true
);
