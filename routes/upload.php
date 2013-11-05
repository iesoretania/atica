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

$app->get('/enviar/:id', function ($id) use ($app, $user, $config, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    $data = array();
    $category = array();
    $parent = array();

    $folder = getFolder($organization['id'], $id);
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
    $stats = getFolderProfileDeliveryStats($id);
    $sidebar = getTree($organization['id'], $app, $folder['category_id'], $category, $parent);

    $breadcrumb = array(
        array('display_name' => 'Árbol', 'target' => $app->urlFor('tree')),
        array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree')),
        array('display_name' => $category['display_name'], 'target' => $app->urlFor('tree', array('id' => $category['id']))),
        array('display_name' => 'Enviar documento')
    );

    $app->flashKeep();
    
    $app->render('upload.html.twig', array(
        'navigation' => $breadcrumb, 'search' => false, 'sidebar' => $sidebar,
        'select2' => true,
        'category' => $category,
        'folder' => $folder,
        'upload_profiles' => $uploadProfiles,
        'manager_profiles' => $managerProfiles,
        'user_profiles' => $userProfiles,
        'upload_as' => $uploadAs,
        'stats' => $stats,
        'data' => $data));
})->name('upload');

$app->post('/enviar/:id', function ($id) use ($app, $user, $preferences, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }
    if ((! isset($_FILES['document']['name'][0])) || (strlen($_FILES['document']['name'][0]) == 0)) {
        // no hay archivos enviados
        $app->redirect($app->urlFor('upload', array('id' => $id)));
    }
    
    $items = array();

    // TODO: Comprobar si la carpeta es válida
    $folder = getFolder($organization['id'], $id);
    
    // TODO: Comprobar perfil
    $profileIsSet = $folder['is_divided'];
    $profileId = $profileIsSet ? $_POST['profile'] : null;

    // buscar si hay una lista de entrega
    $list = $profileIsSet ?
            parseArray(getFolderProfileDeliveryItems($profileId, $id)) :
            array();

    // si es falso, mostrar revisión de los documentos enviados
    $finished = false;
    
    $loop = 0;
    $failed = 0;
    $success = 0;
    while (isset($_FILES['document']['name'][$loop])) {
        $ok = true;
        $type = "";
        if ( is_uploaded_file($_FILES['document']['tmp_name'][$loop]) ) {
            $hash = sha1_file($_FILES['document']['tmp_name'][$loop]);
            $filesize = filesize($_FILES['document']['tmp_name'][$loop]);

            if (!$list) {
                $documentDestination = createDocumentFolder($preferences['upload.folder'], $hash);
                if (move_uploaded_file($_FILES['document']['tmp_name'][$loop], $preferences['upload.folder'] . $documentDestination))
                    $filename = $_FILES['document']['name'][$loop];
                    $info = pathinfo( $filename );
                    $description = str_replace ('_', ' ', $info['filename']);
                    
                    if (false == createDelivery($id, $user['id'], $profileId, $_FILES['document']['name'][$loop], $description, null, null, $documentDestination, $hash, $filesize)) {
                        $ok = false;
                        $type = 'danger';
                        $message = 'cannot register';
                    }               
                    else {
                        $ok = true;
                        $type = 'ok';
                    }
            }
            else {
                // Mover a una carpeta temporal
                @mkdir($preferences['upload.folder'] . "temp/", 0770, true);
                $tempDestination = $preferences['upload.folder'] . "temp/" . $hash;
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
            $ok = false;
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

    $sidebar = getTree($organization['id'], $app, $folder['category_id'], $category, $parent);

    $breadcrumb = array(
        array('display_name' => 'Árbol', 'target' => $app->urlFor('tree')),
        array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree')),
        array('display_name' => $category['display_name'], 'target' => $app->urlFor('tree', array('id' => $category['id']))),
        array('display_name' => 'Revisar documento')
    );

    $profile = $profileIsSet ? getProfile($profileId) : array();

    $deliveries = $profileIsSet ?
            getFolderProfileDeliveredItems($profileId, $id) :
            array();

    $app->flashKeep();

    $app->render('upload_review.html.twig', array(
        'navigation' => $breadcrumb, 'search' => false, 'sidebar' => $sidebar,
        'select2' => true,
        'category' => $category,
        'folder' => $folder,
        'items' => $list,
        'profile' => $profile,
        'deliveries' => $deliveries,
        'data' => $items));

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
            @unlink($tempDestination);
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
                if (false == isset($_POST['confirm' . $loop])) {
                    $ok = false;
                    $type = 'warning';
                    $message = 'ignored';
                }
            }
            
            if ($ok) {
                $filesize = filesize($tempDestination);
                $documentDestination = createDocumentFolder($preferences['upload.folder'], $hash);
                if (rename($tempDestination, $preferences['upload.folder'] . $documentDestination)) {
                    if (false == createDelivery($id, $user['id'], $profileId, $filename, $description, null, $itemId, $documentDestination, $hash, $filesize)) {
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
        if (false == $ok) {
            $app->flash('upload_status_' . $failed, $type);
            $app->flash('upload_name_' . $failed, $_POST['filename' . $loop]);
            $app->flash('upload_error_' . $failed, $message);
            $failed++;
            @unlink($tempDestination);
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
    //$app->redirect($app->urlFor('upload', array('id' => $id)));
    
})->name('confirm');

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
    @mkdir($prefix . $path, 0770, true);
    return $path . "/" . $hash;
}

function getFolderProfileDeliveryItems($profileId, $folderId) {
    $data = ORM::for_table('folder_profile_delivery_item')->
            where('folder_id', $folderId)->
            where('profile_id', $profileId)->
            where('is_visible', 1)->
            order_by_asc('order_nr')->
            find_many();
    return $data;
}

function getFolderProfileDeliveryStatsBase($folderId) {
    $data = ORM::for_table('folder_profile_delivery_item')->
            select('folder_profile_delivery_item.id')->
            select('folder_profile_delivery_item.profile_id')->
            select('profile.display_name')->
            select('profile_group.display_name_neutral')->
            select_expr('COUNT(folder_profile_delivery_item.profile_id)', 'total')->
            select_expr('COUNT(delivery.item_id)', 'c')->
            inner_join('profile', array('profile.id', '=', 'folder_profile_delivery_item.profile_id'))->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            left_outer_join('delivery', array('delivery.item_id', '=', 'folder_profile_delivery_item.id'))->
            where('folder_id', $folderId)->
            where('folder_profile_delivery_item.is_visible', 1)->
            group_by('folder_profile_delivery_item.profile_id')->
            order_by_asc('folder_profile_delivery_item.id')->
            order_by_asc('folder_profile_delivery_item.order_nr');
    
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

function getFolderProfileDeliveredItems($profileId, $folderId) {
    $data = ORM::for_table('folder_profile_delivery_item')->
            select('folder_profile_delivery_item.id')->
            select('folder_profile_delivery_item.display_name')->
            select('folder_profile_delivery_item.profile_id')->
            select('delivery.creation_date')->
            select_expr('COUNT(delivery.item_id)', 'c')->
            left_outer_join('delivery', array('delivery.item_id', '=', 'folder_profile_delivery_item.id'))->
            where('folder_id', $folderId)->
            where('folder_profile_delivery_item.profile_id', $profileId)->
            where('folder_profile_delivery_item.is_visible', 1)->
            group_by('folder_profile_delivery_item.id')->
            order_by_asc('folder_profile_delivery_item.order_nr')->
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
            count();
}

function getDocumentDataByHash($hash) {
    return ORM::for_table('document_data')->
            where('data_hash', $hash)->
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
            inner_join('folder_profile_delivery_item', array('folder_profile_delivery_item.profile_id', '=', 'person_profile.profile_id'))->
            inner_join('folder', array('folder.id', '=', 'folder_profile_delivery_item.folder_id'))->
            inner_join('event', array('event.folder_id', '=', 'folder_profile_delivery_item.folder_id'))->
            where('folder_profile_delivery_item.folder_id', $folderId)->
            where('folder_profile_delivery_item.profile_id', $profileId)->
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

function createRevision($deliveryId, $userId, $fileName, $dataPath, $dataHash, $filesize, $revisionNr) {
    
    $revision = ORM::for_table('revision')->create();
    $revision->set('delivery_id', $deliveryId);
    $revision->set('uploader_person_id', $userId);
    $revision->set('upload_date', date('c'));
    $revision->set('revision_nr', $revisionNr);
    $revision->save();
    
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
    $document->set('revision_id', $revision['id']);
    $document->save();

    $revision->set('original_document_id', $document['id']);
    $revision->save();
    
    return $revision;
    
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