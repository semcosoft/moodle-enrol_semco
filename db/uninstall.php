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
 * Enrolment method "SEMCO" - Uninstallation script
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Require plugin library.
require_once($CFG->dirroot . '/enrol/semco/locallib.php');

// Require user library.
require_once($CFG->dirroot . '/user/lib.php');

// Require user profile field library.
require_once($CFG->dirroot . '/user/profile/definelib.php');

/**
 * Uninstall the plugin.
 */
function xmldb_enrol_semco_uninstall() {
    global $DB, $OUTPUT;

    // If the SEMCO webservice role still exists.
    $rolerecord = $DB->get_record('role', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME]);
    if ($rolerecord != false) {
        // Remove it.
        delete_role($rolerecord->id);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('uninstaller_removedrole', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }

    // If the SEMCO webservice user still exists.
    $userrecord = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME]);
    if ($userrecord != false) {
        // Remove it.
        user_delete_user($userrecord);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('uninstaller_removeduser', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }

    // Initialize feedback flag for profile fields.
    $fieldsremoved = false;

    // If the SEMCO user ID profile field still exists.
    $profilefield1 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD1NAME]);
    if ($profilefield1 != false) {
        // Remove it.
        profile_delete_field($profilefield1->id);

        // And remember that fact.
        $fieldsremoved = true;
    }

    // If the SEMCO user company profile field still exists.
    $profilefield2 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD2NAME]);
    if ($profilefield2 != false) {
        // Remove it.
        profile_delete_field($profilefield2->id);

        // And remember that fact.
        $fieldsremoved = true;
    }

    // If the SEMCO user birthday profile field still exists.
    $profilefield3 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD3NAME]);
    if ($profilefield3 != false) {
        // Remove it.
        profile_delete_field($profilefield3->id);

        // And remember that fact.
        $fieldsremoved = true;
    }

    // If the SEMCO user place of birth profile field still exists.
    $profilefield4 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD4NAME]);
    if ($profilefield4 != false) {
        // Remove it.
        profile_delete_field($profilefield4->id);

        // And remember that fact.
        $fieldsremoved = true;
    }

    // If the SEMCO tenant shortname profile field still exists.
    $profilefield5 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD5NAME]);
    if ($profilefield5 != false) {
        // Remove it.
        profile_delete_field($profilefield5->id);

        // And remember that fact.
        $fieldsremoved = true;
    }

    // If the SEMCO user profile field category still exists.
    $profilefieldcategory = $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]);
    if ($profilefieldcategory != false) {
        // Remove it.
        profile_delete_category($profilefieldcategory->id);

        // And remember that fact.
        $fieldsremoved = true;
    }

    // If any profile field or the category was rewmoved.
    if ($fieldsremoved == true) {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('uninstaller_removedprofilefields', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }

    // Show a notification about the fact that webservices and webservice auth will remain enabled
    // (this also looks fine in the CLI installer).
    $notification = new \core\output\notification(
        get_string('uninstaller_remainenabled', 'enrol_semco'),
        \core\output\notification::NOTIFY_INFO
    );
    $notification->set_show_closebutton(false);
    echo $OUTPUT->render($notification);
}
