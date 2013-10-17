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

    // barra lateral de perfiles
    $profile_bar = array(
        array('caption' => 'Mis actividades', 'icon' => 'calendar'),
    );
    if (count($profiles, COUNT_NORMAL)>1) {
       $profile_bar[] = array('caption' => 'Ver todas', 'active' => ($id == null), 'target' => $app->urlFor('activities'));
    }
    $current = null;
    $detail = '';
    $isMine = true;
    $profile_ids = array();
    $profile_group_ids = array();
    foreach ($profiles as $profile) {
        $gender = array ($profile['display_name_neutral'], $profile['display_name_male'], $profile['display_name_female']);
        $caption = $gender[$user['gender']] . " " . $profile['display_name'];
        if (($profile['id'] == $pid) || ($profile['profile_group_id'] == $pid)) {
            $current = $profile;
            $detail = $caption;
            $active = true;
            $isMine = true;
        }
        else {
            $active = false;
        }
        array_push($profile_ids, $profile['profile_group_id']);
        if (!in_array($profile['profile_group_id'], $profile_group_ids)) {
            $profile_group_ids[] = $profile['profile_group_id'];
        }
        array_push($profile_bar, array('caption' => $caption,
            'active' => $active, 'target' => $app->urlFor('activities', array('id' => $profile['id']))));
    }

    $sidebar = array();

    array_push($sidebar, $profile_bar);

    // obtener otros perfiles
    $otherProfiles = getUserOtherProfiles($user['id'], $organization['id'], $profile_group_ids);
    if (count($otherProfiles, COUNT_NORMAL) > 0) {
       $other_profile_bar = array(
           array('caption' => 'Otras actividades', 'icon' => 'calendar-empty')
        );

        foreach ($otherProfiles as $profile) {
            $captionOther = $profile['display_name_neutral'] . " " . $profile['display_name'];
            if (($profile['id'] == $pid) || ($profile['profile_group_id'] == $pid)) {
                $current = $profile;
                $detail = $captionOther;
                $activeOther = true;
                $isMine = false;
            }
            else {
                $activeOther = false;
            }
            array_push($other_profile_bar, array('caption' => $captionOther,
                'active' => $activeOther, 'target' => $app->urlFor('activities', array('id' => $profile['id']))));
        }
        array_push($sidebar, $other_profile_bar);
    }
    
    // si hay un perfil como parámetro que no está asociado al usuario, redirigir
    if (null == $current) {
        $app->redirect($app->urlFor('activities'));
    }

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        //array('display_name' => $detail, 'target' => $app->urlFor('activities', array('id' => $pid))),
        array('display_name' => $event['activity_display_name'], 'target' => $app->urlFor('activities', array('id' => $pid))),
        array('display_name' => $event['display_name'])
    );
    
    if ($folder && isset($folder['id'])) {
        $profileGender = array();
        $data = getParsedFolderById($organization['id'], $folder['id'], $profileGender);
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
        'sidebar' => $sidebar,
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
        'event' => $event));
})->name('event')->via('GET', 'POST');

$app->map('/actividad/:id', function ($id) use ($app, $user, $organization) {
    if (!$user || !$user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }
    
    // obtener evento
    // TODO: Comprobar que el evento es válido
    if ($id != 0) {
        $event = getEventObject($organization['id'], $id);
    }
    else {
        $event = array(
            'is_visible' => 1
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
        $local->set('description', strlen($_POST['description'])>0 ? $_POST['description'] : null);
        $local->set('is_visible', $_POST['visible']);
        $local->set('is_manual', $_POST['manual']);
        $local->set('is_automatic', $_POST['automatic']);
        if ($_POST['folder']) {
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
    $selectedCategories = ($id == 0) ? array() : getEventActivitiesId($id);
    
    // obtener todos los perfiles
    $allProfiles = getProfilesByOrganization($organization['id'], true, true);
    
    // obtener los perfiles asociados a este evento
    $profiles = ($id == 0) ? array() : getEventProfilesId($id);
    
    // obtener todas las entregas de la organización (!!!)
    $allDeliveries = getParsedDeliveriesByOrganization($organization['id']);
    
    // obtener las entregas asociadas
    $deliveries = ($id == 0) ? array() : parseArray(getDeliveriesFromEvent($id));
    
    // barra lateral
    $sidebar = array(
        array(
            array('caption' => 'Gestión de actividades', 'icon' => 'calendar'),
            array('caption' => 'Gestionar actividad', 'active' => true, 'target' => $app->request()->getPathInfo())
        )
    );
    
    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => ($id == 0) ? 'Nueva actividad' : $event['display_name'])
    );
    
    // generar página
    $app->render('manage_event.html.twig', array(
        'navigation' => $breadcrumb,
        'select2' => true,
        'sidebar' => $sidebar,
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

function getEventObject($orgId, $eventId) {
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

function getParsedFolderById($orgId, $folderId, &$profileGender, $filter = true) {

    $folders = getFoldersByOrganization($orgId, $filter)->
                where('id', $folderId)->
                find_array();

    return getDeliveriesFromFolders($folders, $profileGender);
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
    
    if (!$data) {
        return array();
    }
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
            order_by_asc('category.category_left')->
            order_by_asc('folder.order_nr')->
            order_by_asc('folder_delivery.order_nr')->
            find_array();
    
    $return = array();
    $currentData = array();
    $currentCategory = null;
    $currentCategoryDisplayName = null;
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
    $query = ORM::for_table('event_profile')->
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
    $query = ORM::for_table('activity_event')->
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
    $query = ORM::for_table('event_delivery')->
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
