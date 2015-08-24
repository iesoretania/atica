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

$app->map('/upgrade', function () use ($app, $user, $config, $organization) {
    if (!$user || !$user['is_global_administrator']) {
        $app->redirect($app->urlFor('login'));
    }

    $ok = false;
    $simulate = !isset($_POST['upgrade']);

    $initial = getModuleVersion('core');
    $updates = array();

    $core = $initial;

    if ($core) {
        $ok = true;

        if ($ok && ($core < '2014081001')) {
            include('../sql/upgrades/2014081001.php');
        }
        if ($ok && ($core < '2014112501')) {
            include('../sql/upgrades/2014112501.php');
        }
        if ($ok && ($core < '2015081901')) {
            include('../sql/upgrades/2015081901.php');
        }
    }
    if ($simulate) {
        $app->render('upgrade.html.twig', array(
            'initial' => $initial,
            'items' => $updates,
            'url' => $app->request()->getPathInfo()
        ));
    }
    else {
        if ($ok) {
            $app->flash('save_ok', 'upgrade');
        }
        else {
            $app->flash('save_error', 'upgrade');
        }
        $app->redirect($app->urlFor('frontpage'));
    }

})->name('upgrade')->via('GET', 'POST');

function getModuleVersion($id) {
    $data = ORM::for_table('module')->where('name', $id)->find_one();
    return isset($data['version']) ? $data['version'] : null;
}

function setModuleVersion($id, $version) {
    $data = ORM::for_table('module')->where('name', $id)->find_one();
    return $data->set('version', $version)->save();
}
