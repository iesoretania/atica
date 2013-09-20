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

$app->map('/personal/:section/:id', function ($section, $id) use ($app, $user, $organization, $preferences) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    // ¿se busca la información del usuario activo?
    $itsMe = ($id == NULL) || ($id == $user['id']);

    // si es un nuevo usuario, la única sección admitida es la cero
    if (($id == 0) && ($section != 0)) {
        $app->redirect($app->urlFor('frontpage'));
    }
    
    // si es así, asignar sus datos
    if ($itsMe) {
        $id = $user['id'];
        $userData = $user;
    } else {
        // cargar los datos del usuario indicado como parámetro si no es
        // el identificador 0, que significa nuevo usuario
        if ($id != 0) {
            $userData = getUserById($id, $organization['id']);
            if (!$userData) {
                // ¿no existe en la organización? Salir de aquí
                $app->redirect($app->urlFor('frontpage'));
            }
        }
        else {
            $userData = array( 'new' => true, 'is_active' => 1 );
        }
    }

    // comprobar si se están cambiando datos
    if (isset($_POST['savepersonal']) && ($user['is_admin'] || $itsMe)) {
        
        ORM::get_db()->beginTransaction();
        
        if ($id == 0) {
            $local = ORM::for_table('person')->create();
        }
        else {
            $local = getUserObjectById($id);
        }
        if (isset($_POST['displayname'])) {
            $local->set('display_name', $_POST['displayname']);
        }
        if (isset($_POST['firstname'])) {
            $local->set('first_name', $_POST['firstname']);
        }
        if (isset($_POST['lastname'])) {
            $local->set('last_name', $_POST['lastname']);
        }
        if (isset($_POST['initials'])) {
            $local->set('initials', $_POST['initials']);
        }
        if (isset($_POST['gender'])) {
            $local->set('gender', $_POST['gender']);
        }
        if (isset($_POST['email'])) {
            $local->set('email', $_POST['email']);
        }
        if (isset($_POST['notify'])) {
            $local->set('email_enabled', $_POST['notify']);
        }
        if ($user['is_admin']) {
            if (isset($_POST['username'])) {
                $local->set('user_name', $_POST['username']);
            }
            if (isset($_POST['description'])) {
                $local->set('description', strlen($_POST['description']) > 0 ? $_POST['description'] : NULL);
            }
            // los flags de usuario activo y administrador local se grabarán
            // luego si es un usuario nuevo
            if (($id != 0) && isset($_POST['active']) && isset($_POST['localadmin'])) {
                setPersonIsActiveAndLocalAdmin($_POST['active'], $_POST['localadmin'], $id, $organization['id']);
            }
            // permitir cambiar opción de administrador global si ya lo somos
            // y el usuario activo no somos nosotros (para evitar accidentes)
            if ($user['is_global_administrator'] && isset($_POST['globaladmin']) && !$itsMe) {
                $local->set('is_global_administrator', $_POST['globaladmin']);
            }
        }
        if ($local->save()) {
            $app->flash('save_ok', 'ok');
            
            // si es nuevo, añadirlo a la organización
            if ($id == 0) {
                $id = $local['id'];
                $personOrganization = ORM::for_table('person_organization')->
                        create();
                $personOrganization->set('person_id', $id);
                $personOrganization->set('organization_id', $organization['id']);
                $personOrganization->set('is_active', $_POST['active']);
                $personOrganization->set('is_local_administrator', $_POST['localadmin']);
                $personOrganization->save();
            }
            ORM::get_db()->commit();
        } else {
            $app->flash('save_error', 'error');
            ORM::get_db()->rollBack();
        }
        $app->redirect($app->urlFor('personal', array('id' => $id, 'section' => $section)));
    }

    // cambio de contraseña
    if (isset($_POST['savepassword']) && isset($_POST['password1']) &&
            isset($_POST['password2']) && ($user['is_admin'] || $itsMe)) {
        $changeOk = false;
        if (!$user['is_admin']) {
            if (!isset($_POST['oldpassword']) || !checkUserPassword($user['id'], $_POST['oldpassword'], $preferences['salt'])) {
                $app->flashNow('save_error', 'oldpassword');
            } else {
                $changeOk = true;
            }
        } else {
            $changeOk = true;
        }
        if ($changeOk) {
            if ($_POST['password1'] !== $_POST['password2']) {
                $app->flashNow('save_error', 'passwordmatch');
                $changeOk = false;
            } elseif (strlen($_POST['password1']) < 6) {
                $app->flashNow('save_error', 'passwordlength');
                $changeOk = false;
            }
        }
        if ($changeOk) {
            $local = getUserObjectById($id);
            $local->set('password', sha1($preferences['salt'] . $_POST['password1']));
            $local->save();
            $app->flash('save_ok', 'ok');
            $app->redirect($app->urlFor('personal', array('id' => $id, 'section' => 0)));
        }
    }
    
    // cambio de perfiles
    if ((isset($_POST['saveprofiles']) && ($user['is_admin']))) {
        $ok = setUserProfiles($id, $_POST['profiles']);
        if ($ok) {
            $app->flash('save_ok', 'ok');
        }
        else {
            $app->flash('save_error', 'error');
        }
        $app->redirect($app->urlFor('personal', array('id' => $id, 'section' => 1)));
    }

    // menú lateral de secciones
    $menu = array(
        array('caption' => ($itsMe ? 'Mis datos' : (($id != 0) ? $userData['display_name'] : 'Nuevo usuario')), 'icon' => 'user')
    );

    // las secciones vienen en este array
    $options = array(
        0 => array('caption' => 'Personal', 'template' => 'user_personal'),
        1 => array('caption' => 'Perfiles', 'template' => 'user_profiles')/*,
        2 => array('caption' => 'Envíos realizados', 'template' => 'user_deliveries') */
    );

    // si el usuario es administrador, permitir ver el informe de actividad
    if ($user['is_admin']) {
        $options[3] = array('caption' => 'Registro de actividad', 'template' => 'user_personal');
    }

    // si el usuario es administrador global, permitir asignar organizaciones
    if ($user['is_global_administrator']) {
        $options[5] = array('caption' => 'Pertenencia a centros', 'template' => 'user_personal');
    }

    // si el usuario es él mismo o es administrador, permitir cambiar la contraseña
    // y ver el informe de actividad
    if ($itsMe || $user['is_admin']) {
        //$options[3] = array('caption' => 'Registro de actividad', 'template' => 'personal');
        $options[4] = array('caption' => 'Cambiar contraseña', 'template' => 'user_password');
    }
    
    // comprobar que la sección existe
    if (!isset($options[$section])) {
        $app->redirect($app->urlFor('frontpage'));
    }

    // generar menú
    foreach ($options as $key => $i) {
        $menu[] = array('caption' => $i['caption'], 'active' => ($section == $key), 'target' => $app->urlFor('personal', array('id' => $id, 'section' => $key)));
    }


    if ($user['is_admin']) {
        $sidebar = getPersonManagementSidebar(($id == 0) ? 3 : 0, $app);
    }
    else {
        $sidebar = array();
    }
    
    // mostrar menú de perfil sólo si no estamos creando un nuevo usuario
    if ($id != 0) {
        $sidebar[] = $menu;
        // lista perfiles del usuario
        $profiles = parseArray(getProfilesByUser($id));
    }
    else {
        $profiles = array();
    }

    if ($user['is_admin']) {
        $allProfiles = getProfilesByOrganization($organization['id']);
    }
    else {
        $allProfiles = array();
    }
    
    // generar barra de navegación
    $breadcrumb = array(
        array('display_name' => 'Usuarios', 'target' => $app->urlFor('personal', array('id' => $user['id'], 'section' => 0))),
        array('display_name' => ($id != 0) ? $userData['display_name'] : 'Nuevo usuario', 'target' => $app->urlFor('personal', array('id' => $id, 'section' => 0))),
        array('display_name' => $options[$section]['caption'])
    );
    // lanzar plantilla
    $app->render($options[$section]['template'] . '.html.twig', array(
        'navigation' => $breadcrumb,
        'sidebar' => $sidebar,
        'url' => $app->request()->getPathInfo(),
        'userData' => $userData,
        'profiles' => $profiles,
        'allProfiles' => $allProfiles,
        'local' => $itsMe
    ));
})->name('personal')->via('GET', 'POST')->
    conditions(array('section' => '[0-9]{1}'));

