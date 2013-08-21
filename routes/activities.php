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

$app->get('/actividades(/:id)', function ($id = NULL) use ($app, $user, $config) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }

    // obtener perfiles
    $profiles = ORM::for_table('person_profile')->
            inner_join('profile', array('person_profile.profile_id','=','profile.id'))->
            inner_join('profile_group', array('profile_group.id','=','profile.profile_group_id'))->
            where('person_id', $user['id'])->
            order_by_asc('profile_group.display_name_neutral')->find_many();

    // barra lateral de perfiles
    $profile_bar = array(
        array('caption' => 'Actividades', 'icon' => 'list'),
        array('caption' => 'Ver todas', 'active' => ($id == NULL), 'target' => $app->urlFor('activities'))
    );
    $current = NULL;
    $detail = '';
    $profile_ids = array();
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
        array_push($profile_ids, $profile['id']);
        array_push($profile_bar, array('caption' => $caption,
            'active' => $active, 'target' => $app->urlFor('activities', array('id' => $profile['id']))));
    }

    // si hay un perfil como parámetro que no está asociado al usuario, redirigir
    if ((NULL != $id) && (NULL == $current)) {
        $app->redirect($app->urlFor('activities'));
    }

    $sidebar = array();

    array_push($sidebar, $profile_bar);

    // barra superior de navegación
    if ($id != NULL) {
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
        $profile_ids = array( $id );
    }

    // obtener actividades
    $events = getEventsForProfiles($profile_ids, $user, $config['calendar.base_week']);

    // formatear los eventos en grupos de perfiles de arrays
    $parsedEvents = parseEvents($events,
            'profile_id', array('profile_display_name', 'profile_group_display_name'),
            'activity_id', array('activity_display_name', 'activity_description'));
    
    // generar página
    $app->render('activities.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'detail' => $detail, 'sidebar' => $sidebar, 'events' => $parsedEvents));
})->name('activities');

function getEventsForProfiles($profile_ids, $user, $base = 33) {
    $genderChoice = array ('display_name_neutral', 'display_name_male', 'display_name_female');
    $data = ORM::for_table('event')->
            select('event.*')->
            select('activity_profile.*')->
            select('activity.display_name', 'activity_display_name')->
            select('activity.description', 'activity_description')->
            select('profile.display_name', 'profile_display_name')->
            select('profile_group.' . $genderChoice[$user['gender']], 'profile_group_display_name')->
            select('completed_event.completed_date')->
            select_expr('(event.from_week+48-' . $base . ') % 48', 'n_from_week')->
            select_expr('(event.to_week+48-' . $base . ') % 48', 'n_to_week')->
            inner_join('activity_event', array('activity_event.event_id', '=', 'event.id'))->
            inner_join('activity_profile', array('activity_profile.activity_id', '=', 'activity_event.activity_id'))->
            inner_join('activity', array('activity.id', '=', 'activity_event.activity_id'))->
            inner_join('profile', array('profile.id', '=', 'activity_profile.profile_id'))->
            inner_join('profile_group', array('profile_group.id', '=', 'profile.profile_group_id'))->
            left_outer_join('completed_event', 'completed_event.event_id = event.id AND completed_event.person_id = ' . $user['id'])->
            group_by('activity_profile.profile_id')->
            group_by('activity_event.event_id')->
            order_by_asc('activity_event.activity_id')->
            order_by_asc('activity_event.order_nr')->
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
