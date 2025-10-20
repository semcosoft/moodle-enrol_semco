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
 * Enrolment method "SEMCO" - Library
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Enrolment method "SEMCO"
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_semco_plugin extends enrol_plugin {
    /**
     * Returns localised name of enrol instance.
     *
     * @param object $instance
     * @return string
     */
    public function get_instance_name($instance) {
        // If a SEMCO booking ID is stored in customchar1.
        if (!empty($instance->customchar1)) {
            // Return the instance name with the booking ID.
            return get_string('instance_namewithbookingid', 'enrol_semco', $instance->customchar1);

            // Otherwise (this should not happen however).
        } else {
            // Return the instance name with the booking ID.
            return get_string('instance_namewithoutbookingid', 'enrol_semco');
        }
    }

    /**
     * Does this plugin assign protected roles or can they be manually removed?
     *
     * @return bool
     */
    public function roles_protected() {
        return true;
    }

    /**
     * Does this plugin allow manual enrolments?
     *
     * @param stdClass $instance course enrol instance
     * @return bool
     */
    public function allow_enrol(stdClass $instance) {
        // We return false here as we do not want to allow any manual enrolments in the GUI at all.
        // However, the plugin still implements the enrol/semco:enrol capability to control if the webservice user
        // is allowed to do the enrolments or not.

        return false;
    }

    /**
     * Does this plugin allow manual unenrolment of all users?
     *
     * @param stdClass $instance course enrol instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        // We return false here as we do not want to allow any unmanual enrolments in the GUI at all.
        // However, the plugin still implements the enrol/semco:unenrol capability to control if the webservice user
        // is allowed to do the unenrolments or not.

        return false;
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table, specifies user
     *
     * @return bool
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        // We return false here as we do not want to allow any unmanual enrolments in the GUI at all.
        // However, the plugin still implements the enrol/semco:unenrol capability to control if the webservice user
        // is allowed to do the unenrolments or not.

        return false;
    }

    /**
     * Does this plugin allow manual changes in user_enrolments table?
     *
     * @param stdClass $instance course enrol instance
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return false;
    }

    /**
     * Does this plugin support some way to user to self enrol?
     *
     * @param stdClass $instance course enrol instance
     *
     * @return bool
     */
    public function show_enrolme_link(stdClass $instance) {
        return false;
    }

    /**
     * If we would say that we use the standard editing UI, Moodle would search for the enrol/semco:config capability
     * which we do not define. On the other hand, everything works fine if we return false here.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return false;
    }

    /**
     * Return whether or not, given the current state, it is possible to add a new instance
     * of this enrolment plugin to the course.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        return false;
    }

    /**
     * Return whether or not, given the current state, it is possible to edit an instance
     * of this enrolment plugin in the course. Used by the standard editing UI
     * to generate a link to the edit instance form if editing is allowed.
     *
     * @param stdClass $instance
     * @return boolean
     */
    public function can_edit_instance($instance) {
        return false;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        return false;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        return false;
    }

    /**
     * Returns edit icons for the page with list of instances
     *
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        return [];
    }

    /**
     * Returns true if the plugin has one or more bulk operations that can be performed on
     * user enrolments.
     *
     * @param course_enrolment_manager $manager
     * @return bool
     */
    public function has_bulk_operations(course_enrolment_manager $manager) {
        return false;
    }
}

/**
 * Callback to add head elements (for releases up to Moodle 4.3).
 */
function enrol_semco_before_standard_top_of_body_html() {
    global $CFG;

    // Require local library.
    require_once($CFG->dirroot . '/enrol/semco/locallib.php');

    // Call and return callback implementation.
    return enrol_semco_callbackimpl_before_standard_top_of_body_html();
}
