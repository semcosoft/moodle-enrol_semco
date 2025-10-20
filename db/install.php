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
 * Enrolment method "SEMCO" - Installation script
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

// Require webservice library.
require_once($CFG->dirroot . '/webservice/lib.php');

// Require user profile field library.
require_once($CFG->dirroot . '/user/profile/definelib.php');

/**
 * Install the plugin.
 */
function xmldb_enrol_semco_install() {
    global $CFG, $DB, $OUTPUT;

    // Get system context.
    $systemcontext = context_system::instance();

    // Initialize variable for final notification.
    $therewasaproblem = false;

    // If the webservice subsystem is not enabled yet.
    if (!$CFG->enablewebservices) {
        // Activate it.
        set_config('enablewebservices', 1);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_enabledws', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }

    // If the webservice REST protocal is not enabled yet.
    $enabledprotocols = \core\plugininfo\webservice::get_enabled_plugins();
    if (array_key_exists('rest', $enabledprotocols) == false) {
        // Activate it.
        \core\plugininfo\webservice::enable_plugin('rest', 1);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_enabledrest', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }

    // If the SEMCO webservice role does not exist yet. As this plugin is installed freshly, this should be the case.
    if ($DB->record_exists('role', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME]) == false) {
        // Create the SEMCO webservice role.
        $semcoroleid = create_role(
            get_string('installer_rolename', 'enrol_semco'),
            ENROL_SEMCO_ROLEANDUSERNAME,
            get_string('installer_roledescription', 'enrol_semco')
        );

        // Allow the role in the system context.
        set_role_contextlevels($semcoroleid, [CONTEXT_SYSTEM]);

        // Update the plugin's capabilities. The Moodle core installer would do this himself, but it would do it _after_ processing
        // this file. To be able to run assign_capability() later, we need to prepone this step ourselves.
        update_capabilities('enrol_semco');

        // Assign all necessary capabilities to the role.
        assign_capability('enrol/semco:usewebservice', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('enrol/semco:enrol', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('enrol/semco:unenrol', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('enrol/semco:editenrolment', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('enrol/semco:getenrolments', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('enrol/semco:getcoursecompletions', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('enrol/semco:resetcoursecompletion', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/role:assign', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/course:useremail', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/course:view', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/grade:viewall', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/user:create', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/user:delete', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/user:update', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        assign_capability('moodle/user:viewhiddendetails', CAP_ALLOW, $semcoroleid, $systemcontext->id);

        // Handle the webservice/rest:use capability specially.
        // During the initial installation of Moodle, this capability does not exist yet when this plugin is installed.
        // In this case, the installation would stop with a fatal error saying:
        // "Coding error detected, it must be fixed by a programmer: Capability 'webservice/rest:use' was not found!
        // This has to be fixed in code."
        // We only set this capability now if the plugin is installed to a running Moodle instance.
        // The case of an initial installation is handled later.
        if (!during_initial_install()) {
            assign_capability('webservice/rest:use', CAP_ALLOW, $semcoroleid, $systemcontext->id);
        }

        // Allow the SEMCO webservice role to assign the student role (which is set as default in the plugin settings).
        core_role_set_assign_allowed($semcoroleid, enrol_semco_get_firststudentroleid());

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_createdrole', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Handle the webservice/rest:use capability during an initial installation.
        // We will circumvent the described issue by queueing an ad-hoc task which will deal with setting the capability.
        if (during_initial_install()) {
            // Queue the task.
            $adhoctask = new \enrol_semco\task\set_webservice_capability();
            \core\task\manager::queue_adhoc_task($adhoctask);

            // And show a notification about that fact (this also looks fine in the CLI installer).
            $notification = new \core\output\notification(
                get_string('installer_queuedcapabilitytask', 'enrol_semco'),
                \core\output\notification::NOTIFY_WARNING
            );
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        }

        // Otherwise, there might be leftovers from previous installations and admin's tests.
    } else {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_notcreatedrole', 'enrol_semco'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Remember this fact for the final notification.
        $therewasaproblem = true;
    }

    // If the auth method which we want to use for the SEMCO user is not enabled yet.
    if (is_enabled_auth(ENROL_SEMCO_AUTH) == false) {
        // Enable it.
        \core\plugininfo\auth::enable_plugin(ENROL_SEMCO_AUTH, true);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_enabledauth', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }

    // If the SEMCO webservice user does not exist yet. As this plugin is installed freshly, this should be the case.
    if ($DB->record_exists('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME]) == false) {
        // Create the SEMCO webservice user.
        $semcouser = create_user_record(ENROL_SEMCO_ROLEANDUSERNAME, md5(rand()), ENROL_SEMCO_AUTH);

        // And add firstname, lastname and email to the user account.
        $semcouser->firstname = get_string('installer_userfirstname', 'enrol_semco');
        $semcouser->lastname = get_string('installer_userlastname', 'enrol_semco');
        $semcouser->email = ENROL_SEMCO_ROLEANDUSERNAME . '@' . get_host_from_url($CFG->wwwroot);
        user_update_user($semcouser, false);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_createduser', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // If we haven't skipped creating the SEMCO webservice role before.
        if (isset($semcoroleid) && $semcoroleid > 0) {
            // The upcoming call of role_assign() might trigger a
            // "Notice: Undefined property: stdClass::$coursecontact in /var/www/html/course/classes/category.php"
            // debug message during an initial installation of Moodle as role_assign() will call role_assignment_changed()
            // which wants to access $CFG->coursecontact which might not be there yet.
            // We try to circumvent by faking this setting for this script.
            if (during_initial_install()) {
                $CFG->coursecontact = 3; // This is the standard value for this setting.
            }

            // Add the SEMCO webservice user to the SEMCO webservice role.
            role_assign($semcoroleid, $semcouser->id, $systemcontext->id);

            // And show a notification about that fact (this also looks fine in the CLI installer).
            $notification = new \core\output\notification(
                get_string('installer_addedusertorole', 'enrol_semco'),
                \core\output\notification::NOTIFY_INFO
            );
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        }

        // Install the plugin's services. The Moodle core installer would do this himself, but it would do it _after_ processing
        // this file. To be able to add the SEMCO webservice user to the webservice later, we need to prepone this step ourselves.
        external_update_descriptions('enrol_semco');

        // Get the ID of the service for further processing.
        $semcoserviceid = $DB->get_field('external_services', 'id', ['shortname' => ENROL_SEMCO_SERVICENAME]);

        // Add the SEMCO webservice user to the SEMCO webservice as allowed user.
        $webservicemanager = new webservice();
        $serviceuser = new stdClass();
        $serviceuser->externalserviceid = $semcoserviceid;
        $serviceuser->userid = $semcouser->id;
        $webservicemanager->add_ws_authorised_user($serviceuser);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_addedusertoservice', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Generate a webservice token for the user.
        external_generate_token(EXTERNAL_TOKEN_PERMANENT, $semcoserviceid, $semcouser->id, $systemcontext);

        // Unfortunately, with the previous function, the token was created with a creator ID of 0 which will result in the
        // fact that the token is not shown on /admin/webservice/tokens.php.
        // To avoid this problem, we set the creatorid of the token to the SEMCO webservice user id now.
        $generatedtoken = $DB->get_record(
            'external_tokens',
            ['externalserviceid' => $semcoserviceid, 'userid' => $semcouser->id],
            '*',
            MUST_EXIST
        );
        $generatedtoken->creatorid = $semcouser->id;
        $DB->update_record('external_tokens', $generatedtoken);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_createdusertoken', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Otherwise, there might be leftovers from previous installations and admin's tests.
    } else {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_notcreateduser', 'enrol_semco'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Remember this fact for the final notification.
        $therewasaproblem = true;
    }

    // If the SEMCO user profile field category does not exist yet.
    $profilefieldcategory = $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]);
    if ($profilefieldcategory == false) {
        // Create the SEMCO user profile field category (this is rather hardcoded but should work in the forseeable future).
        $categorydata = new stdClass();
        $categorydata->id = 0;
        $categorydata->action = 'editcategory';
        $categorydata->name = ENROL_SEMCO_USERFIELDCATEGORY;
        profile_save_category($categorydata);

        // And show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_createdprofilefieldcategory', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Get the category object again for further usage.
        $profilefieldcategory = $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]);
    }

    // If the SEMCO user ID profile field does not exist yet.
    $profilefield1 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD1NAME]);
    if ($profilefield1 == false) {
        // Create SEMCO user ID profile field (this is rather hardcoded but should work in the forseeable future).
        $fielddata = new stdClass();
        $fielddata->id = 0;
        $fielddata->action = 'editfield';
        $fielddata->datatype = 'text';
        $fielddata->shortname = ENROL_SEMCO_USERFIELD1NAME;
        $fielddata->name = get_string('installer_userfield1fullname', 'enrol_semco');
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
            get_string('installer_createdprofilefield1', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Otherwise, there might be leftovers from previous installations and admin's tests.
    } else {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_notcreatedprofilefield1', 'enrol_semco'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Remember this fact for the final notification.
        $therewasaproblem = true;
    }

    // If the SEMCO user company profile field does not exist yet.
    $profilefield2 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD2NAME]);
    if ($profilefield2 == false) {
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
            get_string('installer_createdprofilefield2', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Otherwise, there might be leftovers from previous installations and admin's tests.
    } else {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_notcreatedprofilefield2', 'enrol_semco'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Remember this fact for the final notification.
        $therewasaproblem = true;
    }

    // If the SEMCO user birthday profile field does not exist yet.
    $profilefield3 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD3NAME]);
    if ($profilefield3 == false) {
        // Create SEMCO user birthday profile field (this is rather hardcoded but should work in the forseeable future).
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
            get_string('installer_createdprofilefield3', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Otherwise, there might be leftovers from previous installations and admin's tests.
    } else {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_notcreatedprofilefield3', 'enrol_semco'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Remember this fact for the final notification.
        $therewasaproblem = true;
    }

    // If the SEMCO user place of birth profile field does not exist yet.
    $profilefield4 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD4NAME]);
    if ($profilefield4 == false) {
        // Create SEMCO user place of birth profile field (this is rather hardcoded but should work in the forseeable future).
        $fielddata = new stdClass();
        $fielddata->id = 0;
        $fielddata->action = 'editfield';
        $fielddata->datatype = 'text';
        $fielddata->shortname = ENROL_SEMCO_USERFIELD4NAME;
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
            get_string('installer_createdprofilefield4', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Otherwise, there might be leftovers from previous installations and admin's tests.
    } else {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_notcreatedprofilefield4', 'enrol_semco'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Remember this fact for the final notification.
        $therewasaproblem = true;
    }

    // If the SEMCO tenant shortname profile field does not exist yet.
    $profilefield5 = $DB->get_record('user_info_field', ['shortname' => ENROL_SEMCO_USERFIELD5NAME]);
    if ($profilefield5 == false) {
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
            get_string('installer_createdprofilefield5', 'enrol_semco'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Otherwise, there might be leftovers from previous installations and admin's tests.
    } else {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('installer_notcreatedprofilefield5', 'enrol_semco'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);

        // Remember this fact for the final notification.
        $therewasaproblem = true;
    }

    // Enable the SEMCO enrolment plugin with the goal that it is directly usable.
    \core\plugininfo\enrol::enable_plugin('semco', true);

    // And show a notification about that fact (this also looks fine in the CLI installer).
    $notification = new \core\output\notification(
        get_string('installer_enabledplugin', 'enrol_semco'),
        \core\output\notification::NOTIFY_INFO
    );
    $notification->set_show_closebutton(false);
    echo $OUTPUT->render($notification);

    // Show the final notification, depending on the fact if there were problems or not.
    if ($therewasaproblem == false) {
        $notification = new \core\output\notification(
            get_string('installer_finalnotenoproblems', 'enrol_semco'),
            \core\output\notification::NOTIFY_SUCCESS
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    } else {
        $notification = new \core\output\notification(
            get_string('installer_finalnotewithproblems', 'enrol_semco'),
            \core\output\notification::NOTIFY_ERROR
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }
}
