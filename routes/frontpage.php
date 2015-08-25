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

$app->get('/(portada)', function () use ($app, $user) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect($app->urlFor('organization'));
    }
    $breadcrumb = array(
        array('display_name' => 'Portada', 'target' => '#')
    );

    $parentGrouping = array();
    $matchedGrouping = array();

    $sidebar = getGroupings($_SESSION['organization_id'], $app, null, $matchedGrouping, $parentGrouping);

    $app->render('frontpage.html.twig', array(
        'navigation' => $breadcrumb,
        'search' => false,
        'sidebar' => $sidebar,
        'user' => $user));

})->name('frontpage');

$app->get('/portada/:id', function ($id) use ($app, $user) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect($app->urlFor('organization'));
    }
    $matchedGrouping = null;
    $parentGrouping = null;

    $sidebar = getGroupings($_SESSION['organization_id'], $app, $id, $matchedGrouping, $parentGrouping);

    if ($matchedGrouping == null) {
        $app->redirect($app->urlFor('frontpage'));
    }

    $breadcrumb = array(
            array('display_name' => 'Portada', 'target' => $app->urlFor('frontpage')),
            array('display_name' => $parentGrouping['display_name'], 'target' => $app->urlFor('grouping', array('id' => $id))),
            array('display_name' => $matchedGrouping['display_name'])
    );

    $folders = getGroupingFolders($id);
    $data = getParsedDeliveriesFromGroupingFolders($folders);

    $app->render('grouping.html.twig', array(
        'navigation' => $breadcrumb,
        'search' => !empty($data),
        'sidebar' => $sidebar,
        'data' => $data,
        'folders' => $folders,
        'grouping' => $matchedGrouping,
        'user' => $user));
})->name('grouping');

function getGroupings($orgId, $app, $id, &$matchedGrouping, &$parentGrouping) {
    $return = array();
    $currentData = array();
    $currentGrouping = null;
    $match = false;

    $data = ORM::for_table('grouping')->
            order_by_asc('grouping_left')->
            where('organization_id', $orgId)->
            where_gt('grouping_level', 0)->
            find_array();

    foreach ($data as $grouping) {
        if ($grouping['grouping_level'] == 1) {
            if ($currentGrouping != null) {
                array_unshift($currentData,
                        array(
                            'caption' => $currentGrouping['display_name']
                        ));
                $return[] = $currentData;
                if ($match) {
                    $parentGrouping = $currentGrouping;
                }
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
                'target' => $app->urlFor('grouping', array('id' => $grouping['id']))
            );
            if ($localMatch) {
                $matchedGrouping = $grouping;
            }
            $match = $match || $localMatch;
        }
    }
    if ($currentGrouping != null) {
        array_unshift($currentData,
                array(
                    'caption' => $currentGrouping['display_name']
                ));
        $return[] = $currentData;
        if ($match) {
            $parentGrouping = $currentGrouping;
        }
    }

    return $return;
}

function getGroupingFolders($groupingId) {
    return parseArray(ORM::for_table('folder')->
        inner_join('grouping_folder', array('grouping_folder.folder_id', '=', 'folder.id'))->
        where('grouping_folder.grouping_id', $groupingId)->
        order_by_asc('grouping_folder.order_nr')->
        find_many());
}


function getParsedDeliveriesFromGroupingFolders($folders) {

    $return = array();
    foreach($folders as $folder) {
        $deliveries = ORM::for_table('delivery')->
                select('delivery.*')->
                select('folder_delivery.order_nr')->
                select('revision.upload_date')->
                inner_join('folder_delivery', array('folder_delivery.delivery_id', '=', 'delivery.id'))->
                inner_join('revision', array('delivery.current_revision_id', '=', 'revision.id'))->
                inner_join('person', array('person.id', '=', 'revision.uploader_person_id'))->
                where('folder_delivery.folder_id', $folder['id'])->
                where_null('folder_delivery.snapshot_id')->
                order_by_asc('delivery.profile_id')->
                order_by_asc('order_nr')->find_array();

        $return[] = array(
            'id' => $folder['id'],
            'data' => $deliveries
        );
    }
    return $return;
}
