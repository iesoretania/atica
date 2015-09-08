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

$app->get('/enviar/:id(/:return/:data1(/:data2(/:data3)))', function ($id, $return=0, $data1=null, $data2=null, $data3=null)
    use ($app, $user, $config, $organization) {

    if ((!$user) || ($return < 0) || ($return > 1)) {
        $app->redirect($app->urlFor('login'));
    }

    $data = array();
    $parent = array();

    $folder = getFolder($organization['id'], $id);
    if (!$folder) {
        $app->redirect($app->urlFor('login'));
    }

    $restrictedProfiles = parseArray(getPermissionProfiles($id, 2));
    $uploadProfiles = parseArray(getPermissionProfiles($id, 1));
    $managerProfiles = parseArray(getPermissionProfiles($id, 0));
    $userProfiles = parseArray(getUserProfiles($user['id'], $organization['id'], true));

    $isManager = $user['is_admin'];
    foreach ($managerProfiles as $upload) {
        if (isset($userProfiles[$upload['id']])) {
            $isManager = true;
            break;
        }
    }

    $uploadAs = array();
    if (!$isManager) {
        $realUserProfiles = parseArray(getUserProfiles($user['id'], $organization['id'], false));
        foreach ($realUserProfiles as $item) {
            if (isset($uploadProfiles[$item['id']]) || isset($uploadProfiles[$item['profile_group_id']])) {
                $uploadAs[$item['id']] = $item;
            }
        }
    }
    else {
        foreach ($uploadProfiles as $item) {
            if (null == $item['display_name']) {
                $data = parseArray(getSubprofiles($item['id']));
                if (count($data)>1) {
                    foreach($data as $subItem) {
                        if (null != $subItem['display_name']) {
                            $uploadAs[$subItem['id']] = $subItem;
                        }
                    }
                }
                else {
                    $uploadAs[$item['id']] = $item;
                }
            }
            else {
                $uploadAs[$item['id']] = $item;
            }
        }
    }

    $category = getCategoryObjectById($organization['id'], $folder['category_id']);

    if (!$category) {
        $app->redirect($app->urlFor('login'));
    }

    $breadcrumb = array();
    $lastUrl = $app->request()->getPathInfo();
    switch ($return) {
        case 0:
            $breadcrumb = array(
                array('display_name' => 'Árbol', 'target' => $app->urlFor('tree'))
            );
            $parents = getCategoryParentsById($category['id']);
            foreach($parents as $parent) {
                $breadcrumb[] = array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree'));
            }
            $breadcrumb[] = array('display_name' => $category['display_name'], 'target' => $app->urlFor('tree', array('id' => $category['id'])));
            $breadcrumb[] = array('display_name' => 'Enviar documento');
            $lastUrl = $app->urlFor('tree', array('id' => $data1));
            break;
        case 1:
            $event = getEventByIdObject($organization['id'], $data3);
            $activityevent = getActivityEvent($data3, $data2, $user);
            $profile = getProfileById($organization['id'], $data1);
            if ((!$event) || (!$activityevent) || (!$profile) || ($event['folder_id'] != $id)) {
                $app->redirect($app->urlFor('login'));
            }
            $lastUrl = $app->urlFor('event', array('pid' => $data1, 'aid' => $data2, 'id' => $data3));

            $breadcrumb = array(
                array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
                array('display_name' => getProfileFullDisplayName($profile, $user), 'target' => $app->urlFor('activities', array('id' => $data1))),
                array('display_name' => $activityevent['activity_display_name'], 'target' => $app->urlFor('activities', array('id' => $data1))),
                array('display_name' => $event['display_name'], 'target' => $app->urlFor('event', array('pid' => $data1, 'aid' => $data2, 'id' => $data3))),
                array('display_name' => 'Enviar documento')
            );
            break;
    }

    if ($isManager) {
        $stats = getFolderProfileDeliveryStats($id);
    }
    else {
        $stats = array();
    }

    $items = parseVariablesArray(getFolderItemsByUser($user['id'], $id, $organization['id']), $organization, $user, 'profile_id', $userProfiles);

    $localStats = getArrayGroups($items, 'event_id', 'profile_id');
    $now = getdate();
    $currentWeek = ($now['mon']-1)*4 + min(floor(($now['mday']-1)/7), 3);

    $app->render('upload.html.twig', array(
        'navigation' => $breadcrumb, 'search' => false,
        'select2' => true,
        'category' => $category,
        'folder' => $folder,
        'upload_profiles' => $uploadProfiles,
        'manager_profiles' => $managerProfiles,
        'restricted_profiles' => $restrictedProfiles,
        'user_profiles' => $userProfiles,
        'is_manager' => $isManager,
        'upload_as' => $uploadAs,
        'base' => $config['calendar.base_week'],
        'current' => $currentWeek,
        'stats' => $stats,
        'local_stats' => $localStats,
        'url' => $app->request()->getPathInfo(),
        'back_url' => array('return' => $return, 'data1' => $data1, 'data2' => $data2, 'data3' => $data3),
        'last_url' => $lastUrl,
        'data' => $data));
})->name('upload');

