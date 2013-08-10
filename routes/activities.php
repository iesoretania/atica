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

$app->get('/actividades(/:id)', function ($id = NULL) use ($app, $user) {
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
    
    // añadir barra lateral de navegación
    array_push($sidebar, array(
        array('caption' => 'Navegación', 'icon' => 'compass'),
        array('caption' => 'Portada', 'target' => $app->urlFor('frontpage')),
        array('caption' => 'Árbol de documentos', 'target' => $app->urlFor('frontpage'))
    ));    
    
    // barra superior de navegación
    $breadcrumb = array(
        array('display_name' => 'Actividades', 'target' => $app->urlFor('activities')),
        array('display_name' => $detail, 'target' => '#')
    );
    
    if ($id) {
        $profile_ids = array( $id );
    }
    
    // obtener actividades
    $activities = ORM::for_table('event')->
        inner_join('activity_event', array('activity_event.event_id', '=', 'event.id'))->
        inner_join('activity_profile', array('activity_profile.activity_id', '=', 'activity_event.activity_id'))->
        group_by('activity_event.event_id')->
        group_by('activity_profile.profile_id')->
        order_by_asc('activity_event.activity_id')->
        order_by_asc('order_nr')->
        where_in('profile_id', $profile_ids)->find_array();
    
    // generar página
    $app->render('activities.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'detail' => $detail, 'sidebar' => $sidebar, 'activities' => $activities));
})->name('activities');
