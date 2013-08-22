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
    $persons = array();
    $profiles = array();
    $profileGender = array();
    
    $sidebar = getTree($organization['id'], $app, $id, $category);
    
    if (NULL !== $id) {
        $data = getParsedFolders($id, $profileGender);
        $folders = getFolders($id);
        $persons = getFolderPersons($id);
        $profiles = getProfiles($id);
        
        $breadcrumb = array(
            array('display_name' => 'Árbol', 'target' => $app->urlFor('tree')),
            array('display_name' => $category['display_name'])
        );
    }
    else {
        $breadcrumb = array(
            array('display_name' => 'Árbol', 'target' => '#')
        );
    }
    //var_dump($data); die();
    $app->render('tree.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'sidebar' => $sidebar,
        'category' => $category,
        'data' => $data,
        'persons' => $persons,
        'profiles' => $profiles,
        'profileGender' => $profileGender,
        'folders' => $folders));
})->name('tree');

function getTree($org_id, $app, $id, &$matchedCategory) {
    $return = array();
    $currentData = array();
    $currentCategory = NULL;
    $match = false;

    $data = ORM::for_table('category')->
            order_by_asc('category_left')->
            where('organization_id', $org_id)->
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
    }

    return $return;
}

function getFolderPersons($category_id) {
    $data = ORM::for_table('person')->distinct()->
            select('person.*')->
            inner_join('revision', array('person.id', '=', 'revision.uploader_person_id'))->
            inner_join('delivery', array('delivery.current_revision_id', '=', 'revision.id'))->
            inner_join('folder_delivery', array('folder_delivery.delivery_id','=','delivery.id'))->
            inner_join('folder', array('folder.id','=','folder_delivery.folder_id'))->
            where('folder.category_id', $category_id)->
            find_array();
    
    $return = array();
    
    foreach($data as $item) {
        $return[$item['id']] = $item;
    }
    
    return $return;
}

function getFolders($category_id) {
    $data = ORM::for_table('folder')->
            where('folder.category_id', $category_id)->
            find_array();   
    
    $return = array();
    
    foreach($data as $item) {
        $return[$item['id']] = $item;
    }
    
    return $return;
}

function getProfiles($category_id) {
    $data = ORM::for_table('profile')->distinct()->
            select('profile_group.*')->
            select('profile.*')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            inner_join('delivery', array('delivery.profile_id', '=', 'profile.id'))->
            inner_join('folder_delivery', array('folder_delivery.delivery_id','=','delivery.id'))->
            inner_join('folder', array('folder.id','=','folder_delivery.folder_id'))->
            where('folder.category_id', $category_id)->
            order_by_asc('profile_group_id')->
            order_by_asc('profile.id')->
            find_array();   
    
    $return = array();
    
    foreach($data as $item) {
        $return[$item['id']] = $item;
    }
    
    return $return;
}

function getParsedFolders($category_id, &$profileGender) {

    $data = ORM::for_table('delivery')->
            select('delivery.*')->
            select('folder.id', 'folder_id')->
            select('folder.display_name', 'folder_display_name')->
            select('folder.category_id')->
            select('folder.order_nr')->
            select('revision.upload_date')->
            select('revision.uploader_person_id')->
            select('person.gender')->
            inner_join('folder_delivery', array('folder_delivery.delivery_id','=','delivery.id'))->
            inner_join('folder', array('folder.id', '=', 'folder_delivery.folder_id'))->
            inner_join('revision', array('delivery.current_revision_id', '=', 'revision.id'))->
            inner_join('person', array('person.id', '=', 'revision.uploader_person_id'))->
            order_by_asc('folder.order_nr')->
            order_by_asc('delivery.profile_id')->
            order_by_asc('revision.upload_date')->
            where('folder.category_id', $category_id)->
            find_many();
            
    $return = array();
    $currentData = array();
    $currentFolderId = NULL;
    $currentFolderDisplayName = NULL;

    foreach ($data as $delivery) {
        if ($delivery['folder_id'] !== $currentFolderId) {
            if ($currentData != NULL) {
                $return[] = array(
                    'caption' => $currentFolderDisplayName,
                    'data' => $currentData
                );
            }
            $currentData = array();
            $currentFolderId = $delivery['folder_id'];
            $currentFolderDisplayName = $delivery['folder_display_name'];
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
            'caption' => $currentFolderDisplayName,
            'data' => $currentData
        );
    }

    return $return;
}