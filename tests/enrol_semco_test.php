<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace enrol_semco;

use core\exception\moodle_exception;
use moodle_url;

/**
 * Enrolment method "SEMCO" - PHPUnit tests.
 *
 * @package    enrol_semco
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The enrol_semco_test class.
 *
 * @covers \enrol_semco\external
 *
 * @package    enrol_semco
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrol_semco_test extends \advanced_testcase {
    /**
     * Setup testcase.
     */
    public function setUp(): void {
        global $CFG, $DB;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Call the parent setup.
        parent::setUp();

        // Reset after the test.
        $this->resetAfterTest(true);

        // Get the SEMCO webservice user.
        $semcouser = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME]);
        $this->assertNotEmpty($semcouser);

        // Run the tests as SEMCO webservice user.
        $this->setUser($semcouser);
    }

    /*
     * In the following tests, the enrol_user() webservice will be tested.
     * It will be tested with all relevant permutations of successful parameters and
     * it will be tested with all relevant exceptions which may happen due to invalid parameters given by SEMCO for whatever reason.
     * It will not be tested for exceptions which may happen due to severe misconfigurations of the SEMCO plugin or SEMCO webservice
     * user role in Moodle or due to broken SEMCO plugin installations.
     */

    /*
     * In the following tests, the enrol_user() webservice will be tested.
     * It will be tested with all relevant permutations of successful parameters and
     * it will be tested with all relevant exceptions which may happen due to invalid parameters given by SEMCO for whatever reason.
     * It will not be tested for exceptions which may happen due to severe misconfigurations of the SEMCO plugin or SEMCO webservice
     * user role in Moodle or due to broken SEMCO plugin installations.
     */

    /**
     * Data provider for test_enrol_user_successful.
     *
     * @return array
     */
    public static function enrol_user_successful_provider(): array {
        return [
            // Test the webservice without any optional parameters.
            ['timestart' => null, 'timeend' => null, 'suspend' => null],
            // Test the webservice's time parameters.
            ['timestart' => null, 'timeend' => 1836542019, 'suspend' => null],
            ['timestart' => 1636542019, 'timeend' => null, 'suspend' => null],
            ['timestart' => 1636542019, 'timeend' => 1836542019, 'suspend' => null],
            // Test the webservice's suspend parameters.
            ['timestart' => null, 'timeend' => null, 'suspend' => 0],
            ['timestart' => null, 'timeend' => null, 'suspend' => 1],
        ];
    }

    /**
     * Test the enrol_user() webservice function with successful parameters.
     *
     * @param int|null $timestart The timestart parameter
     * @param int|null $timeend The timeend parameter
     * @param int|null $suspend The suspend parameter
     * @dataProvider enrol_user_successful_provider
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_enrol_user_successful($timestart, $timeend, $suspend): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Enrol the user in the course with the webservice which we want to test.
        $webservicereturn = external::enrol_user(
            $user->id,
            $course->id,
            $semcobookingid,
            $timestart,
            $timeend,
            $suspend
        );

        // Check the webservice return structure.
        $this->assertNotEmpty($webservicereturn);
        $this->assertArrayHasKey('enrolid', $webservicereturn);
        $this->assertArrayHasKey('userid', $webservicereturn);
        $this->assertArrayHasKey('courseid', $webservicereturn);
        $this->assertArrayHasKey('semcobookingid', $webservicereturn);
        $this->assertArrayHasKey('warnings', $webservicereturn);

        // Check the webservice return values.
        $this->assertEquals($user->id, $webservicereturn['userid']);
        $this->assertEquals($course->id, $webservicereturn['courseid']);
        $this->assertEquals($semcobookingid, $webservicereturn['semcobookingid']);
        $this->assertEmpty($webservicereturn['warnings']);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);
        $this->assertCount(1, $enrolmentinstances);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Check the enrolment semcobookingid.
        $this->assertEquals($semcobookingid, $enrolmentinstance->customchar1);

        // Get the user enrolment instances from the database.
        $userenrolmentinstances = $DB->get_records('user_enrolments', ['enrolid' => $enrolmentinstance->id, 'userid' => $user->id]);
        $this->assertCount(1, $userenrolmentinstances);

        // Pick the only user enrolment instance.
        $userenrolmentinstance = array_pop($userenrolmentinstances);

        // Check the rest of the webservice return values.
        $this->assertEquals($userenrolmentinstance->id, $webservicereturn['enrolid']);

        // Check the user enrolment time.
        if ($timestart == null) {
            $this->assertEquals(0, $userenrolmentinstance->timestart);
        } else {
            $this->assertEquals($timestart, $userenrolmentinstance->timestart);
        }
        if ($timeend == null) {
            $this->assertEquals(0, $userenrolmentinstance->timeend);
        } else {
            $this->assertEquals($timeend, $userenrolmentinstance->timeend);
        }

        // Check the user enrolment timecreated and timemodified.
        $this->assertGreaterThanOrEqual(time() - 100, $userenrolmentinstance->timecreated);
        $this->assertGreaterThanOrEqual(time() - 100, $userenrolmentinstance->timemodified);

        // Check the user enrolment status.
        if ($suspend == null) {
            $this->assertEquals(0, $userenrolmentinstance->status);
        } else if ($suspend == 0) {
            $this->assertEquals(0, $userenrolmentinstance->status);
        } else if ($suspend == 1) {
            $this->assertEquals(1, $userenrolmentinstance->status);
        }

        // Get the course context from the database.
        $coursecontextinstances = $DB->get_records('context', ['contextlevel' => CONTEXT_COURSE, 'instanceid' => $course->id]);
        $this->assertCount(1, $coursecontextinstances);

        // Pick the only context instance.
        $coursecontextinstance = array_pop($coursecontextinstances);

        // Get the role assignments from the database.
        $roleassignmeninstances = $DB->get_records(
            'role_assignments',
            ['contextid' => $coursecontextinstance->id, 'userid' => $user->id]
        );
        $this->assertCount(1, $roleassignmeninstances);

        // Pick the only role assignment instance.
        $roleassignmeninstance = array_pop($roleassignmeninstances);

        // Check the user role.
        $this->assertEquals(5, $roleassignmeninstance->roleid);
    }

    /**
     * Test the enrol_user() webservice function with the usernotexist exception.
     *
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_enrol_user_usernotexist_exception(): void {
        // Create a course.
        $course = $this->create_course();

        // Initialize more enrolment data.
        $userid = 99999999;
        $semcobookingid = 12345;

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('usernotexist', 'enrol_semco', $userid));

        // Enrol the user in the course with the webservice which we want to test.
        external::enrol_user($userid, $course->id, $semcobookingid);
    }

    /**
     * Test the enrol_user() webservice function with the coursenotexist exception.
     *
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_enrol_user_coursenotexist_exception(): void {
        // Create a user.
        $user = $this->create_user();

        // Initialize more enrolment data.
        $courseid = 99999999;
        $semcobookingid = 12345;

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('coursenotexist', 'enrol_semco', $courseid));

        // Enrol the user in the course with the webservice which we want to test.
        external::enrol_user($user->id, $courseid, $semcobookingid);
    }

    /**
     * Data provider for test_enrol_user_booking1_exceptions.
     *
     * @return array
     */
    public static function enrol_user_booking1_exceptions_provider(): array {
        return [
            // Test the bookingidempty exception.
            ['timestart' => null, 'timeend' => null, 'semcobookingid' => '', 'exception' => 'bookingidempty'],
            // Test the timestartinvalid exception.
            ['timestart' => -1636542019, 'timeend' => null, 'semcobookingid' => 12345, 'exception' => 'timestartinvalid'],
            // Test the timeendinvalid exception.
            ['timestart' => null, 'timeend' => -1836542019, 'semcobookingid' => 12345, 'exception' => 'timeendinvalid'],
            // Test the timestartendorder exception.
            ['timestart' => 1836542019, 'timeend' => 1636542019, 'semcobookingid' => 12345, 'exception' => 'timestartendorder'],
        ];
    }

    /**
     * Test the enrol_user() webservice function with booking exceptions.
     *
     * @param int|null $timestart The timestart parameter
     * @param int|null $timeend The timeend parameter
     * @param int|null $semcobookingid The semcobookingid parameter
     * @param string $exception The expected exception
     * @dataProvider enrol_user_booking1_exceptions_provider
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_enrol_user_booking1_exceptions($timestart, $timeend, $semcobookingid, $exception): void {
        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string($exception, 'enrol_semco'));

        // Enrol the user in the course with the webservice which we want to test.
        external::enrol_user($user->id, $course->id, $semcobookingid, $timestart, $timeend);
    }

    /**
     * Data provider for test_enrol_user_booking2_exceptions.
     *
     * @return array
     */
    public static function enrol_user_booking2_exceptions_provider(): array {
        return [
            // Test the bookingidduplicate exception.
            ['timestart1' => 1636542019, 'timeend1' => 1736542019, 'semcobookingid1' => 12345,
                    'timestart2' => 1836542019, 'timeend2' => 1936542019, 'semcobookingid2' => 12345,
                    'exception' => 'bookingidduplicate'],
            // Test the bookingoverlap exception.
            ['timestart1' => 1636542019, 'timeend1' => 1836542019, 'semcobookingid1' => 12345,
                    'timestart2' => 1736542019, 'timeend2' => 1936542019, 'semcobookingid2' => 12346,
                    'exception' => 'bookingoverlap'],
        ];
    }

    /**
     * Test the enrol_user() webservice function with booking exceptions.
     *
     * @param int|null $timestart1 The timestart1 parameter
     * @param int|null $timeend1 The timeend1 parameter
     * @param int|null $semcobookingid1 The semcobookingid1 parameter
     * @param int|null $timestart2 The timestart2 parameter
     * @param int|null $timeend2 The timeend2 parameter
     * @param int|null $semcobookingid2 The semcobookingid2 parameter
     * @param string $exception The expected exception
     * @dataProvider enrol_user_booking2_exceptions_provider
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_enrol_user_booking2_exceptions(
        $timestart1,
        $timeend1,
        $semcobookingid1,
        $timestart2,
        $timeend2,
        $semcobookingid2,
        $exception
    ): void {
        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Enrol the user in the course with the first booking with the webservice which we want to test.
        external::enrol_user($user->id, $course->id, $semcobookingid1, $timestart1, $timeend1);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string($exception, 'enrol_semco', $semcobookingid2));

        // Enrol the user in the course with the second booking with the webservice which we want to test.
        external::enrol_user($user->id, $course->id, $semcobookingid2, $timestart2, $timeend2);
    }

    /**
     * Test the enrol_user() webservice function with the requirerecompletion parameter.
     *
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_enrol_user_requirerecompletion_successful(): void {
        global $CFG, $DB;

        // Require local_recompletion plugin library.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Set the recompletion config for this course.
        $recompletionconfig['course'] = $course->id;
        $recompletionconfig['name'] = 'recompletiontype';
        $recompletionconfig['value'] = \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND;
        $DB->insert_record('local_recompletion_config', $recompletionconfig);

        // Enrol the user in the course with the webservice which we want to test.
        // The test will be successfully if no exception is thrown.
        external::enrol_user($user->id, $course->id, $semcobookingid, null, null, null, 1);
    }

    /**
     * Data provider for test_enrol_user_localrecompletion_exceptions.
     *
     * @return array
     */
    public static function enrol_user_localrecompletion_exceptions_provider(): array {
        global $CFG;

        // Require local_recompletion plugin library.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        return [
            // Test the localrecompletionnotenabled exception.
            ['recompletiontype' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_DISABLED,
                    'exception' => 'localrecompletionnotenabled'],
            // Test the localrecompletionnotondemand exception.
            ['recompletiontype' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_SCHEDULE,
                    'exception' => 'localrecompletionnotondemand'],
            ['recompletiontype' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_PERIOD,
                    'exception' => 'localrecompletionnotondemand'],
        ];
    }

    /**
     * Test the enrol_user() webservice function with the multiple exceptions.
     *
     * @param int $recompletiontype The recompletiontype parameter
     * @param string $exception The exception exception
     *
     * @dataProvider enrol_user_localrecompletion_exceptions_provider
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_enrol_user_localrecompletion_exceptions($recompletiontype, $exception): void {
        global $CFG, $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Set the recompletion config for this course.
        $recompletionconfig['course'] = $course->id;
        $recompletionconfig['name'] = 'recompletiontype';
        $recompletionconfig['value'] = $recompletiontype;
        $DB->insert_record('local_recompletion_config', $recompletionconfig);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $localrecompletionurl = new moodle_url('/local/recompletion/recompletion.php', ['id' => $course->id]);
        $this->expectExceptionMessage(get_string($exception, 'enrol_semco', $localrecompletionurl->out()));

        // Enrol the user in the course with the webservice which we want to test.
        external::enrol_user($user->id, $course->id, $semcobookingid, null, null, null, 1);
    }

    /**
     * Test the enrol_user() webservice function with the localrecompletionnotexpectable exception.
     *
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_enrol_user_localrecompletionnotexpectable_exception(): void {
        global $CFG;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Simulate that the plugin is not installed by setting a global variable.
        $CFG->localrecompletionnotinstalled = true;

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('localrecompletionnotexpectable', 'enrol_semco'));

        // Enrol the user in the course with the webservice which we want to test.
        external::enrol_user($user->id, $course->id, $semcobookingid, null, null, null, 1);
    }

    /*
     * In the following tests, the unenrol_user() webservice will be tested.
     * It will be tested with all relevant permutations of successful parameters and
     * it will be tested with all relevant exceptions which may happen due to invalid parameters given by SEMCO for whatever reason.
     * It will not be tested for exceptions which may happen due to severe misconfigurations of the SEMCO plugin or SEMCO webservice
     * user role in Moodle or due to broken SEMCO plugin installations.
     */

    /**
     * Test the unenrol_user() webservice function with successful parameters.
     *
     * @covers \enrol_semco\external::unenrol_user
     */
    public function test_unenrol_user_successful(): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Enrol the user in the course.
        $enrolreturn = external::enrol_user($user->id, $course->id, $semcobookingid);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);
        $this->assertCount(1, $enrolmentinstances);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Get the user enrolment instances from the database.
        $userenrolmentinstances = $DB->get_records('user_enrolments', ['enrolid' => $enrolmentinstance->id, 'userid' => $user->id]);
        $this->assertCount(1, $userenrolmentinstances);

        // Unenrol the user again from the course with the webservice which we want to test.
        $webservicereturn = external::unenrol_user($enrolreturn['enrolid']);

        // Check the webservice return structure.
        $this->assertNotEmpty($webservicereturn);
        $this->assertArrayHasKey('result', $webservicereturn);
        $this->assertArrayHasKey('warnings', $webservicereturn);

        // Check the webservice return values.
        $this->assertEquals(true, $webservicereturn['result']);
        $this->assertEmpty($webservicereturn['warnings']);

        // Verify that the SEMCO enrolment instance is gone in the database.
        $enrolmentinstances2 = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);
        $this->assertCount(0, $enrolmentinstances2);

        // Verify that the user enrolment instance is gone in the database as well.
        $userenrolmentinstances2 = $DB->get_records('user_enrolments', ['enrolid' => $enrolmentinstance->id,
                'userid' => $user->id]);
        $this->assertCount(0, $userenrolmentinstances2);
    }

    /**
     * Test the unenrol_user() webservice function with the enrolnouserinstance exception.
     *
     * @covers \enrol_semco\external::unenrol_user
     */
    public function test_unenrol_user_enrolnouserinstance_exception(): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Enrol the user in the course with the webservice which we want to test.
        $enrolreturn = external::enrol_user($user->id, $course->id, $semcobookingid);

        // Unenrol the user from the course.
        external::unenrol_user($enrolreturn['enrolid']);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('enrolnouserinstance', 'enrol_semco', $enrolreturn['enrolid']));

        // Attempt to unenrol the user again, which should throw the exception.
        external::unenrol_user($enrolreturn['enrolid']);
    }

    /**
     * Test the unenrol_user() webservice function with the enrolnoinstance exception.
     *
     * @covers \enrol_semco\external::unenrol_user
     */
    public function test_unenrol_user_enrolnoinstance_exception(): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Enrol the user in the course with the webservice which we want to test.
        $enrolreturn = external::enrol_user($user->id, $course->id, $semcobookingid);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);
        $this->assertCount(1, $enrolmentinstances);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Delete the enrolment instance to simulate the exception.
        $DB->delete_records('enrol', ['id' => $enrolmentinstance->id]);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('enrolnoinstance', 'enrol_semco', $enrolreturn['enrolid']));

        // Attempt to unenrol the user, which should throw the exception.
        external::unenrol_user($enrolreturn['enrolid']);
    }

    /*
     * In the following tests, the edit_enrolment() webservice will be tested.
     * It will be tested with all relevant permutations of successful parameters and
     * it will be tested with all relevant exceptions which may happen due to invalid parameters given by SEMCO for whatever reason.
     * It will not be tested for exceptions which may happen due to severe misconfigurations of the SEMCO plugin or SEMCO webservice
     * user role in Moodle or due to broken SEMCO plugin installations.
     */

    /**
     * Data provider for test_edit_enrolment_successful.
     *
     * @return array
     */
    public static function edit_enrolment_successful_provider(): array {
        return [
            // Test the webservice without any optional parameters.
            ['timestart' => null, 'timeend' => null, 'suspend' => null, 'semcobookingid' => null],
            // Test the webservice's time parameters.
            ['timestart' => null, 'timeend' => 1836542019, 'suspend' => null, 'semcobookingid' => null],
            ['timestart' => 1636542019, 'timeend' => null, 'suspend' => null, 'semcobookingid' => null],
            ['timestart' => 1636542019, 'timeend' => 1836542019, 'suspend' => null, 'semcobookingid' => null],
            // Test the webservice's suspend parameters.
            ['timestart' => null, 'timeend' => null, 'suspend' => 0, 'semcobookingid' => null],
            ['timestart' => null, 'timeend' => null, 'suspend' => 1, 'semcobookingid' => null],
            // Test the webservice's semcobookingid parameters.
            ['timestart' => null, 'timeend' => null, 'suspend' => null, 'semcobookingid' => 15672],
        ];
    }

    /**
     * Test the edit_enrolment() webservice function with successful parameters.
     *
     * @param int|null $timestart The timestart parameter
     * @param int|null $timeend The timeend parameter
     * @param int|null $suspend The suspend parameter
     * @param int|null $semcobookingid The SEMCO booking ID
     * @dataProvider edit_enrolment_successful_provider
     * @covers \enrol_semco\external::edit_enrolment
     */
    public function test_edit_enrolment_successful($timestart, $timeend, $suspend, $semcobookingid): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $initialsemcobookingid = 12345;

        // Enrol the user in the course.
        $enrolreturn = external::enrol_user($user->id, $course->id, $initialsemcobookingid);

        // Edit the enrolment with the webservice which we want to test.
        $webservicereturn = external::edit_enrolment($enrolreturn['enrolid'], $semcobookingid, $timestart, $timeend, $suspend);

        // Check the webservice return structure.
        $this->assertNotEmpty($webservicereturn);
        $this->assertArrayHasKey('result', $webservicereturn);
        $this->assertArrayHasKey('warnings', $webservicereturn);

        // Check the webservice return values.
        $this->assertEquals(true, $webservicereturn['result']);
        $this->assertEmpty($webservicereturn['warnings']);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);
        $this->assertCount(1, $enrolmentinstances);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Check the enrolment semcobookingid.
        if ($semcobookingid == null) {
            $this->assertEquals($initialsemcobookingid, $enrolmentinstance->customchar1);
        } else {
            $this->assertEquals($semcobookingid, $enrolmentinstance->customchar1);
        }

        // Get the user enrolment instances from the database.
        $userenrolmentinstances = $DB->get_records('user_enrolments', ['enrolid' => $enrolmentinstance->id, 'userid' => $user->id]);
        $this->assertCount(1, $userenrolmentinstances);

        // Pick the only user enrolment instance.
        $userenrolmentinstance = array_pop($userenrolmentinstances);

        // Check the user enrolment time.
        if ($timestart == null) {
            $this->assertEquals(0, $userenrolmentinstance->timestart);
        } else {
            $this->assertEquals($timestart, $userenrolmentinstance->timestart);
        }
        if ($timeend == null) {
            $this->assertEquals(0, $userenrolmentinstance->timeend);
        } else {
            $this->assertEquals($timeend, $userenrolmentinstance->timeend);
        }

        // Check the user enrolment status.
        if ($suspend == null) {
            $this->assertEquals(0, $userenrolmentinstance->status);
        } else if ($suspend == 0) {
            $this->assertEquals(0, $userenrolmentinstance->status);
        } else if ($suspend == 1) {
            $this->assertEquals(1, $userenrolmentinstance->status);
        }
    }

    /**
     * Test the edit_enrolment() webservice function with the enrolnouserinstance exception.
     *
     * @covers \enrol_semco\external::edit_enrolment
     */
    public function test_edit_enrolment_enrolnouserinstance_exception(): void {
        // Initialize a non-existing enrolment ID.
        $enrolmentid = 99999;

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('enrolnouserinstance', 'enrol_semco', $enrolmentid));

        // Edit the enrolment with the webservice which we want to test.
        external::edit_enrolment($enrolmentid);
    }

    /**
     * Test the edit_enrolment() webservice function with the enrolnoinstance exception.
     *
     * @covers \enrol_semco\external::edit_enrolment
     */
    public function test_edit_enrolment_enrolnoinstance_exception(): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Enrol the user in the course.
        $enrolreturn = external::enrol_user($user->id, $course->id, $semcobookingid);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);
        $this->assertCount(1, $enrolmentinstances);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Delete the enrolment instance to simulate the exception.
        $DB->delete_records('enrol', ['id' => $enrolmentinstance->id]);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('enrolnoinstance', 'enrol_semco', $enrolreturn['enrolid']));

        // Edit the enrolment with the webservice which we want to test.
        external::edit_enrolment($enrolreturn['enrolid']);
    }

    /**
     * Data provider for test_edit_enrolment_booking1_exceptions.
     *
     * @return array
     */
    public static function edit_enrolment_booking1_exceptions_provider(): array {
        return [
            // Test the bookingidempty exception.
            ['timestart' => null, 'timeend' => null, 'suspend' => null, 'semcobookingid' => '',
                    'exception' => 'bookingidempty'],
            // Test the timestartinvalid exception.
            ['timestart' => -1636542019, 'timeend' => null, 'suspend' => null, 'semcobookingid' => null,
                    'exception' => 'timestartinvalid'],
            // Test the timeendinvalid exception.
            ['timestart' => null, 'timeend' => -1836542019, 'suspend' => null, 'semcobookingid' => null,
                    'exception' => 'timeendinvalid'],
            // Test the timestartendorder exception.
            ['timestart' => 1836542019, 'timeend' => 1636542019, 'suspend' => null, 'semcobookingid' => null,
                    'exception' => 'timestartendorder'],
        ];
    }

    /**
     * Test the edit_enrolment() webservice function with booking exceptions.
     *
     * @param int|null $timestart The timestart parameter
     * @param int|null $timeend The timeend parameter
     * @param int|null $suspend The suspend parameter
     * @param int|null $semcobookingid The semcobookingid parameter
     * @param string $exception The expected exception
     * @dataProvider edit_enrolment_booking1_exceptions_provider
     * @covers \enrol_semco\external::edit_enrolment
     */
    public function test_edit_enrolment_booking1_exceptions($timestart, $timeend, $suspend, $semcobookingid, $exception): void {
        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $initialsemcobookingid = 12345;

        // Enrol the user in the course.
        $enrolreturn = external::enrol_user($user->id, $course->id, $initialsemcobookingid);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string($exception, 'enrol_semco'));

        // Edit the enrolment with the webservice which we want to test.
        external::edit_enrolment($enrolreturn['enrolid'], $semcobookingid, $timestart, $timeend, $suspend);
    }

    /**
     * Data provider for test_edit_enrolment_booking2_exceptions.
     *
     * @return array
     */
    public static function edit_enrolment_booking2_exceptions_provider(): array {
        return [
            // Test the bookingidduplicatemustchange exception.
            ['timestart1' => 1636542019, 'timeend1' => 1736542019, 'semcobookingid1' => 12345,
                    'timestart2' => 1836542019, 'timeend2' => 1936542019, 'semcobookingid2' => 12346,
                    'timestart3' => 2036542019, 'timeend3' => 2136542019, 'semcobookingid3' => 12345,
                    'exception' => 'bookingidduplicatemustchange'],
            // Test the bookingoverlap exception.
            ['timestart1' => 1636542019, 'timeend1' => 1836542019, 'semcobookingid1' => 12345,
                    'timestart2' => 1936542019, 'timeend2' => 2036542019, 'semcobookingid2' => 12346,
                    'timestart3' => 1736542019, 'timeend3' => 1936542019, 'semcobookingid3' => 12347,
                    'exception' => 'bookingoverlap'],
        ];
    }

    /**
     * Test the edit_enrolment() webservice function with booking exceptions.
     *
     * @param int|null $timestart1 The timestart1 parameter
     * @param int|null $timeend1 The timeend1 parameter
     * @param int|null $semcobookingid1 The semcobookingid1 parameter
     * @param int|null $timestart2 The timestart2 parameter
     * @param int|null $timeend2 The timeend2 parameter
     * @param int|null $semcobookingid2 The semcobookingid2 parameter
     * @param int|null $timestart3 The timestart3 parameter
     * @param int|null $timeend3 The timeend3 parameter
     * @param int|null $semcobookingid3 The semcobookingid3 parameter
     * @param string $exception The expected exception
     * @dataProvider edit_enrolment_booking2_exceptions_provider
     * @covers \enrol_semco\external::edit_enrolment
     */
    public function test_edit_enrolment_booking2_exceptions(
        $timestart1,
        $timeend1,
        $semcobookingid1,
        $timestart2,
        $timeend2,
        $semcobookingid2,
        $timestart3,
        $timeend3,
        $semcobookingid3,
        $exception
    ): void {
        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Enrol the user in the course with the first booking.
        $enrolreturn = external::enrol_user($user->id, $course->id, $semcobookingid1, $timestart1, $timeend1);

        // Enrol the user in the course with the second booking.
        $enrolreturn = external::enrol_user($user->id, $course->id, $semcobookingid2, $timestart2, $timeend2);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string($exception, 'enrol_semco', $semcobookingid3));

        // Edit the enrolment with the third booking with the webservice which we want to test.
        external::edit_enrolment($enrolreturn['enrolid'], $semcobookingid3, $timestart3, $timeend3);
    }

    /*
     * In the following tests, the get_enrolments() webservice will be tested.
     * It will be tested with all relevant permutations of successful parameters and
     * it will be tested with all relevant exceptions which may happen due to invalid parameters given by SEMCO for whatever reason.
     * It will not be tested for exceptions which may happen due to severe misconfigurations of the SEMCO plugin or SEMCO webservice
     * user role in Moodle or due to broken SEMCO plugin installations.
     */

    /**
     * Data provider for test_get_enrolments_successful.
     *
     * @return array
     */
    public static function get_enrolments_successful_provider(): array {
        return [
            // Test the webservice with zero enrolments.
            ['numenrolments' => 0, 'timestart' => null, 'timeend' => null, 'suspend' => 0],
            // Test the webservice with one (active) enrolment.
            ['numenrolments' => 1, 'timestart' => 1636542019, 'timeend' => 1836542019, 'suspend' => 0],
            // Test the webservice with one (suspended) enrolment.
            ['numenrolments' => 1, 'timestart' => 1636542019, 'timeend' => 1836542019, 'suspend' => 1],
            // Test the webservice with several enrolments.
            ['numenrolments' => 5, 'timestart' => 1636542019, 'timeend' => 1836542019, 'suspend' => 0],
        ];
    }

    /**
     * Test the get_enrolments() webservice function with successful parameters.
     *
     * @param int $numenrolments The number of enrolments
     * @param int|null $timestart The timestart parameter
     * @param int|null $timeend The timeend parameter
     * @param int|null $suspend The suspend parameter
     * @dataProvider get_enrolments_successful_provider
     * @covers \enrol_semco\external::get_enrolments
     */
    public function test_get_enrolments_successful($numenrolments, $timestart, $timeend, $suspend): void {
        global $DB;

        // Create n users.
        for ($i = 1; $i <= $numenrolments; $i++) {
            ${'user' . $i} = $this->create_user($i);
        }

        // Create a course.
        $course = $this->create_course();

        // Initialize more enrolment data.
        for ($i = 1; $i <= $numenrolments; $i++) {
            ${'semcobookingid' . $i} = 12345 + $i;
        }

        // Enrol the users in the course.
        for ($i = 1; $i <= $numenrolments; $i++) {
            external::enrol_user(
                ${'user' . $i}->id,
                $course->id,
                ${'semcobookingid' . $i},
                $timestart + $i * 10,
                $timeend + $i * 10,
                $suspend
            );
        }

        // Get the enrolments for the course with the webservice which we want to test.
        $enrolments = external::get_enrolments($course->id);

        // Check the enrolments structure.
        $this->assertIsArray($enrolments);

        // Check the enrolments values.
        // If there are no enrolments.
        if ($numenrolments == 0) {
            // The array should be empty.
            $this->assertEmpty($enrolments);

            // Otherwise.
        } else {
            // The array should contain the expected number of enrolments.
            $this->assertNotEmpty($enrolments);
            $this->assertCount($numenrolments, $enrolments);

            // Iterate over the enrolments.
            for ($i = 1; $i <= $numenrolments; $i++) {
                // Check the enrolment details in the webservice return.
                $enrolment = array_shift($enrolments);
                $this->assertEquals(${'user' . $i}->id, $enrolment->userid);
                $this->assertEquals(${'semcobookingid' . $i}, $enrolment->semcobookingid);
                $this->assertEquals($timestart + $i * 10, $enrolment->timestart);
                $this->assertEquals($timeend + $i * 10, $enrolment->timeend);
                $this->assertEquals($suspend, $enrolment->suspend);

                // Get the SEMCO enrolment instance from the database.
                $enrolmentinstances = $DB->get_records('enrol', ['customchar1' => ${'semcobookingid' . $i}, 'enrol' => 'semco']);
                $this->assertCount(1, $enrolmentinstances);

                // Pick the only SEMCO enrolment instance.
                $enrolmentinstance = array_pop($enrolmentinstances);

                // Get the user enrolment instances from the database.
                $userenrolmentinstances = $DB->get_records('user_enrolments', ['enrolid' => $enrolmentinstance->id,
                        'userid' => ${'user' . $i}->id]);
                $this->assertCount(1, $userenrolmentinstances);

                // Pick the only user enrolment instance.
                $userenrolmentinstance = array_pop($userenrolmentinstances);

                // Check the rest of the enrolment details.
                $this->assertEquals($userenrolmentinstance->id, $enrolment->enrolid);
            }
        }
    }

    /**
     * Test the get_enrolments() webservice function with the coursenotexist exception.
     *
     * @covers \enrol_semco\external::get_enrolments
     */
    public function test_get_enrolments_coursenotexist_exception(): void {
        // Initialize a non-existing course ID.
        $courseid = 99999999;

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('coursenotexist', 'enrol_semco', $courseid));

        // Attempt to get enrolments for the non-existing course.
        external::get_enrolments($courseid);
    }

    /*
     * In the following tests, the get_course_completions() webservice will be tested.
     * It will be tested with all relevant permutations of successful parameters and
     * it will be tested with all relevant exceptions which may happen due to invalid parameters given by SEMCO for whatever reason.
     * It will not be tested for exceptions which may happen due to severe misconfigurations of the SEMCO plugin or SEMCO webservice
     * user role in Moodle or due to broken SEMCO plugin installations.
     */

    /**
     * Data provider for test_get_course_completions_successful.
     *
     * @return array
     */
    public static function get_course_completions_successful_provider(): array {
        return [
            // Test the webservice with zero enrolments.
            ['numenrolments' => 0],
            // Test the webservice with one enrolment in a course which cannot be completed.
            ['numenrolments' => 1, 'canbecompleted' => 0],
            // Test the webservice with one enrolment in a course which can be completed but has no completion info yet.
            ['numenrolments' => 1, 'canbecompleted' => 1, 'completioninfo' => 0],
            // Test the webservice with one enrolment in a course which can be completed and has completion info
            // but is not finished yet.
            ['numenrolments' => 1, 'canbecompleted' => 1, 'completioninfo' => 1, 'finished' => 0],
            // Test the webservice with one enrolment in a course which can be completed and has completion info
            // and is finished but has no grade data.
            ['numenrolments' => 1, 'canbecompleted' => 1, 'completioninfo' => 1, 'finished' => 1, 'gradedata' => 0],
            // Test the webservice with one enrolment in a course which can be completed and has completion info
            // and is finished and has grade data.
            ['numenrolments' => 1, 'canbecompleted' => 1, 'completioninfo' => 1, 'finished' => 1, 'gradedata' => 1],
            // Test the webservice with several enrolments in a course which can be completed and has completion info
            // and is finished and has grade data.
            ['numenrolments' => 5, 'canbecompleted' => 1, 'completioninfo' => 1, 'finished' => 1, 'gradedata' => 1],
        ];
    }

    /**
     * Test the get_course_completions() webservice function with successful parameters.
     *
     * @param int $numenrolments The number of enrolments
     * @param int $canbecompleted The canbecompleted parameter
     * @param int $completioninfo The completioninfo parameter
     * @param int $finished The finished parameter
     * @param int $gradedata The gradedata parameter
     * @dataProvider get_course_completions_successful_provider
     * @covers \enrol_semco\external::get_course_completions
     */
    public function test_get_course_completions_successful(
        $numenrolments,
        $canbecompleted = 0,
        $completioninfo = 0,
        $finished = 0,
        $gradedata = 0
    ): void {
        global $CFG, $DB;

        // Create n users.
        for ($i = 1; $i <= $numenrolments; $i++) {
            ${'user' . $i} = $this->create_user($i);
        }

        // Create a course.
        $course = $this->create_course();

        // Enrol the users into the course.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        for ($i = 1; $i <= $numenrolments; $i++) {
            $this->getDataGenerator()->enrol_user(${'user' . $i}->id, $course->id, $studentrole->id);
        }

        // Initialize more enrolment data.
        for ($i = 1; $i <= $numenrolments; $i++) {
            ${'semcobookingid' . $i} = 12345 + $i;
        }

        // Initialize completion data for later comparison.
        for ($i = 1; $i <= $numenrolments; $i++) {
            ${'timecompleted' . $i} = null;
            ${'grade' . $i} = null;
            ${'passed' . $i} = null;
        }
        $grademin = null;
        $grademax = null;
        $gradepass = null;

        // Enrol the users in the course.
        for ($i = 1; $i <= $numenrolments; $i++) {
            ${'enrolment' . $i} = external::enrol_user(${'user' . $i}->id, $course->id, ${'semcobookingid' . $i});
        }

        // Pick the enrolment IDs and put them into an array.
        $enrolmentids = [];
        for ($i = 1; $i <= $numenrolments; $i++) {
            $enrolmentids[] = ${'enrolment' . $i}['enrolid'];
        }

        // If the course can be completed.
        if ($canbecompleted == 1) {
            // Enable completion in the course.
            $course->enablecompletion = COMPLETION_ENABLED;
            $DB->update_record('course', $course);
            // And enable completion globally, just to be sure.
            $CFG->enablecompletion = true;
        } else {
            // Disable completion in the course.
            $course->enablecompletion = COMPLETION_DISABLED;
            $DB->update_record('course', $course);
        }

        // If the course has completion info.
        if ($completioninfo == 1) {
            for ($i = 1; $i <= $numenrolments; $i++) {
                // Create a completion object and mark the course as enrolled and in progress at midnight today.
                $completion = new \completion_completion(['userid' => ${'user' . $i}->id, 'course' => $course->id]);
                $completion->mark_enrolled(strtotime('today midnight'));
                $completion->mark_inprogress(strtotime('today midnight'));
            }

            // If the course is finished.
            if ($finished == 1) {
                // Set the course as finished at 1am today.
                for ($i = 1; $i <= $numenrolments; $i++) {
                    $completion = new \completion_completion(['userid' => ${'user' . $i}->id, 'course' => $course->id]);
                    ${'timecompleted' . $i} = strtotime('today ' . $i . 'am');
                    $completion->mark_complete(${'timecompleted' . $i});
                }

                // If the course has grade data.
                if ($gradedata == 1) {
                    // Create an assignment.
                    $assigngenerator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
                    $assign = $assigngenerator->create_instance(['course' => $course]);

                    // Get the grade item for the course.
                    $gradeitem = \grade_item::fetch_course_item($course->id);

                    // Set the grade item parameters.
                    $grademin = 0;
                    $grademax = 100;
                    $gradepass = 30;
                    $gradeitem->grademin = $grademin;
                    $gradeitem->grademax = $grademax;
                    $gradeitem->gradepass = $gradepass;
                    $gradeitem->update();

                    // Set grades for the users.
                    for ($i = 1; $i <= $numenrolments; $i++) {
                        ${'grade' . $i} = 10 * $i;
                        ${'passed' . $i} = ${'grade' . $i} >= $gradepass;
                        $gradeitem->update_final_grade(${'user' . $i}->id, ${'grade' . $i});
                    }
                }
            }
        }

        // Get the course completions for the enrolments with the webservice which we want to test.
        $completions = external::get_course_completions($enrolmentids);

        // Check the completions structure.
        $this->assertIsArray($completions);

        // Check the completions values.
        // If there are no enrolments.
        if ($numenrolments == 0) {
            // The array should be empty.
            $this->assertEmpty($completions);

            // Otherwise.
        } else {
            // The array should contain the expected number of completions.
            $this->assertNotEmpty($completions);
            $this->assertCount($numenrolments, $completions);

            // Iterate over the completions.
            for ($i = 1; $i <= $numenrolments; $i++) {
                // Check the completion details in the webservice return.
                $completion = array_shift($completions);
                $this->assertEquals(${'user' . $i}->id, $completion['userid']);
                $this->assertEquals(${'semcobookingid' . $i}, $completion['semcobookingid']);
                $this->assertEquals((bool) $canbecompleted, $completion['canbecompleted']);
                $this->assertEquals((bool) $finished, $completion['completed']);
                $this->assertEquals(${'timecompleted' . $i}, $completion['timecompleted']);
                $this->assertEquals(${'grade' . $i}, $completion['finalgrade']);
                $this->assertEquals(${'grade' . $i}, $completion['finalgraderaw']);
                $this->assertEquals($grademin, $completion['grademinraw']);
                $this->assertEquals($grademax, $completion['grademaxraw']);
                $this->assertEquals($gradepass, $completion['gradepassraw']);
                $this->assertEquals(${'passed' . $i}, $completion['passed']);
            }
        }
    }

    /**
     * Test the get_course_completions() webservice function with the enrolnouserinstance exception.
     *
     * @covers \enrol_semco\external::get_course_completions
     */
    public function test_get_course_completions_enrolnouserinstance_exception(): void {
        // Initialize a non-existing enrolment ID.
        $enrolmentid = 99999999;

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('enrolnouserinstance', 'enrol_semco', $enrolmentid));

        // Attempt to get course completions for the non-existing enrolment.
        external::get_course_completions([$enrolmentid]);
    }

    /**
     * Test the get_course_completions() webservice function with the enrolnoinstance exception.
     *
     * @covers \enrol_semco\external::get_course_completions
     */
    public function test_get_course_completions_enrolnoinstance_exception(): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Enrol the user in the course with the webservice which we want to test.
        $enrolreturn = external::enrol_user($user->id, $course->id, $semcobookingid);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);
        $this->assertCount(1, $enrolmentinstances);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Delete the enrolment instance to simulate the exception.
        $DB->delete_records('enrol', ['id' => $enrolmentinstance->id]);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('enrolnoinstance', 'enrol_semco', $enrolreturn['enrolid']));

        // Attempt to get course completions, which should throw the exception.
        external::get_course_completions([$enrolreturn['enrolid']]);
    }

    /**
     * Test the get_course_completions() webservice function with the getcoursecompletionsmaxrequest exception.
     *
     * @covers \enrol_semco\external::get_course_completions
     */
    public function test_get_course_completions_getcoursecompletionsmaxrequest_exception(): void {
        global $DB;

        // Prepare the array of (too many) enrolment IDs.
        $enrolids = array_map(function () {
            return rand(10000000, 99999999);
        }, range(1, 101));

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string(
            'getcoursecompletionsmaxrequest',
            'enrol_semco',
            ENROL_SEMCO_GET_COURSE_COMPLETIONS_MAXREQUEST
        ));

        // Attempt to get course completions, which should throw the exception.
        external::get_course_completions($enrolids);
    }

    /**
     * Test the reset_course_completion() webservice function with successful parameters.
     *
     * @covers \enrol_semco\external::reset_course_completion
     */
    public function test_reset_course_completion_successful(): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Set the recompletion config for this course.
        $recompletionconfig['course'] = $course->id;
        $recompletionconfig['name'] = 'recompletiontype';
        $recompletionconfig['value'] = \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND;
        $DB->insert_record('local_recompletion_config', $recompletionconfig);
        $recompletionconfig['course'] = $course->id;
        $recompletionconfig['name'] = 'archivecompletiondata';
        $recompletionconfig['value'] = false;
        $DB->insert_record('local_recompletion_config', $recompletionconfig);
        $recompletionconfig['course'] = $course->id;
        $recompletionconfig['name'] = 'deletegradedata';
        $recompletionconfig['value'] = true;
        $DB->insert_record('local_recompletion_config', $recompletionconfig);

        // Enrol the user in the course.
        external::enrol_user($user->id, $course->id, $semcobookingid);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Get the user enrolment instances from the database.
        $userenrolmentinstances = $DB->get_records('user_enrolments', ['enrolid' => $enrolmentinstance->id, 'userid' => $user->id]);

        // Pick the only user enrolment instance.
        $userenrolmentinstance = array_pop($userenrolmentinstances);

        // Reset the completion of the user in the course with the webservice which we want to test.
        $webservicereturn = external::reset_course_completion($userenrolmentinstance->id);

        // Check the webservice return structure.
        $this->assertNotEmpty($webservicereturn);
        $this->assertArrayHasKey('result', $webservicereturn);
        $this->assertArrayHasKey('warnings', $webservicereturn);

        // Check the webservice return values.
        $this->assertEquals(true, $webservicereturn['result']);
        $this->assertEmpty($webservicereturn['warnings']);

        // Verify that the completion data has been reset in the database.
        $completion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        $this->assertEmpty($completion);
    }

    /**
     * Test the reset_course_completion() webservice function with the enrolnouserinstance exception.
     *
     * @covers \enrol_semco\external::reset_course_completion
     */
    public function test_reset_course_completion_enrolnouserinstance_exception(): void {
        // Initialize a non-existing enrolment ID.
        $enrolmentid = 99999;

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('enrolnouserinstance', 'enrol_semco', $enrolmentid));

        // Reset the completion with the webservice which we want to test.
        external::reset_course_completion($enrolmentid);
    }

    /**
     * Test the reset_course_completion() webservice function with the enrolnoinstance exception.
     *
     * @covers \enrol_semco\external::reset_course_completion
     */
    public function test_reset_course_completion_enrolnoinstance_exception(): void {
        global $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Set the recompletion config for this course.
        $recompletionconfig['course'] = $course->id;
        $recompletionconfig['name'] = 'recompletiontype';
        $recompletionconfig['value'] = \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND;
        $DB->insert_record('local_recompletion_config', $recompletionconfig);

        // Enrol the user in the course.
        $enrolreturn = external::enrol_user($user->id, $course->id, $semcobookingid);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Delete the enrolment instance to simulate the exception.
        $DB->delete_records('enrol', ['id' => $enrolmentinstance->id]);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('enrolnoinstance', 'enrol_semco', $enrolreturn['enrolid']));

        // Reset the completion of the user in the course with the webservice which we want to test.
        external::reset_course_completion($enrolreturn['enrolid']);
    }

    /**
     * Data provider for test_reset_course_completion_localrecompletion_exceptions.
     *
     * @return array
     */
    public static function reset_course_completion_localrecompletion_exceptions_provider(): array {
        global $CFG;

        // Require local_recompletion plugin library.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        return [
            // Test the localrecompletionnotenabled exception.
            ['recompletiontype' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_DISABLED,
                    'exception' => 'localrecompletionnotenabled'],
            // Test the localrecompletionnotondemand exception.
            ['recompletiontype' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_SCHEDULE,
                    'exception' => 'localrecompletionnotondemand'],
            ['recompletiontype' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_PERIOD,
                    'exception' => 'localrecompletionnotondemand'],
        ];
    }

    /**
     * Test the reset_course_completion() webservice function with multiple exceptions.
     *
     * @param ing $recompletiontype The recompletiontype parameter
     * @param string $exception The exception exception
     *
     * @dataProvider reset_course_completion_localrecompletion_exceptions_provider
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_reset_course_completion_localrecompletion_exceptions($recompletiontype, $exception): void {
        global $CFG, $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Set the recompletion config for this course.
        $recompletionconfig['course'] = $course->id;
        $recompletionconfig['name'] = 'recompletiontype';
        $recompletionconfig['value'] = $recompletiontype;
        $DB->insert_record('local_recompletion_config', $recompletionconfig);

        // Enrol the user in the course.
        external::enrol_user($user->id, $course->id, $semcobookingid);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Get the user enrolment instances from the database.
        $userenrolmentinstances = $DB->get_records('user_enrolments', ['enrolid' => $enrolmentinstance->id, 'userid' => $user->id]);

        // Pick the only user enrolment instance.
        $userenrolmentinstance = array_pop($userenrolmentinstances);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $localrecompletionurl = new moodle_url('/local/recompletion/recompletion.php', ['id' => $course->id]);
        $this->expectExceptionMessage(get_string($exception, 'enrol_semco', $localrecompletionurl->out()));

        // Reset the completion of the user in the course with the webservice which we want to test.
        external::reset_course_completion($userenrolmentinstance->id);
    }

    /**
     * Test the enrol_user() webservice function with the localrecompletionnotinstalled exception.
     *
     * @covers \enrol_semco\external::enrol_user
     */
    public function test_reset_course_completion_localrecompletionnotinstalled_exception(): void {
        global $CFG, $DB;

        // Create a user and a course.
        $user = $this->create_user();
        $course = $this->create_course();

        // Initialize more enrolment data.
        $semcobookingid = 12345;

        // Simulate that the plugin is not installed by setting a global variable.
        $CFG->localrecompletionnotinstalled = true;

        // Enrol the user in the course.
        external::enrol_user($user->id, $course->id, $semcobookingid);

        // Get the SEMCO enrolment instances from the database.
        $enrolmentinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'semco']);

        // Pick the only SEMCO enrolment instance.
        $enrolmentinstance = array_pop($enrolmentinstances);

        // Get the user enrolment instances from the database.
        $userenrolmentinstances = $DB->get_records('user_enrolments', ['enrolid' => $enrolmentinstance->id, 'userid' => $user->id]);

        // Pick the only user enrolment instance.
        $userenrolmentinstance = array_pop($userenrolmentinstances);

        // Expect the specified exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('localrecompletionnotinstalled', 'enrol_semco'));

        // Reset the completion of the user in the course with the webservice which we want to test.
        external::reset_course_completion($userenrolmentinstance->id);
    }

    /**
     * The following functions are helper functions for running the tests.
     */

    /**
     * Create a user for running the tests.
     *
     * @param int $counter An (integer) counter for creating unique user data, by default 1.
     * @return \stdClass The user object.
     */
    private function create_user($counter = 1): \stdClass {
        // Create a user.
        // Normally, this user would be created by SEMCO beforehand with the core_user_create_users webservice.
        $userrecord = [
            'username' => 'testuser' . $counter,
            'password' => 'password',
            'firstname' => 'Test',
            'lastname' => 'User ' . $counter,
            'idnumber' => 'KN-' . $counter,
            'email' => 'foo' . $counter . '@bar.com',
        ];
        $user = $this->getDataGenerator()->create_user($userrecord);

        // Return the user.
        return $user;
    }

    /**
     * Create a course for running the tests.
     *
     * @param int $counter An (integer) counter for creating unique course data, by default 1.
     * @return \stdClass The course object.
     */
    private function create_course($counter = 1): \stdClass {
        // Create a course.
        // Normally, this course would be created by the Moodle manager beforehand.
        $courserecord = [
            'fullname' => 'Test course ' . $counter,
            'shortname' => 'tc' . $counter,
            'idnumber' => 'SEMCO-' . $counter . '2345',
        ];
        $course = $this->getDataGenerator()->create_course($courserecord);

        // Return the course.
        return $course;
    }
}
