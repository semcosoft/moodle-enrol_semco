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
use core\exception\required_capability_exception;

/**
 * Enrolment method "SEMCO" - PHPUnit tests for the plugin's webservice capabilities.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The capabilities_test class.
 *
 * The plugin's webservice capabilities are all assigned to the SEMCO webservice role during the plugin installation and
 * the SEMCO webservice user holds that role. All other PHPUnit tests of this plugin therefore run with a complete set of
 * capabilities and never notice if a capability check was removed, weakened or checked in the wrong context.
 *
 * This test closes that gap. For each capability, it runs the webservice function which the capability is supposed to
 * guard twice: once with the capability assigned (which must work) and once with exactly this one capability revoked
 * from the SEMCO webservice role (which must be rejected). The capability is revoked with the plugin's data generator so
 * that the role itself stays untouched otherwise.
 *
 * The 'enrol/semco:viewreport' capability is not covered here as it does not guard a webservice function. It is covered
 * by the Behat feature tests/behat/report.feature instead.
 *
 * @covers \enrol_semco\external
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capabilities_test extends \advanced_testcase {
    /** @var \stdClass The SEMCO webservice user. */
    private \stdClass $semcouser;

    /** @var \context_system The system context. */
    private \context_system $systemcontext;

    /** @var \enrol_semco_generator The plugin's data generator. */
    private \enrol_semco_generator $semcogenerator;

    /**
     * Setup testcase.
     */
    public function setUp(): void {
        global $CFG, $DB;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Require webservice library (which holds the webservice class and the webservice_access_exception class).
        require_once($CFG->dirroot . '/webservice/lib.php');

        // Call the parent setup.
        parent::setUp();

        // Reset after the test.
        $this->resetAfterTest(true);

        // Get the system context as the plugin assigns all capabilities to the SEMCO webservice role in this context.
        $this->systemcontext = \context_system::instance();

        // Get the plugin's data generator.
        $this->semcogenerator = $this->getDataGenerator()->get_plugin_generator('enrol_semco');

        // Get the SEMCO webservice user which was created during the plugin installation.
        $this->semcouser = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME], '*', MUST_EXIST);

        // Run the tests as SEMCO webservice user.
        $this->setUser($this->semcouser);
    }

    /**
     * Data provider for test_webservice_function_requires_its_capability.
     *
     * It lists all capabilities which guard one of the plugin's webservice functions, i.e. all capabilities from
     * db/access.php except 'enrol/semco:usewebservice' (which guards the webservice as a whole and is tested separately)
     * and 'enrol/semco:viewreport' (which guards the enrolment report and is tested with Behat).
     *
     * @return array
     */
    public static function webservice_capability_provider(): array {
        return [
            'enrol_user()' => ['capability' => 'enrol/semco:enrol'],
            'unenrol_user()' => ['capability' => 'enrol/semco:unenrol'],
            'edit_enrolment()' => ['capability' => 'enrol/semco:editenrolment'],
            'get_enrolments()' => ['capability' => 'enrol/semco:getenrolments'],
            'get_course_completions()' => ['capability' => 'enrol/semco:getcoursecompletions'],
            'reset_course_completion()' => ['capability' => 'enrol/semco:resetcoursecompletion'],
            'check_user_existence_by_field()' => ['capability' => 'enrol/semco:checkuserexistence'],
        ];
    }

    /**
     * Test that each webservice function is really guarded by its own capability.
     *
     * @param string $capability The capability which is under test
     * @dataProvider webservice_capability_provider
     */
    public function test_webservice_function_requires_its_capability($capability): void {
        // Skip the reset_course_completion() dataset if local_recompletion is not installed. The capability check itself
        // would be reachable without the companion plugin, but this test also verifies that the webservice function does
        // its job while the capability is assigned - and neither the fixture with its recompletion configuration nor the
        // reset itself can be built without local_recompletion.
        if ($capability == 'enrol/semco:resetcoursecompletion' && enrol_semco_check_local_recompletion() != true) {
            $this->markTestSkipped('The local_recompletion plugin is not installed, ' .
                    'so the successful run of reset_course_completion() cannot be established.');
        }

        // To start with, the SEMCO webservice user holds the capability as the plugin installation has assigned it to
        // the SEMCO webservice role.
        $this->assertTrue(has_capability($capability, $this->systemcontext, $this->semcouser));

        // With the capability assigned, the webservice function does its job.
        $fixture = $this->create_fixture($capability, 1);
        $this->assert_webservice_function_succeeds($capability, $fixture);

        // Build a second, untouched fixture while the SEMCO webservice role is still complete.
        // This is necessary as the first webservice call might have consumed the first fixture.
        $fixture = $this->create_fixture($capability, 2);

        // Revoke exactly the one capability which is under test from the SEMCO webservice role.
        $this->semcogenerator->set_webservice_role_capability($capability, false);

        // Cross-check that the revocation was effective.
        $this->assertFalse(has_capability($capability, $this->systemcontext, $this->semcouser));

        // Cross-check that the revocation did not touch the other capabilities of the SEMCO webservice role.
        // This makes sure that the upcoming exception is really caused by the capability which is under test.
        foreach (self::webservice_capability_provider() as $dataset) {
            if ($dataset['capability'] != $capability) {
                $this->assertTrue(has_capability($dataset['capability'], $this->systemcontext, $this->semcouser));
            }
        }

        // Expect the specified exception.
        $this->expectException(required_capability_exception::class);
        $this->expectExceptionMessage(get_capability_string($capability));

        // Run exactly the same webservice call again which succeeded before.
        $this->call_webservice_function($capability, $fixture);
    }

    /**
     * Test that the SEMCO webservice as a whole is guarded by the 'enrol/semco:usewebservice' capability.
     *
     * Compared to the capabilities which guard the individual webservice functions, this capability is not checked by the
     * plugin's code but is declared as the service's required capability in db/services.php. It is therefore enforced by
     * Moodle core in two places which are both covered by this test:
     * Firstly, when a token for the SEMCO webservice is created, and
     * secondly, when an existing token is used to authenticate an incoming webservice request.
     */
    public function test_webservice_requires_the_usewebservice_capability(): void {
        global $CFG, $DB;

        // Make sure that webservices are enabled (which the plugin installation has done already).
        $CFG->enablewebservices = 1;

        // Get the SEMCO webservice which was installed during the plugin installation.
        $service = $DB->get_record('external_services', ['shortname' => ENROL_SEMCO_SERVICENAME], '*', MUST_EXIST);

        // The service is guarded by the capability, i.e. db/services.php is set up as expected.
        $this->assertEquals('enrol/semco:usewebservice', $service->requiredcapability);

        // To start with, the SEMCO webservice user holds the capability as the plugin installation has assigned it to
        // the SEMCO webservice role.
        $this->assertTrue(has_capability('enrol/semco:usewebservice', $this->systemcontext, $this->semcouser));

        // With the capability assigned, a token for the SEMCO webservice can be created for the SEMCO webservice user.
        // The token is created as admin as this is what a Moodle administrator would do in the web interface.
        $this->setAdminUser();
        $token = \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $service,
            $this->semcouser->id,
            $this->systemcontext
        );
        $this->assertNotEmpty($token);

        // And with the capability assigned, the token can be used to authenticate an incoming webservice request.
        $webservicemanager = new \webservice();
        $authentication = $webservicemanager->authenticate_user($token);
        $this->assertEquals($this->semcouser->id, $authentication['user']->id);
        $this->assertEquals(ENROL_SEMCO_SERVICENAME, $authentication['service']->shortname);

        // Now, revoke the capability from the SEMCO webservice role.
        // The token which was created before stays in place, just as it would in a real Moodle instance where an
        // administrator revokes the capability after the SEMCO webservice has been set up.
        $this->setUser($this->semcouser);
        $this->semcogenerator->set_webservice_role_capability('enrol/semco:usewebservice', false);

        // Cross-check that the revocation was effective.
        $this->assertFalse(has_capability('enrol/semco:usewebservice', $this->systemcontext, $this->semcouser));

        // Without the capability, another token for the SEMCO webservice cannot be created anymore.
        $this->setAdminUser();
        try {
            \core_external\util::generate_token(
                EXTERNAL_TOKEN_PERMANENT,
                $service,
                $this->semcouser->id,
                $this->systemcontext
            );
            $this->fail('A token for the SEMCO webservice was created even though the required capability was revoked.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nocapabilitytousethisservice', $e->errorcode);
        }

        // And without the capability, the existing token does not authenticate an incoming webservice request anymore.
        try {
            $webservicemanager->authenticate_user($token);
            $this->fail('An incoming webservice request was authenticated even though the required capability was revoked.');
        } catch (\webservice_access_exception $e) {
            // Moodle core reports the generic access exception to the caller and names the missing capability only in the
            // debug info. We check the debug info as this is the only proof that the request was really rejected because
            // of the capability which is under test.
            $this->assertStringContainsString(get_string('accessexception', 'webservice'), $e->getMessage());
            $this->assertStringContainsString('enrol/semco:usewebservice', $e->debuginfo);
        }
    }

    /**
     * The following functions are helper functions for running the tests.
     */

    /**
     * Create the fixture which is needed to run the webservice function which is guarded by the given capability.
     *
     * @param string $capability The capability which is under test
     * @param int $counter An (integer) counter for creating unique fixture data
     * @return \stdClass An object holding the created user, the created course and the created SEMCO enrolment ID
     */
    private function create_fixture($capability, $counter): \stdClass {
        global $DB;

        // Initialize the fixture.
        $fixture = new \stdClass();

        // Create a user.
        // Normally, this user would be created by SEMCO beforehand with the core_user_create_users webservice.
        $fixture->user = $this->getDataGenerator()->create_user([
            'username' => 'testuser' . $counter,
            'firstname' => 'Test',
            'lastname' => 'User ' . $counter,
            'idnumber' => 'KN-' . $counter,
            'email' => 'foo' . $counter . '@bar.com',
        ]);

        // Create a course.
        // Normally, this course would be created by the Moodle manager beforehand.
        $fixture->course = $this->getDataGenerator()->create_course([
            'fullname' => 'Test course ' . $counter,
            'shortname' => 'tc' . $counter,
            'idnumber' => 'SEMCO-' . $counter . '2345',
        ]);

        // Initialize the SEMCO booking ID which the webservice call itself will use.
        // The plugin requires the booking IDs to be unique within the whole Moodle instance.
        $fixture->semcobookingid = 20000 + $counter;

        // The reset_course_completion() webservice function needs a course with an enabled recompletion.
        if ($capability == 'enrol/semco:resetcoursecompletion') {
            $recompletionconfig = [
                'recompletiontype' => $this->semcogenerator->resolve_recompletiontype('ondemand'),
                'archivecompletiondata' => false,
                'deletegradedata' => true,
            ];
            foreach ($recompletionconfig as $name => $value) {
                $DB->insert_record(
                    'local_recompletion_config',
                    ['course' => $fixture->course->id, 'name' => $name, 'value' => $value]
                );
            }
        }

        // All webservice functions except enrol_user() need an existing SEMCO enrolment to work on.
        // We do not create one for enrol_user() as the plugin would reject the upcoming enrolment as an overlapping
        // enrolment of the same user in the same course.
        if ($capability != 'enrol/semco:enrol') {
            // Create the SEMCO enrolment with the plugin's data generator, i.e. exactly the way SEMCO would do it.
            $enrolment = $this->semcogenerator->create_enrolment([
                'userid' => $fixture->user->id,
                'courseid' => $fixture->course->id,
                'semcobookingid' => 10000 + $counter,
            ]);
            $fixture->enrolid = $enrolment['enrolid'];
        }

        // Return the fixture.
        return $fixture;
    }

    /**
     * Call the webservice function which is guarded by the given capability.
     *
     * @param string $capability The capability which is under test
     * @param \stdClass $fixture The fixture as returned by create_fixture()
     * @return array The webservice return array
     */
    private function call_webservice_function($capability, \stdClass $fixture): array {
        switch ($capability) {
            case 'enrol/semco:enrol':
                return external::enrol_user($fixture->user->id, $fixture->course->id, $fixture->semcobookingid);
            case 'enrol/semco:unenrol':
                return external::unenrol_user($fixture->enrolid);
            case 'enrol/semco:editenrolment':
                return external::edit_enrolment($fixture->enrolid, $fixture->semcobookingid);
            case 'enrol/semco:getenrolments':
                return external::get_enrolments($fixture->course->id);
            case 'enrol/semco:getcoursecompletions':
                return external::get_course_completions([$fixture->enrolid]);
            case 'enrol/semco:resetcoursecompletion':
                return external::reset_course_completion($fixture->enrolid);
            case 'enrol/semco:checkuserexistence':
                return external::check_user_existence_by_field($fixture->user->email);
            default:
                throw new \coding_exception('There is no webservice call defined for the capability ' . $capability);
        }
    }

    /**
     * Call the webservice function which is guarded by the given capability and check that it has really done its job.
     *
     * The checks are deliberately kept lightweight as the webservice functions themselves are tested in detail in
     * enrol_semco_test. Here, we just have to be sure that the call was not rejected and that it did not fail for any
     * other reason than a missing capability.
     *
     * @param string $capability The capability which is under test
     * @param \stdClass $fixture The fixture as returned by create_fixture()
     * @return void
     */
    private function assert_webservice_function_succeeds($capability, \stdClass $fixture): void {
        // Call the webservice function.
        $webservicereturn = $this->call_webservice_function($capability, $fixture);

        // Check the webservice return.
        switch ($capability) {
            case 'enrol/semco:enrol':
                $this->assertGreaterThan(0, $webservicereturn['enrolid']);
                $this->assertEquals($fixture->user->id, $webservicereturn['userid']);
                break;
            case 'enrol/semco:unenrol':
            case 'enrol/semco:editenrolment':
            case 'enrol/semco:resetcoursecompletion':
                $this->assertTrue($webservicereturn['result']);
                break;
            case 'enrol/semco:getenrolments':
                $this->assertCount(1, $webservicereturn);
                $enrolment = array_shift($webservicereturn);
                $this->assertEquals($fixture->enrolid, $enrolment->enrolid);
                break;
            case 'enrol/semco:getcoursecompletions':
                $this->assertCount(1, $webservicereturn);
                $completion = array_shift($webservicereturn);
                $this->assertEquals($fixture->enrolid, $completion['enrolid']);
                break;
            case 'enrol/semco:checkuserexistence':
                $this->assertTrue($webservicereturn['userexists']);
                $this->assertEquals($fixture->user->id, $webservicereturn['userid']);
                break;
        }
    }
}
