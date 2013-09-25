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

$app->map('/modificar/:folderid/:id', function ($folderId, $id) use ($app, $user, $config, $organization, $preferences) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }
    $delivery = getDeliveryById($id);
    if (false == $delivery) {
        $app->redirect($app->urlFor('tree'));
    }
    $revisions = getRevisionsObjectByDelivery($id);
    $uploaders = getDeliveryUploadersById($id);
    
    $data = array();
    $category = array();
    $parent = array();

    $folder = getFolder($organization['id'], $folderId);
    $uploadProfiles = parseArray(getPermissionProfiles($folderId, 1));
    $managerProfiles = parseArray(getPermissionProfiles($folderId, 0));
    $userProfiles = parseArray(getUserProfiles($user['id'], $organization['id'], true));

    $isManager = $user['is_admin'];
    foreach ($managerProfiles as $upload) {
        if (isset($userProfiles[$upload['id']])) {
            $isManager = true;
            break;
        }
    }
    // si no tiene permisos para editar la entrega, salir
    // tiene permiso si:
    // - Es administrador o gestor de la carpeta ($isManager)
    // - La revisión activa es suya
    if ((!$isManager) && ($revisions[$delivery['current_revision_id']]['uploader_person_id'] != $user['id'])) {
        $app-redirect($app->urlFor('login'));
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
                    $uploadAs[$item['id']] = $item;
                }
            }
            else {
                $uploadAs[$item['id']] = $item;
            }
        }
    }
    
    $sidebar = getTree($organization['id'], $app, $folder['category_id'], $category, $parent);
    
    if (isset($_POST['save'])) {
        $delivery->set('display_name', $_POST['displayname']);
        $delivery->set('description', strlen($_POST['displayname']) > 0 ? $_POST['displayname'] : NULL);
        $delivery->save();
        $app->flash('save_ok', 'ok');
        $app->redirect($app->request()->getPathInfo());
    }
     
    if (isset($_POST['delete'])) {
        ORM::get_db()->beginTransaction();
        $ok = true;
        foreach($revisions as $revision) {
            if ($ok) {
                $status = deleteDocumentById($revision['original_document_id'], $preferences);
                $ok = $status && $ok;
            }
        }
        foreach($revisions as $revision) {
            $ok = $ok && $revision->delete();
        }
        if ($ok) {
            $delivery->delete();
        }
        
        if ($ok) {
            $app->flash('save_ok', 'delete');
            ORM::get_db()->commit();
        }
        else {
            $app->flash('save_error', 'delete');
            ORM::get_db()->rollback();
        }
        
        $app->redirect($app->urlFor('tree', array('id' => $category['id'])));
    }
    
    if (isset($_POST['new']) && isset($_FILES['document']) && isset($_FILES['document']['name'][0]) && is_uploaded_file($_FILES['document']['tmp_name'][0])) {
        
        $newRevision = getMaxRevisionNrByDelivery($id) + 1;
        
        // añadir nueva revisión en una transacción
        ORM::get_db()->beginTransaction();
        
        $hash = sha1_file($_FILES['document']['tmp_name'][0]);
        $filesize = filesize($_FILES['document']['tmp_name'][0]);
        $documentDestination = createDocumentFolder($preferences['upload.folder'], $hash);
        $filename = $_FILES['document']['name'][0];
        
        $documentData = getDocumentDataByHash($hash);
        $newData = (false == $documentData);
        
        $revision = createRevision($id, $user['id'], $filename, $documentDestination, $hash, $filesize, $newRevision);

        $ok = ($revision !== false);
        
        if ($ok && $newData) {
            $ok = move_uploaded_file($_FILES['document']['tmp_name'][0], $preferences['upload.folder'] . $documentDestination);
        }
        
        if ($ok) {
            $delivery->set('current_revision_id', $revision['id']);
            $delivery->save();
            $app->flash('save_ok', 'ok');
            ORM::get_db()->commit();
        }
        else {
            if ($newData) {
                @unlink($documentDestination);
            }
            $app->flash('save_error', 'error');
            ORM::get_db()->rollback();
        }
        
        $app->redirect($app->urlFor('tree', array('id' => $category['id'])));
    }
    
    $breadcrumb = array(
        array('display_name' => 'Árbol', 'target' => $app->urlFor('tree')),
        array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree')),
        array('display_name' => $category['display_name'], 'target' => $app->urlFor('tree', array('id' => $category['id']))),
        array('display_name' => 'Modificar entrega')
    );

    $app->render('manage_delivery.html.twig', array(
        'navigation' => $breadcrumb, 'search' => false, 'sidebar' => $sidebar,
        'url' => $app->request()->getPathInfo(),
        'category' => $category,
        'folder' => $folder,
        'delivery' => $delivery,
        'revisions' => $revisions,
        'uploaders' => $uploaders,
        'upload_profiles' => $uploadProfiles,
        'manager_profiles' => $managerProfiles,
        'user_profiles' => $userProfiles,
        'upload_as' => $uploadAs,
        'data' => $data));
    
})->name('modify')->via('GET', 'POST');

function getDeliveryUploadersById($deliveryId) {
    return parseArray(ORM::for_table('person')->
        select('person.*')->
        distinct()->
        inner_join('revision', array('revision.uploader_person_id', '=', 'person.id'))->
        inner_join('delivery', array('delivery.id', '=', 'revision.delivery_id'))->
        where('delivery.id', $deliveryId)->
        find_array());
}

function getDeliveryById($deliveryId) {
    $data = ORM::for_table('delivery')->
            find_one($deliveryId);

    return $data;
}

function getRevisionsObjectByDelivery($deliveryId) {
    return ORM::for_table('revision')->
            select('revision.*')->
            select('document.download_filename')->
            where('revision.delivery_id', $deliveryId)->
            inner_join('document', array('document.id', '=', 'revision.original_document_id'))->
            order_by_desc('upload_date')->
            find_many();
}

function deleteDocumentById($docId, $preferences) {
    // comprobar si existen otros documentos con la misma información
    $document = ORM::for_table('document')->find_one($docId);
    if (!$document) {
        return false;
    }
    
    if (ORM::for_table('document')->where('document_data_id', $document['document_data_id'])->count() == 1) {
        // solamente hay un documento con esta información... hay que borrarlo
        $document_data = ORM::for_table('document_data')->find_one($document['document_data_id']);
        
        // borrar físicamente del sistema de archivos si existe
        if (strlen($document_data['download_path'])>0) {
            @unlink($preferences['upload.folder'] . $document_data['download_path']);
        }
        $ok = $document->delete();
        $ok = $ok && $document_data->delete();
        return $ok;
    }
    else {
        $ok = $document->delete();
        return $ok;
    }
}

function getMaxRevisionNrByDelivery($delId) {
    return ORM::for_table('revision')->
            where('delivery_id', $delId)->
            max('revision_nr');
}