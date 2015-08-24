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

$app->map('/actividad/:pid/:aid/:id', function ($pid, $aid, $id) use ($app, $user, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    // obtener evento
    // TODO: Comprobar que el evento es válido
    $event = getActivityEvent($id, $aid, $user)->as_array();

    if (!$event) {
        $app->redirect($app->urlFor('frontpage'));
    }

    // marcar evento como no completado
    if ($event['is_manual'] && ($event['completed_date'] != null) && isset($_POST['unmark'])) {
        deleteCompletedEvent($id, $user['id']);
        $app->redirect($app->urlFor('activities', array('id' => $pid)));
    }

    // marcar evento como completado
    if ($event['is_manual'] && ($event['completed_date'] == null) && isset($_POST['mark'])) {
        addCompletedEvent($id, $user['id']);
        $app->redirect($app->urlFor('activities', array('id' => $pid)));
    }

    // obtener carpeta
    $folder = getFolder($organization['id'], $event['folder_id']);

    // obtener entregas asociadas
    $deliveries = getDeliveriesFromEvent($id);

    // obtener perfiles
    $profiles = parseArray(getUserProfiles($user['id'], $organization['id'], false));
    $userProfiles = getUserProfiles($user['id'], $organization['id'], true);
    $userProfilesList = array();
    foreach($userProfiles as $prof) {
        $userProfilesList[$prof['id']] = $prof['id'];
        $userProfilesList[$prof['profile_group_id']] = $prof['profile_group_id'];
    }
   
    $isMine = isset($profiles[$pid]);
    foreach ($profiles as $p) {
        $isMine = $isMine || ($p['profile_group_id'] == $pid);
    }

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => 'Gestionar actividades', 'target' => $app->urlFor('manageallevents')),
        array('display_name' => $event['activity_display_name'], 'target' => $app->urlFor('activities', array('id' => $pid))),
        array('display_name' => $event['display_name'])
    );

    if ($folder && isset($folder['id'])) {
        $profileGender = array();
        $data = getParsedFolderById($organization['id'], $folder['id'], $userProfilesList, $profileGender, $user['id']);
        $folderProfiles = getProfilesByFolderId($folder['id']);
        $folders = getFoldersById($folder['id']);
        $persons = getFolderPersonsByFolderId($folder['id']);
    }
    else {
        $profileGender = array();
        $data = array();
        $folderProfiles = array();
        $folders = array();
        $persons = array();
    }
    $app->flash('last_url', $app->request()->getPathInfo());

    // generar página
    $app->render('event.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true,
        'url' => $app->request()->getPathInfo(),
        'pid' => $pid,
        'aid' => $aid,
        'user' => $user,
        'profiles' => $profiles,
        'profileGender' => $profileGender,
        'folder' => $folder,
        'folders' => $folders,
        'folderProfiles' => $folderProfiles,
        'persons' => $persons,
        'data' => $data,
        'isMine' => $isMine,
        'deliveries' => $deliveries,
        'backurl' => array('return' => 1, 'data1' => $pid, 'data2' => $aid, 'data3' => $id),
        'event' => $event));
})->name('event')->via('GET', 'POST');