$app->map('/listado(/:sort(/:filter))', function ($sort = 0, $filter = 1) use ($app, $user, $organization) {
    if ((!$user) || (!$user['is_admin'])) {
        $app->redirect($app->urlFor('login'));
    }
    $sidebar = getPersonManagementSidebar(1, $app);
    
    $persons = getOrganizationPersons($organization['id'], $sort, $filter);
    
    // generar barra de navegación
    $breadcrumb = array(
        array('display_name' => 'Usuarios', 'target' => $app->urlFor('personlist'),
        array('display_name' => $organization['display_name'])
    ));
    
    // lanzar plantilla
    $app->render('personal.html.twig', array(
        'navigation' => $breadcrumb,
        'sidebar' => $sidebar,
        'sort' => $sort,
        'filter' => $filter,
        'url' => $app->request()->getPathInfo(),
        'persons' => $persons
    ));
})->name('personlist')->via('GET', 'POST');


$app->map('/perfiles(/:filter)', function ($filter = 1) use ($app, $user, $organization) {
    if ((!$user) || (!$user['is_admin'])) {
        $app->redirect($app->urlFor('login'));
    }
    $sidebar = getPersonManagementSidebar(2, $app);
    
    $filterArray = array();
    if ($filter) {
        $filterArray['person_organization.is_active'] = 1;
    }
    $profiles = getProfilesByOrganization($organization['id']);
    
    // generar barra de navegación
    $breadcrumb = array(
        array('display_name' => 'Perfiles', 'target' => $app->urlFor('personlist'),
        array('display_name' => $organization['display_name'])
    ));
    
    // lanzar plantilla
    $app->render('profiles.html.twig', array(
        'navigation' => $breadcrumb,
        'sidebar' => $sidebar,
        'filter' => $filter,
        'profiles' => $profiles,
        'url' => $app->request()->getPathInfo()
    ));
})->name('profilelist')->via('GET', 'POST');

