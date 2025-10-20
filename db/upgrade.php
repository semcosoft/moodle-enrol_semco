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
 * Enrolment method "SEMCO" - Upgrade script
 *
 * @package    enrol_semco
 * @copyright  2023 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Require plugin library.
require_once($CFG->dirroot . '/enrol/semco/locallib.php');

// Require user profile field library.
require_once($CFG->dirroot . '/user/profile/definelib.php');

/**
 * Function to upgrade enrol_semco
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_enrol_semco_upgrade($oldversion) {
    global $DB, $OUTPUT;

    if ($oldversion < 2023092601) {
        // Get system context.
        $systemcontext = context_system::instance();

        // Get the role ID of the SEMCO role.
        $semcoroleid = $DB->get_field('role', 'id', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME]);

        // Update the plugin's capabilities. The Moodle core updater would do this himself, but it would do it _after_ processing
        // this file. To be able to run assign_capability() now, we need to prepone this step ourselves.
        update_capabilities('enrol_semco');

        // Assign the newly created capability to the SEMCO role.
        assign_capability('enrol/semco:getcoursecompletions', CAP_ALLOW, $semcoroleid, $systemcontext->id);

        // Assign additional Moodle core capability to the SEMCO role.
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/grade:viewall', CAP_ALLOW, $semcoroleid, $systemcontext->id);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('updater_2023092601_addcapability', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Enrol_semco savepoint reached.
        upgrade_plugin_savepoint(true, 2023092601, 'enrol', 'semco');
    }

    if ($oldversion < 2023092605) {
        // If the SEMCO user company profile field does not exist yet.
        $profilefield2 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD2NAME]);
        if ($profilefield2 == false) {
            // Get the profilefield category.
            $profilefieldcategory = $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]);

            // Create SEMCO user company profile field (this is rather hardcoded but should work in the forseeable future).
            $fielddata = new stdClass();
            $fielddata->id = 0;
            $fielddata->action = 'editfield';
            $fielddata->datatype = 'text';
            $fielddata->shortname = ENROL_SEMCO_USERFIELD2NAME;
            $fielddata->name = get_string('installer_userfield2fullname', 'enrol_semco');
            $fielddata->description['text'] = '';
            $fielddata->description['format'] = 1;
            $fielddata->required = 0;
            $fielddata->locked = 1;
            $fielddata->forceunique = 1;
            $fielddata->signup = 0;
            $fielddata->visible = 0;
            $fielddata->categoryid = $profilefieldcategory->id;
            $fielddata->defaultdata = '';
            $fielddata->param1 = 50;
            $fielddata->param2 = 200;
            $fielddata->param3 = 0;
            $fielddata->param4 = '';
            $fielddata->param5 = '';
            profile_save_field($fielddata, []);

            // And show a notification about that fact (this also looks fine in the CLI installer).
            $notification = new \core\output\notification(
                get_string('updater_2023092605_addprofilefield', 'enrol_semco'),
                \core\output\notification::NOTIFY_INFO
            );
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        }

        // Enrol_semco savepoint reached.
        upgrade_plugin_savepoint(true, 2023092605, 'enrol', 'semco');
    }

    if ($oldversion < 2023092606) {
        // If the SEMCO user birthday profile field does not exist yet.
        $profilefield3 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD3NAME]);
        if ($profilefield3 == false) {
            // Get the profilefield category.
            $profilefieldcategory = $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]);

            // Create SEMCO user company profile field (this is rather hardcoded but should work in the forseeable future).
            $fielddata = new stdClass();
            $fielddata->id = 0;
            $fielddata->action = 'editfield';
            $fielddata->datatype = 'text';
            $fielddata->shortname = ENROL_SEMCO_USERFIELD3NAME;
            $fielddata->name = get_string('installer_userfield3fullname', 'enrol_semco');
            $fielddata->description['text'] = '';
            $fielddata->description['format'] = 1;
            $fielddata->required = 0;
            $fielddata->locked = 1;
            $fielddata->forceunique = 1;
            $fielddata->signup = 0;
            $fielddata->visible = 0;
            $fielddata->categoryid = $profilefieldcategory->id;
            $fielddata->defaultdata = '';
            $fielddata->param1 = 30;
            $fielddata->param2 = 30;
            $fielddata->param3 = 0;
            $fielddata->param4 = '';
            $fielddata->param5 = '';
            profile_save_field($fielddata, []);

            // And show a notification about that fact (this also looks fine in the CLI installer).
            $notification = new \core\output\notification(
                get_string('updater_2023092606_addprofilefield3', 'enrol_semco'),
                \core\output\notification::NOTIFY_INFO
            );
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        }

        // If the SEMCO user place of birth profile field does not exist yet.
        $profilefield4 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD4NAME]);
        if ($profilefield4 == false) {
            // Get the profilefield category.
            $profilefieldcategory = $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]);

            // Create SEMCO user company profile field (this is rather hardcoded but should work in the forseeable future).
            $fielddata = new stdClass();
            $fielddata->id = 0;
            $fielddata->action = 'editfield';
            $fielddata->datatype = 'text';
            $fielddata->shortname = ENROL_SEMCO_USERFIELD3NAME;
            $fielddata->name = get_string('installer_userfield4fullname', 'enrol_semco');
            $fielddata->description['text'] = '';
            $fielddata->description['format'] = 1;
            $fielddata->required = 0;
            $fielddata->locked = 1;
            $fielddata->forceunique = 1;
            $fielddata->signup = 0;
            $fielddata->visible = 0;
            $fielddata->categoryid = $profilefieldcategory->id;
            $fielddata->defaultdata = '';
            $fielddata->param1 = 50;
            $fielddata->param2 = 200;
            $fielddata->param3 = 0;
            $fielddata->param4 = '';
            $fielddata->param5 = '';
            profile_save_field($fielddata, []);

            // And show a notification about that fact (this also looks fine in the CLI installer).
            $notification = new \core\output\notification(
                get_string('updater_2023092606_addprofilefield4', 'enrol_semco'),
                \core\output\notification::NOTIFY_INFO
            );
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        }

        // Enrol_semco savepoint reached.
        upgrade_plugin_savepoint(true, 2023092606, 'enrol', 'semco');
    }

    if ($oldversion < 2023092608) {
        // If the SEMCO tenant shortname profile field does not exist yet.
        $profilefield5 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD5NAME]);
        if ($profilefield5 == false) {
            // Get the profilefield category.
            $profilefieldcategory = $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]);

            // Create SEMCO tenant shortname profile field (this is rather hardcoded but should work in the forseeable future).
            $fielddata = new stdClass();
            $fielddata->id = 0;
            $fielddata->action = 'editfield';
            $fielddata->datatype = 'text';
            $fielddata->shortname = ENROL_SEMCO_USERFIELD5NAME;
            $fielddata->name = get_string('installer_userfield5fullname', 'enrol_semco');
            $fielddata->description['text'] = '';
            $fielddata->description['format'] = 1;
            $fielddata->required = 0;
            $fielddata->locked = 1;
            $fielddata->forceunique = 1;
            $fielddata->signup = 0;
            $fielddata->visible = 0;
            $fielddata->categoryid = $profilefieldcategory->id;
            $fielddata->defaultdata = '';
            $fielddata->param1 = 16;
            $fielddata->param2 = 16;
            $fielddata->param3 = 0;
            $fielddata->param4 = '';
            $fielddata->param5 = '';
            profile_save_field($fielddata, []);

            // And show a notification about that fact (this also looks fine in the CLI installer).
            $notification = new \core\output\notification(
                get_string('updater_2023092608_addprofilefield5', 'enrol_semco'),
                \core\output\notification::NOTIFY_INFO
            );
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        }

        // Enrol_semco savepoint reached.
        upgrade_plugin_savepoint(true, 2023092608, 'enrol', 'semco');
    }

    if ($oldversion < 2023092610) {
        // With the 2023092606 upgrade step, the SEMCO user place of birth profile field was created with an incorrect shortname.
        // due to a copy & paste glitch. The shortname was ENROL_SEMCO_USERFIELD3NAME but it should have been
        // ENROL_SEMCO_USERFIELD4NAME.
        // For all upgraded installations, we have to check if they are affected and, if yes, have to change the shortname.
        // As a side note, this wouldn't have happened if the user_info_field table had a unique index on the shortname column.

        // If there are two records with the ENROL_SEMCO_USERFIELD3NAME identifier.
        $countfields = $DB->count_records('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD3NAME]);
        if ($countfields == 2) {
            // Get the field with the wrong shortname according to its fullname.
            // We have to use sql_compare_text() here as the name column is a text column.
            $affectedfieldparams =
                [
                    'shortname' => ENROL_SEMCO_USERFIELD3NAME,
                    'name' => get_string('installer_userfield4fullname', 'enrol_semco'),
                ];
            $affectedfieldsql = 'SELECT * FROM {user_info_field}
                    WHERE ' . $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':name');
            $affectedfield = $DB->get_record_sql($affectedfieldsql, $affectedfieldparams);

            // If we have not found the field.
            // This may happen if the fullname of the field has been changed by the admin or if Moodle is running with
            // a language different from DE or EN currently.
            if ($affectedfield === false) {
                // Show a notification about that fact (this also looks fine in the CLI installer).
                $notification = new \core\output\notification(
                    get_string('updater_2023092610_fixprofilefield4', 'enrol_semco') . ' ' .
                            get_string('updater_2023092610_fixprofilefield4fail', 'enrol_semco'),
                    \core\output\notification::NOTIFY_ERROR
                );
                $notification->set_show_closebutton(false);
                echo $OUTPUT->render($notification);

                // Otherwise.
            } else {
                // Change the shortname.
                $affectedfield->shortname = ENROL_SEMCO_USERFIELD4NAME;

                // Write the field back.
                $DB->update_record('user_info_field', $affectedfield);

                // And show a notification about that fact (this also looks fine in the CLI installer).
                $notification = new \core\output\notification(
                    get_string('updater_2023092610_fixprofilefield4', 'enrol_semco') . ' ' .
                    get_string('updater_2023092610_fixprofilefield4succ', 'enrol_semco'),
                    \core\output\notification::NOTIFY_WARNING
                );
                $notification->set_show_closebutton(false);
                echo $OUTPUT->render($notification);
            }
        }

        // Enrol_semco savepoint reached.
        upgrade_plugin_savepoint(true, 2023092610, 'enrol', 'semco');
    }

    if ($oldversion < 2023100902) {
        // Get system context.
        $systemcontext = context_system::instance();

        // Get the role ID of the SEMCO role.
        $semcoroleid = $DB->get_field('role', 'id', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME]);

        // Update the plugin's capabilities. The Moodle core updater would do this himself, but it would do it _after_ processing
        // this file. To be able to run assign_capability() now, we need to prepone this step ourselves.
        update_capabilities('enrol_semco');

        // Assign the newly created capability to the SEMCO role.
        assign_capability('enrol/semco:resetcoursecompletion', CAP_ALLOW, $semcoroleid, $systemcontext->id);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('updater_2023100902_addcapability', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Enrol_semco savepoint reached.
        upgrade_plugin_savepoint(true, 2023100902, 'enrol', 'semco');
    }

    return true;
}