$app->map('/actividad/:id(/:actid)', function ($id, $actid = null) use ($app, $user, $organization) {
    if (!$user || !$user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }

    // obtener evento
    // TODO: Comprobar que el evento es válido
    if ($id != 0) {
        $event = getEventByIdObject($organization['id'], $id);
    }
    else {
        $event = array(
            'is_visible' => 1,
            'is_manual' => 0,
            'is_automatic' => 0,
            'force_period' => 0,
            'grace_period' => 0
        );
    }

    if (!$event) {
        $app->redirect($app->urlFor('frontpage'));
    }

    if (isset($_POST['saveevent'])) {
        ORM::get_db()->beginTransaction();

        if ($id == 0) {
            $local = ORM::for_table('event')->create();
        }
        else {
            $local = $event;
        }
        $local->set('organization_id', $organization['id']);
        $local->set('display_name', $_POST['displayname']);
        $local->set('description', strlen($_POST['description'])>0 ? $_POST['description'] : $_POST['displayname']);
        $local->set('is_visible', $_POST['visible']);
        $local->set('is_manual', $_POST['manual']);
        $local->set('is_automatic', $_POST['automatic']);
        $local->set('force_period', $_POST['forceperiod']);
        $local->set('grace_period', $_POST['graceperiod']);
        $local->set('is_automatic', $_POST['automatic']);
        $folder_change = ($local['folder_id'] != $_POST['folder']);
        if ($_POST['folder']) {
            if ($local['folder_id'] && $folder_change) {
                checkItemUpdateStatusByFolder($local['folder_id']);
            }
            $local->set('folder_id', $_POST['folder']);

        }
        $local->set('from_week', $_POST['fromweek']+4*$_POST['frommonth']);
        $local->set('to_week', $_POST['toweek']+4*$_POST['tomonth']);

        if ($local->save()) {
            $id = $local['id'];
            $ok = setEventProfiles($id, $_POST['profiles']);
            if (isset($_POST['categories'])) {
                $ok = $ok && setEventActivities($id, $_POST['categories']);
            }
            if (isset($_POST['deliveries'])) {
                $ok = $ok && setEventDeliveries($id, $_POST['deliveries']);
            }
            if ($ok) {
                if ($_POST['folder'] && $folder_change) {
                    checkItemUpdateStatusByFolder($local['folder_id']);
                }
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

    // obtener carpeta
    $folders = getParsedFolderTree($organization['id']);

    // obtener todas las categorías de eventos
    $categories = getActivities($organization['id']);

    // obtener las activas en este evento
    if ($id == 0) {
        $selectedCategories = ($actid != 0) ? array($actid => array('id' => $actid)) : array();
    }
    else {
        $selectedCategories = getEventActivitiesId($id);
    }

    // obtener todos los perfiles
    $allProfiles = getProfilesByOrganization($organization['id'], true, true);

    // obtener los perfiles asociados a este evento
    $profiles = ($id == 0) ? array() : getEventProfilesId($id);

    // obtener todas las entregas de la organización (!!!)
    $allDeliveries = getParsedDeliveriesByOrganization($organization['id']);

    // obtener las entregas asociadas
    $deliveries = ($id == 0) ? array() : parseArray(getDeliveriesFromEvent($id));

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => 'Gestionar actividades', 'target' => $app->urlFor('manageallevents')),
        array('display_name' => ($id == 0) ? 'Nueva actividad' : $event['display_name'])
    );

    // generar página
    $app->render('manage_event.html.twig', array(
        'navigation' => $breadcrumb,
        'select2' => true,
        'url' => $app->request()->getPathInfo(),
        'folders' => $folders,
        'profiles' => $profiles,
        'allProfiles' => $allProfiles,
        'deliveries' => $deliveries,
        'allDeliveries' => $allDeliveries,
        'new' => ($id == 0),
        'categories' => $categories,
        'selectedCategories' => $selectedCategories,
        'event' => $event));
})->name('manageevent')->via('GET', 'POST');

$app->map('/elemento/:id/:profileid/(:actid)', function ($id, $profileid, $actid = null) use ($app, $user, $organization) {
    if (!$user && $user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }

    $event = getEventByIdObject($organization['id'], $id);
    $uploadProfiles = parseArray(getPermissionProfiles($event['folder_id'], 1));

    if (isset($_POST['changeprofile'])) {
        $app->redirect($app->urlFor('manageitem', array('id' => $id, 'profileid' => $_POST['profile'], 'actid' => $actid )));
    }

    if (isset($_POST['up']) || isset($_POST['down'])) {
        if (isset($_POST['up'])) {
            $item1 = getItemById($organization['id'], $_POST['up']);
            $item2 = getPreviousItem($organization['id'], $_POST['up'], $profileid);
        }
        else {
            $item1 = getItemById($organization['id'], $_POST['down']);
            $item2 = getNextItem($organization['id'], $_POST['down'], $profileid);
        }

        if (!$item1 || !$item2) {
            $app->redirect($app->urlFor('login'));
        }

        $order_nr = $item1['order_nr'];
        $item1->set('order_nr', $item2['order_nr'])->save();
        $item2->set('order_nr', $order_nr)->save();
    }

    if (isset($_POST['new'])) {
        $lines = explode("\n", $_POST['newelements']);
        $ok = true;
        ORM::get_db()->beginTransaction();
        foreach($lines as $line) {
            $line = str_replace('\r', '', $line);
            if ($line) {
                $item = explode("*", trim($line));
                $ok = $ok && createEventItem($id, $_POST['profile'], $item[0], isset($item[1]) ? $item[1] : $item[0]);
            }
        }
        if ($ok) {
            if ($event['folder_id']) {
                checkItemUpdateStatusByFolder($event['folder_id']);
            }
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
        if (deleteEventItems($id, $_POST['item'])) {
            if ($event['folder_id']) {
                checkItemUpdateStatusByFolder($event['folder_id']);
            }
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

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => $event['display_name'], 'target' => $app->urlFor('manageevent', array('id' => $id))),
        array('display_name' => 'Gestionar entregas')
    );

    $app->flashKeep();

    $app->render('manage_item.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true,
        'select2' => true,
        'url' => $app->request()->getPathInfo(),
        'uploaders' => $uploadAs,
        'items' => $items,
        'profileid' => $profileid,
        'id' => $id,
        'event' => $event,
        'actid' => $actid,
        'back_url' => $app->urlFor('manageevent', array('id' => $id)),
        'folder' => $folder));
})->name('manageitem')->via('GET', 'POST');

$app->map('/elemento/:id', function ($id) use ($app, $user, $organization) {
    if (!$user && $user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }

    $event = getEventByIdObject($organization['id'], $id);
    $uploadProfiles = parseArray(getPermissionProfiles($event['folder_id'], 1));

    if (isset($_POST['up']) || isset($_POST['down'])) {
        if (isset($_POST['up'])) {
            $split = explode("!", $_POST['up']);
            $item1 = getItemById($organization['id'], $split[0]);
            $item2 = getPreviousItem($organization['id'], $split[0], $split[1]);
        }
        else {
            $split = explode("!", $_POST['down']);
            $item1 = getItemById($organization['id'], $split[0]);
            $item2 = getNextItem($organization['id'], $split[0], $split[1]);
        }

        if (!$item1 || !$item2) {
            $app->redirect($app->urlFor('login'));
        }

        $order_nr = $item1['order_nr'];
        $item1->set('order_nr', $item2['order_nr'])->save();
        $item2->set('order_nr', $order_nr)->save();
    }

    if (isset($_POST['delete'])) {
        ORM::get_db()->beginTransaction();
        if (deleteEventItems($id, $_POST['item'])) {
            if ($event['folder_id']) {
                checkItemUpdateStatusByFolder($event['folder_id']);
            }
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

    if (isset($_POST['new'])) {
        $lines = explode("\n", $_POST['newelements']);
        $ok = true;
        ORM::get_db()->beginTransaction();
        foreach($lines as $line) {
            $line = trim($line);
            $line = str_replace('\r', '', $line);
            if ($line) {
                $item = explode("*", trim($line));
                foreach($_POST['profilenew'] as $profile) {
                    if (isset($uploadAs[$profile])) {
                        $ok = $ok && createEventItem($id, $profile, $item[0], isset($item[1]) ? $item[1] : $item[0]);
                    }
                }
            }
        }
        if ($ok) {
            if ($event['folder_id']) {
                checkItemUpdateStatusByFolder($event['folder_id']);
            }
            $app->flash('save_ok', 'ok');
            ORM::get_db()->commit();
        }
        else {
            $app->flash('save_error', 'error');
            ORM::get_db()->rollBack();
        }
        $app->redirect($app->request()->getPathInfo());
    }

    if (isset($_POST['import']) && isset($_POST['event']) && $_POST['event']) {
        $ok = true;
        ORM::get_db()->beginTransaction();

        $origin = getEventDeliveryItems($organization['id'], $_POST['event']);

        foreach($origin as $it) {
            if (isset($uploadAs[$it['profile_id']])) {
                $ok = $ok && createEventItem($id, $it['profile_id'], $it['display_name'], $it['document_name']);
            }
        }

        if ($ok) {
            if ($event['folder_id']) {
                checkItemUpdateStatusByFolder($event['folder_id']);
            }
            $app->flash('save_ok', 'ok');
            ORM::get_db()->commit();
        }
        else {
            $app->flash('save_error', 'error');
            ORM::get_db()->rollBack();
        }
        $app->redirect($app->request()->getPathInfo());
    }

    if (isset($_POST['replace']) && isset($_POST['replace_this']) && $_POST['replace_this']) {
        $items = getEventDeliveryItems($organization['id'], $id);

        ORM::get_db()->beginTransaction();
        $ok = true;

        foreach($items as $it) {
            $it->set('display_name', str_replace($_POST['replace_this'], $_POST['replace_with'], $it['display_name']));
            $it->set('document_name', str_replace($_POST['replace_this'], $_POST['replace_with'], $it['document_name']));
            $ok = $ok && $it->save();
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

    $folder = getFolder($organization['id'], $event['folder_id']);

    $profiles1 = parseArrayMix(getEventDeliveryItems($organization['id'], $id), 'profile_id');

    $profiles = array();
    
    // damos una vuelta a la lista por si hay perfiles sin elementos
    // hay que ponerlos en orden, de ahí la chapuza
    foreach($uploadAs as $prof) {
        if (!isset($profiles1[$prof['id']])) {
            $profiles[$prof['id']] = array();
        }
        else {
            $profiles[$prof['id']] = $profiles1[$prof['id']];
        }
    }

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => $event['display_name'], 'target' => $app->urlFor('manageevent', array('id' => $id))),
        array('display_name' => 'Gestionar entregas')
    );

    $events = getAllEventsGroupedByActivity($organization['id']);
    $activities = getAllActivities($organization['id']);

    $app->render('manage_all_item.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true,
        'select2' => true,
        'url' => $app->request()->getPathInfo(),
        'uploaders' => $uploadAs,
        'profiles' => $profiles,
        'id' => $id,
        'event' => $event,
        'events' => $events,
        'activities' => $activities,
        'back_url' => $app->urlFor('manageevent', array('id' => $id)),
        'folder' => $folder));
})->name('manageallitems')->via('GET', 'POST');

$app->map('/elemento/modificar/:id/:all', function ($id, $all) use ($app, $user, $organization) {
    if (!$user && $user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }

    $item = getItemById($organization['id'], $id);
    if ($item === false) {
        $app->redirect($app->urlFor('login'));
    }
    $event = getEventByIdObject($organization['id'], $item['event_id']);
    if ($event === false) {
        $app->redirect($app->urlFor('login'));
    }

    $uploadProfiles = parseArray(getPermissionProfiles($event['folder_id'], 1));

    $uploadAs = array();
    $profileid = $item['profile_id'];
    $profile = getProfileById($organization['id'], $profileid);
    foreach ($uploadProfiles as $newitem) {
        if (null == $newitem['display_name']) {
            $data = parseArray(getSubprofiles($newitem['id']));
            if (count($data)>1) {
                foreach($data as $subItem) {
                    if (null != $subItem['display_name']) {
                        $uploadAs[$subItem['id']] = $subItem;
                    }
                }
            }
            else {
                $uploadAs[$newitem['id']] = $newitem;
            }
        }
        else {
            $uploadAs[$newitem['id']] = $newitem;
        }
    }

    if ($all) {
        $backUrl = $app->urlFor('manageallitems', array('id' => $event['id']));
    }
    else {
        $backUrl = $app->urlFor('manageitem', array('id' => $event['id'], 'profileid' => $item['profile_id']));
    }

    if (isset($_POST['save']) && isset($uploadAs[$_POST['profile']])) {
        $item->set('profile_id', $_POST['profile']);
        $displayName = trim($_POST['displayname']);
        $item->set('display_name', $displayName);
        $documentName = trim($_POST['documentname']);
        $item->set('document_name', $documentName ? $documentName : $displayName);

        $ok = $item->save();

        if ($ok) {
            $app->flash('save_ok', 'ok');
            $app->redirect($backUrl);
        }
        else {
            $app->flash('save_error', 'error');
        }
    }

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => $event['display_name'], 'target' => $app->urlFor('manageevent', array('id' => $event['id']))),
        array('display_name' => $profile['display_name_neutral'] . ' ' . $profile['display_name'], 'target' => $backUrl),
        array('display_name' => $item['display_name'])
    );

    $app->render('manage_delivery_item.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true,
        'select2' => true,
        'url' => $app->request()->getPathInfo(),
        'upload_as' => $uploadAs,
        'id' => $id,
        'event' => $event,
        'item' => $item,
        'all' => $all,
        'back_url' => $backUrl
    ));
})->name('managedeliveryitem')->via('GET', 'POST');

