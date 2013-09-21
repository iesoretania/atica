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
    $folderProfiles = array();
    $profileGender = array();

    $sidebar = getTree($organization['id'], $app, $id, $category, $parent);

    if (NULL !== $id) {
        $data = getParsedDeliveriesByCategory($organization['id'], $id, $profileGender);//getParsedFoldersByCategory($id, $profileGender);
        // TODO: Optimizar leyendo todos los permisos de golpe para todas las
        // carpetas y colocándolos en un array
        $allFolders = getFoldersByCategory($id);
        $folders = getFoldersAndStatsByCategoryAndUser($id, $user) + $allFolders;
        $persons = getFolderPersonsByCategory($id);
        $folderProfiles = getProfilesByCategory($id);

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
        'folderProfiles' => $folderProfiles,
        'profileGender' => $profileGender,
        'folders' => $folders));
})->name('tree');

$app->get('/descargar/:kind/:cid/:id/(:p1/)(:p2/)', function ($kind, $cid, $id, $p1 = NULL, $p2 = NULL) use ($app, $user, $preferences) {

    // $kind =
    // 1 -> la descarga se produce desde una carpeta del árbol, $cid = category.id
    // 2 -> la descarga se produce desde un agrupamiento, $cid = grouping.id
    // 3 -> la descarga se produce desde un evento, $cid = event.id, $p1, $p2 pasan
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
        case 3:
            $errorUrl = $app->urlFor('event', array('id' => $cid, 'pid' => $p1, 'aid' => $p2));
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

$app->map('/carpeta/:id(/:catid)', function ($id, $catid = NULL) use ($app, $user, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    $category = array();
    $parent = array();

    $data = getCategories($organization['id']);
    $allProfiles = getProfilesByOrganization($organization['id']);
    $uploadProfiles = parseArray(getPermissionProfiles($id, 1));
    $managerProfiles = parseArray(getPermissionProfiles($id, 0));
    
    if ($user['is_admin'] && isset($_POST['savefolder'])) {
        ORM::get_db()->beginTransaction();
        
        if ($id == 0) {
            $order = getMaxFolderOrder($_POST['category']) + 1000;
            $local = ORM::for_table('folder')->create();
            $local->set('order_nr', $order);
        }
        else {
            $local = getFolderObjectById($id);
        }
        $local->set('category_id', $_POST['category']);
        $local->set('display_name', $_POST['displayname']);
        $local->set('description', strlen($_POST['description'])>0 ? $_POST['description'] : NULL);
        $local->set('is_visible', $_POST['visible']);
        $local->set('is_divided', $_POST['divided']);
        $local->set('show_revision_nr', $_POST['revisionnr']);
        $local->set('auto_clean', $_POST['autoclean']);
        
        if ($local->save()) {
            $id = $local['id'];
            $ok = true;
            if (isset($_POST['managers'])) {
                $ok = $ok && setFolderProfiles($id, 0, $_POST['managers']);
            }
            if (isset($_POST['uploaders'])) {
                $ok = $ok && setFolderProfiles($id, 1, $_POST['uploaders']);
            }
            if ($ok) {
                $app->flash('save_ok', 'ok');
                ORM::get_db()->commit();
            }
            else {
                $app->flash('save_error', 'error');
                ORM::get_db()->rollBack();
            }
        }
        else {
            $app->flash('save_error', 'error');
            ORM::get_db()->rollBack();
        }
        
        $app->redirect($app->request()->getPathInfo());
    }
    
    $folder = getFolder($id);
    
    if (!$folder) {
        // valores por defecto de las carpetas nuevas
        $folder = array();
        $folder['is_visible'] = 1;
        $folder['category_id'] = $catid;
    }
    
    if (NULL == $catid) {
        $catid = $folder['category_id'];
    }
    
    $query = getCategoryObjectById($organization['id'], $catid);
    if (!$query) {
        // error, no existe la categoría en la organización, posible
        // intento de ataque
        $app->redirect($app->urlFor('frontpage'));
    }    
    $sidebar = getTree($organization['id'], $app, $catid, $category, $parent);

    $breadcrumb = array(
        array('display_name' => 'Árbol', 'target' => $app->urlFor('tree')),
        array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree')),
        array('display_name' => $category['display_name']),
        array('display_name' => 'Gestionar carpeta')
    );
    
    $app->render('manage_folder.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'sidebar' => $sidebar,
        'category' => $category,
        'url' => $app->request()->getPathInfo(),
        'data' => $data,
        'new' => ($id == 0),
        'allProfiles' => $allProfiles,
        'uploaders' => $uploadProfiles,
        'managers' => $managerProfiles,
        'folder' => $folder));
})->name('managefolder')->via('GET', 'POST');

function getTree($orgId, $app, $id, &$matchedCategory, &$parentCategory) {
    $return = array();
    $currentData = array();
    $currentCategory = NULL;
    $match = false;

    $data = ORM::for_table('category')->
            order_by_asc('category_left')->
            where('organization_id', $orgId)->
            where_gt('category_level', 0)->
            find_array();

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

function getFolderPersons() {
    return ORM::for_table('person')->distinct()->
            select('person.*')->
            inner_join('revision', array('person.id', '=', 'revision.uploader_person_id'))->
            inner_join('delivery', array('delivery.current_revision_id', '=', 'revision.id'))->
            inner_join('folder_delivery', array('folder_delivery.delivery_id','=','delivery.id'))->
            inner_join('folder', array('folder.id','=','folder_delivery.folder_id'))->
            where('folder.is_visible', 1);
}

function getFolderPersonsByCategory($categoryId) {
    return parseArray(getFolderPersons()->
            where('folder.category_id', $categoryId)->
            find_array());
}

function getFoldersByCategory($categoryId) {
    return parseArray(ORM::for_table('folder')->
            where('folder.category_id', $categoryId)->
            where('folder.is_visible', 1)->
            find_array());
}

function getFolders() {
    return ORM::for_table('folder')->
            select('folder.*')->
            select_expr('sum(folder_permission.permission=0)','manage_permission')->
            select_expr('sum(folder_permission.permission=1)','upload_permission')->
            inner_join('folder_permission', array('folder_permission.folder_id', '=', 'folder.id'))->
            inner_join('profile', array('profile.id', '=', 'folder_permission.profile_id'))->
            inner_join('person_profile', array('person_profile.profile_id', '=', 'profile.id'))->
            where('folder.is_visible', 1)->
            group_by('folder.id');
}

function getFoldersAndStatsByCategoryAndUser($categoryId, $user) {
    return parseArray(getFolders()->
            where('person_profile.person_id', $user['id'])->
            where('folder.category_id', $categoryId)->
            where('folder.is_visible', 1)->
            find_array());
}

function getProfiles() {
    return ORM::for_table('profile')->distinct()->
            select('profile_group.*')->
            select('profile.*')->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            inner_join('delivery', array('delivery.profile_id', '=', 'profile.id'))->
            inner_join('folder_delivery', array('folder_delivery.delivery_id','=','delivery.id'))->
            inner_join('folder', array('folder.id','=','folder_delivery.folder_id'))->
            order_by_asc('profile_group_id')->
            order_by_asc('profile.id');
}

function getProfilesByCategory($category_id) {
    return parseArray(getProfiles()->
            where('folder.category_id', $category_id)->
            find_array());
}

function getFolderObjectById($folderId) {
return ORM::for_table('folder')->
            where('folder.id', $folderId)->
            find_one();
}

function getFolder($folderId) {
    if ((NULL == $folderId) || (0 == $folderId)) {
        return false;
    }
    return getFolderObjectById($folderId)->as_array();
}

function getCategories($orgId) {
    $data = ORM::for_table('category')->
            select('category.*')->
            where('organization_id', $orgId)->
            order_by_asc('category_left')->
            find_array();
    
    $return = array();
    
    $current = NULL;
    $currentData = array();
    
    foreach($data as $element) {
        if ($element['category_level'] == 1) {
            if ($current != NULL) {
                $return[] = array(
                    'info' => $current,
                    'data' => $currentData
                );
            }
            $current = $element;
            $currentData = array();
        }
        else {
            $currentData[] = $element;
        }
    }
    if ($current != NULL) {
        $return[] = array(
                    'info' => $current,
                    'data' => $currentData
        );
    }
    return $return;
}

function setFolderProfiles($folderId, $permission, $profiles) {
    $query = ORM::for_table('folder_permission')->
            where('folder_id', $folderId)->
            where('permission', $permission)->
            delete_many();
    
    $ok = true;
    foreach ($profiles as $profile) {
        $insert = ORM::for_table('folder_permission')->create();
        $insert->set('folder_id', $folderId);
        $insert->set('permission', $permission);
        $insert->set('profile_id', $profile);
        $ok = $ok && $insert->save();
    }
    
    return $ok;
}

function getCategoryObjectById($orgId, $catId) {
    return ORM::for_table('category')->
            where('organization_id', $orgId)->
            where('id', $catId)->
            find_one();
}

function getMaxFolderOrder($catId) {
    return ORM::for_table('folder')->
            where('category_id', $catId)->
            max('order_nr');
}

function getFoldersByOrganization($orgId, $filter = true) {
    $folders = ORM::for_table('folder')->
            select('folder.id')->
            inner_join('category', array('category.id', '=', 'folder.category_id'))->
            where('category.organization_id', $orgId)->
            order_by_asc('order_nr');
    
    if ($filter) {
        $folders = $folders->where('is_visible', 1);
    }
    
    return $folders;
}

function getDeliveriesFromFolders($folders, &$profileGender) {
    
    $return = array();
    foreach($folders as $folder) {
        $deliveries = ORM::for_table('delivery')->
                select('delivery.*')->
                select('folder_delivery.order_nr')->
                select('revision.upload_date')->
                select('revision.uploader_person_id')->
                select('revision.revision_nr')->
                select('person.gender')->
                inner_join('folder_delivery', array('folder_delivery.delivery_id', '=', 'delivery.id'))->
                inner_join('revision', array('delivery.current_revision_id', '=', 'revision.id'))->
                inner_join('person', array('person.id', '=', 'revision.uploader_person_id'))->
                where('folder_delivery.folder_id', $folder['id'])->
                order_by_asc('delivery.profile_id')->
                order_by_asc('order_nr');
        
        $return[] = array(
            'id' => $folder['id'],
            'data' => $deliveries->find_array()
        );
        foreach($deliveries as $delivery) {
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
    return $return;
}
function getParsedDeliveriesByCategory($orgId, $catId, &$profileGender, $filter = true) {
    
    $folders = getFoldersByOrganization($orgId, $filter)->
                where('category_id', $catId)->
                find_array();
    
    return getDeliveriesFromFolders($folders, $profileGender);
}