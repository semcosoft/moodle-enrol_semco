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
 * Enrolment method "SEMCO" - Scheduled task for cleaning up orphaned SEMCO enrolment instances.
 *
 * @package    enrol_semco
 * @copyright  2023 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\task;
use core\task\scheduled_task;

/**
 * Enrolment method "SEMCO" - Scheduled task for cleaning up orphaned SEMCO enrolment instances.
 *
 * @package    enrol_semco
 * @copyright  2023 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_orphaned_enrolment_instances extends scheduled_task {
    /**
     * Return localised task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanorphaned', 'enrol_semco');
    }

    /**
     * Run the main task.
     */
    public function execute() {
        global $DB;

        // Get any enrolment instances of type 'semco' from mdl_enrol which do not have a counterpart in mdl_user_enrolments.
        mtrace('Getting any enrolment instances of type \'semco\' from mdl_enrol which do not have a counterpart in ' .
                'mdl_user_enrolments.');
        $sql = 'SELECT *
                FROM {enrol} e
                WHERE e.enrol = :enrol AND
                    NOT EXISTS (
                    SELECT ue.id
                    FROM {user_enrolments} ue
                    WHERE ue.enrolid = e.id
                )';
        $sqlparams = ['enrol' => 'semco'];
        $orphanedenrolments = $DB->get_records_sql($sql, $sqlparams);

        // If there is at least one enrolment instance.
        if ($orphanedenrolments != false) {
            // Trace.
            mtrace(count($orphanedenrolments) . ' orphaned enrolment instances found.');

            // Retrieve the SEMCO enrolment plugin.
            $enrol = enrol_get_plugin('semco');

            // Iterate over the instances.
            foreach ($orphanedenrolments as $oe) {
                // Trace.
                mtrace('... Removing the enrolment instance ' . $oe->id . ' from course ' . $oe->courseid . '.');

                // Remove the instance.
                // Basically, we would only need to remove the row from mdl_enrol as all course enrolments are gone already,
                // but we still use the API for the sake of cleanness.
                $enrol->delete_instance($oe);
            }

            // Otherwise.
        } else {
            // Trace.
            mtrace('No orphaned enrolment instances found.');
        }
    }
}