$app->map('/elemento/nuevo/:id/:profileid', function ($id, $profileid) use ($app, $user, $organization) {
    if (!$user && $user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }

    $event = getEventByIdObject($organization['id'], $id);
    if ($event === false) {
        $app->redirect($app->urlFor('login'));
    }

    $uploadProfiles = parseArray(getPermissionProfiles($event['folder_id'], 1));

    $uploadAs = array();
    $profile = getProfileById($organization['id'], $profileid);
    foreach ($uploadProfiles as $newitem) {
        if (null == $newitem['display_name']) {
            $data = parseArray(getSubprofiles($newitem['id']));
            if (count($data)>1) {
                foreach($data as $subItem) {
                    if (null != $subItem['display_name']) {
                        $uploadAs[$subItem['id']] = $subItem;
                    }
                }
            }
            else {
                $uploadAs[$newitem['id']] = $newitem;
            }
        }
        else {
            $uploadAs[$newitem['id']] = $newitem;
        }
    }
    $item = ORM::for_table('event_profile_delivery_item')->create();
    $item->set('event_id', $id);
    $item->set('profile_id', $profileid);
    $item->set('display_name', '');
    $item->set('document_name', '');

    if (!isset($uploadAs[$profileid])) {
        $app->redirect($app->urlFor('login'));
    }

    $backUrl = $app->urlFor('manageitem', array('id' => $event['id'], 'profileid' => $item['profile_id']));

    if (isset($_POST['save'])) {
        $displayName = trim($_POST['displayname']);
        $item->set('display_name', $displayName);
        $documentName = trim($_POST['documentname']);
        $item->set('document_name', $documentName ? $documentName : $displayName);
        $item->set('order_nr', getLastItemOrderNr($event['id'], $profileid) + 1000);
        $ok = $item->save();

        if ($ok) {
            $app->flash('save_ok', 'ok');
            $app->redirect($backUrl);
        }
        else {
            $app->flash('save_error', 'error');
        }
    }

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => $event['display_name'], 'target' => $app->urlFor('manageevent', array('id' => $event['id']))),
        array('display_name' => $profile['display_name_neutral'] . ' ' . $profile['display_name'], 'target' => $backUrl),
        array('display_name' => 'Nueva entrada')
    );

    $app->render('manage_delivery_item.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true,
        'select2' => true,
        'url' => $app->request()->getPathInfo(),
        'upload_as' => $uploadAs,
        'id' => $id,
        'event' => $event,
        'item' => $item,
        'new' => true,
        'back_url' => $backUrl
    ));
})->name('newdeliveryitem')->via('GET', 'POST');