$app->post('/enviar/:id(/:return/:data1(/:data2(/:data3)))', function ($id, $return=0, $data1=null, $data2=null, $data3=null)
    use ($app, $user, $config, $organization, $preferences) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    if (isset($_POST['localupload'])) {
        $folder = getFolder($organization['id'], $id);
        if (!$folder) {
            $app->redirect($app->urlFor('login'));
        }
        $userProfiles = parseArray(getUserProfiles($user['id'], $organization['id'], true));

        $items = parseVariablesArray(getFolderItemsByUser($user['id'], $id, $organization['id']), $organization, $user, 'profile_id', $userProfiles);
        $failed = 0;
        $success = 0;

        // comprobar ítem a ítem si se ha recibido un documento
        foreach ($items as $item) {
            $profile = getProfile($item['profile_id']);
            $ref = 'localdocument_' . $item['id'];
            if (($item['c'] == 0) && (isset($_FILES[$ref]['name'])) && (strlen($_FILES[$ref]['name']) > 0) && (is_uploaded_file($_FILES[$ref]['tmp_name']))) {
                // recibido
                $hash = sha1_file($_FILES[$ref]['tmp_name']);
                $filesize = filesize($_FILES[$ref]['tmp_name']);

                $message = "";
                $documentDestination = createDocumentFolder($preferences['upload.folder'], $hash);
                if (move_uploaded_file($_FILES[$ref]['tmp_name'], $preferences['upload.folder'] . $documentDestination)) {
                    $ext = pathinfo($_FILES[$ref]['name'], PATHINFO_EXTENSION);
                    if ($ext) {
                        $ext = '.' . $ext;
                    }
                    $name = $item['document_name'] ? $item['document_name'] : $item['display_name'];
                    $filename = parseVariables($name, $organization, $user, $profile) . $ext;
                    $description = parseVariables($item['display_name'], $organization, $user, $profile);

                    if (false === createDelivery($id, $user['id'], $item['profile_id'], $filename, $description, null, $item['id'], $documentDestination, $hash, $filesize)) {
                        $type = 'danger';
                        $message = 'cannot register';
                    }
                    else {
                        $type = 'ok';
                    }
                }
                else {
                    $type = 'danger';
                    $message = 'cannot move';
                }

                if ($type == 'danger') {
                    $app->flash('upload_status_' . $failed, $type);
                    $app->flash('upload_name_' . $failed, $_FILES[$ref]['name']);
                    $app->flash('upload_error_' . $failed, $message);
                    $failed++;
                }
                else {
                    $success++;
                }
            }
        }

        $app->flash('upload', $failed);
        if ($success>0) {
            $app->flash('upload_ok', $success);
        }
        $app->redirect($app->request()->getPathInfo());
    }
    else {
        if ((! isset($_FILES['document']['name'][0])) || (strlen($_FILES['document']['name'][0]) == 0)) {
            // no hay archivos enviados
            $app->redirect($app->request()->getPathInfo());
        }

        $items = array();

        // TODO: Comprobar si la carpeta es válida
        $folder = getFolder($organization['id'], $id);

        // TODO: Comprobar perfil
        $profileIsSet = $folder['is_divided'];
        $profileId = $profileIsSet ? $_POST['profile'] : null;
        $profile = $profileIsSet ? getProfile($profileId) : array();

        // buscar si hay una lista de entrega
        $list = $profileIsSet ?
                parseArray(getFolderProfileDeliveryItems($profileId, $id)) :
                array();

        $list = parseVariablesArray($list, $organization, $user, $profile);

        // si es falso, mostrar revisión de los documentos enviados
        $finished = false;

        $loop = 0;
        $failed = 0;
        $success = 0;
        while (isset($_FILES['document']['name'][$loop])) {
            $type = "";
            $message = "";
            if ( is_uploaded_file($_FILES['document']['tmp_name'][$loop]) ) {
                $hash = sha1_file($_FILES['document']['tmp_name'][$loop]);
                $filesize = filesize($_FILES['document']['tmp_name'][$loop]);

                if (!$list) {
                    // Entregar directamente pues no hay lista de entrega
                    $documentDestination = createDocumentFolder($preferences['upload.folder'], $hash);
                    if (move_uploaded_file($_FILES['document']['tmp_name'][$loop], $preferences['upload.folder'] . $documentDestination)) {
                        $filename = $_FILES['document']['name'][$loop];
                        $info = pathinfo( $filename );
                        $description = str_replace ('_', ' ', $info['filename']);

                        if (false === createDelivery($id, $user['id'], $profileId, $_FILES['document']['name'][$loop], $description, null, null, $documentDestination, $hash, $filesize)) {
                            $type = 'danger';
                            $message = 'cannot register';
                        }
                        else {
                            $type = 'ok';
                        }
                    }
                    else {
                        $type = 'danger';
                        $message = 'cannot register';
                    }
                }
                else {
                    // Mover a una carpeta temporal
                    $tempFolder = $preferences['upload.folder'] . "temp/";
                    if (!is_dir($tempFolder)) {
                        mkdir($tempFolder, 0770, true);
                    }
                    $tempDestination = $tempFolder . $hash;
                    move_uploaded_file($_FILES['document']['tmp_name'][$loop], $tempDestination);

                    $filename = $_FILES['document']['name'][$loop];
                    $info = pathinfo( $filename );
                    $description = $info['filename'];
                    $items[] = array(
                        'name' => $filename,
                        'description' => $description,
                        'hash' => $hash,
                        'filesize' => $filesize
                    );
                }
            }
            else {
                $type = 'danger';
                $message = 'cannot move';
            }
            if ($type) {
                if ($type == 'danger') {
                    $app->flash('upload_status_' . $failed, $type);
                    $app->flash('upload_name_' . $failed, $_FILES['document']['name'][$loop]);
                    $app->flash('upload_error_' . $failed, $message);
                    $failed++;
                }
                else {
                    $success++;
                }
                $finished = true;
            }
            $loop++;
        }

        if ($finished) {
            $app->flash('upload', $failed);
            if ($success>0) {
                $app->flash('upload_ok', $success);
            }
            $url = isset($_SESSION['slim.flash']['last_url']) ?
                $_SESSION['slim.flash']['last_url'] :
                $app->urlFor('tree', array( 'id' => $folder['category_id']));

            $app->redirect($url);
        }

        $category = array();
        $parent = array();

        getTree($organization['id'], $app, $folder['category_id'], $category, $parent);

        $breadcrumb = array(
            array('display_name' => 'Árbol', 'target' => $app->urlFor('tree')),
            array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree')),
            array('display_name' => $category['display_name'], 'target' => $app->urlFor('tree', array('id' => $category['id']))),
            array('display_name' => 'Revisar documento')
        );

        $deliveries = $profileIsSet ?
                getFolderProfileDeliveredItems($profileId, $id, $organization['id']) :
                array();
        $deliveries = parseVariablesArray($deliveries, $organization, $user, $profile);

        $now = getdate();
        $currentWeek = ($now['mon']-1)*4 + min(floor(($now['mday']-1)/7), 3);

        $app->flashKeep();

        $app->render('upload_review.html.twig', array(
            'navigation' => $breadcrumb, 'search' => false,
            'base' => $config['calendar.base_week'],
            'current' => $currentWeek,
            'select2' => true,
            'category' => $category,
            'folder' => $folder,
            'items' => $list,
            'profile' => $profile,
            'deliveries' => $deliveries,
            'data' => $items));
    }
});

