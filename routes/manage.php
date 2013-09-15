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

$app->get('/modificar/:folderid/:id', function ($folderId, $id) use ($app, $user, $config, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }
    $delivery = getDeliveryById($id);
    if (false == $delivery) {
        $app->redirect($app->urlFor('tree'));
    }
    $revisions = getRevisionsByDelivery($id);
    $uploaders = getDeliveryUploadersById($id);
    //echo "<pre>"; var_dump($uploaders); die();
    $data = array();
    $category = array();
    $parent = array();

    $folder = getFolder($folderId);
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
    
})->name('modify');

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

    if ($data) {
        $data = $data->as_array();
    }
    return $data;
}

function getRevisionsByDelivery($deliveryId) {
    return ORM::for_table('revision')->
            select('revision.*')->
            select('document.download_filename')->
            where('revision.delivery_id', $deliveryId)->
            inner_join('document', array('document.id', '=', 'revision.original_document_id'))->
            order_by_asc('upload_date')->
            find_array();
}