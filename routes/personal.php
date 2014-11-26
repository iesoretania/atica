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
    $itsMe = ($id == null) || ($id == $user['id']);

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
            $userData = array( 'new' => true, 'is_active' => 1, 'gender' => 0, 'email_enabled' => 0 );
        }
    }

    // comprobar si se están cambiando datos
    if (isset($_POST['savepersonal']) && ($user['is_admin'] || $itsMe)) {

        ORM::get_db()->beginTransaction();

        $ok = true;
        if ($id == 0) {
            $local = ORM::for_table('person')->create();
        }
        else {
            $local = getUserObjectById($id);
        }
        if (isset($_POST['displayname'])) {
            $local->set('display_name', $_POST['displayname']);
        }
        if (isset($_POST['description']) && (null != $_POST['description'])) {
            $local->set('description', $_POST['description']);
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
                $local->set('description', strlen($_POST['description']) > 0 ? $_POST['description'] : null);
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
        $ok = $ok && $local->save();
        // si es nuevo, añadirlo a la organización
        if ($ok && ($id == 0)) {
            $id = $local['id'];
            $personOrganization = ORM::for_table('person_organization')->
                    create();
            $personOrganization->set('person_id', $id);
            $personOrganization->set('organization_id', $organization['id']);
            $personOrganization->set('is_active', $_POST['active']);
            $personOrganization->set('is_local_administrator', $_POST['localadmin']);
            $ok = $ok && $personOrganization->save();
        }

        // cambio de perfiles
        if ($user['is_admin']) {
            $ok = $ok && setUserProfiles($id, $_POST['profiles'], $organization['id']);
        }

        if ($ok) {
            $app->flash('save_ok', 'ok');
            ORM::get_db()->commit();

            $url = isset($_SESSION['slim.flash']['last_url']) ?
                $_SESSION['slim.flash']['last_url'] :
                $app->urlFor('personal', array('id' => $id, 'section' => 0));

            $app->redirect($url);

        } else {
            $app->flash('save_error', 'error');
            ORM::get_db()->rollBack();
        }
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
            $url = isset($_SESSION['slim.flash']['last_url']) ?
                $_SESSION['slim.flash']['last_url'] :
                $app->urlFor('personal', array('id' => $id, 'section' => 0));

            $app->redirect($url);
        }
    }
    $app->flashKeep();

    // menú lateral de secciones
    $menu = array(
        array('caption' => ($itsMe ? 'Mis datos' : (($id != 0) ? $userData['display_name'] : 'Nuevo usuario')), 'icon' => 'user')
    );

    // las secciones vienen en este array
    $options = array(
        0 => array('caption' => 'Personal', 'template' => 'user_personal', 'select2' => true)/*,
        2 => array('caption' => 'Envíos realizados', 'template' => 'user_deliveries') */
    );

    // si el usuario es administrador, permitir ver el informe de actividad
    if ($user['is_admin']) {
        $options[3] = array('caption' => 'Registro de actividad', 'template' => 'user_personal', 'select2' => false);
    }

    // si el usuario es administrador global, permitir asignar organizaciones
    if ($user['is_global_administrator']) {
        $options[5] = array('caption' => 'Pertenencia a centros', 'template' => 'user_personal', 'select2' => false);
    }

    // si el usuario es él mismo o es administrador, permitir cambiar la contraseña
    // y ver el informe de actividad
    if ($itsMe || $user['is_admin']) {
        //$options[3] = array('caption' => 'Registro de actividad', 'template' => 'personal');
        $options[4] = array('caption' => 'Cambiar contraseña', 'template' => 'user_password', 'select2' => false);
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
        'select2' => $options[$section]['select2'],
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

    if (isset($_POST['enable']) || isset($_POST['disable'])) {
        if (enablePersons($organization['id'], $_POST['user'], isset($_POST['enable']))) {
            $app->flash('save_ok', 'ok');
        }
        else {
            $app->flash('save_error', 'error');
        }
        $app->redirect($app->request()->getPathInfo());
    }
    $app->flash('last_url', $app->request()->getPathInfo());

    // lanzar plantilla
    $app->render('manage_person.html.twig', array(
        'navigation' => $breadcrumb,
        'sidebar' => $sidebar,
        'sort' => $sort,
        'filter' => $filter,
        'url' => $app->request()->getPathInfo(),
        'persons' => $persons
    ));
})->name('personlist')->via('GET', 'POST');