$app->post('/confirmar/:id', function ($id) use ($app, $user, $preferences, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    // TODO: Comprobar si la carpeta es válida
    $folder = getFolder($organization['id'], $id);

    if (isset($_POST['discard'])) {
        // descartar envío: borrar archivos temporales
        $loop = 1;
        while (isset($_POST['hash' . $loop])) {
            $tempDestination = $preferences['upload.folder'] . "temp/" . $_POST['hash' . $loop];
            unlink($tempDestination);
            $loop++;
        }
        $app->redirect($app->urlFor('tree', array('id' => $folder['category_id'])));
    }

    // TODO: Comprobar perfil
    $profileIsSet = $folder['is_divided'];
    $profileId = $profileIsSet ? $_POST['profile'] : null;

    // buscar si hay una lista de entrega
    $list = $profileIsSet ?
            parseArray(getFolderProfileDeliveryItems($profileId, $id)) :
            array();

    $loop = 1;
    $success = 0;
    $failed = 0;

    if (! isset($_POST['hash' . $loop])) {
        // no hay archivos enviados
        $app->redirect($app->urlFor('upload', array('id' => $id)));
    }
    // TODO: comprobar que $hash es realmente un hash
    // TODO: comprobar que 'profile' es correcto

    while (isset($_POST['hash' . $loop])) {
        $ok = true;
        $hash = $_POST['hash' . $loop];
        $filename = $_POST['filename'. $loop];
        $description = isset($_POST['description'. $loop]) ? $_POST['description'. $loop] : $_POST['filename'. $loop];

        $tempDestination = $preferences['upload.folder'] . "temp/" . $hash;

        $itemId = null;

        if (file_exists($tempDestination)) {
            $message = "";
            $type = "";

            // si es un ítem, hacer comprobaciones adicionales
            if (count($list) > 0) {
                // ¿se ha elegido ignorar el documento?
                if (0 == $_POST['element' . $loop]) {
                    $ok = false;
                    $type = 'warning';
                    $message = 'ignored';
                }
                else {
                    // ¿pertenece el elemento a la lista?
                    if (isset($list[$_POST['element' . $loop]])) {
                        // sí
                        if ($profileId && (getDeliveryItemCount($profileId, $id, $_POST['element' . $loop]) > 0)) {
                            // error, ya existe un ítem de ese tipo
                            $ok = false;
                            $type = 'danger';
                            $message = 'already exists';
                        }
                        else {
                            // correcto
                            $itemId = $_POST['element' . $loop];
                            $profile = getProfile($profileId);
                            $description = parseVariables($list[$itemId]['display_name'], $organization, $user, $profile);
                            if ($list[$itemId]['document_name']) {
                                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                                if ($ext) {
                                    $ext = '.' . $ext;
                                }
                                $filename = parseVariables($list[$itemId]['document_name'], $organization, $user, $profile) . $ext;
                            }
                        }
                    }
                    else {
                        // error, el elemento no se aplica a este perfil/carpeta
                        $ok = false;
                        $type = 'danger';
                        $message = 'invalid item';
                    }
                }
            }
            else {
                // ¿se ha elegido ignorar el documento?
                if (false === isset($_POST['confirm' . $loop])) {
                    $ok = false;
                    $type = 'warning';
                    $message = 'ignored';
                }
            }

            if ($ok) {
                $filesize = filesize($tempDestination);
                $documentDestination = createDocumentFolder($preferences['upload.folder'], $hash);
                if (rename($tempDestination, $preferences['upload.folder'] . $documentDestination)) {
                    if (false === createDelivery($id, $user['id'], $profileId, $filename, $description, null, $itemId, $documentDestination, $hash, $filesize)) {
                        $ok = false;
                        $type = 'danger';
                        $message = 'cannot register';
                        // TODO: Borrar documento movido *si no existe en la base de datos*
                        //unlink($preferences['upload.folder'] . $documentDestination);
                    }
                }
                else {
                    $ok = false;
                    $type = 'danger';
                    $message = 'cannot move';
                }
            }
        }
        else {
            $ok = false;
            $type = 'danger';
            $message = 'not_found';
        }
        if (false === $ok) {
            $app->flash('upload_status_' . $failed, $type);
            $app->flash('upload_name_' . $failed, $_POST['filename' . $loop]);
            $app->flash('upload_error_' . $failed, $message);
            $failed++;
            unlink($tempDestination);
        }
        else {
            $success++;
        }
        $loop++;
    }
    $app->flash('upload', $failed);
    if ($success>0) {
        $app->flash('upload_ok', $success);
    }
    $app->redirect($app->urlFor('tree', array( 'id' => $folder['category_id'])));

})->name('confirm');

