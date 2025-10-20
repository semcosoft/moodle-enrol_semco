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
 * Enrolment method "SEMCO" - Ad-hoc task for setting the webservice capability
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\task;
use core\task\adhoc_task;

/**
 * Enrolment method "SEMCO" - Ad-hoc task for setting the webservice capability
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_webservice_capability extends adhoc_task {
    /**
     * Run the main task.
     */
    public function execute() {
        global $CFG, $DB;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Get system context.
        $systemcontext = \context_system::instance();

        // Get the SEMCO webservice role.
        $semcoroleid = $DB->get_field('role', 'id', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME], MUST_EXIST);

        // Assign the capability.
        assign_capability('webservice/rest:use', CAP_ALLOW, $semcoroleid, $systemcontext->id);
    }
}
