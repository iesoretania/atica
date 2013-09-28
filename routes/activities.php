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

$app->get('/actividades(/:id)', function ($id = NULL) use ($app, $user, $config, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    // indica si el perfil pertence al usuario
    $isMine = true;
    
    // obtener perfiles
    $profiles = parseArray(getUserProfiles($user['id'], $organization['id'], false));

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
        if ($profile['id'] == $id) {
            $current = $profile;
            $detail = $caption;
            $active = true;
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
            if ($profile['id'] == $id) {
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
    if ((NULL != $id) && (NULL == $current)) {
        $app->redirect($app->urlFor('activities'));
    }

    // barra superior de navegación
    if (NULL != $id) {
        $breadcrumb = array(
            array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
            array('display_name' => $detail)
        );
    }
    else {
        $breadcrumb = array(
            array('display_name' => 'Actividades')
        );
    }

    if ($id) {
        // si es un perfil del usuario, extraer el perfil contenedor
        // si no, dejarlo como estaba
        $idParam = isset($profiles[$id]) ? $profiles[$id]['profile_group_id'] : $id;
        $profile_ids = array( $idParam );
    }

    // obtener actividades
    $events = getEventsForProfiles($profile_ids, $user, $config['calendar.base_week']);

    // formatear los eventos en grupos de perfiles de arrays
    $parsedEvents = parseEvents($events,
            'profile_id', array('profile_display_name', 'profile_group_display_name', 'profile_id'),
            'activity_id', array('activity_display_name', 'activity_description', 'activity_id'));

    $now = getdate();
    $currentWeek = ($now['mon']-1)*4 + floor(($now['mday']-1)/7);

    // generar página
    $app->render('activities.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true,
        'detail' => $detail,
        'base' => $config['calendar.base_week'],
        'current' => $currentWeek,
        'sidebar' => $sidebar,
        'isMine' => $isMine,
        'events' => $parsedEvents));
})->name('activities');

$app->map('/grupoactividad/:id', function ($id) use ($app, $user, $config, $organization) {
    
    if (!$user || !$user['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }
    
    if (0 != $id) {
        $activity = getActivityObject($organization['id'], $id);
    
        if (!$activity) {
            $app->redirect($app->urlFor('frontpage'));
        }
    }
    else {
        $activity = array();
    }

    
    if (isset($_POST['saveactivity'])) {
        ORM::get_db()->beginTransaction();
        
        if ($id == 0) {
            $local = ORM::for_table('activity')->create();
        }
        else {
            $local = $activity;
        }
        $local->set('organization_id', $organization['id']);
        $local->set('display_name', $_POST['displayname']);
        $local->set('description', strlen($_POST['description'])>0 ? $_POST['description'] : NULL);
        
        if ($local->save()) {      
            $app->flash('save_ok', 'ok');
                ORM::get_db()->commit();
        }
        else {
            $app->flash('save_error', 'error');
            ORM::get_db()->rollBack();
        }
        $app->redirect($app->request()->getPathInfo());
    }
    
    // barra lateral
    $sidebar = array(
        array(
            array('caption' => 'Gestión de actividades', 'icon' => 'calendar'),
            array('caption' => 'Gestionar agrupación', 'active' => true, 'target' => $app->request()->getPathInfo())
        )
    );
    
    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => ($id == 0) ? 'Nueva agrupación de actividades' : $activity['display_name'])
    );
    
    // generar página
    $app->render('manage_activity.html.twig', array(
        'navigation' => $breadcrumb,
        'sidebar' => $sidebar,
        'url' => $app->request()->getPathInfo(),
        'new' => ($id == 0),
        'activity' => ($id == 0) ? array() : $activity));
    
})->name('manageactivity')->via('GET', 'POST');

function getUserProfiles($user_id, $org_id, $extended) {
    // $extended indica si queremos recibir también los perfiles generales
    $data = ORM::for_table('person_profile')->
            select('profile.*')->
            select('profile_group.display_name_neutral')->
            select('profile_group.display_name_male')->
            select('profile_group.display_name_female')->
            inner_join('profile', array('person_profile.profile_id','=','profile.id'))->
            inner_join('profile_group', array('profile_group.id','=','profile.profile_group_id'))->
            where('person_id', $user_id)->
            where('profile_group.organization_id', $org_id)->
            order_by_asc('profile_group.display_name_neutral')->
            order_by_asc('profile.order_nr')->find_array();

    if ($extended) {
        $data = array_merge($data,
            ORM::for_table('person_profile')->
                select('profile.profile_group_id')->
                select_expr('NULL', 'display_name')->
                select('profile.order_nr')->
                select('profile_group.id', 'id')->
                select('profile_group.display_name_neutral')->
                select('profile_group.display_name_male')->
                select('profile_group.display_name_female')->
                inner_join('profile', array('person_profile.profile_id','=','profile.id'))->
                inner_join('profile_group', array('profile_group.id','=','profile.profile_group_id'))->
                where('person_id', $user_id)->
                where_not_null('profile.display_name')->
                where('profile_group.organization_id', $org_id)->
                order_by_asc('profile_group.display_name_neutral')->
                order_by_asc('profile.order_nr')->find_array()
        );
    }

    return $data;
}

function getUserOtherProfiles($user_id, $org_id, $current) {
    $data = ORM::for_table('profile_group')->
            select('profile_group.*')->
            where_not_in('profile_group.id', $current)->
            where('profile_group.organization_id', $org_id)->
            order_by_asc('profile_group.display_name_neutral')->find_many();

    return $data;
}

function getEventsForProfiles($profile_ids, $user, $base = 33) {
    $genderChoice = array ('display_name_neutral', 'display_name_male', 'display_name_female');
    $data = ORM::for_table('event')->
            select('event.*')->
            select('event_profile.*')->
            select('activity.id', 'activity_id')->
            select('activity.display_name', 'activity_display_name')->
            select('activity.description', 'activity_description')->
            select('profile.display_name', 'profile_display_name')->
            select('profile_group.' . $genderChoice[$user['gender']], 'profile_group_display_name')->
            select('completed_event.completed_date')->
            select_expr('(event.from_week+48-' . $base . ') % 48', 'n_from_week')->
            select_expr('(event.to_week+48-' . $base . ') % 48', 'n_to_week')->
            inner_join('activity_event', array('activity_event.event_id', '=', 'event.id'))->
            inner_join('event_profile', array('event_profile.event_id', '=', 'event.id'))->
            inner_join('activity', array('activity.id', '=', 'activity_event.activity_id'))->
            inner_join('profile', array('profile.id', '=', 'event_profile.profile_id'))->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            left_outer_join('completed_event', 'completed_event.event_id = event.id AND completed_event.person_id = ' . $user['id'])->
            order_by_asc('profile_group.display_name_neutral')->
            order_by_asc('activity.id')->
            order_by_asc('n_from_week')->
            order_by_asc('n_to_week');

    if ($profile_ids) {
        $data = $data->where_in('profile_id', $profile_ids);
    }

    return $data->find_array();
}

function addDataInfo($data, $info = array(), $fields = array()) {
    $current = array();

    foreach ($info as $field) {
        $current[$field] = $fields[$field];
    }
    return array(
        'info' => $current,
        'data' => $data
    );
}

function parseEvents($events,
        $first_level = 'profile_id', $first_info = array(),
        $second_level = 'activity_id', $second_info = array()) {

    $return = array();
    $currentFirst = array();
    $currentSecond = array();

    $lastItem = NULL;

    $old = array(
        'first' => NULL,
        'second' => NULL
    );

    foreach ($events as $event) {

        if (($old['first'] != $event[$first_level]) ||
            ($old['second'] != $event[$second_level])) {

            if (!empty($currentSecond)) {
                $currentFirst[$old['second']] = addDataInfo($currentSecond, $second_info, $lastItem);
                $currentSecond = array();
            }
            $old['second'] = $event[$second_level];

            if ($old['first'] != $event[$first_level]) {
                if (!empty($currentFirst)) {
                    $return[$old['first']] = addDataInfo($currentFirst, $first_info, $lastItem);

                    $currentFirst = array();
                }
                $old['first'] = $event[$first_level];
            }
        }

        $currentSecond[] = $event;
        $lastItem = $event;
    }

    if (!empty($currentSecond)) {
        $currentFirst[$old['second']] = addDataInfo($currentSecond, $second_info, $lastItem);
    }

    if (!empty($currentFirst)) {
        $return[$old['first']] = addDataInfo($currentFirst, $first_info, $lastItem);
    }

    return $return;
}

function getActivityObject($orgId, $actId) {
    return ORM::for_table('activity')->
            where('organization_id', $orgId)->
            where('id', $actId)->
            find_one();
}