function getActivityEvent($eventId, $activityId, $user) {
    return ORM::for_table('event')->
            select('event.*')->
            select('activity.id', 'activity_id')->
            select('activity.display_name', 'activity_display_name')->
            select('completed_event.completed_date')->
            inner_join('activity_event', array('activity_event.event_id','=','event.id'))->
            inner_join('activity', array('activity.id','=','activity_event.activity_id'))->
            left_outer_join('completed_event', 'completed_event.event_id = event.id AND completed_event.person_id = ' . $user['id'])->
            where('activity_event.activity_id', $activityId)->
            find_one($eventId);
}

function getEventByIdObject($orgId, $eventId) {
    return ORM::for_table('event')->
            select('event.*')->
            inner_join('activity_event', array('activity_event.event_id','=','event.id'))->
            inner_join('activity', array('activity.id','=','activity_event.activity_id'))->
            where('activity.organization_id', $orgId)->
            find_one($eventId);
}

function getDeliveriesFromEvent($eventId) {

    $data = ORM::for_table('delivery')->
            select('delivery.*')->
            select('event_delivery.description', 'event_delivery_description')->
            select('revision.upload_date')->
            select('revision.uploader_person_id')->
            select('person.display_name', 'person_display_name')->
            inner_join('event_delivery', array('event_delivery.delivery_id','=','delivery.id'))->
            inner_join('revision', array('delivery.current_revision_id', '=', 'revision.id'))->
            inner_join('person', array('person.id', '=', 'revision.uploader_person_id'))->
            order_by_asc('delivery.display_name')->
            where('event_delivery.event_id', $eventId)->
            find_array();

    return (!$data) ? array() : $data;
}

