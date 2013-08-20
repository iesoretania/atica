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
    $username = strtolower(trim($_POST['username']));

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