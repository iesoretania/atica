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

// Preparar aplicación
$app = new \Slim\Slim(array(
    'templates.path' => '../templates',
    'log.level' => 4,
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
    'cache' => realpath('../templates/cache'),
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
);
$twig = $view->getInstance();


// Leer configuración global
$config = array( 'appname' => $preferences['appname'] );

$data = ORM::for_table('configuration')->where_not_null('content_type')->
        where_null('organization_id')->find_array();

// Leer datos de la organización
$organization = NULL;
if (isset($_SESSION['organization_id'])) {
    $organization =
            ORM::for_table('organization')->
            find_one($_SESSION['organization_id'])->as_array();
} else {
    if (1 == ORM::for_table('organization')->count()) {
        $organization = ORM::for_table('organization')->find_one()->as_array();
        $_SESSION['organization_id'] = $organization['id'];
    }
}

if (NULL != $organization) {
    // configuración local de la organización
    $data = array_merge($data, ORM::for_table('configuration')->
            where_equal('organization_id', $_SESSION['organization_id'])->
            where_not_null('content_type')->
            find_array());
}

foreach($data as $item) {
    $config[$item['item_id']] = $item['content'];
}

// Leer datos del usuario activo
$user = NULL;
if (isset($_SESSION['person_id'])) {
    $user = ORM::for_table('person')->
            find_one($_SESSION['person_id'])->as_array();
    
    // comprobar si pertenece a la organización
    $membership = ORM::for_table('person_organization')->
            where('organization_id', $_SESSION['organization_id'])->
            where('person_id', $_SESSION['person_id'])->find_one();
    
    // si no pertenece, sacar al usuario porque no debería ocurrir
    if (!$membership) {
        $user = NULL;
        unset($_SESSION['person_id']);
    }
    else {
        // comprobar si es administrador local (siempre lo será si es
        // administrador global)
        $user['is_local_administrator'] = $user['is_global_administrator'] ||
                $membership['is_local_administrator'];
    }
}

// Definir variables globales para las plantillas
$twig->addGlobal('config', $config);
$twig->addGlobal('organization', $organization);
$twig->addGlobal('user', $user);

// Definir rutas
$app->get('/', function () use ($app) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect('organizacion');
    }
    $app->redirect('inicio');
});

$app->get('/organizacion', function () use ($app) {
    $breadcrumb = NULL;
    $organizations = ORM::for_table('organization')->
            order_by_asc('display_name')->find_array();
    $app->render('organizacion.html.twig', array('navigation' => $breadcrumb,
        'organizations' => $organizations));
})->name('organizacion');

$app->post('/organizacion', function () use ($app) {
    $organization_nr = ORM::for_table('organization')->
            where('id',$_POST['organization_id'])->count();
    if (1 == $organization_nr) {
        $_SESSION['organization_id'] = $_POST['organization_id'];
        $app->redirect('/inicio');
    }
    else {
        $app->redirect('/organizacion');
    }
});

$app->get('/inicio(/:id)', function ($id = '') use ($app) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect('organizacion');
    }
    $breadcrumb = array(
        array('display_name' => 'Portada', 'target' => '#'));
    $app->render('inicio.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true));
})->name('inicio');

$app->get('/entrar(/:id)', function ($id = '') use ($app) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect('organizacion');
    }    
    $breadcrumb = array(array('display_name' => 'Acceder', 'target' => '#'));
    $app->render('entrar.html.twig', array('navigation' => $breadcrumb));
})->name('entrar');

$app->post('/entrar(/:id)', function ($id = '') use ($app, $preferences) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect('organizacion');
    }
    $username = trim($_POST['username']);
    
    $login_security = ORM::for_table('person')->
            select('retry_count')->select('blocked_access')->
            where('user_name', $username)->find_one();
    
    if ((!$login_security) ||
        ($login_security && (NULL == $login_security['blocked_access']))) {
        
        $user = ORM::for_table('person')->
                where('user_name', $username)->
                where('password', sha1($preferences['salt'] . $_POST['password']))->
                find_one();

        if ($user) {
            $login_security->set('retry_count', 0);
            $login_security->save();            
            
            $membership = ORM::for_table('person_organization')->
                    where('organization_id', $_SESSION['organization_id'])->
                    where('person_id', $user['id'])->find_one();

            if ($membership) {
                if ($membership['is_active']) {
                    $_SESSION['person_id'] = $user['id'];
                    $app->redirect('bienvenida');                
                }
            }
            else {
                $app->flash('login_error', 'no organization');
            }
        }
        else {
            if ($login_security) {
                $login_security->set('retry_count', 1);
                $login_security->save();
            }
            $app->flash('login_error', 'not found');
        }
    }
    else {
        $app->flash('login_error', 'blocked');
        $app->flash('login_blocked_for', $login_security['blocked_access']);
    }
    $app->redirect('entrar');
});

$app->get('/bienvenida', function () use ($app, $user) {
    if (!$user) {
        $app->redirect('inicio');
    }    
    $breadcrumb = array(array('display_name' => 'Primer acceso', 'target' => '#'));
    $app->render('bienvenida.html.twig', array('navigation' => $breadcrumb));
})->name('bienvenida');

// Run app
$app->run();
