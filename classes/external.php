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
 * Enrolment method "SEMCO" - External API
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Require libraries.
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

/**
 * Enrolment method "SEMCO" - External API
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_semco_external extends external_api {
    /*
     * Note to future developers:
     * Throughout these webservices, we use the outward-facing term 'enrolid' different from the inward-facing term.
     * In the outward direction, i.e. als webservice parameters and return values, it will mean a _user enrolment_ ID.
     * In the inward direction, i.e. in database queries, it will mean an _enrolment instance_ ID.
     * This was purely done to ease the terminology for the SEMCO developers.
     * And as soon as you have understood it as well, it shouldn't impose any problem to you.
     */

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function enrol_user_parameters() {
        return new external_function_parameters(
            [
                'userid' =>
                    new external_value(
                        PARAM_INT,
                        'The Moodle user ID that is going to be enrolled.',
                        VALUE_REQUIRED
                    ),
                'courseid' =>
                    new external_value(
                        PARAM_INT,
                        'The Moodle course ID to enrol the user into.',
                        VALUE_REQUIRED
                    ),
                'semcobookingid' =>
                    new external_value(
                        PARAM_TEXT,
                        'The SEMCO booking ID which is the basis for this Moodle enrolment.',
                        VALUE_REQUIRED
                    ),
                'timestart' =>
                    new external_value(
                        PARAM_INT,
                        'The timestamp when the enrolment starts (or set to 0 to omit the timestart date)' .
                                    ' [optional].',
                        VALUE_DEFAULT,
                        0
                    ),
                'timeend' =>
                    new external_value(
                        PARAM_INT,
                        'The timestamp when the enrolment ends (or set to 0 to omit the timeend date) [optional].',
                        VALUE_DEFAULT,
                        0
                    ),
                'suspend' =>
                    new external_value(
                        PARAM_BOOL,
                        'The fact if the enrolment is suspended or not (0: not suspended, 1: suspended)' .
                                    ' [optional].',
                        VALUE_DEFAULT,
                        false
                    ),
                'requirerecompletion' =>
                    new external_value(
                        PARAM_BOOL,
                        'The fact if local_recompletion is required to be enabled in the course or not' .
                                    ' (0: not required, 1: required) [optional].',
                        VALUE_DEFAULT,
                        false
                    ),
            ]
        );
    }

    /**
     * Enrolment of a user.
     *
     * This function was adopted from enrol_manual and modified to match the needs of SEMCO enrolment.
     *
     * @param int $userid The Moodle user ID that is going to be enrolled.
     * @param int $courseid The Moodle course ID to enrol the user into.
     * @param string $semcobookingid The SEMCO booking ID which is the basis for this Moodle enrolment.
     * @param int $timestart The timestamp when the enrolment starts [optional].
     * @param int $timeend The timestamp when the enrolment ends [optional].
     * @param bool $suspend The fact if the enrolment is suspended or not (0: not suspended, 1: suspended) [optional].
     * @param bool $requirerecompletion The fact if local_recompletion is required to be enabled in the course or not
                                        (0: not expected, 1: expected) [optional]
     * @return array The webservice's return array
     * @throws moodle_exception
     */
    public static function enrol_user(
        $userid,
        $courseid,
        $semcobookingid,
        $timestart = null,
        $timeend = null,
        $suspend = null,
        $requirerecompletion = null
    ) {
        global $DB, $CFG;

        // Require enrolment library.
        require_once($CFG->libdir . '/enrollib.php');

        // Validate given parameters.
        $arrayparams = [
                'userid' => $userid,
                'courseid' => $courseid,
                'semcobookingid' => $semcobookingid,
                'timestart' => $timestart,
                'timeend' => $timeend,
                'suspend' => $suspend,
                'requirerecompletion' => $requirerecompletion,
        ];
        $params = self::validate_parameters(self::enrol_user_parameters(), $arrayparams);

        // Initialize warnings.
        $warnings = [];

        // Start a transaction to rollback the changes if an error occurs (except if the DB doesn't support it).
        $transaction = $DB->start_delegated_transaction();

        // Retrieve the SEMCO enrolment plugin.
        $enrol = enrol_get_plugin('semco');
        if (empty($enrol)) {
            throw new moodle_exception('semcopluginnotinstalled', 'enrol_semco');
        }

        // Throw an exception if the SEMCO enrolment plugin is not enabled.
        if (enrol_is_enabled('semco') == false) {
            throw new moodle_exception('semcopluginnotenabled', 'enrol_semco');
        }

        // Retrieve the role from the SEMCO enrolment plugin configuration.
        $roleid = get_config('enrol_semco', 'role');

        // Ensure the webservice user is allowed to run this function in the enrolment context.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Check that the webservice user has the permission to enrol SEMCO users.
        require_capability('enrol/semco:enrol', $context);

        // Throw an exception if the webservice user is not able to assign the role.
        $roles = get_assignable_roles($context);
        if (!array_key_exists($roleid, $roles)) {
            $errorparams = new stdClass();
            $errorparams->roleid = $roleid;
            $errorparams->courseid = $params['courseid'];
            $errorparams->userid = $params['userid'];
            throw new moodle_exception('wsusercannotassign', 'enrol_semco', '', $errorparams);
        }

        // Get the user to enrol from the DB, throw an exception if it does not exist.
        $user = \core_user::get_user($params['userid']);
        if (!$user) {
            throw new moodle_exception('usernotexist', 'enrol_semco', '', $params['userid']);
        }

        // Throw an exception if the SEMCO booking ID parameter is empty.
        if (empty($params['semcobookingid'])) {
            throw new moodle_exception('bookingidempty', 'enrol_semco');
        }

        // Throw an exception if there is already an enrolment instance with the given booking ID.
        $instanceexists = $DB->record_exists('enrol', ['customchar1' => $params['semcobookingid']]);
        if ($instanceexists == true) {
            throw new moodle_exception('bookingidduplicate', 'enrol_semco', '', $params['semcobookingid']);
        }

        // Throw an exception if the timestart parameter was invalid.
        if ($params['timestart'] < 0) {
            throw new moodle_exception('timestartinvalid', 'enrol_semco');
        }

        // Throw an exception if the timestart parameter was invalid.
        if ($params['timeend'] < 0) {
            throw new moodle_exception('timeendinvalid', 'enrol_semco');
        }

        // Throw an exception if the timestart parameter is greater than the timeend parameter.
        if ($params['timestart'] > 0 && $params['timeend'] > 0 && $params['timestart'] > $params['timeend']) {
            throw new moodle_exception('timestartendorder', 'enrol_semco');
        }

        // Throw an exception if there is already an enrolment instance which overlaps with the given enrolment period.
        $overlapexists = enrol_semco_detect_enrolment_overlap(
            $params['courseid'],
            $params['userid'],
            $params['timestart'],
            $params['timeend']
        );
        if ($overlapexists == true) {
            throw new moodle_exception('bookingoverlap', 'enrol_semco');
        }

        // If the caller expects that local_recompletion is enabled.
        if (!empty($params['requirerecompletion']) && $params['requirerecompletion'] == true) {
            // Throw an exception if local_recompletion is not installed (or too old).
            if (enrol_semco_check_local_recompletion() != true) {
                throw new moodle_exception('localrecompletionnotexpectable', 'enrol_semco');
            }

            // Require local_recompletion plugin library.
            require_once($CFG->dirroot . '/local/recompletion/locallib.php');

            // Get the recompletion config for this course.
            $recompletionconfig = (object) $DB->get_records_menu(
                'local_recompletion_config',
                ['course' => $params['courseid']],
                '',
                'name, value'
            );

            // Throw an exception if recompletion is not enabled at all.
            if (empty($recompletionconfig->recompletiontype)) {
                $localrecompletionurl = new moodle_url('/local/recompletion/recompletion.php', ['id' => $params['courseid']]);
                throw new moodle_exception('localrecompletionnotenabled', 'enrol_semco', '', $localrecompletionurl->out());
            }

            // Throw an exception if recompletion is not set to OnDemand.
            if ($recompletionconfig->recompletiontype != \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND) {
                $localrecompletionurl = new moodle_url('/local/recompletion/recompletion.php', ['id' => $params['courseid']]);
                throw new moodle_exception('localrecompletionnotondemand', 'enrol_semco', '', $localrecompletionurl->out());
            }
        }

        // Add an enrolment instance to the course on-the-fly.
        // For each particular enrolment, a new enrolment instance will be created in the course.
        // This might sound crazy, however it's the only way to overcome Moodle's database unique contraint for the
        // instanceid + userid tupel on the one hand and to show the SEMCO booking ID in the enrolment details modal
        // on the other hand.
        $instancefields = ['status' => ENROL_INSTANCE_ENABLED, 'customchar1' => $params['semcobookingid']];
        $newinstanceid = $enrol->add_instance(get_course($params['courseid']), $instancefields);

        // And remember it for further processing.
        $instance = $DB->get_record('enrol', ['id' => $newinstanceid], '*', MUST_EXIST);

        // Finally proceed the enrolment.
        // As written above, this endpoint will create a new enrolment instance per call.
        // The function enrol_user() from enrollib is not really prepared for this scenario and would want to update existing
        // enrolment instance as soon as a user is enrolled a second time.
        // However, as we are passing a new enrolment instance for each enrolment, enrol_user() from enrollib still does
        // what we want it to do and we don't need to override it in our plugin.
        $params['timestart'] = (isset($params['timestart']) && !empty($params['timestart'])) ? $params['timestart'] : 0;
        $params['timeend'] = (isset($params['timeend']) && !empty($params['timeend'])) ? $params['timeend'] : 0;
        $status = (isset($params['suspend']) && $params['suspend'] == true) ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
        $enrol->enrol_user($instance, $params['userid'], $roleid, $params['timestart'], $params['timeend'], $status);

        // Get the created enrolment ID from the database (as we have to return it and enrol_user() didn't give it to us).
        $enrolid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $params['userid']]);

        // Commit the DB transaction.
        $transaction->allow_commit();

        // Return the results.
        $result = ['enrolid' => $enrolid,
                'userid' => $params['userid'],
                'courseid' => $params['courseid'],
                'semcobookingid' => $params['semcobookingid'],
                'warnings' => $warnings, ];
        return $result;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function enrol_user_returns() {
        return new external_single_structure(
            [
                'enrolid' =>
                    new external_value(PARAM_INT, 'The Moodle enrolment ID of the created enrolment.'),
                'userid' =>
                    new external_value(PARAM_INT, 'The Moodle user ID of the created enrolment.'),
                'courseid' =>
                    new external_value(PARAM_INT, 'The Moodle course ID of the created enrolment.'),
                'semcobookingid' =>
                    new external_value(PARAM_TEXT, 'The SEMCO booking ID of the created enrolment.'),
                'warnings' =>
                    new external_warnings(),
            ]
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function unenrol_user_parameters() {
        return new external_function_parameters(
            [
                'enrolid' =>
                    new external_value(
                        PARAM_INT,
                        'The Moodle enrolment ID that should be unenrolled.',
                        VALUE_REQUIRED
                    ),
            ]
        );
    }

    /**
     * Unenrolment of a user.
     *
     * This function was adopted from enrol_manual and modified to match the needs of SEMCO enrolment.
     *
     * @param int $enrolid The Moodle enrolment ID that should be unenrolled.
     * @return array The webservice's return array
     * @throws moodle_exception
     */
    public static function unenrol_user($enrolid) {
        global $CFG, $DB;

        // Require enrolment library.
        require_once($CFG->libdir . '/enrollib.php');

        // Validate given parameters.
        $arrayparams = [
                'enrolid' => $enrolid,
        ];
        $params = self::validate_parameters(self::unenrol_user_parameters(), $arrayparams);

        // Initialize warnings.
        $warnings = [];

        // Start a transaction to rollback all changes if an error occurs (except if the DB doesn't support it).
        $transaction = $DB->start_delegated_transaction();

        // Retrieve the SEMCO enrolment plugin.
        $enrol = enrol_get_plugin('semco');
        if (empty($enrol)) {
            throw new moodle_exception('semcopluginnotinstalled', 'enrol_semco');
        }

        // Throw an exception if the SEMCO enrolment plugin is not enabled.
        if (enrol_is_enabled('semco') == false) {
            throw new moodle_exception('semcopluginnotenabled', 'enrol_semco');
        }

        // Get the user enrolment associated to the given enrolment ID from the database,
        // throw an exception if it does not exist.
        $userinstance = $DB->get_record('user_enrolments', ['id' => $params['enrolid']]);
        if (empty($userinstance)) {
            throw new moodle_exception('enrolnouserinstance', 'enrol_semco', '', $params['enrolid']);
        }

        // Get the enrolment instance associated to the given enrolment ID from the database,
        // throw an exception if it does not exist.
        $instance = $DB->get_record('enrol', ['enrol' => 'semco', 'id' => $userinstance->enrolid]);
        if (empty($instance)) {
            throw new moodle_exception('enrolnoinstance', 'enrol_semco', '', $params['enrolid']);
        }

        // Ensure the webservice user is allowed to run this function in the enrolment context.
        $context = context_course::instance($instance->courseid);
        self::validate_context($context);

        // Check that the webservice user has the permission to unenrol SEMCO users.
        require_capability('enrol/semco:unenrol', $context);

        // Get the enrolled user from the DB, throw an exception if it does not exist.
        $user = \core_user::get_user($userinstance->userid);
        if (!$user) {
            throw new moodle_exception('usernotexist', 'enrol_semco', '', $userinstance->userid);
        }

        // Finally proceed the unenrolment.
        $enrol->unenrol_user($instance, $userinstance->userid);

        // As this plugin works in a way that there is one enrolment instance per SEMCO booking ID, we just unenrolled the last
        // user for this enrolment instance.
        // Thus, remove the instance on-the-fly.
        $enrol->delete_instance($instance);

        // Commit the DB transaction.
        $transaction->allow_commit();

        // Return the results.
        $result = ['result' => true,
                'warnings' => $warnings, ];
        return $result;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function unenrol_user_returns() {
        return new external_single_structure(
            [
                'result' => new external_value(PARAM_BOOL, 'The unenrolment result.'),
                'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function edit_enrolment_parameters() {
        return new external_function_parameters(
            [
                'enrolid' =>
                    new external_value(
                        PARAM_INT,
                        'The Moodle enrolment ID that should be edited.',
                        VALUE_REQUIRED
                    ),
                'semcobookingid' =>
                    new external_value(
                        PARAM_TEXT,
                        'The SEMCO booking ID which is the basis for this Moodle enrolment [optional].',
                        VALUE_DEFAULT,
                        null
                    ),
                'timestart' =>
                    new external_value(
                        PARAM_INT,
                        'The timestamp when the enrolment starts (alternatively set to 0 to remove the timestart' .
                                    ' date) [optional].',
                        VALUE_DEFAULT,
                        null
                    ),
                'timeend' =>
                    new external_value(
                        PARAM_INT,
                        'The timestamp when the enrolment ends (alternatively set to 0 to remove the timeend' .
                                    ' date) [optional].',
                        VALUE_DEFAULT,
                        null
                    ),
                'suspend' =>
                    new external_value(
                        PARAM_BOOL,
                        'The fact if the enrolment is suspended or not (0: not suspended, 1: suspended)' .
                                    ' [optional].',
                        VALUE_DEFAULT,
                        null
                    ),
            ]
        );
    }

    /**
     * Editing of an existing user enrolment.
     *
     * @param int $enrolid The Moodle enrolment ID that should be edited.
     * @param string $semcobookingid The SEMCO booking ID which is the basis for this Moodle enrolment.
     * @param int $timestart The timestamp when the enrolment starts [optional].
     * @param int $timeend The timestamp when the enrolment ends [optional].
     * @param bool $suspend The fact if the enrolment is suspended or not (0: not suspended, 1: suspended) [optional].
     * @return array The webservice's return array
     * @throws moodle_exception
     */
    public static function edit_enrolment($enrolid, $semcobookingid = null, $timestart = null, $timeend = null, $suspend = null) {
        global $CFG, $DB;

        // Require enrolment library.
        require_once($CFG->libdir . '/enrollib.php');

        // Validate given parameters.
        $arrayparams = [
                'enrolid' => $enrolid,
                'semcobookingid' => $semcobookingid,
                'timestart' => $timestart,
                'timeend' => $timeend,
                'suspend' => $suspend,
        ];
        $params = self::validate_parameters(self::edit_enrolment_parameters(), $arrayparams);

        // Initialize warnings.
        $warnings = [];

        // Start a transaction to rollback all changes if an error occurs (except if the DB doesn't support it).
        $transaction = $DB->start_delegated_transaction();

        // Retrieve the SEMCO enrolment plugin.
        $enrol = enrol_get_plugin('semco');
        if (empty($enrol)) {
            throw new moodle_exception('semcopluginnotinstalled', 'enrol_semco');
        }

        // Throw an exception if the SEMCO enrolment plugin is not enabled.
        if (enrol_is_enabled('semco') == false) {
            throw new moodle_exception('semcopluginnotenabled', 'enrol_semco');
        }

        // Get the user enrolment associated to the given enrolment ID from the database,
        // throw an exception if it does not exist.
        $userinstance = $DB->get_record('user_enrolments', ['id' => $params['enrolid']]);
        if (empty($userinstance)) {
            throw new moodle_exception('enrolnouserinstance', 'enrol_semco', '', $params['enrolid']);
        }

        // Get the enrolment instance associated to the given enrolment ID from the database,
        // throw an exception if it does not exist.
        $instance = $DB->get_record('enrol', ['enrol' => 'semco', 'id' => $userinstance->enrolid]);
        if (empty($instance)) {
            throw new moodle_exception('enrolnoinstance', 'enrol_semco', '', $params['enrolid']);
        }

        // Ensure the webservice user is allowed to run this function in the enrolment context.
        $context = context_course::instance($instance->courseid);
        self::validate_context($context);

        // Check that the webservice user has the permission to edit SEMCO user enrolments.
        require_capability('enrol/semco:editenrolment', $context);

        // Get the enrolled user from the DB, throw an exception if it does not exist.
        $user = \core_user::get_user($userinstance->userid);
        if (!$user) {
            throw new moodle_exception('usernotexist', 'enrol_semco', '', $userinstance->userid);
        }

        // Throw an exception if the SEMCO booking ID parameter was given (i.e. the caller wants to overwrite it) but is empty.
        if ($params['semcobookingid'] !== null && empty($params['semcobookingid'])) {
            throw new moodle_exception('bookingidempty', 'enrol_semco');
        }

        // Throw an exception if the SEMCO booking ID parameter was given and there is already an enrolment instance with the
        // given booking ID.
        // This can happen if the booking ID was set again or if a booking ID from another booking was submitted.
        if ($params['semcobookingid'] !== null) {
            $instanceexists = $DB->record_exists('enrol', ['customchar1' => $params['semcobookingid']]);
            if ($instanceexists == true) {
                throw new moodle_exception('bookingidduplicatemustchange', 'enrol_semco', '', $params['semcobookingid']);
            }
        }

        // Throw an exception if the timestart parameter was given (i.e. the caller wants to overwrite it) but is invalid.
        if ($params['timestart'] !== null && $params['timestart'] < 0) {
            throw new moodle_exception('timestartinvalid', 'enrol_semco');
        }

        // Throw an exception if the timestart parameter was given (i.e. the caller wants to overwrite it) but is invalid.
        if ($params['timeend'] !== null && $params['timeend'] < 0) {
            throw new moodle_exception('timeendinvalid', 'enrol_semco');
        }

        // Throw an exception if the timestart parameter and the timeend parameter was given,
        // but timestart is greater than timeend.
        if (
            $params['timestart'] !== null && $params['timeend'] !== null &&
                $params['timestart'] > 0 && $params['timeend'] > 0 && $params['timestart'] > $params['timeend']
        ) {
            throw new moodle_exception('timestartendorder', 'enrol_semco');
        }

        // Throw an exception if either timestart or timeend parameter was given, but there is already an enrolment instance
        // which overlaps with the given enrolment period.
        if ($params['timestart'] !== null || $params['timeend'] !== null) {
            // Pick the parameters for calling the overlap function.
            $timestartforoverlap = $params['timestart'];
            $timeendforoverlap = $params['timeend'];
            // If no timestart was given (but obviously timeend was given).
            if ($params['timestart'] === null) {
                // Get the original timestart from the enrolment instance.
                $timestartforoverlap = (int) $userinstance->timestart;
            }
            // If no timeend was given (but obviously timestart was given).
            if ($params['timeend'] === null) {
                // Get the original timeend from the enrolment instance.
                $timeendforoverlap = (int) $userinstance->timeend;
            }
            $overlapexists = enrol_semco_detect_enrolment_overlap(
                $instance->courseid,
                $userinstance->userid,
                $timestartforoverlap,
                $timeendforoverlap,
                $instance->id
            );
            if ($overlapexists == true) {
                throw new moodle_exception('bookingoverlap', 'enrol_semco');
            }
        }

        // Finally, if there were any enrolment fields set.
        if (isset($params['timestart']) || isset($params['timeend']) || isset($params['suspend'])) {
            // Edit the enrolment details.
            $params['timestart'] = (isset($params['timestart']) && !empty($params['timestart'])) ? $params['timestart'] : 0;
            $params['timeend'] = (isset($params['timeend']) && !empty($params['timeend'])) ? $params['timeend'] : 0;
            $status = (isset($params['suspend']) && $params['suspend'] == true) ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
            $enrol->update_user_enrol($instance, $userinstance->userid, $status, $timestart, $timeend);
        }

        // And if the SEMCO booking ID field is set.
        if (isset($params['semcobookingid'])) {
            // Edit the SEMCO booking ID.
            $instance->customchar1 = $params['semcobookingid'];
            $DB->update_record('enrol', $instance);
        }

        // Commit the DB transaction.
        $transaction->allow_commit();

        // Return the results.
        $result = ['result' => true,
                'warnings' => $warnings, ];
        return $result;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function edit_enrolment_returns() {
        return new external_single_structure(
            [
                'result' => new external_value(PARAM_BOOL, 'The editing result.'),
                'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_enrolments_parameters() {
        return new external_function_parameters(
            [
                'courseid' =>
                    new external_value(
                        PARAM_INT,
                        'The Moodle course ID of which the enrolments should be returned.',
                        VALUE_REQUIRED
                    ),
            ]
        );
    }

    /**
     * Getting the existing user enrolments.
     *
     * @param int $courseid The Moodle course ID of which the enrolments should be returned.
     * @return array The webservice's return array
     * @throws moodle_exception
     */
    public static function get_enrolments($courseid) {
        global $DB, $CFG;

        // Require enrolment library.
        require_once($CFG->libdir . '/enrollib.php');

        // Validate given parameters.
        $arrayparams = [
                'courseid' => $courseid,
        ];
        $params = self::validate_parameters(self::get_enrolments_parameters(), $arrayparams);

        // Retrieve the SEMCO enrolment plugin.
        $enrol = enrol_get_plugin('semco');
        if (empty($enrol)) {
            throw new moodle_exception('semcopluginnotinstalled', 'enrol_semco');
        }

        // Throw an exception if the SEMCO enrolment plugin is not enabled.
        if (enrol_is_enabled('semco') == false) {
            throw new moodle_exception('semcopluginnotenabled', 'enrol_semco');
        }

        // Get the course from the DB, throw an exception if it does not exist.
        $courseexists = $DB->record_exists('course', ['id' => $params['courseid']]);
        if ($courseexists == false) {
            throw new moodle_exception('coursenotexist', 'enrol_semco', '', $params['courseid']);
        }

        // Ensure the webservice user is allowed to run this function in the enrolment context.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Check that the webservice user has the permission to get SEMCO user enrolments.
        require_capability('enrol/semco:getenrolments', $context);

        // Get the enrolments from the DB.
        $sql = 'SELECT ue.id AS enrolid,
                    ue.userid AS userid,
                    e.customchar1 AS semcobookingid,
                    ue.timestart AS timestart,
                    ue.timeend AS timeend,
                    ue.status AS suspend
                FROM {user_enrolments} ue
                JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = :courseid
                WHERE e.enrol = :enrol
                ORDER BY ue.id';
        $sqlparams = ['courseid' => $params['courseid'], 'enrol' => 'semco'];
        $enrolments = $DB->get_records_sql($sql, $sqlparams);

        // Return the results.
        return $enrolments;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function get_enrolments_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'enrolid' => new external_value(PARAM_INT, 'The Moodle enrolment ID of the enrolment.'),
                    'userid' => new external_value(PARAM_INT, 'The Moodle user ID of the enrolment.'),
                    'semcobookingid' => new external_value(PARAM_TEXT, 'The SEMCO booking ID of the enrolment.'),
                    'timestart' => new external_value(PARAM_INT, 'The timestamp when the enrolment starts (or 0 if' .
                            ' there isn\'t any timestart date).'),
                    'timeend' => new external_value(PARAM_INT, 'The timestamp when the enrolment ends (or 0 if there' .
                            ' isn\'t any timeend date).'),
                    'suspend' => new external_value(PARAM_BOOL, 'The fact if the enrolment is suspended or not (0:' .
                            ' not suspended, 1: suspended).'),

                ]
            )
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_course_completions_parameters() {
        return new external_function_parameters(
            [
                'enrolmentids' =>
                    new external_multiple_structure(
                        new external_value(
                            PARAM_INT,
                            'The Moodle enrolment ID for which the course completion should be returned.',
                            VALUE_REQUIRED
                        ),
                    ),
            ]
        );
    }

    /**
     * Getting the existing course completions for given SEMCO user enrolments.
     *
     * @param array $enrolmentids The Moodle enrolment IDs for which the course completions should be returned.
     * @return array The webservice's return array
     * @throws moodle_exception
     */
    public static function get_course_completions($enrolmentids) {
        global $DB, $CFG;

        // Initialize a static variable to hold the 'canbecompleted' status for courses.
        // We don't want to fetch that more than once per course.
        static $coursescanbecompleted = [];

        // Require enrolment library.
        require_once($CFG->libdir . '/enrollib.php');

        // Require grade libraries.
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/overview/lib.php');

        // Validate given parameters.
        $arrayparams = [
                'enrolmentids' => $enrolmentids,
        ];
        $params = self::validate_parameters(self::get_course_completions_parameters(), $arrayparams);

        // Throw an exception if the caller passed too many enrolment IDs (for performance reasons).
        if (count($params['enrolmentids']) > ENROL_SEMCO_GET_COURSE_COMPLETIONS_MAXREQUEST) {
            throw new moodle_exception(
                'getcoursecompletionsmaxrequest',
                'enrol_semco',
                '',
                ENROL_SEMCO_GET_COURSE_COMPLETIONS_MAXREQUEST
            );
        }

        // Retrieve the SEMCO enrolment plugin.
        $enrol = enrol_get_plugin('semco');
        if (empty($enrol)) {
            throw new moodle_exception('semcopluginnotinstalled', 'enrol_semco');
        }

        // Throw an exception if the SEMCO enrolment plugin is not enabled.
        if (enrol_is_enabled('semco') == false) {
            throw new moodle_exception('semcopluginnotenabled', 'enrol_semco');
        }

        // Initialize the return array.
        $completions = [];

        // Iterate over the given enrolment IDs.
        foreach ($params['enrolmentids'] as $e) {
            // Get the user enrolment associated to the given enrolment ID from the database,
            // throw an exception if it does not exist.
            $userinstance = $DB->get_record('user_enrolments', ['id' => $e]);
            if (empty($userinstance)) {
                throw new moodle_exception('enrolnouserinstance', 'enrol_semco', '', $e);
            }

            // Get the enrolment instance associated to the given enrolment ID from the database,
            // throw an exception if it does not exist.
            $instance = $DB->get_record('enrol', ['enrol' => 'semco', 'id' => $userinstance->enrolid]);
            if (empty($instance)) {
                throw new moodle_exception('enrolnoinstance', 'enrol_semco', '', $e);
            }

            // Ensure the webservice user is allowed to run this function in the enrolment context.
            $coursecontext = context_course::instance($instance->courseid);
            self::validate_context($coursecontext);

            // Check that the webservice user has the permission to get SEMCO user enrolments.
            require_capability('enrol/semco:getcoursecompletions', $coursecontext);

            // Check if the course can be completed or not.
            // If we have already got this status for the given course.
            if (array_key_exists($instance->courseid, $coursescanbecompleted)) {
                // Just pick it from the static array.
                $canbecompleted = $coursescanbecompleted[$instance->courseid];

                // Otherwise.
            } else {
                // Fetch the status once.
                $course = get_course($instance->courseid);
                $completioninfo = new completion_info($course);

                // Pick it.
                $canbecompleted = $completioninfo->is_enabled();

                // And store it for subsequent calls.
                $coursescanbecompleted[$instance->courseid] = $canbecompleted;

                // Additionally, trigger a regrade of the course as we are getting the course grades as well.
                grade_regrade_final_grades($instance->courseid);
            }

            // If the course can be completed.
            if ($canbecompleted == true) {
                // Get the course completion time for this enrolment instance from the DB.
                $timecompleted = $DB->get_field(
                    'course_completions',
                    'timecompleted',
                    ['userid' => $userinstance->userid, 'course' => $instance->courseid]
                );

                // If a course completion time could be retrieved.
                if ($timecompleted != false) {
                    // If the course is completed.
                    if ($timecompleted > 0) {
                        // Get the grade data.
                        $gradeitem = grade_item::fetch_course_item($instance->courseid);
                        $coursegrade = grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userinstance->userid]);

                        // If we got grade data.
                        if ($coursegrade != false) {
                            // Deduce the final grade.
                            $finalgraderaw = $coursegrade->finalgrade;
                            $finalgrade = grade_format_gradevalue($finalgraderaw, $gradeitem, true);

                            // Get the pass information.
                            $grademinraw = $gradeitem->grademin;
                            $grademaxraw = $gradeitem->grademax;
                            $gradepassraw = $gradeitem->gradepass;
                            $passed = $coursegrade->is_passed($gradeitem);

                            // Build the completion record.
                            $completion = ['enrolid' => $e,
                                    'userid' => $userinstance->userid,
                                    'semcobookingid' => $instance->customchar1,
                                    'canbecompleted' => true,
                                    'completed' => true,
                                    'timecompleted' => $timecompleted,
                                    'finalgrade' => $finalgrade,
                                    'finalgraderaw' => $finalgraderaw,
                                    'grademinraw' => $grademinraw,
                                    'grademaxraw' => $grademaxraw,
                                    'gradepassraw' => $gradepassraw,
                                    'passed' => $passed,
                            ];

                            // Otherwise.
                        } else {
                            // Build the completion record.
                            $completion = ['enrolid' => $e,
                                    'userid' => $userinstance->userid,
                                    'semcobookingid' => $instance->customchar1,
                                    'canbecompleted' => true,
                                    'completed' => true,
                                    'timecompleted' => $timecompleted,
                                    'finalgrade' => null,
                                    'finalgraderaw' => null,
                                    'grademinraw' => null,
                                    'grademaxraw' => null,
                                    'gradepassraw' => null,
                                    'passed' => null,
                            ];
                        }

                        // Otherwise.
                    } else {
                        // Build the completion record.
                        $completion = ['enrolid' => $e,
                                'userid' => $userinstance->userid,
                                'semcobookingid' => $instance->customchar1,
                                'canbecompleted' => true,
                                'completed' => false,
                                'timecompleted' => null,
                                'finalgrade' => null,
                                'finalgraderaw' => null,
                                'grademinraw' => null,
                                'grademaxraw' => null,
                                'gradepassraw' => null,
                                'passed' => null,
                        ];
                    }

                    // Otherwise.
                    // (This can happen if the user has just been enrolled into the course and course completions have not been
                    // processed yet by cron).
                } else {
                    // Build an empty completion record.
                    $completion = ['enrolid' => $e,
                            'userid' => $userinstance->userid,
                            'semcobookingid' => $instance->customchar1,
                            'canbecompleted' => true,
                            'completed' => false,
                            'timecompleted' => null,
                            'finalgrade' => null,
                            'finalgraderaw' => null,
                            'grademinraw' => null,
                            'grademaxraw' => null,
                            'gradepassraw' => null,
                            'passed' => null,
                    ];
                }

                // Otherwise.
            } else {
                // Build an empty completion record.
                $completion = ['enrolid' => $e,
                        'userid' => $userinstance->userid,
                        'semcobookingid' => $instance->customchar1,
                        'canbecompleted' => false,
                        'completed' => false,
                        'timecompleted' => null,
                        'finalgrade' => null,
                        'finalgraderaw' => null,
                        'grademinraw' => null,
                        'grademaxraw' => null,
                        'gradepassraw' => null,
                        'passed' => null,
                ];
            }

            // Add the completion record to the return array.
            $completions[] = $completion;
        }

        // Return the completions.
        return $completions;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function get_course_completions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'enrolid' => new external_value(PARAM_INT, 'The Moodle enrolment ID of the enrolment.'),
                    'userid' => new external_value(PARAM_INT, 'The Moodle user ID of the enrolment.'),
                    'semcobookingid' => new external_value(PARAM_TEXT, 'The SEMCO booking ID of the enrolment.'),
                    'canbecompleted' => new external_value(PARAM_BOOL, 'The fact if the course can be completed,' .
                            ' i.e. if course completion has been enabled (0: not enabled, 1: enabled).'),
                    'completed' => new external_value(PARAM_BOOL, 'The fact if the user has completed' .
                            ' the course or not (0: not completed, 1: completed).'),
                    'timecompleted' => new external_value(PARAM_INT, 'The timestamp when the course was completed' .
                            ' (or null if the course is not completed yet).'),
                    'finalgrade' => new external_value(PARAM_RAW, 'The (formatted) final grade which the user got' .
                            ' after he has completed the course (or null if the course is not completed yet or' .
                            ' the user did not receive a grade in the course).'),
                    'finalgraderaw' => new external_value(PARAM_RAW, 'The (raw) final grade which the user got' .
                            ' after he has completed the course (or null if the course is not completed yet or' .
                            ' the user did not receive a grade in the course).'),
                    'grademinraw' => new external_value(PARAM_RAW, 'The (raw) min grade which is the lower limit' .
                            ' for the user\'s grade (or null if the course is not completed yet or' .
                            ' the user did not receive a grade in the course).'),
                    'grademaxraw' => new external_value(PARAM_RAW, 'The (raw) max grade which is the upper limit' .
                            ' for the user\'s grade (or null if the course is not completed yet or' .
                            ' the user did not receive a grade in the course).'),
                    'gradepassraw' => new external_value(PARAM_RAW, 'The (raw) grade which is required' .
                            ' for the course to be assessed as passed (or null if the course is not completed yet or' .
                            ' the user did not receive a grade in the course).'),
                    'passed' => new external_value(PARAM_BOOL, 'The fact if the user has passed' .
                            ' the course or not, i.e. if his finalgrade was greater or equal to the course\'s' .
                            ' pass grade (0: not passed, 1: passed, null if the course is not completed yet' .
                            ' or if the course\'s passing grade is zero).'),
                ]
            )
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function reset_course_completion_parameters() {
        return new external_function_parameters(
            [
                'enrolid' =>
                    new external_value(
                        PARAM_INT,
                        'The Moodle enrolment ID for which the course completion should be reset.',
                        VALUE_REQUIRED
                    ),
            ]
        );
    }

    /**
     * Resetting the existing course completion for the given SEMCO user enrolment.
     *
     * @param array $enrolid The Moodle enrolment ID for which the course completion should be reset.
     * @return array The webservice's return array
     * @throws moodle_exception
     */
    public static function reset_course_completion($enrolid) {
        global $CFG, $DB;

        // Validate given parameters.
        $arrayparams = [
                'enrolid' => $enrolid,
        ];
        $params = self::validate_parameters(self::reset_course_completion_parameters(), $arrayparams);

        // Initialize warnings.
        $warnings = [];

        // Start a transaction to rollback all changes if an error occurs (except if the DB doesn't support it).
        $transaction = $DB->start_delegated_transaction();

        // Retrieve the SEMCO enrolment plugin.
        $enrol = enrol_get_plugin('semco');
        if (empty($enrol)) {
            throw new moodle_exception('semcopluginnotinstalled', 'enrol_semco');
        }

        // Throw an exception if the SEMCO enrolment plugin is not enabled.
        if (enrol_is_enabled('semco') == false) {
            throw new moodle_exception('semcopluginnotenabled', 'enrol_semco');
        }

        // Throw an exception if local_recompletion is not installed (or too old).
        if (enrol_semco_check_local_recompletion() != true) {
            throw new moodle_exception('localrecompletionnotinstalled', 'enrol_semco');
        }

        // Require local_recompletion plugin library.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        // Get the user enrolment associated to the given enrolment ID from the database,
        // throw an exception if it does not exist.
        $userinstance = $DB->get_record('user_enrolments', ['id' => $params['enrolid']]);
        if (empty($userinstance)) {
            throw new moodle_exception('enrolnouserinstance', 'enrol_semco', '', $params['enrolid']);
        }

        // Get the enrolment instance associated to the given enrolment ID from the database,
        // throw an exception if it does not exist.
        $instance = $DB->get_record('enrol', ['enrol' => 'semco', 'id' => $userinstance->enrolid]);
        if (empty($instance)) {
            throw new moodle_exception('enrolnoinstance', 'enrol_semco', '', $params['enrolid']);
        }

        // Ensure the webservice user is allowed to run this function in the enrolment context.
        $context = context_course::instance($instance->courseid);
        self::validate_context($context);

        // Check that the webservice user has the permission to reset course completions for SEMCO users.
        require_capability('enrol/semco:resetcoursecompletion', $context);

        // Get the enrolled user from the DB, throw an exception if it does not exist.
        $user = \core_user::get_user($userinstance->userid);
        if (!$user) {
            throw new moodle_exception('usernotexist', 'enrol_semco', '', $userinstance->userid);
        }

        // Get the recompletion config for this course.
        $recompletionconfig = (object) $DB->get_records_menu(
            'local_recompletion_config',
            ['course' => $instance->courseid],
            '',
            'name, value'
        );

        // Throw an exception if recompletion is not enabled at all.
        if (empty($recompletionconfig->recompletiontype)) {
            $localrecompletionurl = new moodle_url('/local/recompletion/recompletion.php', ['id' => $instance->courseid]);
            throw new moodle_exception('localrecompletionnotenabled', 'enrol_semco', '', $localrecompletionurl->out());
        }

        // Throw an exception if recompletion is not set to OnDemand.
        if ($recompletionconfig->recompletiontype != \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND) {
            $localrecompletionurl = new moodle_url('/local/recompletion/recompletion.php', ['id' => $instance->courseid]);
            throw new moodle_exception('localrecompletionnotondemand', 'enrol_semco', '', $localrecompletionurl->out());
        }

        // Get the local_recompletion reset task.
        $resettask = new \local_recompletion\task\check_recompletion();

        // Get the full course record.
        $course = get_course($instance->courseid);

        // Trigger the reset and store the error output (which is an array).
        $reseterrors = $resettask->reset_user($userinstance->userid, $course, $recompletionconfig);

        // If there was an error.
        if (!empty($reseterrors)) {
            // Remember the reset result.
            $resetresult = false;

            // Pipe the reset errors through to the webservice warnings as we can't do anything else.
            foreach ($reseterrors as $re) {
                // Because of a glitch which is reported on https://github.com/danmarsden/moodle-local_recompletion/issues/146,
                // we drop all empty error messages.
                if (empty($re)) {
                    continue;
                }

                // Add the error to the warning array.
                $warnings[] = [
                    'item' => 'course',
                    'itemid' => $instance->courseid,
                    'warningcode' => 'localrecompletionerror',
                    'message' => $re,
                ];
            }

            // Otherwise, if the reset seemed to be successful.
        } else {
            // Remember the reset result.
            $resetresult = true;
        }

        // Commit the DB transaction.
        $transaction->allow_commit();

        // Return the results.
        $result = ['result' => $resetresult,
            'warnings' => $warnings, ];
        return $result;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function reset_course_completion_returns() {
        return new external_single_structure(
            [
                'result' => new external_value(PARAM_BOOL, 'The course completion reset result' .
                        ' (1: reset successful, 0: reset not successful, please check the warnings).'),
                'warnings' => new external_warnings(),
            ]
        );
    }
}
