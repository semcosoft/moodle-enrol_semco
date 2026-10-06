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

namespace enrol_semco\check;

use core\check\result;
use enrol_semco\healthcheck\healthcheck as healthcheckitem;

/**
 * Enrolment method "SEMCO" - PHPUnit tests for the plugin's Checks API integration.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The healthcheck_test class.
 *
 * @covers \enrol_semco\check\healthcheck
 * @covers \enrol_semco\healthcheck\healthcheck
 * @covers \enrol_semco\healthcheck\manager
 * @covers \enrol_semco\healthcheck\check\allowaccountssameemail
 * @covers \enrol_semco\healthcheck\check\authmethod
 * @covers \enrol_semco\healthcheck\check\capabilitiesexclusive
 * @covers \enrol_semco\healthcheck\check\capabilitiesregistered
 * @covers \enrol_semco\healthcheck\check\coursecompletedmessage
 * @covers \enrol_semco\healthcheck\check\enrolmentroleconfigured
 * @covers \enrol_semco\healthcheck\check\enrolmentroleviewparticipants
 * @covers \enrol_semco\healthcheck\check\enrolpluginenabled
 * @covers \enrol_semco\healthcheck\check\externalserviceconfig
 * @covers \enrol_semco\healthcheck\check\externalserviceexists
 * @covers \enrol_semco\healthcheck\check\externalservicefunctions
 * @covers \enrol_semco\healthcheck\check\manualauthlockedfields
 * @covers \enrol_semco\healthcheck\check\messaging
 * @covers \enrol_semco\healthcheck\check\profilefield
 * @covers \enrol_semco\healthcheck\check\profilefield_branchtoken
 * @covers \enrol_semco\healthcheck\check\profilefield_userbirthday
 * @covers \enrol_semco\healthcheck\check\profilefield_usercompany
 * @covers \enrol_semco\healthcheck\check\profilefield_userid
 * @covers \enrol_semco\healthcheck\check\profilefield_userplaceofbirth
 * @covers \enrol_semco\healthcheck\check\profilefieldcategory
 * @covers \enrol_semco\healthcheck\check\recompletionactivities
 * @covers \enrol_semco\healthcheck\check\recompletioninstalled
 * @covers \enrol_semco\healthcheck\check\recompletionnotify
 * @covers \enrol_semco\healthcheck\check\recompletionondemand
 * @covers \enrol_semco\healthcheck\check\recompletiongrades
 * @covers \enrol_semco\healthcheck\check\recompletionarchive
 * @covers \enrol_semco\healthcheck\check\recompletionrestrictenrol
 * @covers \enrol_semco\healthcheck\check\recompletionresetmycompletion
 * @covers \enrol_semco\healthcheck\check\recompletionmanage
 * @covers \enrol_semco\healthcheck\check\restprotocol
 * @covers \enrol_semco\healthcheck\check\roleassignallowed
 * @covers \enrol_semco\healthcheck\check\rolecapabilities
 * @covers \enrol_semco\healthcheck\check\rolecapabilitiesmoodle
 * @covers \enrol_semco\healthcheck\check\rolecapabilitiessemco
 * @covers \enrol_semco\healthcheck\check\rolecapabilitiessurplus
 * @covers \enrol_semco\healthcheck\check\rolecapabilityrest
 * @covers \enrol_semco\healthcheck\check\rolecontextlevel
 * @covers \enrol_semco\healthcheck\check\roleexists
 * @covers \enrol_semco\healthcheck\check\selfenrolment
 * @covers \enrol_semco\healthcheck\check\useractive
 * @covers \enrol_semco\healthcheck\check\userauthmethod
 * @covers \enrol_semco\healthcheck\check\userexists
 * @covers \enrol_semco\healthcheck\check\userprofile
 * @covers \enrol_semco\healthcheck\check\userroleassignment
 * @covers \enrol_semco\healthcheck\check\userserviceauthorised
 * @covers \enrol_semco\healthcheck\check\userserviceiprestriction
 * @covers \enrol_semco\healthcheck\check\usertoken
 * @covers \enrol_semco\healthcheck\check\usertokeniprestriction
 * @covers \enrol_semco\healthcheck\check\webservicesenabled
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class healthcheck_test extends \advanced_testcase {
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

        // Make sure that the plugin installation is intact even if this Moodle instance was installed from scratch.
        // In that case, the REST capability is not assigned by the plugin installer but by an ad-hoc task which has
        // not run yet in the test environment.
        $semcorole = $DB->get_record('role', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME], '*', MUST_EXIST);
        assign_capability('webservice/rest:use', CAP_ALLOW, $semcorole->id, \context_system::instance()->id, true);
        $DB->delete_records('task_adhoc', [
            'classname' => '\\' . \enrol_semco\task\set_webservice_capability::class,
        ]);

        // Reset the health check caches so that each test starts with a clean state.
        healthcheckitem::reset_caches();

        // The check covers the recompletion and the recommendations category as well, and a stock Moodle instance does
        // not follow all of these recommendations. Mute the affected items, just as an admin would do who has decided
        // against them, so that each test starts with a check which is fine. This is restricted to the items which do
        // not cover the plugin installation on purpose: An item of the installation categories which needs attention
        // means that the test environment is broken, and muting it would let test_check_reports_ok() pass nonetheless.
        foreach (\enrol_semco\healthcheck\manager::get_healthchecks_needing_attention() as $item) {
            $this->assertNotSame(
                'installation',
                \enrol_semco\healthcheck\manager::get_status_context($item),
                'Health check "' . $item->get_id() . '" reports a broken plugin installation in the test ' .
                    'environment, it must not be muted.'
            );
            \enrol_semco\healthcheck\manager::set_healthcheck_muted($item->get_id(), true);
        }
    }

    /**
     * Test that the check takes the recommended Moodle settings into account, unless they are muted.
     */
    public function test_check_covers_recommendations_unless_muted(): void {
        // Do not follow one of the recommendations and make sure that the item is not muted.
        set_config('messaging', 1);
        \enrol_semco\healthcheck\manager::set_healthcheck_muted('messaging', false);
        healthcheckitem::reset_caches();

        // The check must report the warning of the recommendation.
        $result = (new healthcheck())->get_result();
        $this->assertEquals(result::WARNING, $result->get_status());
        $this->assertStringContainsString(
            get_string('healthcheck_messaging_title', 'enrol_semco'),
            $result->get_details()
        );

        // After the item has been muted, the check is fine again.
        \enrol_semco\healthcheck\manager::set_healthcheck_muted('messaging', true);
        $result = (new healthcheck())->get_result();
        $this->assertEquals(result::OK, $result->get_status());
    }

    /**
     * Test that the check is registered in the Moodle Checks API.
     */
    public function test_check_is_registered(): void {
        // Get the status checks which are registered in Moodle.
        $checks = \core\check\manager::get_status_checks();

        // Our check must be among them.
        $found = false;
        foreach ($checks as $check) {
            if ($check instanceof healthcheck) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'The SEMCO health check is not registered in the Moodle Checks API.');
    }

    /**
     * Test that the check reports an intact installation.
     */
    public function test_check_reports_ok(): void {
        // Get the check result.
        $check = new healthcheck();
        $result = $check->get_result();

        // The installation is intact.
        $this->assertEquals(result::OK, $result->get_status());

        // The check must have a name and an action link and its language strings must exist.
        $this->assertStringNotContainsString('[[', $check->get_name());
        $this->assertStringNotContainsString('[[', $result->get_summary());
        $this->assertStringNotContainsString('[[', $result->get_details());
        $this->assertNotNull($check->get_action_link());
    }

    /**
     * Test that the check reports an error if the SEMCO integration is broken.
     */
    public function test_check_reports_error(): void {
        // Break one aspect of the plugin installation which the SEMCO integration cannot work without.
        set_config('enablewebservices', 0);
        healthcheckitem::reset_caches();

        // Get the check result.
        $result = (new healthcheck())->get_result();

        // The check must report an error and not just a warning.
        $this->assertEquals(result::ERROR, $result->get_status());

        // And the details must name the affected health check item.
        $this->assertStringContainsString(
            get_string('healthcheck_webservicesenabled_title', 'enrol_semco'),
            $result->get_details()
        );
    }

    /**
     * Test that the check reports an info result if the deviations are notices only.
     *
     * A notice neither breaks nor endangers the SEMCO integration, thus it must not colour the Moodle system status
     * page as if something had to be repaired.
     */
    public function test_check_reports_info(): void {
        global $DB;

        // Deviate one aspect of the plugin installation which is reported as a notice only.
        $DB->set_field(
            'external_tokens',
            'creatorid',
            0,
            ['userid' => $DB->get_field('user', 'id', ['username' => ENROL_SEMCO_ROLEANDUSERNAME])]
        );
        healthcheckitem::reset_caches();

        // The check must report an info result and not a warning.
        $result = (new healthcheck())->get_result();
        $this->assertEquals(result::INFO, $result->get_status());

        // And the details must name the affected health check item.
        $this->assertStringContainsString(
            get_string('healthcheck_usertoken_title', 'enrol_semco'),
            $result->get_details()
        );
    }

    /**
     * Test that the check reports a warning if the SEMCO integration still works.
     */
    public function test_check_reports_warning(): void {
        global $DB;

        // Break one aspect of the plugin installation which does not stop the SEMCO integration from working.
        $DB->set_field(
            'external_services',
            'restrictedusers',
            0,
            ['shortname' => ENROL_SEMCO_SERVICENAME]
        );
        healthcheckitem::reset_caches();

        // Get the check result.
        $result = (new healthcheck())->get_result();

        // The check must report a warning and not an error.
        $this->assertEquals(result::WARNING, $result->get_status());

        // And the details must name the affected health check item.
        $this->assertStringContainsString(
            get_string('healthcheck_externalserviceconfig_title', 'enrol_semco'),
            $result->get_details()
        );
    }
}