function getParsedFolderById($orgId, $folderId, $profiles, &$profileGender, $userId, $filter = true) {

    $folders = getFoldersByOrganization($orgId, $filter)->
                where('id', $folderId)->
                find_array();

    return getDeliveriesFromFolders($folders, $profiles, $profileGender, $userId);
}

function getFoldersById($folderId) {
    return parseArray(getFolders()->
            where('folder.id', $folderId)->
            find_array());
}

function getFolderPersonsByFolderId($folderId) {
    return parseArray(getFolderPersons()->
            where('folder.id', $folderId)->
            find_array());
}

function getProfilesByFolderId($folderId) {
    return parseArray(getProfiles()->
            where('folder.id', $folderId)->
            find_array());
}

function deleteCompletedEvent($eventId, $personId) {
    return ORM::for_table('completed_event')->
            where('event_id', $eventId)->
            where('person_id', $personId)->
            delete_many();
}

function addCompletedEvent($eventId, $personId) {
    $completedEvent = ORM::for_table('completed_event')->create();
    $completedEvent->set('event_id', $eventId);
    $completedEvent->set('person_id', $personId);
    $completedEvent->set('completed_date', date('c'));

    return $completedEvent->save();
}

function removeCompletedEvent($eventId, $personId) {
    return ORM::for_table('completed_event')->
            where('event_id', $eventId)->
            where('person_id', $personId)->
            delete_many();
}