$app->map('/perfiles', function () use ($app, $user, $organization) {
    if ((!$user) || (!$user['is_admin'])) {
        $app->redirect($app->urlFor('login'));
    }
    $sidebar = getPersonManagementSidebar(2, $app);

    $profiles = getProfileGroupsByOrganization($organization['id']);

    if (isset($_POST['delete']) && isset($_POST['profilegroup'])) {
        $ok = deleteProfileGroupsById($_POST['profilegroup'], $organization['id']);
        if ($ok) {
            $app->flash('save_ok', 'delete');
        }
        else {
            $app->flash('save_error', 'error');
        }
        $app->redirect($app->request()->getPathInfo());
    }

    // generar barra de navegación
    $breadcrumb = array(
        array('display_name' => 'Perfiles', 'target' => $app->urlFor('personlist'),
        array('display_name' => $organization['display_name'])
    ));

    // lanzar plantilla
    $app->render('manage_profile_groups.html.twig', array(
        'navigation' => $breadcrumb,
        'sidebar' => $sidebar,
        'profiles' => $profiles,
        'url' => $app->request()->getPathInfo()
    ));
})->name('profilelist')->via('GET', 'POST');

$app->map('/perfiles/:id(/:filter)', function ($id, $filter = 0) use ($app, $user, $config, $organization, $preferences) {

    if ((!$user) || (!$user['is_admin'])) {
        $app->redirect($app->urlFor('login'));
    }

    $sidebar = getPersonManagementSidebar(2, $app);
    array_push($sidebar, getProfileGroupsSidebar($id, $organization['id'], $app));

    $new = false;

    if (0 == $id) {
        // grupo de perfil nuevo
        $profileGroup = array(
            'id' => 0,
            'display_name_neutral' => '',
            'display_name_male' => '',
            'display_name_female' => '',
            'abbreviation' => '',
            'is_manager' => 0,
            'description' => null,
            'is_container' => 0
        );
        $profiles = array();
        $personCount = 0;
        $isContainer = false;
        $new = true;
    }
    else {
        $profileGroup = getProfileGroupById($id, $organization['id']);

        if (!$profileGroup) {
            $app->redirect($app->urlFor('login'));
        }

        if (isset($_POST['enable']) || isset($_POST['disable'])) {
            if (enableProfiles($organization['id'], $_POST['profile'], isset($_POST['enable']))) {
                $app->flash('save_ok', 'ok');
            }
            else {
                $app->flash('save_error', 'error');
            }
            $app->redirect($app->request()->getPathInfo());
        }

        $profiles = getProfilesByGroup($id, $organization['id'], $filter);
        $personCount = 0;
        foreach($profiles as $profile) {
            $personCount += count($profile['persons']);
        }

        $isContainer = isProfileGroupContainer($id);
    }

    if (!$isContainer) {
        $persons = parseArray(getPersonsByProfile($id, $organization['id']));
        $allPersons = getPersonsByOrganization($organization['id']);
    }
    else {
        $persons = array();
        $allPersons = array();
    }

    if (isset($_POST['saveprofilegroup'])) {

        $ok = true;

        if ($new) {
            ORM::get_db()->beginTransaction();
            $profile = ORM::for_table('profile')->create();
            $profile->set('is_active', 1);
            $profile->set('is_container', 0);
            $profile->save();
            $profileGroup = ORM::for_table('profile_group')->create();
            $profileGroup->set('id', $profile['id']);
            $profileGroup->set('organization_id', $organization['id']);
            $profileGroup->save();
            $profile->set('profile_group_id', $profileGroup['id']);
            $profile->save();
            $ok = $ok && ORM::get_db()->commit();
            $id = $profileGroup['id'];
        }
        $profileGroup->set('display_name_neutral', $_POST['displaynameneutral']);
        $profileGroup->set('display_name_male', $_POST['displaynamemale']);
        $profileGroup->set('display_name_female', $_POST['displaynamefemale']);
        $profileGroup->set('abbreviation', $_POST['abbreviation']);
        $profileGroup->set('is_manager', $_POST['ismanager']);
        $profileGroup->set('description', $_POST['description']);

        $ok = $ok && $profileGroup->save();

        // comprobar si no hay usuarios con el perfil y ha cambiado el tipo
        if (($personCount == 0) && ($isContainer ? "1" : "0") != $_POST['iscontainer']) {
            // sanity check: sólo usuarios de esta organización

            $ok = $ok && setProfileGroupContainer($id, $_POST['iscontainer']);
        }

        // si no es contenedor, actualizar la lista de usuarios asociados
        if (!$isContainer) {
            $ok = $ok && setProfilePersons($id, isset($_POST['persons']) ? $_POST['persons'] : array());
        }

        if ($ok) {
            $app->flash('save_ok', 'ok');
        }
        else {
            $app->flash('save_error', 'error');
        }

        if ($new) {
            $app->redirect($app->urlFor('profilelist'));
        }
        else {
            $app->redirect($app->request()->getPathInfo());
        }
    }

    if (isset($_POST['delete']) && isset($_POST['profile'])) {
        $ok = deleteProfilesById($_POST['profile'], $organization['id']);
        if ($ok) {
            $app->flash('save_ok', 'delete');
        }
        else {
            $app->flash('save_error', 'error');
        }
        $app->redirect($app->request()->getPathInfo());
    }

    // generar barra de navegación
    $breadcrumb = array(
        array('display_name' => 'Perfiles', 'target' => $app->urlFor('profilelist')),
        array('display_name' => $profileGroup['display_name_neutral'],
            $app->request()->getPathInfo()),
        array('display_name' => 'Detalles del perfil')
    );

    // lanzar plantilla
    $app->render('manage_profiles.html.twig', array(
        'select2' => true,
        'navigation' => $breadcrumb,
        'sidebar' => $sidebar,
        'profiles' => $profiles,
        'isContainer' => $isContainer,
        'profileGroup' => $profileGroup,
        'personCount' => $personCount,
        'persons' => $persons,
        'allPersons' => $allPersons,
        'new' => $new,
        'user' => $user,
        'filter' => $filter,
        'id' => $id,
        'url' => $app->request()->getPathInfo()
    ));

})->name('profile')->via('GET', 'POST');

