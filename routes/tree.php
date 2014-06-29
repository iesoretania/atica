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

$app->get('/arbol(/:id)', function ($id = null) use ($app, $user, $organization) {
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

    $topbar = getTree($organization['id'], $app, $id, $category, $parent);

    // obtener lista de perfiles para controlar la visibilidad de la carpeta
    $userProfiles = getUserProfiles($user['id'], $organization['id'], true);
    $userProfilesList = array();
    foreach($userProfiles as $prof) {
        $userProfilesList[] = $prof['id'];
        $userProfilesList[] = $prof['profile_group_id'];
    }
    
    if (null !== $id) {
        $data = getParsedDeliveriesByCategory($organization['id'], $id, $profileGender, $user['is_admin'] ? null : $userProfilesList);
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
    $app->flash('last_url', $app->request()->getPathInfo());

    $app->render('tree.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'topbar' => $topbar,
        'category' => $category,
        'data' => $data,
        'persons' => $persons,
        'folderProfiles' => $folderProfiles,
        'profileGender' => $profileGender,
        'backurl' => array('return' => 0, 'data1' => $id, 'data2' => 0, 'data3' => 0),
        'folders' => $folders));
})->name('tree');

$app->get('/descargar/:kind/:cid/:id/(:p1/)(:p2/)', function ($kind, $cid, $id, $p1 = null, $p2 = null) use ($app, $user, $preferences, $organization) {

    $catId = null;
    $groupId = null;
    $eventId = null;
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
            $catId = $cid;
            break;
        case 2:
            $groupId = $cid;
            break;
        case 3:
            $eventId = $cid;
            break;
        default:
            $app->redirect($app->urlFor('frontpage'));
    }

    $delivery = getDelivery($id, $user['id']);
    if (!$delivery) {
       doRegisterAction($app, $user, $organization, 'tree', 1, 'download_error', 'no delivery',
               null, null, null, $eventId, $groupId, null, null, $id,
               $delivery['current_delivery_id'], null, null);
       $app->flash('home_error', 'no_delivery');
       $app->redirect($app->request->getReferrer());
    }
    $file = $preferences['upload.folder'] . $delivery['download_path'];

    if (!file_exists($file)) {
       doRegisterAction($app, $user, $organization, 'tree', 1, 'download_error', 'no document',
               null, null, null, $eventId, $groupId, null, null, $id,
               $delivery['current_delivery_id'], null, null);
       $app->flash('home_error', 'no_document');
       $app->redirect($app->request->getReferrer());
    }

    $res = $app->response();
    $res['Content-Description'] = 'File Transfer';
    $res['Content-Type'] = ($delivery['mime'] == null) ?
            'application/octet-stream' : $delivery['mime'];
    $res['Content-Disposition'] ='attachment; filename=' . trim(basename($delivery['download_filename']));
    $res['Content-Transfer-Encoding'] = 'binary';
    $res['Expires'] = '0';
    $res['Cache-Control'] = 'must-revalidate';
    $res['Pragma'] = 'public';
    $res['Content-Length'] = $delivery['download_filesize'];
    doRegisterAction($app, $user, $organization, 'tree', 0, 'download',
            $delivery['download_filename'],
            null, null, null, $eventId, $groupId, null, null, $id,
            $delivery['current_delivery_id'], null, null);

    readfile($file);
})->name('download');