$app->get('/estadisticas/:id(/:return/:data1(/:data2(/:data3)))', function ($id, $return=0, $data1=null, $data2=null, $data3=null)
        use ($app, $user, $organization, $config) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    $folder = getFolderById($organization['id'], $id);

    $restrictedProfiles = parseArray(getPermissionProfiles($id, 2));
    $uploadProfiles = parseArray(getPermissionProfiles($id, 1));
    $managerProfiles = parseArray(getPermissionProfiles($id, 0));
    $userProfiles = parseArray(getUserProfiles($user['id'], $organization['id'], true));
    $allProfiles = parseArray(getProfilesByOrganization($organization['id']));

    $isManager = $user['is_admin'];
    foreach ($managerProfiles as $upload) {
        if (isset($userProfiles[$upload['id']])) {
            $isManager = true;
            break;
        }
    }

    $breadcrumb = array();
    $lastUrl = $app->request()->getPathInfo();

    switch ($return) {
        case 0:
            $breadcrumb = array(
                array('display_name' => 'Árbol', 'target' => $app->urlFor('tree'))
            );
            $category = getCategoryObjectById($organization['id'], $folder['category_id']);
            $parents = getCategoryParentsById($category['id']);
            foreach($parents as $parent) {
                $breadcrumb[] = array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree'));
            }
            $breadcrumb[] = array('display_name' => $category['display_name'], 'target' => $app->urlFor('tree', array('id' => $category['id'])));
            $breadcrumb[] = array('display_name' => 'Estadísticas');
            $lastUrl = $app->urlFor('tree', array('id' => $data1));
            break;
        case 1:
            $event = getEventByIdObject($organization['id'], $data3);
            $activityevent = getActivityEvent($data3, $data2, $user);
            $profile = getProfileById($organization['id'], $data1);
            if ((!$event) || (!$activityevent) || (!$profile) || ($event['folder_id'] != $id)) {
                $app->redirect($app->urlFor('login'));
            }
            $lastUrl = $app->urlFor('event', array('pid' => $data1, 'aid' => $data2, 'id' => $data3));

            $breadcrumb = array(
                array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
                array('display_name' => getProfileFullDisplayName($profile, $user), 'target' => $app->urlFor('activities', array('id' => $data1))),
                array('display_name' => $activityevent['activity_display_name'], 'target' => $app->urlFor('activities', array('id' => $data1))),
                array('display_name' => $event['display_name'], 'target' => $app->urlFor('event', array('pid' => $data1, 'aid' => $data2, 'id' => $data3))),
                array('display_name' => 'Estadísticas')
            );
            break;
    }

    $stats = getFolderProfileDeliveryStats($id);

    $data = getFolderItems($id, $organization['id'])->find_array();
    $items = parseVariablesArray($data, $organization, $user, 'profile_id', $allProfiles);

    $localStats = getArrayGroups($items,'event_id', 'profile_id');
    $now = getdate();
    $currentWeek = ($now['mon']-1)*4 + min(floor(($now['mday']-1)/7), 3);

    $app->render('folder_stats.html.twig', array(
        'navigation' => $breadcrumb,
        'search' => true,
        'url' => $app->request()->getPathInfo(),
        'back_url' => array('return' => $return, 'data1' => $data1, 'data2' => $data2, 'data3' => $data3),
        'last_url' => $lastUrl,
        'stats' => $stats,
        'local_stats' => $localStats,
        'base' => $config['calendar.base_week'],
        'current' => $currentWeek,
        'is_manager' => $isManager,
        'restricted_profiles' => $restrictedProfiles,
        'upload_profiles' => $uploadProfiles,
        'manager_profiles' => $managerProfiles,
        'user_profiles' => $userProfiles,
        'all_profiles' => $allProfiles,
        'folder' => $folder));

})->name('folderstats');

