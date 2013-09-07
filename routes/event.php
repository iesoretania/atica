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

$app->map('/evento/:pid/:aid/:id', function ($pid, $aid, $id) use ($app, $user, $organization) {
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
    if ($event['is_manual'] && ($event['completed_date'] != NULL) && isset($_POST['unmark'])) {
        deleteCompletedEvent($id, $user['id']);
        $event = getActivityEvent($id, $aid, $user)->as_array();
    }
    
    // marcar evento como completado
    if ($event['is_manual'] && ($event['completed_date'] == NULL) && isset($_POST['mark'])) {
        addCompletedEvent($id, $user['id']);
        $event = getActivityEvent($id, $aid, $user)->as_array();
    }
    
    // obtener carpeta
    $folder = getFolder($event['folder_id']);
    
    // obtener entregas asociadas
    $deliveries = getDeliveriesFromEvent($id);
    
    
    // obtener perfiles
    $profiles = getUserProfiles($user['id'], $organization['id'], false);
    
    // barra lateral de perfiles
    $profile_bar = array(
        array('caption' => 'Mis actividades', 'icon' => 'calendar'),
    );
    if (count($profiles, COUNT_NORMAL)>1) {
       $profile_bar[] = array('caption' => 'Ver todas', 'active' => ($id == NULL), 'target' => $app->urlFor('activities'));
    }
    $current = NULL;
    $detail = '';
    $profile_ids = array();
    $profile_group_ids = array();
    foreach ($profiles as $profile) {
        $gender = array ($profile['display_name_neutral'], $profile['display_name_male'], $profile['display_name_female']);
        $caption = $gender[$user['gender']] . " " . $profile['display_name'];
        if ($profile['id'] == $pid) {
            $current = $profile;
            $detail = $caption;
            $active = true;
        }
        else {
            $active = false;
        }
        array_push($profile_ids, $profile['id']);
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
            if ($profile['id'] == $pid) {
                $current = $profile;
                $detail = $captionOther;
                $activeOther = true;
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
    if (NULL == $current) {
        $app->redirect($app->urlFor('activities'));
    }

    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        //array('display_name' => $detail, 'target' => $app->urlFor('activities', array('id' => $pid))),
        array('display_name' => $event['activity_display_name'], 'target' => $app->urlFor('activities', array('id' => $pid))),
        array('display_name' => $event['display_name'])
    );
    
    if ($folder['id']) {
        $profileGender = array();
        $data = getParsedFolderById($folder['id'], $profileGender);
        $folderProfiles = getProfilesByFolderId($folder['id']);
        $folders = getFoldersById($folder['id'], $user);
        $persons = getFolderPersonsByFolderId($folder['id']);
    }
    else {
        $profileGender = array();
        $data = array();
        $folderProfiles = array();
        $folders = array();
        $persons = array();        
    }
    
    // generar página
    $app->render('event.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true,
        'url' => $app->request()->getPathInfo(),
        'sidebar' => $sidebar,
        'user' => $user,
        'profiles' => $profiles,
        'profileGender' => $profileGender,        
        'folder' => $folder,
        'folders' => $folders,
        'folderProfiles' => $folderProfiles,
        'persons' => $persons,
        'data' => $data,
        'deliveries' => $deliveries,
        'event' => $event));
})->name('event')->via('GET', 'POST');

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
            order_by_asc('revision.upload_date')->
            where('event_delivery.event_id', $eventId)->
            find_many();
    
    return $data;
}

function getParsedFolderById($folderId, &$profileGender) {

    $data = getParsedFolders()->
            where('folder.id', $folderId)->
            find_many();

    return parseFolders($data, $profileGender);
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