$app->map('/carpeta/:id(/:catid)', function ($id, $catid = null) use ($app, $user, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    $category = array();
    $parent = array();

    $data = getCategories($organization['id']);
    $allProfiles = getProfilesByOrganization($organization['id'], true, true);
    $uploadProfiles = parseArray(getPermissionProfiles($id, 1));
    $managerProfiles = parseArray(getPermissionProfiles($id, 0));
    $restrictedProfiles = parseArray(getPermissionProfiles($id, 2));

    if ($user['is_admin'] && isset($_POST['savefolder'])) {
        ORM::get_db()->beginTransaction();

        if ($id == 0) {
            $order = getMaxFolderOrder($_POST['category']) + 1000;
            $local = ORM::for_table('folder')->create();
            $local->set('order_nr', $order);
        }
        else {
            $local = getFolderObjectById($organization['id'], $id);
        }
        $local->set('category_id', $_POST['category']);
        $local->set('display_name', $_POST['displayname']);
        $local->set('description', strlen($_POST['description'])>0 ? $_POST['description'] : null);
        $local->set('is_visible', $_POST['visible']);
        $local->set('is_divided', $_POST['divided']);
        $local->set('is_restricted', $_POST['restrictedaccess']);
        $local->set('show_revision_nr', $_POST['revisionnr']);
        $local->set('auto_clean', $_POST['autoclean']);

        if ($local->save()) {
            $id = $local['id'];
            $ok = true;
            if (isset($_POST['managers'])) {
                $ok = $ok && setFolderProfiles($id, 0, $_POST['managers']);
            }
            else {
                $ok = $ok && setFolderProfiles($id, 0, array());
            }
            if (isset($_POST['uploaders'])) {
                $ok = $ok && setFolderProfiles($id, 1, $_POST['uploaders']);
            }
            else {
                $ok = $ok && setFolderProfiles($id, 1, array());
            }
            if (isset($_POST['restricted'])) {
                $ok = $ok && setFolderProfiles($id, 2, $_POST['restricted']);
            }
            else {
                $ok = $ok && setFolderProfiles($id, 2, array());
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
        $url = isset($_SESSION['slim.flash']['last_url']) ?
                $_SESSION['slim.flash']['last_url'] :
                $app->request()->getPathInfo();

        $app->redirect($url);
    }

    $folder = getFolder($organization['id'], $id);

    if (!$folder) {
        // valores por defecto de las carpetas nuevas
        $folder = array();
        $folder['is_visible'] = 1;
        $folder['category_id'] = $catid;
    }

    if (null == $catid) {
        $catid = $folder['category_id'];
    }

    $query = getCategoryObjectById($organization['id'], $catid);
    if (!$query) {
        // error, no existe la categoría en la organización, posible
        // intento de ataque
        $app->redirect($app->urlFor('frontpage'));
    }
    $topbar = getTree($organization['id'], $app, $catid, $category, $parent);

    $breadcrumb = array(
        array('display_name' => 'Árbol', 'target' => $app->urlFor('tree')),
        array('display_name' => $parent['display_name'], 'target' => $app->urlFor('tree', array('id' => $catid))),
        array('display_name' => $category['display_name'], 'target' => $app->urlFor('tree', array('id' => $catid))),
        array('display_name' => 'Gestionar carpeta')
    );

    $app->render('manage_folder.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'topbar' => $topbar,
        'select2' => true,
        'category' => $category,
        'url' => $app->request()->getPathInfo(),
        'data' => $data,
        'new' => ($id == 0),
        'allProfiles' => $allProfiles,
        'uploaders' => $uploadProfiles,
        'managers' => $managerProfiles,
        'restricted' => $restrictedProfiles,
        'folder' => $folder));
})->name('managefolder')->via('GET', 'POST');

$app->get('/opcarpeta/:id/:oper(/:data)', function ($id, $oper, $data = null) use ($app, $user, $organization) {
    if (!$user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }

    $folder = getFolderObjectById($organization['id'], $id);

    switch ($oper) {
        case 'swap':
            ORM::get_db()->beginTransaction();
            $folder2 = getFolderObjectById($organization['id'], $data);
            $tmpOrderNr = $folder2['order_nr'];
            $folder2->set('order_nr', $folder['order_nr']);
            $folder->set('order_nr', $tmpOrderNr);
            $folder->save();
            $folder2->save();
            ORM::get_db()->commit();
            break;
        case 'swapnext':
            ORM::get_db()->beginTransaction();
            $folder2 = getNextFolderObject($folder);
            $tmpOrderNr = $folder2['order_nr'];
            $folder2->set('order_nr', $folder['order_nr']);
            $folder->set('order_nr', $tmpOrderNr);
            $folder->save();
            $folder2->save();
            ORM::get_db()->commit();
            break;
    }

    $app->redirect($app->urlFor('tree', array('id' => $folder['category_id'])));
})->name('folderoperation');

$app->map('/elemento/:id/:profileid/(:actid)', function ($id, $profileid, $actid = null) use ($app, $user, $organization) {
    if (!$user && $user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }

    $event = getEventObject($organization['id'], $id);
    $uploadProfiles = parseArray(getPermissionProfiles($event['folder_id'], 1));

    if (isset($_POST['changeprofile'])) {
        $app->redirect($app->urlFor('manageitem', array('id' => $id, 'profileid' => $_POST['profile'], 'actid' => $actid )));
    }

    if (isset($_POST['new'])) {
        $lines = explode("\n", $_POST['newelements']);
        $ok = true;
        ORM::get_db()->beginTransaction();
        foreach($lines as $line) {
            $item = explode("*", trim($line));
            $ok = $ok && createEventItem($id, $_POST['profile'], $item[0], isset($item[1]) ? $item[1] : null);
        }
        if ($ok) {
            $app->flash('save_ok', 'ok');
            ORM::get_db()->commit();
        }
        else {
            $app->flash('save_error', 'error');
            ORM::get_db()->rollBack();
        }
        $app->redirect($app->request()->getPathInfo());
    }

    if (isset($_POST['delete'])) {
        ORM::get_db()->beginTransaction();
        if (deleteEventItems($id, $_POST['profile'], $_POST['item'])) {
            ORM::get_db()->commit();
            $app->flash('save_ok', 'ok');
        }
        else {
            ORM::get_db()->rollBack();
            $app->flash('save_error', 'error');
        }
        $app->redirect($app->request()->getPathInfo());
    }

    if (isset($_POST['order'])) {
        ORM::get_db()->beginTransaction();
        if (orderEventItems($id, $_POST['profile'])) {
            ORM::get_db()->commit();
            $app->flash('save_ok', 'ok');
        }
        else {
            ORM::get_db()->rollBack();
            $app->flash('save_error', 'error');
        }
        $app->redirect($app->request()->getPathInfo());
    }

    $uploadAs = array();

    foreach ($uploadProfiles as $item) {
        if (null == $item['display_name']) {
            $data = parseArray(getSubprofiles($item['id']));
            if (count($data)>1) {
                foreach($data as $subItem) {
                    if (null != $subItem['display_name']) {
                        $uploadAs[$subItem['id']] = $subItem;
                        if ($profileid == 0) {
                            $profileid = $subItem['id'];
                        }
                    }
                }
            }
            else {
                $uploadAs[$item['id']] = $item;
                if ($profileid == 0) {
                    $profileid = $item['id'];
                }
            }
        }
        else {
            $uploadAs[$item['id']] = $item;
            if ($profileid == 0) {
                $profileid = $item['id'];
            }
        }
    }
    $folder = getFolder($organization['id'], $event['folder_id']);

    $items = parseArray(getEventProfileDeliveryItems($profileid, $id));

    // barra lateral
    $topbar = array(
        array(
            array('caption' => 'Gestión de actividades', 'icon' => 'calendar'),
            array('caption' => 'Gestionar actividad', 'active' => true, 'target' => $app->request()->getPathInfo())
        )
    );

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => $event['display_name'], 'target' => $app->urlFor('event', array('id' => $id, 'actid' => $actid))),
        array('display_name' => 'Gestionar entregas')
    );

    $app->flashKeep();

    $app->render('manage_item.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'topbar' => $topbar,
        'select2' => true,
        'url' => $app->request()->getPathInfo(),
        'uploaders' => $uploadAs,
        'items' => $items,
        'profileid' => $profileid,
        'id' => $id,
        'event' => $event,
        'actid' => $actid,
        'folder' => $folder));
})->name('manageitem')->via('GET', 'POST');