function getDelivery($deliveryId) {
    return ORM::for_table('delivery')->
            select('document.download_filename')->
            select('file_extension.mime')->
            select('document_data.download_path')->
            select('document_data.download_filesize')->
            inner_join('revision', array('delivery.current_revision_id', '=', 'revision.id'))->
            inner_join('document', array('document.id', '=', 'revision.original_document_id'))->
            inner_join('file_extension', array('file_extension.id', '=', 'document.extension_id'))->
            inner_join('document_data', array('document_data.id', '=', 'document.document_data_id'))->
            where('delivery.id', $deliveryId)->
            find_one();
}

function getDeliveryWithRevision($deliveryId, $revId) {
    return ORM::for_table('delivery')->
        select('document.download_filename')->
        select('file_extension.mime')->
        select('document_data.download_path')->
        select('document_data.download_filesize')->
        inner_join('revision', array('delivery.id', '=', 'revision.delivery_id'))->
        inner_join('document', array('document.id', '=', 'revision.original_document_id'))->
        inner_join('file_extension', array('file_extension.id', '=', 'document.extension_id'))->
        inner_join('document_data', array('document_data.id', '=', 'document.document_data_id'))->
        where('delivery.id', $deliveryId)->
        where('revision.id', $revId)->
        find_one();
}

function getPermissionProfiles($folderId, $permission) {
    return ORM::for_table('profile')->
            select('profile.*')->
            select('profile_group.display_name_male')->
            select('profile_group.display_name_female')->
            select('profile_group.display_name_neutral')->
            inner_join('folder_permission', array('folder_permission.profile_id', '=', 'profile.id'))->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            where('folder_permission.folder_id', $folderId)->
            where('folder_permission.permission', $permission)->
            find_array();
}

function getSubprofiles($profileGroupId) {
    return ORM::for_table('profile')->
            select('profile.*')->
            select('profile_group.display_name_male')->
            select('profile_group.display_name_female')->
            select('profile_group.display_name_neutral')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            where('profile.profile_group_id', $profileGroupId)->
            order_by_asc('profile.order_nr')->
            find_array();
}

function createDocumentFolder($prefix, $hash) {
    $path = substr($hash,0,2) . "/" . substr($hash,2,2);
    if (!is_dir($prefix . $path)) {
        mkdir($prefix . $path, 0770, true);
    }
    return $path . "/" . $hash;
}

function getFolderProfileDeliveryItems($profileId, $folderId) {
    $data = ORM::for_table('event_profile_delivery_item')->
            select('event_profile_delivery_item.*')->
            select('event.display_name', 'event_display_name')->
            select('event.from_week')->
            select('event.to_week')->
            select('event.force_period')->
            select('event.grace_period')->
            inner_join('event', array('event.id', '=', 'event_profile_delivery_item.event_id'))->
            where('event.folder_id', $folderId)->
            where('profile_id', $profileId)->
            where('is_visible', 1)->
            order_by_asc('event.id')->
            order_by_asc('order_nr')->
            find_many();
    return $data;
}

function getFolderProfileDeliveryStatsBase($folderId) {
    $data = ORM::for_table('event_profile_delivery_item')->
            select('event_profile_delivery_item.id')->
            select('event_profile_delivery_item.profile_id')->
            select('profile.display_name')->
            select('profile_group.display_name_neutral')->
            select('folder_delivery.snapshot_id')->
            select_expr('COUNT(DISTINCT event_profile_delivery_item.id)', 'total')->
            select_expr('SUM(folder_delivery.delivery_id IS NOT NULL AND (folder_delivery.snapshot_id IS NULL))', 'c')->
            inner_join('profile', array('profile.id', '=', 'event_profile_delivery_item.profile_id'))->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            inner_join('event', array('event.id', '=', 'event_profile_delivery_item.event_id'))->
            left_outer_join('delivery', array('delivery.item_id', '=', 'event_profile_delivery_item.id'))->
            left_outer_join('folder_delivery', 'folder_delivery.delivery_id=delivery.id AND folder_delivery.folder_id=event.folder_id')->
            where('event.folder_id', $folderId)->
            where('event_profile_delivery_item.is_visible', 1)->
            group_by('event_profile_delivery_item.profile_id')->
            order_by_asc('event_profile_delivery_item.id')->
            order_by_asc('event_profile_delivery_item.order_nr');

    return $data;
}

