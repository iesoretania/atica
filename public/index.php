<?php

/*  ATICA - Web application for supporting Quality Management Systems
  Copyright (C) 2009-2013: Luis-Ramón López López

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

session_name('ATICAID');
session_cache_limiter(false);
session_start();

// Leer configuración global
$config = array();

$data = ORM::for_table('configuration')->where_not_null('content_type')->
        where_null('organization_id')->find_many();

foreach($data as $item) {
    $config[$item['id']] = $item['content'];
}

// Leer datos de la organización
if (isset($_SESSION['organization_id'])) {
    $organization =
            ORM::for_table('organization')->
            find_one($_SESSION['organization_id'])->as_array();
    
    $data = ORM::for_table('configuration')->
            where_equal('organization_id', $_SESSION['organization_id'])->
            where_not_null('content_type')->
            find_many();

            foreach($data as $item) {
                $config[$item['id']] = $item['content'];
            }
} else {
    $organization = NULL;
}

// Prepare app
$app = new \Slim\Slim(array(
    'templates.path' => '../templates',
    'log.level' => 4,
    'log.enabled' => true,
    'log.writer' => new \Slim\Extras\Log\DateTimeFileWriter(array(
        'path' => '../logs',
        'name_format' => 'y-m-d'
            ))
        ));

// Prepare view
$view = $app->view(new \Slim\Views\Twig());
$view->parserOptions = array(
    'charset' => 'utf-8',
    'cache' => realpath('../templates/cache'),
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
);
$twig = $view->getInstance();
$twig->addGlobal('organization', $organization);
$twig->addGlobal('config', $config);
// Define routes
$app->get('/', function () use ($app) {
            $app->redirect('/inicio');
        });

$app->get('/inicio(/:id)', function ($id = '') use ($app) {
            $people = ORM::for_table('person')->order_by_asc('user_name')->find_array();
            $breadcrumb = array(array('display_name' => 'Tareas', 'target' => '#'), array('display_name' => 'Pendientes', 'target' => '#'));
            $app->render('inicio.html.twig', array('people' => $people, 'navigation' => $breadcrumb, 'search' => true));
        })->name('inicio');

$app->get('/entrar(/:id)', function ($id = '') use ($app) {
            $breadcrumb = array(array('display_name' => 'Acceder', 'target' => '#'));
            $app->render('entrar.html.twig', array('navigation' => $breadcrumb));
        })->name('entrar');

$app->post('/entrar(/:id)', function ($id = '') use ($app) {
            $breadcrumb = array(array('display_name' => 'Dentro', 'target' => '#'));
            $app->render('base.html.twig', array('navigation' => $breadcrumb));
        });

$app->get('/bienvenida', function () use ($app) {
            $instance = array('display_name' => 'I.E.S. Oretania');
            $breadcrumb = array(array('display_name' => 'Primer acceso', 'target' => '#'));
            $app->render('bienvenida.html.twig', array('navigation' => $breadcrumb));
        })->name('bienvenida');

// Run app
$app->run();