$app->get('/historial/:id(/:return/:data1(/:data2(/:data3)))', function ($id, $return=0, $data1=null, $data2=null, $data3=null)
        use ($app, $user, $organization, $config) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    $folder = getFolderObjectById($organization['id'], $id);
    if (!$folder) {
        $app->redirect($app->urlFor('login'));
    }

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

    $isAllowed = false;
    if (!$isManager && $folder['is_restricted']) {
        foreach ($restrictedProfiles as $restrict) {
            if (isset($userProfiles[$restrict['id']])) {
                $isAllowed = true;
                break;
            }
        }
    }
    else {
        $isAllowed = true;
    }

    if (!$isAllowed) {
        $app->redirect($app->urlFor('login'));
    }

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
            $event = getEventObject($organization['id'], $data3);
            $activityevent = getActivityEvent($data3, $data2, $user);
            $profile = getProfileById($data1, $organization['id']);
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

    $snapshots = parseArray(getSnapshotsFromFolder($folder['id']), 'snapshot_id');

    $profileGender = array();

    foreach($snapshots as $key => $snapshot) {
        $snapshots[$key]['data'] = getDeliveriesFromFolders(array($folder), $profileGender, null, $key);
    }

    $folderProfiles = getProfilesByFolderId($folder['id']);
    $folders = getFoldersById($folder['id']);
    $persons = getFolderPersonsByFolderId($folder['id']);

    $app->render('folder_snapshots.html.twig', array(
        'navigation' => $breadcrumb,
        'search' => false,
        'url' => $app->request()->getPathInfo(),
        'back_url' => array('return' => $return, 'data1' => $data1, 'data2' => $data2, 'data3' => $data3),
        'last_url' => $lastUrl,
        'snapshots' => $snapshots,
        'folderProfiles' => $folderProfiles,
        'folders' => $folders,
        'persons' => $persons,
        'profileGender' => $profileGender,
        'restricted_profiles' => $restrictedProfiles,
        'upload_profiles' => $uploadProfiles,
        'manager_profiles' => $managerProfiles,
        'user_profiles' => $userProfiles,
        'all_profiles' => $allProfiles,
        'folder' => $folder));

})->name('foldersnapshots');