function getFolderProfileDeliveryStats($folderId) {
    $data = getFolderProfileDeliveryStatsBase($folderId)->
            find_array();
    return $data;
}

function getFolderProfileDeliveryStatsByProfile($folderId, $profileId) {
    $data = getFolderProfileDeliveryStatsBase($folderId)->
            where('profile.id', $profileId)->
            find_array();
    return $data;
}

function getFolderProfileDeliveredItems($profileId, $folderId, $orgId) {
    $data = ORM::for_table('event_profile_delivery_item')->
            inner_join('event', array('event.id', '=', 'event_id'))->
            select('event_profile_delivery_item.id')->
            select('event_profile_delivery_item.display_name')->
            select('event_profile_delivery_item.profile_id')->
            select('delivery.creation_date')->
            select_expr('SUM(folder_delivery.delivery_id IS NOT NULL AND (folder_delivery.snapshot_id IS NULL))', 'c')->
            left_outer_join('delivery', array('delivery.item_id', '=', 'event_profile_delivery_item.id'))->
            left_outer_join('folder_delivery', 'folder_delivery.delivery_id=delivery.id AND folder_delivery.folder_id=event.folder_id')->
            where('event.folder_id', $folderId)->
            where('event_profile_delivery_item.profile_id', $profileId)->
            where('event_profile_delivery_item.is_visible', 1)->
            where('event.organization_id', $orgId)->
            group_by('event_profile_delivery_item.id')->
            order_by_asc('event_profile_delivery_item.order_nr')->
            find_array();

    return $data;
}

function getArrayGroups($data, $key, $key2 = null) {
    $lastgroup = null;
    $return = array();
    $partial = array();
    foreach ($data as $item) {
        if ($lastgroup != $item[$key]) {
            if ($lastgroup !== null) {
                $return[$lastgroup] = $partial;
            }
            $partial = array();
            $lastgroup = $item[$key];
        }
        $partial[] = $item;
    }
    if ($lastgroup !== null) {
        $return[$lastgroup] = $partial;
    }
    if ($key2 !== null) {
        $return2 = array();
        foreach ($return as $key => $item) {
            $return2[$key] = getArrayGroups($item, $key2);
        }
        return $return2;
    }
    return $return;
}

function getFolderItemsBase($folderId, $orgId) {
    $data = ORM::for_table('event_profile_delivery_item')->
            inner_join('event', array('event.id', '=', 'event_id'))->
            select('event_profile_delivery_item.id')->
            select('event_profile_delivery_item.event_id')->
            select('event_profile_delivery_item.display_name')->
            select('event_profile_delivery_item.document_name')->
            select('event_profile_delivery_item.profile_id')->
            select('event.display_name', 'event_display_name')->
            select('event.from_week')->
            select('event.to_week')->
            select('event.grace_period')->
            select('event.force_period')->
            select('delivery.creation_date')->
            select('delivery.id', 'delivery_id')->
            select('event.folder_id')->
            select_expr('SUM(folder_delivery.delivery_id IS NOT NULL AND (folder_delivery.snapshot_id IS NULL))', 'c')->
            left_outer_join('delivery', array('delivery.item_id', '=', 'event_profile_delivery_item.id'))->
            left_outer_join('folder_delivery', 'folder_delivery.delivery_id=delivery.id AND folder_delivery.folder_id=event.folder_id')->
            where('event.folder_id', $folderId)->
            where('event.organization_id', $orgId)->
            where('event_profile_delivery_item.is_visible', 1)->
            group_by('event_profile_delivery_item.id');

    return $data;
}

function getFolderItems($folderId, $orgId) {
    $data = getFolderItemsBase($folderId, $orgId)->
            order_by_asc('event_profile_delivery_item.profile_id')->
            order_by_asc('event_profile_delivery_item.order_nr');

    return $data;
}

