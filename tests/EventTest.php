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

class EventTest extends PHPUnit_Framework_TestCase {

	/**
	 * Setup the test environment.
	 */
	public function setUp()
	{
            //Remove environment mode if set
            unset($_ENV['SLIM_MODE']);

            //Reset session
            $_SESSION = array();

            //Prepare default environment variables
            \Slim\Environment::mock(array(
                'SCRIPT_NAME' => '/foo', //<-- Physical
                'PATH_INFO' => '/bar', //<-- Virtual
                'QUERY_STRING' => 'one=foo&two=bar',
                'SERVER_NAME' => 'slimframework.com',
            ));
            
	}

	/**
	 * Teardown the test environment.
	 */
	public function tearDown()
	{
	}
        
        /**
	 * Test that event got parsed the right way
	 *
	 * @test
	 */
	public function testAddDataInfo()
	{
            $app = new \Slim\Slim();
            $user = array();
            $config = array();
            
            include 'routes/activities.php';
            
            $event = array(
                'example' => 2,
                'id' => 'A',
                'name' => 'test'
            );
            
            // Test 1: Simple input
            $data = array(1, 2, 3);
            $expectedOutput = array(
                'info' => array(),
                'data' => $data
            );
            $this->assertEquals(
                addDataInfo($data),
                $expectedOutput);
                        
            // Test 2: Complex input
            $expectedOutput = array(
                'info' => array('name' => 'test'),
                'data' => $data
            );
            
            $this->assertEquals(
                addDataInfo($data, array('name'), $event),
                $expectedOutput);
            
            // Test 3: Complex input
            $expectedOutput = array(
                'info' => array('example' => 2, 'name' => 'test'),
                'data' => $data
            );
            
            $this->assertEquals(
                addDataInfo($data, array('name', 'example'), $event),
                $expectedOutput);
        }

	/**
	 * Test that event got parsed the right way
	 *
	 * @test
	 */
	public function testParseEvents()
	{
            // Test 1: Empty input
            $this->assertEquals(
                parseEvents(array()),
                array());

            $in = array(
                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 1),
            );

            // Test 2: Only one element
            $expected_output = array(
                'PROF' => array(
                    'info' => array(),
                    'data' => array(
                        'UNO' => array(
                            'info' => array(),
                            'data' => array(
                                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 1)
                            )
                         )
                    )
                )
            );

            $this->assertEquals(
                    parseEvents($in),
                    $expected_output);

            // Test 3: Complex
            $in = array(
                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 1),
                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 2),
                array('profile_id' => 'PROF', 'activity_id' => 'DOS', 'event_id' => 3),
                array('profile_id' => 'PROF', 'activity_id' => 'TRES', 'event_id' => 4),
                array('profile_id' => 'COOR', 'activity_id' => 'TRES', 'event_id' => 5),
                array('profile_id' => 'DIRE', 'activity_id' => 'TRES', 'event_id' => 6),
                array('profile_id' => 'DIRE', 'activity_id' => 'CUATRO', 'event_id' => 7)
            );

            $expected_output = array(
                'PROF' => array(
                    'info' => array(),
                    'data' => array(
                        'UNO' => array(
                            'info' => array(),
                            'data' => array(
                                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 1),
                                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 2)
                            )
                        ),
                        'DOS' => array(
                            'info' => array(),
                            'data' => array(
                                array('profile_id' => 'PROF', 'activity_id' => 'DOS', 'event_id' => 3)
                            )
                        ),
                        'TRES' => array(
                            'info' => array(),
                            'data' => array(
                                array('profile_id' => 'PROF', 'activity_id' => 'TRES', 'event_id' => 4)
                            )
                        )
                    )
                ),
                'COOR' => array(
                    'info' => array(),
                    'data' => array(
                        'TRES' => array(
                            'info' => array(),
                            'data' => array(
                                array('profile_id' => 'COOR', 'activity_id' => 'TRES', 'event_id' => 5)
                            )
                        )
                    )
                ),
                'DIRE' => array(
                    'info' => array(),
                    'data' => array(
                        'TRES' => array(
                            'info' => array(),
                            'data' => array(
                                array('profile_id' => 'DIRE', 'activity_id' => 'TRES', 'event_id' => 6)
                            )
                        ),
                        'CUATRO' => array(
                            'info' => array(),
                            'data' => array(
                                array('profile_id' => 'DIRE', 'activity_id' => 'CUATRO', 'event_id' => 7)
                            )
                        )
                    )
                )
            );

            $this->assertEquals(
                    parseEvents($in),
                    $expected_output);
            
            // Test 4: Complex with info fields
            $in = array(
                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 1),
                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 2),
                array('profile_id' => 'PROF', 'activity_id' => 'DOS', 'event_id' => 3),
                array('profile_id' => 'PROF', 'activity_id' => 'TRES', 'event_id' => 4),
                array('profile_id' => 'COOR', 'activity_id' => 'TRES', 'event_id' => 5),
                array('profile_id' => 'DIRE', 'activity_id' => 'TRES', 'event_id' => 6),
                array('profile_id' => 'DIRE', 'activity_id' => 'CUATRO', 'event_id' => 7)
            );

            $expected_output = array(
                'PROF' => array(
                    'info' => array('profile_id' => 'PROF'),
                    'data' => array(
                        'UNO' => array(
                            'info' => array('activity_id' => 'UNO'),
                            'data' => array(
                                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 1),
                                array('profile_id' => 'PROF', 'activity_id' => 'UNO', 'event_id' => 2)
                            )
                        ),
                        'DOS' => array(
                            'info' => array('activity_id' => 'DOS'),
                            'data' => array(
                                array('profile_id' => 'PROF', 'activity_id' => 'DOS', 'event_id' => 3)
                            )
                        ),
                        'TRES' => array(
                            'info' => array('activity_id' => 'TRES'),
                            'data' => array(
                                array('profile_id' => 'PROF', 'activity_id' => 'TRES', 'event_id' => 4)
                            )
                        )
                    )
                ),
                'COOR' => array(
                    'info' => array('profile_id' => 'COOR'),
                    'data' => array(
                        'TRES' => array(
                            'info' => array('activity_id' => 'TRES'),
                            'data' => array(
                                array('profile_id' => 'COOR', 'activity_id' => 'TRES', 'event_id' => 5)
                            )
                        )
                    )
                ),
                'DIRE' => array(
                    'info' => array('profile_id' => 'DIRE'),
                    'data' => array(
                        'TRES' => array(
                            'info' => array('activity_id' => 'TRES'),
                            'data' => array(
                                array('profile_id' => 'DIRE', 'activity_id' => 'TRES', 'event_id' => 6)
                            )
                        ),
                        'CUATRO' => array(
                            'info' => array('activity_id' => 'CUATRO'),
                            'data' => array(
                                array('profile_id' => 'DIRE', 'activity_id' => 'CUATRO', 'event_id' => 7)
                            )
                        )
                    )
                )
            );

            $this->assertEquals(
                    parseEvents($in, 'profile_id', array('profile_id'), 'activity_id', array('activity_id')),
                    $expected_output);

	}

}