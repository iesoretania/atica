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

require '../vendor/autoload.php';
require_once '../config/config.php';
error_reporting(E_ALL ^ E_DEPRECATED);
session_set_cookie_params(0, null, null,
        isset($preferences['security.securecookies']) ?
            $preferences['security.securecookies'] : false,
        true);

session_name('ATICAID');
session_cache_limiter(false);
session_start();

// Preparar aplicación
$app = new \Slim\Slim(array(
    'mode' => 'development',
    'templates.path' => '../templates',
    'log.level' => \Slim\Log::DEBUG,
    'log.enabled' => true,
    'log.writer' => new \Slim\Extras\Log\DateTimeFileWriter(array(
        'path' => '../logs',
        'name_format' => 'y-m-d'
            ))
        ));

// Preparar vistas
$view = $app->view(new \Slim\Views\Twig());
$view->parserOptions = array(
    'charset' => 'utf-8',
    'debug' => true,
    'cache' => realpath('../templates/cache'),
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new \Atica\Extension\TwigExtension(),
    new \Twig_Extension_Debug()
);
$twig = $view->getInstance();

// Leer configuración global
$config = array(
    'appname' => $preferences['appname'],
    'base_url' => $app->request()->getUrl() . $app->request()->getRootUri() . '/');

$app->setName($preferences['appname']);

$data = ORM::for_table('configuration')->where_not_null('content_type')->
        where_null('organization_id')->find_array();

// Leer datos de la organización
$organization = null;
if (isset($_SESSION['organization_id'])) {
    $organization =
            ORM::for_table('organization')->
            find_one($_SESSION['organization_id'])->as_array();
} else {
    if (1 == ORM::for_table('organization')->count()) {
        $organization = ORM::for_table('organization')->find_one()->as_array();
        $_SESSION['organization_id'] = $organization['id'];
    }
    else {
        // autodetectar organización a partir de la URL
        $organizations = ORM::for_table('organization')->order_by_asc('code')->
                where_not_null('url_prefix')->find_many();

        foreach ($organizations as $org) {
            if (0 === strpos($config['base_url'], $org['url_prefix'])) {
                $_SESSION['organization_id'] = $org['id'];
                $organization = $org;
                break;
            }
        }
    }
}

// Leer configuración local de la organización
if (null != $organization) {
    $data = array_merge($data, ORM::for_table('configuration')->
            where_equal('organization_id', $_SESSION['organization_id'])->
            where_not_null('content_type')->
            find_array());
}

if (($data) && (count($data)>0)) {
    foreach($data as $item) {
        // convertir las filas de configuración en un array
        $config[$item['item_id']] = $item['content'];
    }
}

// Leer datos del usuario activo
$user = null;
if (isset($_SESSION['person_id'])) {
    $user = ORM::for_table('person')->
            find_one($_SESSION['person_id'])->as_array();

    // comprobar si pertenece a la organización
    $membership = ORM::for_table('person_organization')->
            where('organization_id', $_SESSION['organization_id'])->
            where('person_id', $_SESSION['person_id'])->
            where('is_active', 1)->
            find_one()->as_array();

    // si no pertenece, sacar al usuario porque no debería ocurrir
    if (!$membership) {
        $user = null;
        unset($_SESSION['person_id']);
    }
    else {
        // comprobar si es administrador local (siempre lo será si es
        // administrador global)
        $user['is_admin'] = $user['is_global_administrator'] ||
                $membership['is_local_administrator'];
        $user['is_active'] = $membership['is_active'];
        $user['is_local_administrator'] = $membership['is_local_administrator'];
    }
}

// Definir variables globales para las plantillas
$twig->addGlobal('config', $config);
$twig->addGlobal('organization', $organization);
$twig->addGlobal('user', $user);

// Definir rutas
\Slim\Route::setDefaultConditions(array(
    'id' => '[0-9]{1,}'
));

require('../routes/session.php');
require('../routes/personal.php');
require('../routes/frontpage.php');
require('../routes/activities.php');
require('../routes/event.php');
require('../routes/tree.php');
require('../routes/upload.php');
require('../routes/manage.php');
require('../routes/admin.php');

// Ejecutar aplicación
$app->run();

function parseArray($data, $column='id') {
    $return = array();
    
    foreach($data as $item) {
        $return[$item[$column]] = $item;
    }
    
    return $return;
}

function parseArrayMix($data, $column='id') {
    $return = array();

    foreach($data as $item) {
        $return[$item[$column]][] = $item;
    }

    return $return;
}