function getFolderItemsInSnapshot($folderId, $orgId, $snapshotId = null) {
        $data = ORM::for_table('event_profile_delivery_item')->
            inner_join('event', array('event.id', '=', 'event_id'))->
            select('event_profile_delivery_item.id')->
            select('event_profile_delivery_item.event_id')->
            select('event_profile_delivery_item.display_name')->
            select('event_profile_delivery_item.document_name')->
            select('event_profile_delivery_item.profile_id')->
            select('event.display_name', 'event_display_name')->
            select('event.from_week')->
            select('event.to_week')->
            select('event.grace_period')->
            select('event.force_period')->
            select('delivery.creation_date')->
            select('delivery.id', 'delivery_id')->
            select('event.folder_id')->
            select_expr('SUM(folder_delivery.delivery_id IS NOT NULL AND (folder_delivery.snapshot_id IS NULL))', 'c')->
            left_outer_join('delivery', array('delivery.item_id', '=', 'event_profile_delivery_item.id'))->
            left_outer_join('folder_delivery', 'folder_delivery.delivery_id=delivery.id AND folder_delivery.folder_id=event.folder_id')->
            where('event.folder_id', $folderId)->
            where('event.organization_id', $orgId)->
            where('event_profile_delivery_item.is_visible', 1)->
            group_by('event_profile_delivery_item.id');

    if ($snapshotId) {
        $data = $data->where('folder_delivery.snapshot_id', $snapshotId);
    }
    else {
        $data = $data->where_not_null('folder_delivery.snapshot_id')->
                order_by_asc('folder_delivery.snapshot_id');
    }
    
    return $data;
}

function getFolderItemsByUser($userId, $folderId, $orgId) {
    $data = getFolderItems($folderId, $orgId)->
            left_outer_join('person_profile', array('person_profile.profile_id', '=', 'event_profile_delivery_item.profile_id'))->
            where('person_profile.person_id', $userId)->
            find_array();

    return $data;
}

function getProfile($profileId) {
    return ORM::for_table('profile')->
            select('profile.id')->
            select('profile.display_name')->
            select('profile.profile_group_id')->
            select('profile.initials')->
            select('profile_group.display_name_neutral')->
            select('profile_group.display_name_male')->
            select('profile_group.display_name_female')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            find_one($profileId);
}

function getDeliveryItemCount($profileId, $folderId, $itemId) {
    return ORM::for_table('delivery')->
            inner_join('folder_delivery', array('folder_delivery.delivery_id', '=', 'delivery.id'))->
            where('folder_delivery.folder_id', $folderId)->
            where('delivery.profile_id', $profileId)->
            where('delivery.item_id', $itemId)->
            where_null('folder_delivery.snapshot_id')->
            count();
}

function getDocumentDataByHash($hash) {
    return ORM::for_table('document_data')->
            where('data_hash', $hash)->
            find_one();
}

function getDocumentByHash($hash) {
    return ORM::for_table('document')->
            select('document.*')->
            inner_join('document_data', array('document_data.id', '=', 'document.document_data_id'))->
            where('document_data.data_hash', $hash)->
            find_one();
}

function getExtension($ext) {
    return ORM::for_table('file_extension')->
            find_one($ext);
}

function getPersonsWithEventByFolderAndProfile($folderId, $profileId) {
    return ORM::for_table('person')->distinct()->
            select('person.id')->
            select('event.id', 'event_id')->
            inner_join('person_profile', array('person_profile.person_id', '=', 'person.id'))->
            inner_join('event_profile_delivery_item', array('event_profile_delivery_item.profile_id', '=', 'person_profile.profile_id'))->
            inner_join('event', array('event.id', '=', 'event_profile_delivery_item.event_id'))->
            inner_join('folder', array('folder.id', '=', 'event.folder_id'))->
            where('event.folder_id', $folderId)->
            where('event_profile_delivery_item.profile_id', $profileId)->
            where('event.is_automatic', 1)->
            find_array();
}

function checkItemUpdateStatus($folderId, $profileId) {
    $data = getFolderProfileDeliveryStatsByProfile($folderId, $profileId);

    // para cada perfil que tiene ítems, comprobamos si están todos los
    // elementos
    foreach ($data as $item) {

        // obtener usuarios asociados a esta carpeta y perfil
        $persons = getPersonsWithEventByFolderAndProfile($folderId, $profileId);

        if ($item['total'] == $item['c']) {
            // están todos los elementos: marcar como completados los eventos
            // de todos los usuarios
            foreach($persons as $person) {
                removeCompletedEvent($person['event_id'], $person['id']);
                addCompletedEvent($person['event_id'], $person['id']);
            }
        }
        else {
            // no están todos los elementos: marcar como incompletos los eventos
            // de todos los usuarios
            foreach($persons as $person) {
                removeCompletedEvent($person['event_id'], $person['id']);
            }
        }
    }
}

function checkItemUpdateStatusByProfile($profile) {
    // revisar los eventos de completado automático
    $profileData = getProfile($profile);
    $events = ORM::for_table('event_profile')->
    select('event.*')->
    inner_join('event', array('event.id', '=', 'event_id'))->
    where_in('profile_id', array($profile, $profileData['profile_group_id']))->
    find_array();

    foreach($events as $event) {
        if ($event['folder_id']) {
            checkItemUpdateStatus($event['folder_id'], $profile);
        }
    }
}

