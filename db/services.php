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
 * Enrolment method "SEMCO" - Webservices
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Require plugin library.
require_once($CFG->dirroot . '/enrol/semco/locallib.php');

$services = [
        'SEMCO' => [
                'functions' => [
                        'core_course_get_courses_by_field',
                        'core_user_create_users',
                        'core_user_delete_users',
                        'core_user_get_users_by_field',
                        'core_user_update_users', ],
                'requiredcapability' => 'enrol/semco:usewebservice',
                'enabled' => 1,
                'restrictedusers' => 1,
                'shortname' => ENROL_SEMCO_SERVICENAME,
                'downloadfiles' => 0,
                'uploadfiles' => 0,
        ],
];

$functions = [
        'enrol_semco_enrol_user' => [
                'classname' => 'enrol_semco_external',
                'methodname' => 'enrol_user',
                'description' => 'Enrol a given user from SEMCO',
                'capabilities' => 'enrol/semco:enrol',
                'type' => 'write',
                'services' => [ENROL_SEMCO_SERVICENAME],
        ],
        'enrol_semco_unenrol_user' => [
                'classname' => 'enrol_semco_external',
                'methodname' => 'unenrol_user',
                'description' => 'Unenrol a given user from SEMCO',
                'capabilities' => 'enrol/semco:unenrol',
                'type' => 'write',
                'services' => [ENROL_SEMCO_SERVICENAME],
        ],
        'enrol_semco_edit_enrolment' => [
                'classname' => 'enrol_semco_external',
                'methodname' => 'edit_enrolment',
                'description' => 'Edit an existing user enrolment from SEMCO',
                'capabilities' => 'enrol/semco:editenrolment',
                'type' => 'write',
                'services' => [ENROL_SEMCO_SERVICENAME],
        ],
        'enrol_semco_get_enrolments' => [
                'classname' => 'enrol_semco_external',
                'methodname' => 'get_enrolments',
                'description' => 'Get the existing user enrolments from SEMCO in a course',
                'capabilities' => 'enrol/semco:getenrolments',
                'type' => 'read',
                'services' => [ENROL_SEMCO_SERVICENAME],
        ],
        'enrol_semco_get_course_completions' => [
                'classname' => 'enrol_semco_external',
                'methodname' => 'get_course_completions',
                'description' => 'Get the course completions for given SEMCO user enrolments',
                'capabilities' => 'enrol/semco:getcoursecompletions',
                'type' => 'read',
                'services' => [ENROL_SEMCO_SERVICENAME],
        ],
        'enrol_semco_reset_course_completion' => [
                'classname' => 'enrol_semco_external',
                'methodname' => 'reset_course_completion',
                'description' => 'Reset the course completion for the given SEMCO user enrolment',
                'capabilities' => 'enrol/semco:resetcoursecompletion',
                'type' => 'write',
                'services' => [ENROL_SEMCO_SERVICENAME],
        ],
];
