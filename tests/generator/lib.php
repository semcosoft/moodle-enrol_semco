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
 * Enrolment method "SEMCO" - Test data generator
 *
 * @package    enrol_semco
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Enrolment method "SEMCO" - Test data generator class.
 *
 * @package    enrol_semco
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_semco_generator extends component_generator_base {
    /**
     * Run the given callback as the SEMCO webservice user.
     *
     * The webservice functions which SEMCO calls - the ones of this plugin as well as the Moodle core user webservices -
     * perform their capability checks against the calling user. This helper temporarily switches the session user to the
     * SEMCO webservice user, so that a callback can drive these webservices exactly as SEMCO does in production.
     *
     * This helper is used by the generator functions below and by the plugin's Behat step definitions (which reach it
     * through behat_enrol_semco::get_semco_data_generator()), so that PHPUnit and Behat really go through the same code.
     *
     * @param callable $callback The callback to run (it receives no arguments and its return value is passed through).
     * @return mixed The return value of the callback.
     */
    public function run_as_semco_webservice_user(callable $callback) {
        global $CFG, $COURSE, $DB, $USER;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Get the SEMCO webservice user which was created during the plugin installation.
        $semcouser = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME], '*', MUST_EXIST);

        // Remember the user and the course which are currently active.
        // The user is switched by this helper on purpose, while the course is replaced by the webservice functions as a side
        // effect: They call external_api::validate_context() which runs require_login() which in turn puts the course of the
        // webservice call into the $COURSE global. Both have to be restored afterwards. Restoring the course matters most in
        // Behat, where this helper is called from within the Behat process: The $COURSE global outlives the scenario while
        // the database is reset between the scenarios, so a course of a following scenario can reuse the course ID of the
        // stale course. get_course() hands out the stale course as soon as the IDs match, which lets the data generators of
        // that scenario work with the settings of a course from a previous scenario.
        $olduser = $USER;
        $oldcourse = $COURSE;

        // Run the callback as the SEMCO webservice user (which is needed to pass the webservice's capability checks).
        \core\session\manager::set_user($semcouser);
        try {
            return $callback();
        } finally {
            // Restore the previous user and course in any case.
            \core\session\manager::set_user($olduser);
            $COURSE = $oldcourse;
        }
    }

    /**
     * Build a core user webservice record from the given user data.
     *
     * The standard user fields are taken over directly, while any key which is named like one of the SEMCO custom profile
     * fields (semco_userid, semco_usercompany, ...) is turned into a customfields entry, as this is the structure which
     * the core user webservices expect. A key which is not given (or which holds an empty value) is skipped.
     *
     * Please note that the 'suspended' field is only accepted by core_user_update_users. Handing it over to
     * core_user_create_users makes that webservice reject the whole record as it does not know this field.
     *
     * This helper is used by create_semcouser() below and by the plugin's Behat step definitions
     * (see behat_enrol_semco::the_following_users_are_updated_by_semco()).
     *
     * @param array $data The user data.
     * @return array The user record in the structure which the core user webservices expect.
     */
    public function build_core_user_webservice_record(array $data): array {
        global $CFG;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Take over the standard user fields.
        $record = [];
        foreach (['username', 'firstname', 'lastname', 'email', 'password', 'auth', 'suspended'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '') {
                $record[$field] = $data[$field];
            }
        }

        // Turn the SEMCO profile field data into the customfields structure which the webservices expect.
        $semcofields = [ENROL_SEMCO_USERFIELD1NAME, ENROL_SEMCO_USERFIELD2NAME, ENROL_SEMCO_USERFIELD3NAME,
                ENROL_SEMCO_USERFIELD4NAME, ENROL_SEMCO_USERFIELD5NAME];
        $customfields = [];
        foreach ($semcofields as $shortname) {
            if (array_key_exists($shortname, $data) && $data[$shortname] !== '') {
                $customfields[] = ['type' => $shortname, 'value' => $data[$shortname]];
            }
        }
        if (!empty($customfields)) {
            $record['customfields'] = $customfields;
        }

        return $record;
    }

    /**
     * Resolve a recompletion type identifier into the value which local_recompletion stores in its course configuration.
     *
     * The identifiers are the ones which local_recompletion uses in the names of its class constants, i.e. 'disabled',
     * 'period', 'ondemand' and 'schedule'.
     *
     * The tests use these identifiers instead of the class constants themselves as they have to be able to name a
     * recompletion type without touching local_recompletion at all: PHPUnit evaluates the data providers when it builds the
     * test suite, i.e. even before a test has the chance to skip itself if local_recompletion is not installed, and Behat
     * names the recompletion types in the data tables of its scenarios. This helper is therefore the single place where the
     * tests get in touch with the constants of the companion plugin.
     *
     * This helper is used by the plugin's PHPUnit tests and by its Behat step definitions (which reach it through
     * behat_enrol_semco::get_semco_data_generator()), so that both really go through the same code.
     *
     * @param string $identifier The recompletion type identifier.
     * @return string The value which local_recompletion stores for this recompletion type.
     */
    public function resolve_recompletiontype(string $identifier): string {
        global $CFG;

        // Require local_recompletion plugin library.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        // Compose the list of recompletion types which local_recompletion supports.
        $recompletiontypes = [
            'disabled' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_DISABLED,
            'period' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_PERIOD,
            'ondemand' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND,
            'schedule' => \local_recompletion_recompletion_form::RECOMPLETION_TYPE_SCHEDULE,
        ];

        // Throw an exception if the given identifier is unknown.
        if (!array_key_exists($identifier, $recompletiontypes)) {
            throw new coding_exception('The recompletion type \'' . $identifier . '\' is unknown, it has to be one of: ' .
                    implode(', ', array_keys($recompletiontypes)));
        }

        return $recompletiontypes[$identifier];
    }

    /**
     * Create a SEMCO enrolment.
     *
     * As there is no manual way to create a SEMCO enrolment, this helper runs the enrol_user() webservice as the
     * SEMCO webservice user. This mimics exactly what SEMCO would do during a real enrolment.
     *
     * @param array $data The enrolment data. The keys 'userid', 'courseid' and 'semcobookingid' are required.
     *                    The keys 'semcouserid', 'timestart', 'timeend', 'suspend' and 'requirerecompletion' are optional.
     * @return array The webservice return array.
     */
    public function create_enrolment(array $data): array {
        global $CFG;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Require profile library.
        require_once($CFG->dirroot . '/user/profile/lib.php');

        // Throw an exception if a required field is missing.
        foreach (['userid', 'courseid', 'semcobookingid'] as $requiredfield) {
            if (!isset($data[$requiredfield])) {
                throw new coding_exception('The field \'' . $requiredfield .
                        '\' is required when creating a SEMCO enrolment.');
            }
        }

        // If a SEMCO user ID was given, store it in the user's SEMCO user ID profile field.
        // In real life, this field is not filled by this plugin but by SEMCO through the core webservices.
        if (isset($data['semcouserid'])) {
            $profiledata = new stdClass();
            $profiledata->id = $data['userid'];
            $profiledata->{'profile_field_' . ENROL_SEMCO_USERFIELD1NAME} = $data['semcouserid'];
            profile_save_data($profiledata);
        }

        // Compose the webservice arguments, picking the optional fields with the same defaults as the webservice.
        $arguments = [
            $data['userid'],
            $data['courseid'],
            $data['semcobookingid'],
            isset($data['timestart']) ? (int) $data['timestart'] : 0,
            isset($data['timeend']) ? (int) $data['timeend'] : 0,
            isset($data['suspend']) ? (bool) $data['suspend'] : false,
            isset($data['requirerecompletion']) ? (bool) $data['requirerecompletion'] : false,
        ];

        // Run the enrolment as the SEMCO webservice user, i.e. exactly the way SEMCO would do it.
        return $this->run_as_semco_webservice_user(function () use ($arguments) {
            return \enrol_semco\external::enrol_user(...$arguments);
        });
    }

    /**
     * Assign or revoke a single capability of the SEMCO webservice role.
     *
     * The SEMCO webservice role is created during the plugin installation and carries all capabilities which the SEMCO
     * webservice user needs to do his job. This helper modifies exactly one of these capabilities which allows a test to
     * verify that a particular capability really guards what it is supposed to guard.
     *
     * @param string $capability The capability to be modified.
     * @param bool $allow True to assign the capability with CAP_ALLOW, false to revoke it completely.
     * @return void
     */
    public function set_webservice_role_capability(string $capability, bool $allow): void {
        global $CFG, $DB;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Get the SEMCO webservice role which was created during the plugin installation.
        $semcoroleid = $DB->get_field('role', 'id', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME], MUST_EXIST);

        // Get the system context as the plugin assigns all capabilities to the role in the system context.
        $systemcontext = context_system::instance();

        // Assign or revoke the capability.
        if ($allow == true) {
            assign_capability($capability, CAP_ALLOW, $semcoroleid, $systemcontext->id, true);
        } else {
            unassign_capability($capability, $semcoroleid, $systemcontext->id);
        }

        // The permissions of the currently logged in user are cached, so the change would not have any effect within the
        // running test. Throw the caches away to make the change effective immediately.
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            accesslib_clear_all_caches_for_unit_testing();
        }
    }

    /**
     * Create a user the SEMCO way, i.e. through the Moodle core user webservice.
     *
     * As SEMCO does not create users through the SEMCO plugin but through the Moodle core user webservice, this helper runs
     * the core_user_create_users() webservice as the SEMCO webservice user. This mimics exactly what SEMCO would do when it
     * creates a user. Besides the standard user fields, the SEMCO custom profile fields (semco_userid, semco_usercompany,
     * ...) can be given as additional keys; they are turned into the customfields structure which the webservice expects.
     *
     * @param array $data The user data. The keys 'username', 'firstname', 'lastname', 'email' and 'password' are required.
     * @return array The webservice return array.
     */
    public function create_semcouser(array $data): array {
        global $CFG;

        // Require the core user webservice library.
        require_once($CFG->dirroot . '/user/externallib.php');

        // Throw an exception if a required field is missing.
        foreach (['username', 'firstname', 'lastname', 'email', 'password'] as $requiredfield) {
            if (!isset($data[$requiredfield])) {
                throw new coding_exception('The field \'' . $requiredfield . '\' is required when creating a SEMCO user.');
            }
        }

        // Build the webservice user record.
        $user = $this->build_core_user_webservice_record($data);

        // Run the user creation as the SEMCO webservice user, i.e. exactly the way SEMCO would do it.
        return $this->run_as_semco_webservice_user(function () use ($user) {
            return \core_user_external::create_users([$user]);
        });
    }
}