$app->map('/detalleperfil/:id(/:gid)', function ($id, $gid = null) use ($app, $user, $config, $organization, $preferences) {

    if ((!$user) || (!$user['is_admin'])) {
        $app->redirect($app->urlFor('login'));
    }

    $sidebar = getPersonManagementSidebar(2, $app);
    $new = false;

    if (0 == $id) {
        $profile = array('id' => 0, 'profile_group_id' => $gid, 'is_active' => true);
        $new = true;
    }
    else {
        $profile = getProfileById($id, $organization['id']);

        if (!$profile) {
            $app->redirect($app->urlFor('login'));
        }
    }

    $profileGroup = getProfileGroupById($profile['profile_group_id'], $organization['id']);
    array_push($sidebar, getProfileGroupsSidebar($profileGroup['id'], $organization['id'], $app));

    if (!$profileGroup) {
        $app->redirect($app->urlFor('login'));
    }

    $persons = parseArray(getPersonsByProfile($id, $organization['id']));
    $allPersons = getPersonsByOrganization($organization['id']);

    if (isset($_POST['saveprofile'])) {
        if ($new) {
            $profile = ORM::for_table('profile')->create();
            $profile->set('profile_group_id', $gid);
        }
        $profile->set('display_name', $_POST['displayname']);
        $profile->set('initials', $_POST['initials']);
        $profile->set('is_active', $_POST['isactive']);
        $profile->set('description', $_POST['description']);

        $ok = $profile->save();

        $ok = $ok && setProfilePersons($profile['id'], isset($_POST['persons']) ? $_POST['persons'] : array());

        if ($ok) {
            $app->flash('save_ok', 'ok');
        }
        else {
            $app->flash('save_error', 'error');
        }
        if ($new) {
            $app->redirect($app->urlFor('profile', array('id' => $gid)));
        }
        else {
            $app->redirect($app->request()->getPathInfo());
        }
    }

    // generar barra de navegación
    $breadcrumb = array(
        array('display_name' => 'Perfiles', 'target' => $app->urlFor('profilelist')),
        array('display_name' => $profileGroup['display_name_neutral'], $app->request()->getPathInfo()),
        array('display_name' => 'Detalles del perfil')
    );

    // lanzar plantilla
    $app->render('manage_profile_details.html.twig', array(
        'select2' => true,
        'navigation' => $breadcrumb,
        'sidebar' => $sidebar,
        'profile' => $profile,
        'persons' => $persons,
        'allPersons' => $allPersons,
        'profileGroup' => $profileGroup,
        'new' => $new,
        'user' => $user,
        'id' => $id,
        'url' => $app->request()->getPathInfo()
    ));

})->name('profiledetail')->via('GET', 'POST');

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
         array('caption' => 'Gestionar usuarios', 'active' => (($section == 1) || ($section == 3)),'target' => $app->urlFor('personlist')),
         array('caption' => 'Administrar perfiles', 'active' => ($section == 2),'target' => $app->urlFor('profilelist'))
        )
    );
}

