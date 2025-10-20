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
 * Enrolment method "SEMCO" - Settings
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Require plugin library.
    require_once($CFG->dirroot . '/enrol/semco/locallib.php');

    // Show the webservice token from the DB.
    // But only if we are on the right page to save unnecessary database queries.
    // And if we are not during the initial install or if the script is called without setting the page URL
    // (which will happen during the plugin installation and will show a debug warning, that's why we suppress debugging messages
    // temporarily).
    $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'enrolsettingssemco']);
    $olddebug = $CFG->debug;
    $CFG->debug = 0;
    $pageurl = $PAGE->url;
    $CFG->debug = $olddebug;
    if (!during_initial_install() && $settingsurl->compare($pageurl, URL_MATCH_PARAMS) == true) {
        // Create connection information heading.
        $name = 'enrol_semco/settings_connectioninfoheading';
        $title = get_string('settings_connectioninfoheading', 'enrol_semco', null, true);
        $description = '';
        $setting = new admin_setting_heading($name, $title, $description);
        $settings->add($setting);

        // Create wwwroot information widget.
        $name = 'enrol_semco/settings_wwwrootinfo';
        $title = get_string('settings_wwwrootinfo', 'enrol_semco', null, true);
        $description = '<p>' . get_string('settings_wwwrootinfofound', 'enrol_semco', $CFG->wwwroot, true) . '</p>';
        $setting = new admin_setting_description($name, $title, $description);
        $settings->add($setting);

        // Get the webservice token.
        $sql = 'SELECT et.token
                FROM {external_tokens} et
                JOIN {external_services} es ON et.externalserviceid = es.id
                JOIN {user} u ON et.userid = u.id
                WHERE u.username = :username AND es.shortname = :serviceshortname';
        $sqlparams = ['serviceshortname' => ENROL_SEMCO_SERVICENAME, 'username' => ENROL_SEMCO_ROLEANDUSERNAME];
        $webservicetoken = $DB->get_field_sql($sql, $sqlparams);

        // If a token was found.
        if ($webservicetoken != false) {
            // Create token information widget.
            $name = 'enrol_semco/settings_tokeninfo';
            $title = get_string('settings_tokeninfo', 'enrol_semco', null, true);
            $description = '<p>' . get_string('settings_tokeninfofound', 'enrol_semco', $webservicetoken, true) . '</p>';
            $setting = new admin_setting_description($name, $title, $description);
            $settings->add($setting);

            // Otherwise.
        } else {
            // Create token information widget.
            $name = 'enrol_semco/settings_tokeninfo';
            $title = get_string('settings_tokeninfo', 'enrol_semco', null, true);
            $description = '<p>' . get_string('settings_tokeninfononefound', 'enrol_semco', null, true) . '</p>';
            $setting = new admin_setting_description($name, $title, $description);
            $settings->add($setting);
        }
    }

    // Create enrolment report heading.
    $name = 'enrol_semco/settings_enrolmentreportheading';
    $reporturl = new moodle_url('/enrol/semco/enrolreport.php');
    $title = get_string('settings_enrolmentreportheading', 'enrol_semco', null, true);
    $description = get_string('settings_enrolmentreportheading_desc', 'enrol_semco', null, true) . '<br />' .
            html_writer::link(
                $reporturl,
                get_string('settings_enrolmentreportbutton', 'enrol_semco', null, true),
                ['class' => 'btn btn-secondary my-3']
            );
    $setting = new admin_setting_heading($name, $title, $description);
    $settings->add($setting);

    // Create enrolment settings heading.
    $name = 'enrol_semco/settings_enrolmentheading';
    $title = get_string('settings_enrolmentheading', 'enrol_semco', null, true);
    $description = '';
    $setting = new admin_setting_heading($name, $title, $description);
    $settings->add($setting);

    // Create role chooser widget.
    $roleoptions = [];
    // Get some basic data we are going to need.
    $roles = get_all_roles();
    $systemcontext = context_system::instance();
    $rolenames = role_fix_names($roles, $systemcontext, ROLENAME_ORIGINAL);
    if (!empty($rolenames)) {
        foreach ($rolenames as $key => $role) {
            if (!array_key_exists($role->id, $roleoptions)) {
                $roleoptions[$role->id] = $role->localname;
            }
        }
    }
    // Get first default role for 'student' archetype.
    $firststudentroleid = enrol_semco_get_firststudentroleid();
    // And add the widget finally.
    $name = 'enrol_semco/role';
    $title = get_string('settings_role', 'enrol_semco', null, true);
    $description = get_string('settings_role_desc', 'enrol_semco', null, true);
    $setting = new admin_setting_configselect($name, $title, $description, $firststudentroleid, $roleoptions);
    $setting->set_updatedcallback('enrol_semco_roleassign_updatecallback');
    $settings->add($setting);

    unset($roleoptions);

    // Create course completion settings heading.
    $name = 'enrol_semco/settings_coursecompletionheading';
    $title = get_string('settings_coursecompletionheading', 'enrol_semco', null, true);
    $description = '';
    $setting = new admin_setting_heading($name, $title, $description);
    $settings->add($setting);

    // If local_recompletion is installed.
    if (enrol_semco_check_local_recompletion() == true) {
        // Create information widget.
        $name = 'enrol_semco/settings_coursecompletionnotfound';
        $title = '';
        $localrecompletionurl = new moodle_url('/admin/settings.php', ['section' => 'local_recompletion']);
        $notification = new \core\output\notification(
            get_string('settings_coursecompletionlrcintro', 'enrol_semco', null, true) .
                get_string('settings_coursecompletionlrcfound', 'enrol_semco', null, true) .
                get_string('settings_coursecompletionnote', 'enrol_semco', $localrecompletionurl->out(), true),
            \core\output\notification::NOTIFY_SUCCESS
        );
        $notification->set_show_closebutton(false);
        $description = $OUTPUT->render($notification);
        $setting = new admin_setting_heading($name, $title, $description);
        $settings->add($setting);

        // Otherwise.
    } else {
        // Create information widget.
        $name = 'enrol_semco/settings_coursecompletionnotfound';
        $title = '';
        $localrecompletionurl = new moodle_url('/admin/settings.php', ['section' => 'local_recompletion']);
        $notification = new \core\output\notification(
            get_string('settings_coursecompletionlrcintro', 'enrol_semco', null, true) .
                get_string('settings_coursecompletionlrcnotfound', 'enrol_semco', null, true) .
                get_string('settings_coursecompletionnote', 'enrol_semco', $localrecompletionurl->out(), true),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        $description = $OUTPUT->render($notification);
        $setting = new admin_setting_heading($name, $title, $description);
        $settings->add($setting);
    }
}
