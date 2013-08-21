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

$app->get('/arbol(:/id)', function ($id = NULL) use ($app, $user, $organization) {
    if (!$user) {
        $app->redirect($app->urlFor('login'));
    }
    $breadcrumb = array(
        array('display_name' => 'Portada', 'target' => '#')
    );
    
    $sidebar = getTree($organization['id']);

    $app->render('tree.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'sidebar' => $sidebar));
})->name('tree');

function getTree($org_id) {
    $return = array();
    $currentData = array();
    $currentCategory = NULL;
    
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
                    'data' => $currentData
                );
            }
            $currentData = array();
            $currentCategory = $category;
        }
        else {
            $currentData[] = array(
                'caption' => $category['display_name'],
                'target' => '#'
            );
        }
    }
    if ($currentCategory != NULL) {
        $return[] = array(
            'caption' => $currentCategory['display_name'],
            'data' => $currentData
        );
    }
    
    return $return;
}