function getProfileGroupsSidebar($id, $orgId, $app) {
    $sidebar = array(
         array('caption' => 'Perfiles', 'icon' => 'list')
    );
    $profileGroups = getProfileGroupsByOrganization($orgId);
    foreach($profileGroups as $profileGroup) {
        array_push($sidebar, array('caption' => $profileGroup['display_name_neutral'], 'active' => $profileGroup['id'] == $id, 'target' => $app->urlFor('profile', array('id' => $profileGroup['id']))));
    }

    // array('caption' => 'Gestionar usuarios', 'active' => (($section == 1) || ($section == 3)),'target' => $app->urlFor('personlist')),

    return $sidebar;
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

function getProfileGroupsByOrganization($orgId) {
    $data = ORM::for_table('profile_group')->
            select('profile_group.*')->
            where('profile_group.organization_id', $orgId)->
            order_by_asc('profile_group.display_name_neutral');

    return $data->find_array();
}

function getProfilesByOrganization($orgId, $filter = true, $containers = false) {
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

    if ($containers == false) {
        $data = $data->where('profile.is_container', 0);
    }
    if ($filter) {
        $data = $data->where('profile.is_active', 1);
    }

    return $data->find_array();
}

function getProfileById($id, $orgId) {
    $data = ORM::for_table('profile')->
            select('profile.*')->
            select('profile_group.display_name_neutral')->
            select('profile_group.display_name_male')->
            select('profile_group.display_name_female')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            where('profile_group.organization_id', $orgId)->
            where('profile.id', $id)->
            find_one();

    return $data;
}

function getProfileFullDisplayName($profile, $user) {
    $names = array(
        $profile['display_name_neutral'],
        $profile['display_name_male'],
        $profile['display_name_female']
    );

    $name = $names[$user['gender']];

    if ($profile['display_name']) {
        $name .= ' ' . $profile['display_name'];
    }
    return $name;
}

function getProfileGroupById($id, $orgId) {
    $data = ORM::for_table('profile_group')->
            select('profile_group.*')->
            where('profile_group.organization_id', $orgId)->
            where('profile_group.id', $id)->find_one();

    return $data;
}

function getPersonsByProfile($id, $orgId) {
    return ORM::for_table('person')->
                select('person.id', 'id')->
                select('person.display_name')->
                select('person.user_name')->
                select('person_organization.is_active')->
                inner_join('person_profile', array('person_profile.person_id', '=', 'person.id'))->
                inner_join('person_organization', array('person_organization.person_id', '=', 'person.id'))->
                where('person_organization.organization_id', $orgId)->
                where('person_profile.profile_id', $id)->
                order_by_asc('person.display_name')->
                find_array();
}

function getPersonsByOrganization($orgId) {
    $data = ORM::for_table('person')->
                select('person.id', 'id')->
                select('person.display_name')->
                select('person.user_name')->
                select('person_organization.is_active')->
                inner_join('person_organization', array('person_organization.person_id', '=', 'person.id'))->
                where('person_organization.organization_id', $orgId)->
                order_by_desc('person_organization.is_active')->
                order_by_asc('person.display_name')->
                find_array();

    return parseArray($data);
}

function getProfilesByGroup($id, $orgId, $filter = true) {
    $data = ORM::for_table('profile')->
            select('profile.*')->
            select('profile_group.display_name_neutral')->
            select('profile_group.display_name_male')->
            select('profile_group.display_name_female')->
            select('profile_group.abbreviation')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            where('profile_group.id', $id)->
            where('profile.is_container', 0)->
            order_by_asc('profile_group.display_name_neutral')->
            order_by_asc('profile.display_name');

    if ($filter) {
        $data = $data->where('profile.is_active', 1);
    }

    $data = $data->find_array();

    foreach($data as $key => $profile) {

        $persons = getPersonsByProfile($profile['id'], $orgId);

        $data[$key]['persons'] = $persons;
    }

    return $data;
}

function isProfileGroupContainer($id) {
    return (ORM::for_table('profile')->
            where('profile_group_id', $id)->
            where('is_container', 1)->
            count())>0;
}

function setProfileGroupContainer($id, $value) {
    $profile = ORM::for_table('profile')->
            where('profile_group_id', $id)->
            find_one();
    $profile->set('is_container', $value);
    return $profile->save();
}

function setProfilePersons($profileId, $persons) {
    ORM::get_db()->beginTransaction();

    $ok = ORM::for_table('person_profile')->
            where('profile_id', $profileId)->
            delete_many();

    foreach ($persons as $person) {
        $insert = ORM::for_table('person_profile')->create();
        $insert->set('person_id', $person);
        $insert->set('profile_id', $profileId);
        $ok = $ok && $insert->save();
    }

    return $ok && ORM::get_db()->commit();
}

function setUserProfiles($userId, $profiles, $orgId) {

    // hay que eliminar los perfiles a los que pertenezca este usuario
    // dentro de la organización
    $query = ORM::for_table('profile')->
            select('profile.id')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            where('profile_group.organization_id', $orgId)->
            find_array();

    $allProfiles = array();

    foreach($query as $prof) {
        $allProfiles[] = $prof['id'];
    }

    $query = ORM::for_table('person_profile')->
            where('person_id', $userId)->
            where_in('profile_id', $allProfiles)->
            delete_many();

    $ok = true;
    foreach ($profiles as $profile) {
        $insert = ORM::for_table('person_profile')->create();
        $insert->set('person_id', $userId);
        $insert->set('profile_id', $profile);
        $ok = $ok && $insert->save();
    }

    return $ok;
}

function deleteProfilesById($profileIds, $orgId) {
    $data = ORM::for_table('profile')->
            select('profile.id')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            where_in('profile.id', $profileIds)->
            where('profile_group.organization_id', $orgId)->
            find_result_set();

    return $data->delete();
}

function deleteProfileGroupsById($profileGroupsIds, $orgId) {
    $data = ORM::for_table('profile')->
            select('profile.id')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            where_in('profile.profile_group_id', $profileGroupsIds)->
            where('profile_group.organization_id', $orgId)->
            find_result_set()->set('profile_group_id', null);

    $data->save();

    $profileGroup = ORM::for_table('profile_group')->
            where('organization_id', $orgId)->
            where_in('id', $profileGroupsIds)->
            delete();

    $data->delete();

    return $data->delete();
}

function enablePersons($orgId, $persons, $status) {
    // Cuidado: ataque SQL injection. Usar clave primaria compuesta
    // para solucionarlo
    $organization = ORM::get_db()->quote($orgId);
    $active = $status ? 1 : 0;
    $list = implode(',', $persons);
    return ORM::get_db()->exec('UPDATE person_organization SET is_active=' . $active .
            ' WHERE organization_id=' . $organization . ' AND '.
            'person_id IN (' . $list . ');');

}

function enableProfiles($orgId, $profiles, $status) {
    ORM::get_db()->beginTransaction();
    foreach($profiles as $profile) {
        $row = ORM::for_table('profile')->
                select('profile.id')->
                select('is_active')->
                inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
                where('profile.id', $profile)->
                where('profile_group.organization_id', $orgId)->
                find_one();
        $row->set('is_active', $status);
        $row->save();
    }
    return ORM::get_db()->commit();
}