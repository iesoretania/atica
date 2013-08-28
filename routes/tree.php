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

$app->get('/arbol(/:id)', function ($id = NULL) use ($app, $user, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    $data = array();
    $folders = array();
    $category = array();
    $parent = array();
    $persons = array();
    $profiles = array();
    $profileGender = array();

    $sidebar = getTree($organization['id'], $app, $id, $category, $parent);

    if (NULL !== $id) {
        $data = getParsedFolders($id, $profileGender);
        $folders = getFolders($id, $user);
        $persons = getFolderPersons($id);
        $profiles = getProfiles($id);

        $breadcrumb = array(
            array('display_name' => 'Árbol', 'target' => $app->urlFor('tree')),
            array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree')),
            array('display_name' => $category['display_name'])
        );
    }
    else {
        $breadcrumb = array(
            array('display_name' => 'Árbol', 'target' => '#')
        );
    }

    $app->render('tree.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'sidebar' => $sidebar,
        'category' => $category,
        'data' => $data,
        'persons' => $persons,
        'profiles' => $profiles,
        'profileGender' => $profileGender,
        'folders' => $folders));
})->name('tree');

$app->get('/descargar/:kind/:cid/:id/', function ($kind, $cid, $id) use ($app, $user, $preferences) {

    // $kind =
    // 1 -> la descarga se produce desde una carpeta del árbol, $cid = category.id
    // 2 -> la descarga se produce desde un agrupamiento, $cid = grouping.id
    switch($kind) {
        case 1:
            // sólo usuarios autenticados
            if (!$user) {
                $app->redirect($app->urlFor('login'));
            }
            $errorUrl = $app->urlFor('tree', array('id' => $cid));
            break;
        case 2:
            $errorUrl = $app->urlFor('grouping', array('id' => $cid));
            break;
        default:
            $app-redirect($app->urlFor('frontpage'));
    }

    $delivery = getDelivery($id, $user['id']);
    if (!$delivery) {
       $app->flash('home_error', 'no_delivery');
       $app->redirect($errorUrl);
    }
    $file = $preferences['upload.folder'] . $delivery['download_path'];

    if (!file_exists($file)) {
       $app->flash('home_error', 'no_document');
       $app->redirect($errorUrl);
    }

    $res = $app->response();
    $res['Content-Description'] = 'File Transfer';
    $res['Content-Type'] = ($delivery['mime'] == NULL) ?
            'application/octet-stream' : $delivery['mime'];
    $res['Content-Disposition'] ='attachment; filename=' . basename($delivery['download_filename']);
    $res['Content-Transfer-Encoding'] = 'binary';
    $res['Expires'] = '0';
    $res['Cache-Control'] = 'must-revalidate';
    $res['Pragma'] = 'public';
    $res['Content-Length'] = $delivery['download_filesize'];
    readfile($file);
})->name('download');