function getActivities($orgId) {
    $data = ORM::for_table('activity')->
            where('organization_id', $orgId)->
            order_by_asc('display_name')->
            find_array();

    return $data;
}

function getEventActivitiesId($eventId) {
    return parseArray(ORM::for_table('activity')->
            select('activity.id')->
            inner_join('activity_event', array('activity_event.activity_id', '=', 'activity.id'))->
            where('activity_event.event_id', $eventId)->
            find_array());
}

function getAllFoldersByOrganization($orgId, $filter = true) {
    $folders = ORM::for_table('folder')->
            select('folder.*')->
            select('category.display_name', 'category_display_name')->
            inner_join('category', array('category.id', '=', 'folder.category_id'))->
            where('category.organization_id', $orgId)->
            order_by_asc('category.category_left')->
            order_by_asc('category.id')->
            order_by_asc('order_nr');

    if ($filter) {
        $folders = $folders->where('is_visible', 1);
    }
    return $folders->find_array();
}

function getParsedFolderTree($orgId) {
    $data = getAllFoldersByOrganization($orgId);
    $return = array();
    $currentData = array();
    $currentCategory = null;
    $currentCategoryDisplayName = null;
    $first = true;

    foreach($data as $folder) {
        if ($first) {
            $currentCategory = $folder['category_id'];
            $currentCategoryDisplayName = $folder['category_display_name'];
            $first = false;
        }
        if ($currentCategory != $folder['category_id']) {
            $return[] = array(
                'info' => array('id' => $currentCategory, 'display_name' => $currentCategoryDisplayName),
                'data' => $currentData
            );
            $currentCategory = $folder['category_id'];
            $currentCategoryDisplayName = $folder['category_display_name'];
            $currentData = array();
        }
        $currentData[] = $folder;
    }
    if (count($currentData) > 0) {
        $return[] = array(
            'info' => array('id' => $currentCategory, 'display_name' => $currentCategoryDisplayName),
            'data' => $currentData
        );
    }
    return $return;
}

function getEventProfilesId($eventId) {
    $data = ORM::for_table('event_profile')->
            select('event_profile.profile_id', 'id')->
            where('event_profile.event_id', $eventId)->
            find_array();

    return !$data ? false : parseArray($data);
}