function checkItemUpdateStatusByFolder($folderId) {
    // revisar los eventos de completado automático
    $profiles = ORM::for_table('event_profile')->
        select('profile_id')->
        distinct()->
        inner_join('event', array('event.id', '=', 'event_id'))->
        where('event.folder_id', $folderId)->
        find_array();

    $profiles = array_column($profiles, 'profile_id');

    if (empty($profiles)) {
        $allProfiles = array();
    }
    else {
        $allProfiles = ORM::for_table('profile')->
            select('id')->
            distinct()->
            where_in('profile_group_id', $profiles)->
            find_array();
        $allProfiles = array_column($allProfiles, 'id');
    }

    $allProfiles = array_merge($profiles, $allProfiles);

    foreach($allProfiles as $profile) {
        checkItemUpdateStatus($folderId, $profile);
    }
}

function createDelivery($folderId, $userId, $profileId, $fileName, $deliveryName, $description, $itemId, $dataPath, $dataHash, $filesize, $revisionNr = 0) {

    $order = ORM::for_table('folder_delivery')->
            where('folder_id', $folderId)->
            max('order_nr');

    ORM::get_db()->beginTransaction();

    $delivery = ORM::for_table('delivery')->create();
    $delivery->set('profile_id', $profileId);
    $delivery->set('item_id', $itemId);
    $delivery->set('display_name', $deliveryName);
    $delivery->set('description', $description);
    $delivery->set('creation_date', date('c'));
    $delivery->save();

    $folderDelivery = ORM::for_table('folder_delivery')->create();
    $folderDelivery->set('folder_id', $folderId);
    $folderDelivery->set('delivery_id', $delivery['id']);
    $folderDelivery->set('order_nr', $order + 1000);
    $folderDelivery->save();

    $revision = createRevision($delivery['id'], $userId, $fileName, $dataPath, $dataHash, $filesize, $revisionNr);

    $delivery->set('current_revision_id', $revision['id']);
    $delivery->save();

    checkItemUpdateStatus($folderId, $profileId);

    return ORM::get_db()->commit();
}

function createRevision($deliveryId, $userId, $fileName, $dataPath, $dataHash, $filesize, $revisionNr, $uploadComment = null) {

    $revision = ORM::for_table('revision')->create();
    $revision->set('delivery_id', $deliveryId);
    $revision->set('uploader_person_id', $userId);
    $revision->set('upload_date', date('c'));
    $revision->set('revision_nr', $revisionNr);
    if ($uploadComment) {
        $revision->set('upload_comment', $uploadComment);
    }
    $revision->save();

    $document = createDocument($revision['id'], $fileName, $dataHash, $dataPath, $filesize);

    $revision->set('original_document_id', $document['id']);
    $revision->save();

    return $revision;
}

function createDocument($revisionId, $fileName, $dataHash, $dataPath, $filesize) {

    $documentData = getDocumentDataByHash($dataHash);

    if (false === $documentData) {
        $documentData = ORM::for_table('document_data')->create();
        $documentData->set('download_path', $dataPath);
        $documentData->set('data_hash', $dataHash);
        $documentData->set('download_filesize', $filesize);
        $documentData->save();
    }

    $ext = pathinfo($fileName, PATHINFO_EXTENSION);

    $extension = getExtension($ext);
    if (false === $extension) {
        $extension = ORM::for_table('file_extension')->create();
        $extension->set('id', $ext);
        $extension->set('mime', 'application/octet-stream');
        $extension->set('display_name', 'Documento .' . $ext);
        $extension->set('icon', 'icon-none.png');
        $extension->save();
    }

    $document = ORM::for_table('document')->create();
    $document->set('document_data_id', $documentData['id']);
    $document->set('download_filename', $fileName);
    $document->set('extension_id', $extension['id']);
    $document->set('revision_id', $revisionId);
    $document->save();

    return $document;
}

function parseVariables($string, $organization, $user, $profile) {
    return preg_replace_callback('~(\\{[^}]+\\})~',
            function($token_array) use ($organization, $user, $profile) {
                $token = trim($token_array[0], '{}');
                switch ($token) {
                    case 'user.initials':
                        return $user['initials'];
                    case 'user.name':
                        return $user['display_name'];
                    case 'profile.initials':
                        return $profile['initials'];
                    case 'profile.name':
                        return $profile['display_name'];
                }

                // probar con las variables de la tabla 'variables'
                // específico para esta organización
                $data = ORM::for_table('variable')->
                        where('name', $token)->
                        where('organization_id', $organization['id'])->
                        find_one();

                // probar con las variables de la tabla 'variables'
                // en genérico si no hay nada específico
                if (!$data) {
                    $data = ORM::for_table('variable')->
                        where('name', $token)->
                        where_null('organization_id')->
                        find_one();
                }
                return $data ? $data['content'] : NULL;
        }, $string);
}

function parseVariablesArray($data, $organization, $user, $profile, $profiles = null) {
    foreach ($data as $k => $item) {
        $data[$k]['display_name'] = parseVariables($data[$k]['display_name'], $organization, $user, $profiles ? $profiles[$data[$k][$profile]] : $profile);
    }
    return $data;
}
