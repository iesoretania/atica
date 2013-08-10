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

$app->get('/(portada)', function () use ($app, $user) {
    if (!isset($_SESSION['organization_id'])) {
        $app->redirect($app->urlFor('organization'));
    }
    $breadcrumb = array(
        array('display_name' => 'Portada', 'target' => '#')
    );
    $sidebar = array();

    array_push($sidebar, array(
        array('caption' => 'Secciones', 'icon' => 'list'),
        array('caption' => 'Portada', 'active' => true, 'target' => $app->urlFor('frontpage')),
        array('caption' => 'Plan de centro', 'target' => '#'),
        array('caption' => 'Criterios de evaluación', 'target' => '#'),
        array('caption' => 'Programaciones didácticas', 'target' => '#')
    ));

    if ($user) {
        array_push($sidebar, array(
            array('caption' => 'Navegación', 'icon' => 'compass'),
            array('caption' => 'Actividades', 'target' => $app->urlFor('activities')),
            array('caption' => 'Árbol de documentos', 'target' => $app->urlFor('frontpage'))
        ));
    }
    $app->render('frontpage.html.twig', array(
        'navigation' => $breadcrumb, 'search' => true, 'sidebar' => $sidebar));
})->name('frontpage');