function getParsedDeliveriesByOrganization($orgId) {
    $data = ORM::for_table('delivery')->
            select('delivery.*')->
            select('folder.display_name', 'folder_display_name')->
            select('category.display_name', 'category_display_name')->
            inner_join('folder_delivery', array('folder_delivery.delivery_id', '=', 'delivery.id'))->
            inner_join('folder', array('folder.id', '=', 'folder_delivery.folder_id'))->
            inner_join('category', array('category.id', '=', 'folder.category_id'))->
            where('category.organization_id', $orgId)->
            where_null('folder_delivery.snapshot_id')->
            order_by_asc('category.category_left')->
            order_by_asc('folder.order_nr')->
            order_by_asc('folder_delivery.order_nr')->
            find_array();

    $return = array();
    $currentData = array();
    $currentCategory = null;
    $first = true;

    foreach($data as $delivery) {
        $category = $delivery['category_display_name'] . ': ' . $delivery['folder_display_name'];
        if ($first) {
            $currentCategory = $category;
            $first = false;
        }
        if ($currentCategory != $category) {
            $return[] = array(
                'info' => array('display_name' => $currentCategory),
                'data' => $currentData
            );
            $currentCategory = $category;
            $currentData = array();
        }
        $currentData[] = $delivery;
    }
    if (count($currentData) > 0) {
        $return[] = array(
            'info' => array('display_name' => $currentCategory),
            'data' => $currentData
        );
    }
    return $return;
}

function setEventProfiles($eventId, $profiles) {
    ORM::for_table('event_profile')->
            where('event_id', $eventId)->
            delete_many();

    $ok = true;
    foreach ($profiles as $profile) {
        $insert = ORM::for_table('event_profile')->create();
        $insert->set('event_id', $eventId);
        $insert->set('profile_id', $profile);
        $ok = $ok && $insert->save();
    }

    return $ok;
}

function setEventActivities($eventId, $activities) {
    ORM::for_table('activity_event')->
            where('event_id', $eventId)->
            delete_many();

    $ok = true;
    foreach ($activities as $activity) {
        $insert = ORM::for_table('activity_event')->create();
        $insert->set('event_id', $eventId);
        $insert->set('activity_id', $activity);
        $ok = $ok && $insert->save();
    }

    return $ok;
}

function setEventDeliveries($eventId, $deliveries) {
    ORM::for_table('event_delivery')->
            where('event_id', $eventId)->
            delete_many();

    $ok = true;
    foreach ($deliveries as $delivery) {
        $insert = ORM::for_table('event_delivery')->create();
        $insert->set('event_id', $eventId);
        $insert->set('delivery_id', $delivery);
        $ok = $ok && $insert->save();
    }

    return $ok;
}

function getItemById($orgId, $id) {
    $data = ORM::for_table('event_profile_delivery_item')->
            select('event_profile_delivery_item.*')->
            select('event.display_name', 'event_display_name')->
            select('event.from_week')->
            select('event.to_week')->
            select('event.force_period')->
            select('event.grace_period')->
            inner_join('event', array('event.id', '=', 'event_profile_delivery_item.event_id'))->
            where('event.organization_id', $orgId)->
            find_one($id);

    return $data;
}

function getNextItem($orgId, $itemId, $profileId) {
    $item = getItemById($orgId, $itemId);

    if ($item === false) {
        return false;
    }

    return ORM::for_table('event_profile_delivery_item')->
        where('event_id', $item['event_id'])->
        where('profile_id',  $profileId)->
        where_gt('order_nr', $item['order_nr'])->
        order_by_asc('order_nr')->
        find_one();
}

function getPreviousItem($orgId, $itemId, $profileId) {
    $item = getItemById($orgId, $itemId);

    if ($item === false) {
        return false;
    }

    return ORM::for_table('event_profile_delivery_item')->
        where('event_id', $item['event_id'])->
        where('profile_id',  $profileId)->
        where_lt('order_nr', $item['order_nr'])->
        order_by_desc('order_nr')->
        find_one();
}

function getLastItemOrderNr($eventId, $profileId) {
    $data = ORM::for_table('event_profile_delivery_item')->
        where('event_id', $eventId)->
        where('profile_id',  $profileId)->
        order_by_desc('order_nr')->
        find_one();

    if (false === $data) {
        return 0;
    }
    return $data['order_nr'];
}

function getAllActivities($orgId) {
    return parseArray(ORM::for_table('activity')->
        where('organization_id', $orgId)->
        order_by_asc('display_name')->
        find_many());
}

function getAllEventsGroupedByActivity($orgId) {
    $events = ORM::for_table('activity_event')->
        inner_join('event', array('event.id', '=', 'event_id'))->
        where('event.organization_id', $orgId)->
        order_by_asc('event.display_name')->
        find_many();

    $data = parseArrayMix($events, 'activity_id');

    return $data;
}

function deleteEvent($orgId, $id) {
    return ORM::for_table('event')->
        where('organization_id', $orgId)->
        where('event.id', $id)->
        delete_many();
}

