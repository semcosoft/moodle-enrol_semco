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
 * Enrolment method "SEMCO" - Health check autofix helpers
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck;

/**
 * The automatic fixes of the health check items.
 *
 * The health check items assess the plugin installation, the functions in this class change it. They are kept apart
 * from the health check items as they do not have anything to do with the assessment itself: An item's apply_autofix()
 * does nothing but to call the functions of this class.
 *
 * The class is split into three sections:
 * 1. The fixes which restore the state which the plugin installer (db/install.php) has established, one installer step
 *    at a time. Whenever the installer changes, this section has to follow, and vice versa.
 * 2. The fixes which do not correspond to an installer step: They take something back which somebody has changed
 *    after the installation, or they implement a recommendation from the README.
 * 3. The shared helpers which several fixes are built upon.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autofix {
    /*
     * ==========================================================================================================
     * Section 1: Fixes which restore the state of the plugin installer (db/install.php).
     * ==========================================================================================================
     */

    /**
     * Enable the Moodle webservice subsystem, just as the plugin installer does.
     *
     * @return void
     */
    public static function enable_webservices(): void {
        set_config('enablewebservices', 1);
    }

    /**
     * Enable the Moodle webservice REST protocol, just as the plugin installer does.
     *
     * @return void
     */
    public static function enable_rest_protocol(): void {
        \core\plugininfo\webservice::enable_plugin('rest', 1);
    }

    /**
     * Enable the authentication method of the SEMCO webservice user, just as the plugin installer does.
     *
     * @return void
     */
    public static function enable_auth_method(): void {
        \core\plugininfo\auth::enable_plugin(ENROL_SEMCO_AUTH, true);
    }

    /**
     * Enable the SEMCO enrolment plugin, just as the plugin installer does.
     *
     * @return void
     */
    public static function enable_enrol_plugin(): void {
        \core\plugininfo\enrol::enable_plugin('semco', true);
    }

    /**
     * Register the capabilities which the plugin declares in db/access.php, just as the plugin installer does.
     *
     * @return void
     */
    public static function register_capabilities(): void {
        update_capabilities('enrol_semco');
    }

    /**
     * Create the SEMCO webservice role, just as the plugin installer does.
     *
     * Everything which makes up the role - its context level, its capabilities, the roles which it is allowed to
     * assign and its assignment to the SEMCO webservice user - is not set up here, each of these aspects has a fix
     * of its own.
     *
     * @return int The id of the new role.
     */
    public static function create_semco_role(): int {
        return create_role(
            get_string('installer_rolename', 'enrol_semco'),
            ENROL_SEMCO_ROLEANDUSERNAME,
            get_string('installer_roledescription', 'enrol_semco')
        );
    }

    /**
     * Allow the SEMCO webservice role in the system context only, just as the plugin installer does.
     *
     * @param int $roleid The id of the SEMCO webservice role.
     * @return void
     */
    public static function set_semco_role_contextlevels(int $roleid): void {
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
    }

    /**
     * Grant a capability to the SEMCO webservice role in the system context, just as the plugin installer does.
     *
     * @param int $roleid The id of the SEMCO webservice role.
     * @param string $capability The capability to grant.
     * @return void
     */
    public static function assign_semco_role_capability(int $roleid, string $capability): void {
        assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
    }

    /**
     * Allow the SEMCO webservice role to assign the given role, just as the plugin installer does.
     *
     * In contrast to the installer, the event which Moodle triggers when this is done on the 'Allow role assignments'
     * page is triggered as well, so that the change shows up in the logs.
     *
     * @param int $semcoroleid The id of the SEMCO webservice role.
     * @param int $roleid The id of the role which the SEMCO webservice role shall be allowed to assign.
     * @return void
     */
    public static function allow_semco_role_to_assign(int $semcoroleid, int $roleid): void {
        core_role_set_assign_allowed($semcoroleid, $roleid);
        \core\event\role_allow_assign_updated::create([
            'context' => \context_system::instance(),
            'objectid' => $semcoroleid,
            'other' => ['targetroleid' => $roleid, 'allow' => true],
        ])->trigger();
    }

    /**
     * Return the email address which the plugin installer gives the SEMCO webservice user.
     *
     * The address is derived from the site URL and thus changes when the site moves.
     *
     * @return string
     */
    public static function get_semco_user_email(): string {
        global $CFG;

        return ENROL_SEMCO_ROLEANDUSERNAME . '@' . get_host_from_url($CFG->wwwroot);
    }

    /**
     * Create the SEMCO webservice user, just as the plugin installer does.
     *
     * The user authenticates with a webservice token only, see the security note in db/install.php.
     * Everything which depends on the user - its role assignment, its authorisation for the SEMCO external service and
     * its webservice token - is not set up here, each of these aspects has a fix of its own.
     *
     * @return \stdClass The user record.
     */
    public static function create_semco_user(): \stdClass {
        global $CFG;

        // Require the user library.
        require_once($CFG->dirroot . '/user/lib.php');

        // Create the user and add its names and its email address.
        // The random password is nothing but a formal argument which Moodle discards for this authentication method,
        // see the security note in db/install.php.
        $user = create_user_record(ENROL_SEMCO_ROLEANDUSERNAME, random_string(40), ENROL_SEMCO_AUTH);
        $user->firstname = get_string('installer_userfirstname', 'enrol_semco');
        $user->lastname = get_string('installer_userlastname', 'enrol_semco');
        $user->email = self::get_semco_user_email();
        user_update_user($user, false);

        return $user;
    }

    /**
     * Assign the SEMCO webservice role to the SEMCO webservice user in the system context, just as the plugin installer
     * does.
     *
     * @param int $roleid The id of the SEMCO webservice role.
     * @param int $userid The id of the SEMCO webservice user.
     * @return void
     */
    public static function assign_semco_role_to_user(int $roleid, int $userid): void {
        role_assign($roleid, $userid, \context_system::instance()->id);
    }

    /**
     * Register the SEMCO external service and its functions which the plugin declares in db/services.php, just as the
     * plugin installer does.
     *
     * @return void
     */
    public static function update_semco_service(): void {
        global $CFG;

        // Require the upgrade library.
        require_once($CFG->libdir . '/upgradelib.php');

        // Let Moodle create or update the service and add the plugin's own functions to it.
        // Note that external_update_descriptions() alone is not enough and would even make things worse: It syncs the
        // function list of the service with the list which the service declares in db/services.php and therefore
        // removes the plugin's own functions from the service. It is external_update_services() which adds the
        // functions which declare their service membership themselves back to the service. Moodle core calls the two
        // functions in exactly this order during a plugin upgrade as well.
        external_update_descriptions('enrol_semco');
        external_update_services();
    }

    /**
     * Authorise the SEMCO webservice user to use the SEMCO external service, just as the plugin installer does.
     *
     * @param int $serviceid The id of the SEMCO external service.
     * @param int $userid The id of the SEMCO webservice user.
     * @return void
     */
    public static function authorise_semco_user(int $serviceid, int $userid): void {
        global $CFG;

        // Require the webservice library.
        require_once($CFG->dirroot . '/webservice/lib.php');

        // Add the user to the list of authorised users of the service.
        (new \webservice())->add_ws_authorised_user((object) [
            'externalserviceid' => $serviceid,
            'userid' => $userid,
        ]);
    }

    /**
     * Generate a permanent webservice token for the SEMCO webservice user and the SEMCO external service, just as the
     * plugin installer does.
     *
     * @param int $serviceid The id of the SEMCO external service.
     * @param int $userid The id of the SEMCO webservice user.
     * @return string The token.
     */
    public static function create_semco_token(int $serviceid, int $userid): string {
        global $DB;

        // Generate the token.
        $service = \core_external\util::get_service_by_id($serviceid);
        $token = \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $service,
            $userid,
            \context_system::instance()
        );

        // Set the SEMCO webservice user as its creator, see set_semco_token_creator().
        $tokenid = $DB->get_field('external_tokens', 'id', ['token' => $token], MUST_EXIST);
        self::set_semco_token_creator($tokenid, $userid);

        return $token;
    }

    /**
     * Set the SEMCO webservice user as creator of a webservice token, just as the plugin installer does.
     *
     * Moodle generates the token with a creator id of 0 which results in the fact that the token is not shown on the
     * webservice tokens page, thus the plugin installer sets the SEMCO webservice user as its creator.
     *
     * @param int $tokenid The id of the token.
     * @param int $userid The id of the SEMCO webservice user.
     * @return void
     */
    public static function set_semco_token_creator(int $tokenid, int $userid): void {
        global $DB;

        $DB->set_field('external_tokens', 'creatorid', $userid, ['id' => $tokenid]);
    }

    /**
     * Create the SEMCO user profile field category, just as the plugin installer does.
     *
     * @return \stdClass The user profile field category record.
     */
    public static function create_semco_profilefield_category(): \stdClass {
        global $CFG, $DB;

        // Require the user profile field library.
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        // Create the category.
        $categorydata = new \stdClass();
        $categorydata->id = 0;
        $categorydata->action = 'editcategory';
        $categorydata->name = ENROL_SEMCO_USERFIELDCATEGORY;
        profile_save_category($categorydata);

        // Return the new record.
        return $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY], '*', MUST_EXIST);
    }

    /**
     * Create a SEMCO user profile field, just as the plugin installer does.
     *
     * All SEMCO user profile fields share the same shape - a locked and hidden text field - and differ only in their
     * shortname, their name, their size and whether they force unique values.
     *
     * @param string $shortname The shortname of the field.
     * @param string $name The name of the field.
     * @param int $categoryid The id of the SEMCO user profile field category.
     * @param int $displaysize The display size of the field (param1).
     * @param int $maxlength The maximum length of the field (param2).
     * @param bool $forceunique Whether the field forces unique values.
     * @return void
     */
    public static function create_semco_profilefield(
        string $shortname,
        string $name,
        int $categoryid,
        int $displaysize,
        int $maxlength,
        bool $forceunique
    ): void {
        global $CFG;

        // Require the user profile field library.
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        // Create the field.
        $fielddata = new \stdClass();
        $fielddata->id = 0;
        $fielddata->action = 'editfield';
        $fielddata->datatype = 'text';
        $fielddata->shortname = $shortname;
        $fielddata->name = $name;
        $fielddata->description = ['text' => '', 'format' => FORMAT_HTML];
        $fielddata->required = 0;
        $fielddata->locked = 1;
        $fielddata->forceunique = $forceunique ? 1 : 0;
        $fielddata->signup = 0;
        $fielddata->visible = 0;
        $fielddata->categoryid = $categoryid;
        $fielddata->defaultdata = '';
        $fielddata->param1 = $displaysize;
        $fielddata->param2 = $maxlength;
        $fielddata->param3 = 0;
        $fielddata->param4 = '';
        $fielddata->param5 = '';
        profile_save_field($fielddata, []);
    }

    /*
     * ==========================================================================================================
     * Section 2: Fixes which do not correspond to an installer step. They take something back which has been changed
     * after the installation or they implement a recommendation from the README.
     * ==========================================================================================================
     */

    /**
     * Configure the plugin's default role as SEMCO enrolment role, just as the settings page does.
     *
     * This includes the update callback of the setting which allows the SEMCO webservice role to assign the enrolment
     * role.
     *
     * @return void
     */
    public static function configure_default_enrolment_role(): void {
        set_config('role', enrol_semco_get_firststudentroleid(), 'enrol_semco');
        enrol_semco_roleassign_updatecallback();
    }

    /**
     * Make a role assignable in the course context. The context levels which the role can be assigned in already are
     * kept as they are.
     *
     * @param int $roleid The id of the role.
     * @return void
     */
    public static function allow_role_in_course_context(int $roleid): void {
        $contextlevels = array_map('intval', get_role_contextlevels($roleid));
        set_role_contextlevels($roleid, array_merge($contextlevels, [CONTEXT_COURSE]));
    }

    /**
     * Revoke the permission of the SEMCO webservice role to assign the given role.
     *
     * Moodle does not offer a counterpart of core_role_set_assign_allowed(), the core role administration deletes the
     * records directly as well, see core_role_allow_role_page::process_submission(). The event which Moodle triggers
     * when this is done on the 'Allow role assignments' page is triggered as well, so that the change shows up in the
     * logs.
     *
     * @param int $semcoroleid The id of the SEMCO webservice role.
     * @param int $roleid The id of the role which the SEMCO webservice role shall not be allowed to assign anymore.
     * @return void
     */
    public static function disallow_semco_role_to_assign(int $semcoroleid, int $roleid): void {
        global $DB;

        $DB->delete_records('role_allow_assign', ['roleid' => $semcoroleid, 'allowassign' => $roleid]);
        \core\event\role_allow_assign_updated::create([
            'context' => \context_system::instance(),
            'objectid' => $semcoroleid,
            'other' => ['targetroleid' => $roleid, 'allow' => false],
        ])->trigger();
    }

    /**
     * Enable the SEMCO external service.
     *
     * @param int $serviceid The id of the SEMCO external service.
     * @return void
     */
    public static function enable_semco_service(int $serviceid): void {
        global $DB;

        $DB->update_record('external_services', (object) [
            'id' => $serviceid,
            'enabled' => 1,
            'timemodified' => time(),
        ]);
    }

    /**
     * Restrict the SEMCO external service to authorised users.
     *
     * @param int $serviceid The id of the SEMCO external service.
     * @return void
     */
    public static function restrict_semco_service(int $serviceid): void {
        global $DB;

        $DB->update_record('external_services', (object) [
            'id' => $serviceid,
            'restrictedusers' => 1,
            'timemodified' => time(),
        ]);
    }

    /**
     * Revoke the authorisation of the given users to use the SEMCO external service.
     *
     * @param int $serviceid The id of the SEMCO external service.
     * @param int[] $userids The ids of the users.
     * @return void
     */
    public static function revoke_semco_service_authorisation(int $serviceid, array $userids): void {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $DB->delete_records_select(
            'external_services_users',
            'externalserviceid = :serviceid AND userid ' . $insql,
            $inparams + ['serviceid' => $serviceid]
        );
    }

    /**
     * Remove the IP restriction from a webservice token.
     *
     * The plugin installer does not restrict the SEMCO webservice token to particular IP addresses, thus this restores
     * the installer's state. Moodle stores an unrestricted token with an empty restriction, see
     * \core_external\util::generate_token().
     *
     * @param int $tokenid The id of the token.
     * @return void
     */
    public static function remove_semco_token_iprestriction(int $tokenid): void {
        global $DB;

        $DB->set_field('external_tokens', 'iprestriction', null, ['id' => $tokenid]);
    }

    /**
     * Remove the IP restriction from the authorisation of a user for an external service.
     *
     * The plugin installer does not restrict the authorisation of the SEMCO webservice user to particular IP addresses,
     * thus this restores the installer's state. Moodle stores an unrestricted authorisation with an empty restriction,
     * see \webservice::add_ws_authorised_user().
     *
     * @param int $authorisationid The id of the authorisation record.
     * @return void
     */
    public static function remove_semco_service_authorisation_iprestriction(int $authorisationid): void {
        global $DB;

        $DB->set_field('external_services_users', 'iprestriction', null, ['id' => $authorisationid]);
    }

    /**
     * Move a user profile field to the end of a user profile field category.
     *
     * @param \stdClass $field The user profile field record.
     * @param int $categoryid The id of the category.
     * @return void
     */
    public static function move_profilefield_to_category(\stdClass $field, int $categoryid): void {
        global $CFG, $DB;

        // Require the user profile field library.
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        // Move the field and let Moodle renumber the fields.
        $field->categoryid = $categoryid;
        $field->sortorder = $DB->count_records('user_info_field', ['categoryid' => $categoryid]) + 1;
        $DB->update_record('user_info_field', $field);
        profile_reorder_fields();
    }

    /**
     * Stop a user profile field from forcing unique values, just as the plugin updater does for the SEMCO user profile
     * fields which several users legitimately share the same value in.
     *
     * @param \stdClass $field The user profile field record.
     * @return void
     */
    public static function unset_profilefield_forceunique(\stdClass $field): void {
        global $DB;

        $DB->set_field('user_info_field', 'forceunique', 0, ['id' => $field->id]);
    }

    /**
     * Lock a field of the manual authentication method, as the README recommends for the SEMCO user profile fields.
     *
     * @param string $field The shortname of the field.
     * @return void
     */
    public static function lock_manual_auth_field(string $field): void {
        set_config('field_lock_' . $field, 'locked', 'auth_manual');
    }

    /**
     * Forbid user accounts with the same email address, as the README recommends.
     *
     * @return void
     */
    public static function disallow_accounts_same_email(): void {
        set_config('allowaccountssameemail', 0);
    }

    /**
     * Disable the Moodle messaging system, as the README recommends.
     *
     * @return void
     */
    public static function disable_messaging(): void {
        set_config('messaging', 0);
    }

    /**
     * Disable the Moodle 'Course completed' notification, as the README recommends.
     *
     * @return void
     */
    public static function disable_course_completed_message(): void {
        set_config('moodle_coursecompleted_disable', 1, 'message');
    }

    /*
     * ==========================================================================================================
     * Section 3: Shared helpers which several fixes are built upon.
     * ==========================================================================================================
     */

    /**
     * Revoke a capability from a role in a context.
     *
     * @param string $capability The capability.
     * @param int $roleid The id of the role.
     * @param int $contextid The id of the context, the system context for the role definition itself.
     * @return void
     */
    public static function revoke_capability(string $capability, int $roleid, int $contextid): void {
        unassign_capability($capability, $roleid, $contextid);
    }

    /**
     * Update the given fields of the SEMCO webservice user.
     *
     * @param int $userid The id of the SEMCO webservice user.
     * @param array $fields The fields to update, keyed by field name.
     * @return void
     */
    public static function update_semco_user(int $userid, array $fields): void {
        global $CFG;

        // Require the user library.
        require_once($CFG->dirroot . '/user/lib.php');

        // Update the user.
        user_update_user((object) (['id' => $userid] + $fields), false);
    }

    /**
     * Remove the password hash of the SEMCO webservice user and store the AUTH_PASSWORD_NOT_CACHED marker instead.
     *
     * The marker is what Moodle stores itself for an account of a non-internal authentication method like
     * 'webservice', see create_user_record() and update_internal_user_password(). We deliberately do not call
     * update_internal_user_password() here: It would store the same marker, but it would also delete all webservice
     * tokens of the user if $CFG->passwordchangetokendeletion is enabled and would cut off SEMCO this way.
     *
     * @param int $userid The id of the SEMCO webservice user.
     * @return void
     */
    public static function remove_semco_user_password(int $userid): void {
        global $DB;

        // Replace the hash with the marker.
        $DB->set_field('user', 'password', AUTH_PASSWORD_NOT_CACHED, ['id' => $userid]);

        // Trigger the same event as Moodle triggers when it updates a password so that the change is logged.
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        \core\event\user_password_updated::create_from_user($user)->trigger();
    }

    /**
     * Set a site-wide local_recompletion setting.
     *
     * @param string $name The name of the setting.
     * @param string $value The value to set.
     * @return void
     */
    public static function set_recompletion_site_config(string $name, string $value): void {
        set_config($name, $value, 'local_recompletion');
    }

    /**
     * Set a local_recompletion setting in the given courses.
     *
     * local_recompletion does not offer an API for its course settings, its own course settings page writes the
     * records directly as well, see local/recompletion/recompletion.php.
     *
     * @param int[] $courseids The ids of the courses to change.
     * @param string $name The name of the setting.
     * @param string $value The value to set.
     * @return void
     */
    public static function set_recompletion_course_config(array $courseids, string $name, string $value): void {
        global $DB;

        // Iterate over the courses.
        foreach ($courseids as $courseid) {
            // Update the setting if the course holds it already.
            $record = $DB->get_record('local_recompletion_config', ['course' => $courseid, 'name' => $name]);
            if ($record !== false) {
                $record->value = $value;
                $DB->update_record('local_recompletion_config', $record);

                // Otherwise, add it.
            } else {
                $DB->insert_record('local_recompletion_config', (object) [
                    'course' => $courseid,
                    'name' => $name,
                    'value' => $value,
                ]);
            }
        }
    }
}
