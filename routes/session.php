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

$app->get('/centros', function () use ($app, $user) {
    if (isset($user)) {
        $app->redirect($app->urlFor('activities'));
    }
    $breadcrumb = null;
    $organizations = ORM::for_table('organization')->
            order_by_asc('display_name')->find_array();
    $app->render('organization.html.twig', array('navigation' => $breadcrumb,
        'organizations' => $organizations));
})->name('organization');

$app->post('/centros', function () use ($app, $user) {
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

$app->post('/entrar', function () use ($app, $preferences, $organization) {
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
            select('last_login')->
            select('is_external')->
            select('password')->
            where('user_name', $username)->
            find_one();

    if ((!$login_security) ||
        ($login_security && ($login_security['blocked_access'] <= $now))) {

        $ok = false;
        $user = null;

        // si la autenticación externa está activada y el usuario habilitado, comprobar
        if ($login_security && $login_security['is_external'] && isset($preferences['external.enabled'])) {
            $authenticator = new \Atica\Service\SenecaAuthenticatorService($preferences['external.url'],
                $preferences['external.url.force_security'], $preferences['external.enabled']);
            $ok = $authenticator->checkUserCredentials($username, $_POST['password']);
            // si autentica, actualizar la clave en la base de datos
            if ($ok) {
                $user = ORM::for_table('person')->
                where('user_name', $username)->
                find_one();
                $login_security->set('password', sha1($preferences['salt'] . $_POST['password']));
                $login_security->save();
            }
        }

        if (!$ok) {
            $user = ORM::for_table('person')->
            where('user_name', $username)->
            where('password', sha1($preferences['salt'] . $_POST['password']))->
            find_one();
        }

        if ($ok || $user) {
            // obtener pertenencia a la organización
            $membership = ORM::for_table('person_organization')->
                    where('organization_id', $_SESSION['organization_id'])->
                    where('person_id', $user['id'])->find_one();

            // poner a cero la cuenta de intentos infructuosos
            $user->set('retry_count' ,0);
            $user->set('blocked_access', null);
            $firstLogin = ($user['last_login'] == null);

            if ($membership) {
                if ($membership['is_active']) {
                    // registrar la hora en la que ha entrado con éxito
                    $user->set('last_login', $now);
                    $user->save();
                    $_SESSION['person_id'] = $user['id'];
                    // si es la primera conexión, enviar a su página de datos
                    // personales
                    if ($firstLogin) {
                        doRegisterAction($app, $user, $organization, 'session', 0, 'login', 'first');
                        $app->flash('last_url', $app->urlFor('activities'));
                        $app->redirect($app->urlFor('personal', array('id' => $user['id'], 'section' => 0)));
                    }
                    else {
                        doRegisterAction($app, $user, $organization, 'session', 0, 'login', '');
                        $app->redirect($app->urlFor('activities'));
                    }
                }
                else {
                    doRegisterAction($app, $user, $organization, 'session', 1, 'login_error', 'not active');
                    $app->flash('login_error', 'not active');
                }
            }
            else {
                doRegisterAction($app, $user, null, 'session', 2, 'login_error', 'no organization');
                $app->flash('login_error', 'no organization');
            }
            // guardar cambios aunque haya ocurrido un error
            $user->save();
        }
        else {
            if ($login_security) {
                // comprobar el número de intentos infructuosos
                $login_security->set('retry_count', $login_security['retry_count']+1);
                doRegisterAction($app, $user, null, 'session', 3, 'login_error', 'bad password');
                if ($login_security['retry_count'] >= $preferences['login.retries']) {
                    // bloquear al usuario
                    $until = new DateTime;
                    $until->modify("+" . $preferences['login.block'] . " min");
                    $login_security->set('blocked_access', $until->format('Y-m-d H:i:s'));
                    doRegisterAction($app, $user, null, 'session', 4, 'login_error', 'blocked');
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

$app->get('/salir', function () use ($app, $user, $organization) {
    if (isset($user['id'])) {
        doRegisterAction($app, $user, $organization, 'session', 0, 'logout', '');
    }
    unset($_SESSION['person_id']);
    $app->flash('home_info', 'logout');
    $app->redirect($app->urlFor('frontpage'));
})->name('logout');

function doRegisterAction($app, $user, $organization, $module, $command, $action,
        $info, $data = null,
        $time = null, $activityId = null, $eventId = null, $groupingId = null,
        $folderId = null, $profileId = null, $deliveryId = null,
        $revisionId = null, $documentId = null, $deliveryItemId = null) {

    if (null == $time) {
        $time = date('Y-m-d H:i:s');
    }

    $personId = (is_null($user) || !isset($user['id'])) ? null : $user['id'];
    //$personId = is_null($user) ? null : $user['id'];
    $orgId = is_null($organization) ? null : $organization['id'];

    $log = ORM::for_table('log')->create();
    $log->set(array(
        'time' => $time,
        'person_id' => $personId,
        'ip' => $app->request()->getIp(),
        'organization_id' => $orgId,
        'module' => $module,
        'command' => $command,
        'action' => $action,
        'url' => $app->request()->getPathInfo(),
        'info' => $info,
        'data' => $data,
        'activity_id' => $activityId,
        'event_id' => $eventId,
        'grouping_id' => $groupingId,
        'folder_id' => $folderId,
        'profile_id' => $profileId,
        'delivery_id' => $deliveryId,
        'revision_id' => $revisionId,
        'document_id' => $documentId,
        'delivery_item_id' => $deliveryItemId
    ));

    return $log->save();
}
