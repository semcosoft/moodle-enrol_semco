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

/**
 * Enrolment method "SEMCO" - PHPUnit tests for the ad-hoc task which sets the webservice capability.
 *
 * @package    enrol_semco
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\task;

use context_system;

/**
 * Enrolment method "SEMCO" - PHPUnit tests for the ad-hoc task which sets the webservice capability.
 *
 * During an initial installation of Moodle, the plugin is not able to assign the 'webservice/rest:use' capability to the
 * SEMCO webservice role as the webservice subsystem is installed after this plugin and the capability does not exist yet
 * at this point in time. Instead, the plugin queues this ad-hoc task which assigns the capability as soon as the Moodle cron
 * is running for the first time.
 *
 * This situation cannot be relied on in a PHPUnit test as it depends on the way how the PHPUnit site was set up. If the
 * plugin was added to an existing Moodle installation instead of being present during an initial installation, the
 * capability was assigned directly by the installation routine and no ad-hoc task exists at all. The tests therefore
 * establish the state explicitly.
 *
 * The tests verify the capability assignment on the SEMCO webservice role itself and do not use has_capability() for this
 * purpose. This is done on purpose as the effective permission of the SEMCO webservice user also depends on the other roles
 * which the user holds. Especially, Moodle assigns the 'webservice/rest:use' capability to the authenticated user role as
 * soon as the Moodle app webservices are enabled, which would cover the missing capability on the SEMCO webservice role.
 *
 * @covers \enrol_semco\task\set_webservice_capability
 *
 * @package    enrol_semco
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_webservice_capability_test extends \advanced_testcase {
    /** @var int The ID of the SEMCO webservice role. */
    private int $semcoroleid;

    /** @var \context_system The system context. */
    private context_system $systemcontext;

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

        // Get the system context.
        $this->systemcontext = context_system::instance();

        // Get the SEMCO webservice role which was created during the plugin installation.
        $this->semcoroleid = $DB->get_field('role', 'id', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME], MUST_EXIST);

        // Bring the SEMCO webservice role into the state which it has right after an initial installation of Moodle,
        // i.e. remove the capability which the plugin was not able to assign at that point in time.
        unassign_capability('webservice/rest:use', $this->semcoroleid, $this->systemcontext->id);
    }

    /**
     * Get the 'webservice/rest:use' capability assignments of the SEMCO webservice role in the system context.
     *
     * @param int|null $permission The permission to look for, or null to look for any permission.
     * @return array The matching records of the 'role_capabilities' table.
     */
    private function get_webservice_capability_assignments(?int $permission = null): array {
        global $DB;

        $conditions = [
            'roleid' => $this->semcoroleid,
            'contextid' => $this->systemcontext->id,
            'capability' => 'webservice/rest:use',
        ];
        if ($permission !== null) {
            $conditions['permission'] = $permission;
        }

        return $DB->get_records('role_capabilities', $conditions);
    }

    /**
     * Test that the ad-hoc task assigns the webservice protocol capability to the SEMCO webservice role.
     */
    public function test_task_assigns_the_webservice_protocol_capability(): void {
        global $DB;

        // To start with, the capability is not assigned to the SEMCO webservice role.
        // This is the very problem which the ad-hoc task exists for.
        $this->assertCount(0, $this->get_webservice_capability_assignments());

        // Cross-check: a capability which the plugin was able to assign directly during the installation is assigned already.
        $this->assertTrue($DB->record_exists('role_capabilities', [
            'roleid' => $this->semcoroleid,
            'contextid' => $this->systemcontext->id,
            'capability' => 'enrol/semco:usewebservice',
            'permission' => CAP_ALLOW,
        ]));

        // Run the ad-hoc task.
        $task = new set_webservice_capability();
        $task->execute();

        // Now, the capability is assigned to the SEMCO webservice role and it is allowed.
        $this->assertCount(1, $this->get_webservice_capability_assignments(CAP_ALLOW));
    }

    /**
     * Test that the ad-hoc task does not do any harm if the capability is assigned already.
     *
     * This is the situation which is given if the Moodle cron happens to run the task more than once for whatever reason.
     */
    public function test_task_is_idempotent(): void {
        // Run the ad-hoc task twice.
        $task = new set_webservice_capability();
        $task->execute();
        $task->execute();

        // The capability is assigned to the SEMCO webservice role exactly once and it is allowed.
        $this->assertCount(1, $this->get_webservice_capability_assignments(CAP_ALLOW));
    }

    /**
     * Test that the ad-hoc task can be queued and run through the task manager, just as the Moodle cron would do it.
     */
    public function test_task_runs_through_the_task_manager(): void {
        // Queue the ad-hoc task, just as the plugin's installation routine does during an initial installation.
        $adhoctask = new set_webservice_capability();
        \core\task\manager::queue_adhoc_task($adhoctask);

        // The task is waiting in the queue and it belongs to the plugin.
        $queuedtasks = \core\task\manager::get_adhoc_tasks('\enrol_semco\task\set_webservice_capability');
        $this->assertCount(1, $queuedtasks);
        $queuedtask = reset($queuedtasks);
        $this->assertEquals('enrol_semco', $queuedtask->get_component());

        // Run all queued tasks as the Moodle cron would do it.
        $this->run_all_adhoc_tasks();

        // The task has left the queue as it has done its job.
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks('\enrol_semco\task\set_webservice_capability'));

        // And the capability is assigned to the SEMCO webservice role and it is allowed.
        $this->assertCount(1, $this->get_webservice_capability_assignments(CAP_ALLOW));
    }
}
