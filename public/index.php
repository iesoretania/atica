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
$config = array( 'appname' => $preferences['appname'],
        'base_url' => $app->request()->getUrl() .
                $app->request()->getRootUri() . '/');

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
$app->get('/organizacion', function () use ($app, $user) {
    if (isset($user)) {
        $app->redirect($app->urlFor('activities'));
    }
    $breadcrumb = NULL;
    $organizations = ORM::for_table('organization')->
            order_by_asc('display_name')->find_array();
    $app->render('organization.html.twig', array('navigation' => $breadcrumb,
        'organizations' => $organizations));
})->name('organization');

$app->post('/organizacion', function () use ($app, $user) {
    if (isset($user)) {
        $app->redirect($app->urlFor('activities'));
    }
    $organization_nr = ORM::for_table('organization')->
            where('id',$_POST['organization_id'])->count();
    if (1 == $organization_nr) {
        $_SESSION['organization_id'] = $_POST['organization_id'];
        $app->redirect($app->urlFor('frontpage'));
    }
    else {
        $app->redirect($app->urlFor('organization'));
    }
});
$app->get('/entrar', function () use ($app) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect($app->urlFor('organization'));
    }
    $breadcrumb = array(array('display_name' => 'Acceder', 'target' => '#'));
    $app->render('login.html.twig', array('navigation' => $breadcrumb));
})->name('login');

$app->post('/entrar', function () use ($app, $preferences) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect($app->urlFor('organization'));
    }
    $username = trim($_POST['username']);

    // comprobar si el usuario está bloqueado
    $now = date('Y-m-d H:i:s');
    $login_security = ORM::for_table('person')->
            select('id')->
            select('user_name')->
            select('blocked_access')->
            select('retry_count')->
            where('user_name', $username)->
            find_one();

    if ((!$login_security) ||
        ($login_security && ($login_security['blocked_access'] <= $now))) {

        $user = ORM::for_table('person')->
                where('user_name', $username)->
                where('password', sha1($preferences['salt'] . $_POST['password']))->
                find_one();

        if ($user) {
            // poner a cero la cuenta de intentos infructuosos
            $user->set('retry_count' ,0);
            $user->set('blocked_access', NULL);
            $user->set('last_login', $now);
            $user->save();

            $membership = ORM::for_table('person_organization')->
                    where('organization_id', $_SESSION['organization_id'])->
                    where('person_id', $user['id'])->find_one();

            if ($membership) {
                if ($membership['is_active']) {
                    $_SESSION['person_id'] = $user['id'];
                    $req = $app->request();
                    $app->redirect($app->urlFor('activities'));
                }
                else {
                    $app->flash('login_error', 'not active');
                }
            }
            else {
                $app->flash('login_error', 'no organization');
            }
        }
        else {
            if ($login_security) {
                // comprobar el número de intentos infructuosos
                $login_security->set('retry_count', $login_security['retry_count']+1);
                if ($login_security['retry_count'] >= $preferences['login.retries']) {
                    // bloquear al usuario
                    $until = new DateTime;
                    $until->modify("+" . $preferences['login.block'] . " min");
                    $login_security->set('blocked_access', $until->format('Y-m-d H:i:s'));
                }
                $login_security->save();
            }
            $app->flash('login_error', 'not found');
        }
    }
    else {
        $app->flash('login_error', 'blocked');
        $until = new DateTime($login_security['blocked_access']);
        $until->modify('+1 min');
        $app->flash('login_blocked_for', $until->format('H:i'));
    }
    $app->redirect($app->urlFor('login'));
});

$app->get('/salir', function () use ($app) {
    unset($_SESSION['person_id']);
    $app->flash('home_info', 'logout');
    $app->redirect($app->urlFor('frontpage'));
})->name('logout');

$app->get('/bienvenida', function () use ($app, $user) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }
    $breadcrumb = array(array('display_name' => 'Primer acceso', 'target' => '#'));
    $app->render('welcome.html.twig', array('navigation' => $breadcrumb));
})->name('welcome');