function getTree($orgId, $app, $id, &$matchedCategory, &$parentCategory) {
    $return = array();
    $currentData = array();
    $currentCategory = null;
    $match = false;

    $data = ORM::for_table('category')->
            order_by_asc('category_left')->
            where('organization_id', $orgId)->
            where_gt('category_level', 0)->
            find_array();

    foreach ($data as $category) {
        if ($category['category_level'] == 1) {
            if ($currentCategory != null) {
                $return[] = array(
                    'caption' => $currentCategory['display_name'],
                    'active' => $match,
                    'target' => '#',
                    'subitems' => $currentData
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
    if ($currentCategory != null) {
        $return[] = array(
            'caption' => $currentCategory['display_name'],
            'active' => $match,
            'target' => '#',
            'subitems' => $currentData
        );
        if ($match) {
            $parentCategory = $currentCategory;
        }
    }

    return array($return);
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
            select_expr('sum(person_profile.profile_id IS NOT NULL AND folder_permission.permission=0)','manage_permission')->
            select_expr('sum(person_profile.profile_id IS NOT NULL AND folder_permission.permission=1)','upload_permission')->
            left_outer_join('folder_permission', array('folder_permission.folder_id', '=', 'folder.id'))->
            left_outer_join('profile', 'folder_permission.profile_id IN (profile.profile_group_id, profile.id)')->
            left_outer_join('person_profile', array('person_profile.profile_id', '=', 'profile.id'))->
            where('folder.is_visible', 1)->
            group_by('folder.id');
}

function getFoldersAndStatsByCategoryAndUser($categoryId, $user) {
    $data = parseArray(getFolders()->
            where('person_profile.person_id', $user['id'])->
            where('folder.category_id', $categoryId)->
            where('folder.is_visible', 1)->
            find_array());
    return $data;
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

function getFolderObjectById($orgId, $folderId) {
return ORM::for_table('folder')->
            select('folder.*')->
            inner_join('category', array('category.id', '=', 'folder.category_id'))->
            where('category.organization_id', $orgId)->
            where('folder.id', $folderId)->
            find_one();
}

function getFolder($orgId, $folderId) {
    if ((null == $folderId) || (0 == $folderId)) {
        return false;
    }
    return getFolderObjectById($orgId, $folderId)->as_array();
}

function getCategories($orgId) {
    $data = ORM::for_table('category')->
            select('category.*')->
            where('organization_id', $orgId)->
            order_by_asc('category_left')->
            find_array();

    $return = array();

    $current = null;
    $currentData = array();

    foreach($data as $element) {
        if ($element['category_level'] == 1) {
            if ($current != null) {
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
    if ($current != null) {
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

function getCategoryParentsById($catId) {
    $data = array();
    $category = ORM::for_table('category')->find_one($catId);
    while ($category['category_level']>1) {
        $category = ORM::for_table('category')->
                where('category_level', $category['category_level']-1)->
                where_lt('category_left', $category['category_left'])->
                order_by_Desc('category_left')->
                find_one();
        array_unshift($data, $category);
    }
    return $data;
}

function getMaxFolderOrder($catId) {
    return ORM::for_table('folder')->
            where('category_id', $catId)->
            max('order_nr');
}

function getFoldersByOrganization($orgId, $filter = true) {
    $folders = ORM::for_table('folder')->
            select('folder.*')->
            inner_join('category', array('category.id', '=', 'folder.category_id'))->
            where('category.organization_id', $orgId)->
            order_by_asc('order_nr');

    if ($filter) {
        $folders = $folders->where('is_visible', 1);
    }

    return $folders;
}

function getSnapshotsFromFolder($folderId) {
    return ORM::for_table('folder_delivery')->
            distinct()->
            select('snapshot_id')->
            select('snapshot.display_name')->
            select('snapshot.order_nr')->
            inner_join('snapshot', array('snapshot.id', '=', 'folder_delivery.snapshot_id'))->
            where_not_null('snapshot_id')->
            where('folder_id', $folderId)->
            where('snapshot.visible', 1)->
            order_by_desc('snapshot.order_nr')->
            find_many();
}

function getDeliveriesFromFolders($folders, &$profileGender, $userProfiles, $snapshot = null) {

    $return = array();
    foreach($folders as $folder) {
        // comprobar si la carpeta es de acceso restringido
        $skip = false;
        if ($folder['is_restricted'] && ($userProfiles !== null)) {
            $visible = ORM::for_table('folder_permission')->
                    where('folder_id', $folder['id'])->
                    where('permission', 2)->
                    where_in('profile_id', $userProfiles)->
                    count();
            
            $skip = ($visible == 0);
        }
        if ($skip == false) {    
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
                    order_by_asc('folder_delivery.snapshot_id')->
                    order_by_asc('delivery.profile_id')->
                    order_by_asc('order_nr');

            if (is_null($snapshot)) {
                $deliveries = $deliveries->where_null('folder_delivery.snapshot_id');
            }
            elseif ($snapshot == 0) {
                $deliveries = $deliveries->where_not_null('folder_delivery.snapshot_id');
            }
            else {
                $deliveries = $deliveries->where('folder_delivery.snapshot_id', $snapshot);
            }

            $deliveries = $deliveries->find_array();

            $return[] = array(
                'id' => $folder['id'],
                'data' => $deliveries
            );
            foreach($deliveries as $delivery) {
                if (isset($profileGender[$delivery['profile_id']])) {
                    if ($profileGender[$delivery['profile_id']] != $delivery['gender']) {
                        $profileGender[$delivery['profile_id']] = 0;
                    }
                }
            }
        }
    }   
    return $return;
}

function getParsedDeliveriesByCategory($orgId, $catId, &$profileGender, $userProfiles, $filter = true) {

    $folders = getFoldersByOrganization($orgId, $filter)->
                where('category_id', $catId)->
                find_array();

    return getDeliveriesFromFolders($folders, $profileGender, $userProfiles);
}

function getNextFolderObject($folder) {
    return ORM::for_table('folder')->
            where('category_id', $folder['category_id'])->
            where_gt('order_nr', $folder['order_nr'])->
            find_one();
}

function createEventItem($id, $profileId, $displayName, $documentName) {
    $order = ORM::for_table('event_profile_delivery_item')->
            where('profile_id', $profileId)->
            where('event_id', $id)->max('order_nr');

    $order = ($order) ? ($order + 1000) : 0;

    $item = ORM::for_table('event_profile_delivery_item')->create()->
            set('profile_id', $profileId)->
            set('event_id', $id)->
            set('display_name', $displayName)->
            set('document_name', $documentName);
    return $item->save();
}

function deleteEventItems($id, $profileId, $items) {
    $ok = true;
    foreach($items as $item) {
        $ok = $ok && ORM::for_table('event_profile_delivery_item')->
            where('id', $item)->
            where('profile_id', $profileId)->
            where('event_id', $id)->delete_many();
    }
    return $ok;
}

function orderEventItems($id, $profileId) {
    $ok = true;
    $order = 0;

    $items = ORM::for_table('event_profile_delivery_item')->
            where('profile_id', $profileId)->
            where('event_id', $id)->
            order_by_asc('display_name')->
            find_many();

    foreach($items as $item) {
        $ok = $ok && $item->set('order_nr', $order)->save();
        $order += 1000;
    }
    return $ok;
}

function getEventProfileDeliveryItems($profileId, $eventId) {
    $data = ORM::for_table('event_profile_delivery_item')->
            where('event_id', $eventId)->
            where('profile_id', $profileId)->
            where('is_visible', 1)->
            order_by_asc('order_nr')->
            find_many();
    return $data;
}