function getUserById($personId, $orgId) {
    $data = ORM::for_table('person')->
                    select('person.*')->
                    select('person_organization.is_local_administrator')->
                    select('person_organization.is_active')->
                    inner_join('person_organization', array('person_organization.person_id', '=', 'person.id'))->
                    where('person_organization.person_id', $personId)->
                    where('person_organization.organization_id', $orgId)->
                    find_one();
    if ($data) {
        return $data->as_array();
    }
    else {
        return false;
    }
}

function getUserObjectById($personId) {
    return ORM::for_table('person')->
                    find_one($personId);
}

function getProfilesByUser($personId) {
    return ORM::for_table('profile')->
                    select('profile.*')->
                    select('profile_group.display_name_neutral')->
                    select('profile_group.display_name_male')->
                    select('profile_group.display_name_female')->
                    select('profile_group.description', 'profile_group_description')->
                    inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
                    inner_join('person_profile', array('person_profile.profile_id', '=', 'profile.id'))->
                    where('person_profile.person_id', $personId)->
                    order_by_asc('profile_group.display_name_neutral')->
                    find_array();
}

function setPersonIsActiveAndLocalAdmin($stateActive, $stateLocalAdmin, $personId, $orgId) {
    $personOrganization = ORM::for_table('person_organization')->
            where('person_id', $personId)->
            where('organization_id', $orgId)->
            delete_many();

    if (!$personOrganization) {
        return false;
    }

    $personOrganization2 = ORM::for_table('person_organization')->create();
    $personOrganization2->set('person_id', $personId);
    $personOrganization2->set('organization_id', $orgId);
    $personOrganization2->set('is_active', $stateActive);
    $personOrganization2->set('is_local_administrator', $stateLocalAdmin);
    $personOrganization2->save();

    return $personOrganization2->save();
}

function checkUserPassword($personId, $password, $salt) {
    return ORM::for_table('person')->
                    where('id', $personId)->
                    where('password', sha1($salt . $password))->
                    count() > 0;
}

function getPersonManagementSidebar($section, $app) {
    return array(
        array(
         array('caption' => 'Operaciones', 'icon' => 'group'),
         array('caption' => 'Gestionar usuarios', 'active' => ($section == 1),'target' => $app->urlFor('personlist')),
         array('caption' => 'Administrar perfiles', 'active' => ($section == 2),'target' => $app->urlFor('profilelist')),
         array('caption' => 'Nuevo usuario', 'active' => ($section == 3), 'target' => $app->urlFor('personal', array('id' => 0, 'section' => 0)))
        )
    );
}

function getOrganizationPersons($orgId, $sortIndex = 0, $filter = true) {
    $fields = array('user_name', 'first_name', 'email', 'last_login',
        'last_name', 'gender', 'email_enabled',
        'person_organization.is_local_administrator', 'is_global_administrator');
    
    $data = ORM::for_table('person')->
            select('person.*')->
            select('person_organization.is_active')->
            select('person_organization.is_local_administrator')->
            inner_join('person_organization', array('person_organization.person_id', '=', 'person.id'))->
            where('person_organization.organization_id', $orgId)->
            order_by_asc($fields[$sortIndex]);
    
    if ($filter) {
        $data = $data->where('person_organization.is_active', 1);
    }
    
    return $data->find_many();
}

function getProfilesByOrganization($orgId, $filter = true) {
    $data = ORM::for_table('profile')->
            select('profile.*')->
            select('profile_group.display_name_neutral')->
            select('profile_group.display_name_male')->
            select('profile_group.display_name_female')->
            select('profile_group.abbreviation')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            where('profile_group.organization_id', $orgId)->
            order_by_asc('profile_group.display_name_neutral')->
            order_by_asc('profile.display_name');
    
    if ($filter) {
        //$data = $data->where('profile.is_active', 1);
    }
    
    return $data->find_many();
}

function setUserProfiles($userId, $profiles) {
    ORM::get_db()->beginTransaction();
    $query = ORM::for_table('person_profile')->
            where('person_id', $userId)->
            delete_many();
    
    foreach ($profiles as $profile) {
        $insert = ORM::for_table('person_profile')->create();
        $insert->set('person_id', $userId);
        $insert->set('profile_id', $profile);
        $insert->save();
    }
    
    return ORM::get_db()->commit();
}