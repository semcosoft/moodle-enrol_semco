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
 * Enrolment method "SEMCO" - Privacy provider tests.
 *
 * @package    enrol_semco
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\privacy;

use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\external_location;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Enrolment method "SEMCO" - Privacy provider tests class.
 *
 * SEMCO is a course management system which is connected to Moodle. The user data which the plugin handles
 * is mostly imported from SEMCO to Moodle and not the other way round. Therefore, the plugin's privacy provider
 * only declares an external location link as metadata and does not store any personal user data in Moodle which
 * would have to be exported or deleted through the Privacy API. These tests verify exactly this behaviour, i.e.
 * that the provider announces the external location correctly and that all data request functions return empty
 * results or do nothing, even when a real SEMCO enrolment exists.
 *
 * @covers \enrol_semco\privacy\provider
 *
 * @package    enrol_semco
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Setup testcase.
     */
    public function setUp(): void {
        // Call the parent setup.
        parent::setUp();

        // Reset after the test.
        $this->resetAfterTest(true);
    }

    /**
     * Verify that the provider returns exactly one metadata item and that this item just links to an external location
     * (SEMCO) with the expected privacy fields.
     */
    public function test_get_metadata(): void {
        // Get the metadata collection from the provider.
        $collection = new collection('enrol_semco');
        $collection = provider::get_metadata($collection);

        // Check that the collection is not empty.
        $this->assertNotEmpty($collection);

        // Get the items from the collection.
        $items = $collection->get_collection();

        // Check that the collection contains exactly one item.
        $this->assertCount(1, $items);

        // Pick the only item.
        $item = reset($items);

        // Check that the item is an external location link.
        $this->assertInstanceOf(external_location::class, $item);

        // Check that the external location is named 'SEMCO'.
        $this->assertEquals('SEMCO', $item->get_name());

        // Check that the external location declares exactly the expected privacy fields.
        $privacyfields = $item->get_privacy_fields();
        $this->assertEqualsCanonicalizing(
            ['user_profile', 'course_enrolments', 'course_completions'],
            array_keys($privacyfields)
        );

        // Check that the external location has a summary.
        $this->assertEquals('privacy:metadata:enrol_semco:SEMCO', $item->get_summary());
    }

    /**
     * Verify that the provider returns an empty contextlist for a user, even if the user has a SEMCO enrolment.
     */
    public function test_get_contexts_for_userid(): void {
        // Create a user and a SEMCO enrolment.
        [$user] = $this->create_semco_enrolment();

        // Get the contexts for the user.
        $contextlist = provider::get_contexts_for_userid($user->id);

        // Check that no context is returned.
        $this->assertCount(0, $contextlist);
    }

    /**
     * Verify that the provider returns an empty userlist for a context, even if a user has a SEMCO enrolment in it.
     */
    public function test_get_users_in_context(): void {
        // Create a user and a SEMCO enrolment.
        [, $course] = $this->create_semco_enrolment();

        // Get the course context.
        $coursecontext = context_course::instance($course->id);

        // Get the users in the course context.
        $userlist = new userlist($coursecontext, 'enrol_semco');
        provider::get_users_in_context($userlist);

        // Check that no user is returned.
        $this->assertCount(0, $userlist);
    }

    /**
     * Verify that exporting the user data does not write any data, even if the user has a SEMCO enrolment.
     */
    public function test_export_user_data(): void {
        // Create a user and a SEMCO enrolment.
        [$user, $course] = $this->create_semco_enrolment();

        // Get the course context.
        $coursecontext = context_course::instance($course->id);

        // Export the user data for the course context.
        $approvedcontextlist = new approved_contextlist($user, 'enrol_semco', [$coursecontext->id]);
        provider::export_user_data($approvedcontextlist);

        // Check that no data has been written for the context.
        $writer = writer::with_context($coursecontext);
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Verify that deleting all data for all users in a context does nothing and does not touch the SEMCO enrolment.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        // Create a user and a SEMCO enrolment.
        [$user, $course] = $this->create_semco_enrolment();

        // Get the course context.
        $coursecontext = context_course::instance($course->id);

        // Delete all data for all users in the course context.
        provider::delete_data_for_all_users_in_context($coursecontext);

        // Check that the SEMCO user enrolment still exists (as it must only be removed via the SEMCO plugin itself).
        $this->assertTrue($this->semco_user_enrolment_exists($user->id, $course->id));
    }

    /**
     * Verify that deleting the data for a single user does nothing and does not touch the SEMCO enrolment.
     */
    public function test_delete_data_for_user(): void {
        // Create a user and a SEMCO enrolment.
        [$user, $course] = $this->create_semco_enrolment();

        // Get the course context.
        $coursecontext = context_course::instance($course->id);

        // Delete the data for the user in the course context.
        $approvedcontextlist = new approved_contextlist($user, 'enrol_semco', [$coursecontext->id]);
        provider::delete_data_for_user($approvedcontextlist);

        // Check that the SEMCO user enrolment still exists (as it must only be removed via the SEMCO plugin itself).
        $this->assertTrue($this->semco_user_enrolment_exists($user->id, $course->id));
    }

    /**
     * Verify that deleting the data for multiple users does nothing and does not touch the SEMCO enrolment.
     */
    public function test_delete_data_for_users(): void {
        // Create a user and a SEMCO enrolment.
        [$user, $course] = $this->create_semco_enrolment();

        // Get the course context.
        $coursecontext = context_course::instance($course->id);

        // Delete the data for the user in the course context.
        $approveduserlist = new approved_userlist($coursecontext, 'enrol_semco', [$user->id]);
        provider::delete_data_for_users($approveduserlist);

        // Check that the SEMCO user enrolment still exists (as it must only be removed via the SEMCO plugin itself).
        $this->assertTrue($this->semco_user_enrolment_exists($user->id, $course->id));
    }

    /**
     * Create a user, a course and a real SEMCO enrolment which connects them.
     *
     * The SEMCO enrolment is created via the plugin's own generator which mimics exactly what SEMCO would do during a
     * real enrolment (by running the enrol_user() webservice as the SEMCO webservice user).
     *
     * @return array An array with the created user object and the created course object.
     */
    private function create_semco_enrolment(): array {
        // Create a user and a course.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Enrol the user in the course as SEMCO would do.
        $generator = $this->getDataGenerator()->get_plugin_generator('enrol_semco');
        $generator->create_enrolment([
            'userid' => $user->id,
            'courseid' => $course->id,
            'semcobookingid' => 12345,
        ]);

        // Return the user and the course.
        return [$user, $course];
    }

    /**
     * Check whether a SEMCO user enrolment exists for the given user in the given course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return bool True if a SEMCO user enrolment exists, false otherwise.
     */
    private function semco_user_enrolment_exists(int $userid, int $courseid): bool {
        global $DB;

        // Look up the SEMCO user enrolment via its enrolment instance.
        return $DB->record_exists_sql(
            "SELECT ue.id
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid
                    AND e.courseid = :courseid
                    AND e.enrol = :enrol",
            ['userid' => $userid, 'courseid' => $courseid, 'enrol' => 'semco']
        );
    }
}