$app->get('/enviar/:id', function ($id) use ($app, $user, $config, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    $data = array();
    $category = array();
    $parent = array();

    $folder = getFolder($id);
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
            if (NULL == $item['display_name']) {
                $data = parseArray(getSubprofiles($item['id']));
                if (count($data)>1) {
                    foreach($data as $subItem) {
                        if (NULL != $subItem['display_name']) {
                            $uploadAs[$subItem['id']] = $subItem;
                        }
                    }
                }
                else {
                    $uploadAs = array_merge($uploadAs, $data);
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

    $app->render('upload.html.twig', array(
        'navigation' => $breadcrumb, 'search' => false, 'sidebar' => $sidebar,
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

    $items = array();

    // TODO: Comprobar si la carpeta es válida
    $folder = getFolder($id);
    
    // TODO: Comprobar perfil
    $profileIsSet = $folder['is_folder_divided'];
    $profileId = $profileIsSet ? $_POST['profile'] : NULL;

    // buscar si hay una lista de entrega
    $list = $profileIsSet ?
            parseArray(getFolderProfileDeliveryItems($profileId, $id)) :
            array();

    $loop = 0;
    while (isset($_FILES['document']['name'][$loop])) {
        if ( is_uploaded_file($_FILES['document']['tmp_name'][$loop]) ) {
            $hash = sha1_file($_FILES['document']['tmp_name'][$loop]);
            $filesize = filesize($_FILES['document']['tmp_name'][$loop]);

            // Mover a una carpeta temporal
            @mkdir($preferences['upload.folder'] . "temp/", 0770, true);
            $tempDestination = $preferences['upload.folder'] . "temp/" . $hash;
            move_uploaded_file($_FILES['document']['tmp_name'][$loop], $tempDestination);

            $items[] = array(
                'name' => $_FILES['document']['name'][$loop],
                'hash' => $hash,
                'filesize' => $filesize
            );

        }
        else {
            // TODO: error
        }
        $loop++;
    }

    if (count($items)>0) {
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

        $app->render('upload_review.html.twig', array(
            'navigation' => $breadcrumb, 'search' => false, 'sidebar' => $sidebar,
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
    $folder = getFolder($id);
    
    // TODO: Comprobar perfil
    $profileIsSet = $folder['is_folder_divided'];
    $profileId = $profileIsSet ? $_POST['profile'] : NULL;

    // buscar si hay una lista de entrega
    $list = $profileIsSet ?
            parseArray(getFolderProfileDeliveryItems($profileId, $id)) :
            array();
    
    $loop = 1;
    $success = 0;
    $failed = 0;
    
    // TODO: comprobar que $hash es realmente un hash
    // TODO: comprobar que 'profile' es correcto
    
    while (isset($_POST['hash' . $loop])) {
        $ok = true;
        $hash = $_POST['hash' . $loop];
        $tempDestination = $preferences['upload.folder'] . "temp/" . $hash;
        
        $itemId = NULL;
        
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
                        if ($profileId && (getDeliveryItemCount($profileId, $id) > 0)) {
                            // error, ya existe un ítem de ese tipo
                            $ok = false;
                            $type = 'danger';
                            $message = 'already exists';
                        }
                        else {
                            // correcto
                            $itemId = $_POST['element' . $loop];
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
                    if (false == createDelivery($id, $user['id'], $profileId, $_POST['filename'. $loop], NULL, $itemId, $documentDestination, $hash, $filesize)) {
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
    
    $app->redirect($app->urlFor('upload', array('id' => $id)));

})->name('confirm');

function getTree($orgId, $app, $id, &$matchedCategory, &$parentCategory) {
    $return = array();
    $currentData = array();
    $currentCategory = NULL;
    $match = false;

    $data = ORM::for_table('category')->
            order_by_asc('category_left')->
            where('organization_id', $orgId)->
            where_gt('category_level', 0)->
            find_many();

    foreach ($data as $category) {
        if ($category['category_level'] == 1) {
            if ($currentCategory != NULL) {
                $return[] = array(
                    'caption' => $currentCategory['display_name'],
                    'active' => $match,
                    'data' => $currentData
                );
                if ($match) {
                    $parentCategory = $currentCategory;
                }
            }
            $currentData = array();
            $currentCategory = $category;
            $match = false;
        }
        else {
            $localMatch = ($id == $category['id']);
            $currentData[] = array(
                'caption' => $category['display_name'],
                'active' => $localMatch,
                'target' => $app->urlFor('tree', array('id' => $category['id']))
            );
            if ($localMatch) {
                $matchedCategory = $category;
            }
            $match = $match || $localMatch;
        }
    }
    if ($currentCategory != NULL) {
        $return[] = array(
            'caption' => $currentCategory['display_name'],
            'active' => $match,
            'data' => $currentData
        );
        if ($match) {
            $parentCategory = $currentCategory;
        }
    }

    return $return;
}

function getFolderPersons($categoryId) {
    return parseArray(ORM::for_table('person')->distinct()->
            select('person.*')->
            inner_join('revision', array('person.id', '=', 'revision.uploader_person_id'))->
            inner_join('delivery', array('delivery.current_revision_id', '=', 'revision.id'))->
            inner_join('folder_delivery', array('folder_delivery.delivery_id','=','delivery.id'))->
            inner_join('folder', array('folder.id','=','folder_delivery.folder_id'))->
            where('folder.category_id', $categoryId)->
            where('folder.is_visible', 1)->
            find_array());
}

function getFolders($category_id, $user) {
    return parseArray(ORM::for_table('folder')->
            select('folder.*')->
            select_expr('sum(folder_permission.permission=0)','manage_permission')->
            select_expr('sum(folder_permission.permission=1)','upload_permission')->
            inner_join('folder_permission', array('folder_permission.folder_id', '=', 'folder.id'))->
            inner_join('profile', array('profile.id', '=', 'folder_permission.profile_id'))->
            inner_join('person_profile', array('person_profile.profile_id', '=', 'profile.id'))->
            where('person_profile.person_id', $user['id'])->
            where('folder.category_id', $category_id)->
            where('folder.is_visible', 1)->
            group_by('folder.id')->
            find_array());
}

function getProfiles($category_id) {
    return parseArray(ORM::for_table('profile')->distinct()->
            select('profile_group.*')->
            select('profile.*')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            inner_join('delivery', array('delivery.profile_id', '=', 'profile.id'))->
            inner_join('folder_delivery', array('folder_delivery.delivery_id','=','delivery.id'))->
            inner_join('folder', array('folder.id','=','folder_delivery.folder_id'))->
            where('folder.category_id', $category_id)->
            order_by_asc('profile_group_id')->
            order_by_asc('profile.id')->
            find_array());
}

function getParsedFolders($categoryId, &$profileGender) {

    $data = ORM::for_table('delivery')->
            select('delivery.*')->
            select('folder.id', 'folder_id')->
            select('revision.upload_date')->
            select('revision.uploader_person_id')->
            select('person.gender')->
            inner_join('folder_delivery', array('folder_delivery.delivery_id','=','delivery.id'))->
            inner_join('folder', array('folder.id', '=', 'folder_delivery.folder_id'))->
            inner_join('revision', array('delivery.current_revision_id', '=', 'revision.id'))->
            inner_join('person', array('person.id', '=', 'revision.uploader_person_id'))->
            order_by_asc('folder.order_nr')->
            order_by_asc('delivery.profile_id')->
            order_by_asc('folder_delivery.order_nr')->
            order_by_asc('revision.upload_date')->
            where('folder.category_id', $categoryId)->
            where('folder.is_visible', 1)->
            find_many();

    $return = array();
    $currentData = array();
    $currentFolderId = NULL;

    foreach ($data as $delivery) {
        if ($delivery['folder_id'] !== $currentFolderId) {
            if ($currentData != NULL) {
                $return[] = array(
                    'id' => $currentFolderId,
                    'data' => $currentData
                );
            }
            $currentData = array();
            $currentFolderId = $delivery['folder_id'];
        }
        else {
            $currentData[] = $delivery->as_array();
            if (isset($profileGender[$delivery['profile_id']])) {
                if ($profileGender[$delivery['profile_id']] != $delivery['gender']) {
                    $profileGender[$delivery['profile_id']] = 0;
                }
            }
            else {
                $profileGender[$delivery['profile_id']] = $delivery['gender'];
            }
        }
    }
    if ($currentData != NULL) {
        $return[] = array(
            'id' => $currentFolderId,
            'data' => $currentData
        );
    }

    return $return;
}

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

function getFolder($folderId) {
    return ORM::for_table('folder')->
            where('folder.id', $folderId)->
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
            find_many();
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
            find_many();
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
            order_by_asc('order_nr')->
            find_many();
    if ($data == false) {
        $data = ORM::for_table('folder_profile_delivery_item')->
                select('folder_profile_delivery_item.*')->
                inner_join('profile', array('folder_profile_delivery_item.profile_id', '=', 'profile.profile_group_id'))->
                where('folder_id', $folderId)->
                where('profile.id', $profileId)->
                order_by_asc('folder_profile_delivery_item.order_nr')->
                find_many();
    }
    return $data;
}

function getFolderProfileDeliveryStats($folderId) {
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
            group_by('folder_profile_delivery_item.profile_id')->
            group_by('delivery.item_id')->
            order_by_asc('folder_profile_delivery_item.id')->
            order_by_asc('folder_profile_delivery_item.order_nr')->
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
            select('profile_group.display_name_neutral')->
            select('profile_group.display_name_male')->
            select('profile_group.display_name_female')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            find_one($profileId);
}

function getDeliveryItemCount($profileId, $folderId) {
    return ORM::for_table('delivery')->
            inner_join('folder_delivery', array('folder_delivery.delivery_id', '=', 'delivery.id'))->
            where('folder_delivery.folder_id', $folderId)->
            where('delivery.profile_id', $profileId)->
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

function createDelivery($folderId, $userId, $profileId, $displayName, $description, $itemId, $dataPath, $dataHash, $filesize, $revision = 0) {
    
    $documentData = getDocumentDataByHash($dataHash);
    $order = ORM::for_table('folder_delivery')->
            where('folder_id', $folderId)->
            max('order_nr');
    
    
    ORM::get_db()->beginTransaction();
    
    if (false === $documentData) {
        $documentData = ORM::for_table('document_data')->create();
        $documentData->set('download_path', $dataPath);
        $documentData->set('data_hash', $dataHash);
        $documentData->set('download_filesize', $filesize);
        $documentData->save();
    }
    
    $ext = pathinfo($displayName, PATHINFO_EXTENSION);
    $name = preg_replace("/\\.[^.\\s]{2,3,4}$/", "", $displayName);
            
    $extension = getExtension($ext);
    if (false === $extension) {
        // TODO: Cuidado:
        die($ext);
        $extension = ORM::for_table('file_extension')->create();
        $extension->set('id', $ext);
        $extension->set('mime', 'application/octet-stream');
        $extension->set('display_name', 'Documento .' . $ext);
        $extension->set('icon', 'icon-none.png');
        $extension->save();
    }
    
    $delivery = ORM::for_table('delivery')->create();
    $delivery->set('profile_id', $profileId);
    $delivery->set('item_id', $itemId);
    $delivery->set('display_name', $name);
    $delivery->set('description', $description);
    $delivery->set('creation_date', date('c'));
    $delivery->save();
    
    $folderDelivery = ORM::for_table('folder_delivery')->create();
    $folderDelivery->set('folder_id', $folderId);
    $folderDelivery->set('delivery_id', $delivery['id']);
    $folderDelivery->set('order_nr', $order + 1000);
    $folderDelivery->save();
    
    $revision = ORM::for_table('revision')->create();
    $revision->set('delivery_id', $delivery['id']);
    $revision->set('uploader_person_id', $userId);
    $revision->set('upload_date', date('c'));
    $revision->save();
    
    $document = ORM::for_table('document')->create();
    $document->set('document_data_id', $documentData['id']);
    $document->set('download_filename', $displayName);
    $document->set('extension_id', $extension['id']);
    $document->set('revision_id', $revision['id']);
    $document->save();

    $revision->set('original_document_id', $document['id']);
    $revision->save();
    
    $delivery->set('current_revision_id', $revision['id']);
    $delivery->save();
    
    // TODO: crear 'document', 'revision' y 'delivery'
    
    ORM::get_db()->commit();
    
    return true;
}