$app->get('/personal', function () use ($app, $user) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }
    $breadcrumb = array(array('display_name' => 'Datos personales', 'target' => '#'));
    $app->render('personal.html.twig', array('navigation' => $breadcrumb));
})->name('personal');

$app->get('/(portada)', function () use ($app, $user) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect($app->urlFor('organization'));
    }
    $breadcrumb = array(
        array('display_name' => 'Portada', 'target' => '#')
    );
    $sidebar = array();

    array_push($sidebar, array(
        array('caption' => 'Secciones', 'icon' => 'list'),
        array('caption' => 'Portada', 'active' => true, 'target' => $app->urlFor('frontpage')),
        array('caption' => 'Plan de centro', 'target' => '#'),
        array('caption' => 'Criterios de evaluación', 'target' => '#'),
        array('caption' => 'Programaciones didácticas', 'target' => '#')
    ));

    if ($user) {
        array_push($sidebar, array(
            array('caption' => 'Navegación', 'icon' => 'compass'),
            array('caption' => 'Actividades', 'target' => $app->urlFor('activities')),
            array('caption' => 'Árbol de documentos', 'target' => $app->urlFor('frontpage'))
        ));
    }
    $app->render('frontpage.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'sidebar' => $sidebar));
})->name('frontpage');

$app->get('/actividades(/:id)', function ($id = NULL) use ($app, $user) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }
    
    // obtener perfiles
    $profiles = ORM::for_table('person_profile')->
            inner_join('profile', array('person_profile.profile_id','=','profile.id'))->
            inner_join('profile_group', array('profile_group.id','=','profile.profile_group_id'))->
            where('person_id', $user['id'])->
            order_by_asc('profile_group.display_name_neutral')->find_many();
    
    // barra lateral de perfiles
    $profile_bar = array(
        array('caption' => 'Actividades', 'icon' => 'list'),
        array('caption' => 'Ver todas', 'active' => ($id == NULL), 'target' => $app->urlFor('activities'))
    );
    $current = NULL;
    $detail = '';
    $profile_ids = array();
    foreach ($profiles as $profile) {
        $gender = array ($profile['display_name_neutral'], $profile['display_name_male'], $profile['display_name_female']);
        $caption = $gender[$user['gender']] . " " . $profile['display_name'];
        if ($profile['id'] == $id) {
            $current = $profile;
            $detail = $caption;
            $active = true;
        }
        else {
            $active = false;
        }
        array_push($profile_ids, $profile['id']);
        array_push($profile_bar, array('caption' => $caption,
            'active' => $active, 'target' => $app->urlFor('activities', array('id' => $profile['id']))));
    }
    
    // si hay un perfil como parámetro que no está asociado al usuario, redirigir
    if ((NULL != $id) && (NULL == $current)) {
        $app->redirect($app->urlFor('activities'));
    }
    
    $sidebar = array();

    array_push($sidebar, $profile_bar);
    
    // añadir barra lateral de navegación
    array_push($sidebar, array(
        array('caption' => 'Navegación', 'icon' => 'compass'),
        array('caption' => 'Portada', 'target' => $app->urlFor('frontpage')),
        array('caption' => 'Árbol de documentos', 'target' => $app->urlFor('frontpage'))
    ));    
    
    // barra superior de navegación
    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => $detail, 'target' => '#')
    );
    
    if ($id) {
        $profile_ids = array( $id );
    }
    
    // obtener actividades
    $activities = ORM::for_table('event')->
        inner_join('activity_event', array('activity_event.event_id', '=', 'event.id'))->
        inner_join('activity_profile', array('activity_profile.activity_id', '=', 'activity_event.activity_id'))->
        group_by('activity_event.event_id')->
        group_by('activity_profile.profile_id')->
        order_by_asc('activity_event.activity_id')->
        order_by_asc('order_nr')->
        where_in('profile_id', $profile_ids)->find_array();
    
    // generar página
    $app->render('activities.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'detail' => $detail, 'sidebar' => $sidebar, 'activities' => $activities));
})->name('activities');

// Ejecutar aplicación
$app->run();
