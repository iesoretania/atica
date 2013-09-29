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

require './vendor/autoload.php';

class TwigExtensionTest extends PHPUnit_Framework_TestCase {

	/**
	 * Setup the test environment.
	 */
	public function setUp()
	{
	}

	/**
	 * Teardown the test environment.
	 */
	public function tearDown()
	{
	}

        /**
	 * Converts week periods into text
	 *
	 * @test
	 */
	public function testParsePeriod()
	{
            $ext = new \Atica\Extension\TwigExtension();

            $strings = array(
                'months' => array('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'),
                'weeks' => array('1ª sem. ', '2ª sem. ', '3ª sem. ', '4ª sem. '),
                'halfmonths' => array('1ª quincena ', '2ª quincena ')
            );
            
            $this->assertEquals($ext->parsePeriod(NULL, 3, $strings) , '');
            $this->assertEquals($ext->parsePeriod(3, NULL, $strings) , '');
            $this->assertEquals($ext->parsePeriod(0, 0, $strings) , '1ª sem. enero');
            $this->assertEquals($ext->parsePeriod(0, 3, $strings) , 'enero');
            $this->assertEquals($ext->parsePeriod(0, 1, $strings) , '1ª quincena enero');
            $this->assertEquals($ext->parsePeriod(6, 7, $strings) , '2ª quincena febrero');
            $this->assertEquals($ext->parsePeriod(7, 7, $strings) , '4ª sem. febrero');
            $this->assertEquals($ext->parsePeriod(47, 47, $strings) , '4ª sem. diciembre');
            $this->assertEquals($ext->parsePeriod(5, 6, $strings) , '2ª sem. febrero a 3ª sem. febrero');
            $this->assertEquals($ext->parsePeriod(7, 8, $strings) , '4ª sem. febrero a 1ª sem. marzo');
            $this->assertEquals($ext->parsePeriod(47, 0, $strings) , '4ª sem. diciembre a 1ª sem. enero');
	}

        /**
	 * Converts week periods into text (exception)
	 *
	 * @test
	 */
	public function testParsePeriodException()
	{
            $ext = new \Atica\Extension\TwigExtension();

            $strings = array(
                'months' => array('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'),
                'weeks' => array('1ª sem. ', '2ª sem. ', '3ª sem. ', '4ª sem. '),
                'halfmonths' => array('1ª quincena ', '2ª quincena ')
            );
            $this->setExpectedException('PHPUnit_Framework_Error_Notice');
            $ext->parsePeriod(50, 53, $strings);
	}
}