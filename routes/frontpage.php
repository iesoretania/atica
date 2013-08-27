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

$app->get('/(portada)(/:id)', function ($id = NULL) use ($app, $user) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect($app->urlFor('organization'));
    }
    $breadcrumb = array(
        array('display_name' => 'Portada', 'target' => '#')
    );
    
    $matchedGrouping = array();
    
    $sidebar = getGroupings($_SESSION['organization_id'], $app, $id, $matchedGrouping);
    
    $app->render('frontpage.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'sidebar' => $sidebar,
        'user' => $user));
})->name('frontpage');

function getGroupings($orgId, $app, $id, &$matchedGrouping) {
    $return = array();
    $currentData = array();
    $currentGrouping = NULL;
    $match = false;

    $data = ORM::for_table('grouping')->
            order_by_asc('grouping_left')->
            where('organization_id', $orgId)->
            where_gt('grouping_level', 0)->
            find_many();

    foreach ($data as $grouping) {
        if ($grouping['grouping_level'] == 1) {
            if ($currentGrouping != NULL) {
                array_unshift($currentData,
                        array(
                            'caption' => $currentGrouping['display_name']
                        ));
                $return[] = $currentData;
            }
            $currentData = array();
            $currentGrouping = $grouping;
            $match = false;
        }
        else {
            $localMatch = ($id == $grouping['id']);
            $currentData[] = array(
                'caption' => $grouping['display_name'],
                'active' => $localMatch,
                'target' => $app->urlFor('frontpage', array('id' => $grouping['id']))
            );
            if ($localMatch) {
                $matchedGrouping = $grouping;
            }
            $match = $match || $localMatch;
        }
    }
    if ($currentGrouping != NULL) {
          array_unshift($currentData,
                  array(
                      'caption' => $currentGrouping['display_name']
                  ));
          $return[] = $currentData;
    }

    return $return;
}