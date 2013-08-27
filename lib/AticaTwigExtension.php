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

use Slim\Slim;

class AticaTwigExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'atica';
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('parsePeriod', array($this, 'parsePeriod')),
            new \Twig_SimpleFunction('matchesRoute', array($this, 'matchesRoute'))
        );
    }
    
    public function parsePeriod($from, $to, $strings) {

        if (($from === NULL) || ($to === NULL)) {
            return "";
        }
        
        $return = "";

        // Mes(es) completo
        if ((($from % 4) == 0) && (($to % 4) == 3)) {
            $return = $strings['months'][floor($from / 4)];
            if ((floor($from / 4)) != floor($to / 4)) {
                $return .= "-" . $strings['months'][floor($to / 4)];
            }
        }
        elseif ((($from % 2) == 0) && ($to == $from + 1)) {
            $return = $strings['halfmonths'][($from % 4)/2] . $strings['months'][floor($from / 4)];
        }
        else {
            $return = $strings['weeks'][$from % 4] . $strings['months'][floor($from / 4)];
            if ($from != $to) {
                $return .= ' a ';
                $return .= $strings['weeks'][$to % 4] . $strings['months'][floor($to / 4)];
            }
        }

        return $return;
    }
    
    public function matchesRoute($route, $appName = 'default')
    {
        return (Slim::getInstance($appName)->router()->getCurrentRoute()->getName() == $route);
    }
}
