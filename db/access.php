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
 * Enrolment method "SEMCO" - Capabilities
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
        // Ability to control Moodle enrolments via the SEMCO enrolment webservice.
        // This might be unnecessary as we also have the enrol/semco:enrol, enrol/semco:unenrol and
        // enrol/semco:editenrolment capabilities.
        // However, we added this capability as some kind of kill switch and to control who is allowed to use the
        // Webservice set defined by this plugin.
        // By default, this is not allowed to any role archetype as it should just be used by a webservice.
        // It will be automatically assigned to the SEMCO webservice role in install.php.
        'enrol/semco:usewebservice' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
        ],
        // Ability to enrol a SEMCO user into a course.
        // By default, this is not allowed to any role archetype as it should just be used by a webservice.
        // It will be automatically assigned to the SEMCO webservice role in install.php.
        'enrol/semco:enrol' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_COURSE,
        ],
        // Ability to unenrol a SEMCO user from a course.
        // By default, this is not allowed to any role archetype as it should just be used by a webservice.
        // It will be automatically assigned to the SEMCO webservice role in install.php.
        'enrol/semco:unenrol' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_COURSE,
        ],
        // Ability to edit an existing SEMCO user enrolment in a course.
        // By default, this is not allowed to any role archetype as it should just be used by a webservice.
        // It will be automatically assigned to the SEMCO webservice role in install.php.
        'enrol/semco:editenrolment' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_COURSE,
        ],
        // Ability to get the existing SEMCO user enrolments in a course.
        // By default, this is not allowed to any role archetype as it should just be used by a webservice.
        // It will be automatically assigned to the SEMCO webservice role in install.php.
        'enrol/semco:getenrolments' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_COURSE,
        ],
        // Ability to get the course completions for given SEMCO user enrolments.
        // By default, this is not allowed to any role archetype as it should just be used by a webservice.
        // It will be automatically assigned to the SEMCO webservice role in install.php.
        'enrol/semco:getcoursecompletions' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_COURSE,
        ],
        // Ability to reset the course completion for a given SEMCO user enrolment.
        // By default, this is not allowed to any role archetype as it should just be used by a webservice.
        // It will be automatically assigned to the SEMCO webservice role in install.php.
        'enrol/semco:resetcoursecompletion' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_COURSE,
        ],
        // Ability to check the existence of a Moodle user by a given field.
        // By default, this is not allowed to any role archetype as it should just be used by a webservice.
        // It will be automatically assigned to the SEMCO webservice role in install.php.
        'enrol/semco:checkuserexistence' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_SYSTEM,
        ],
        // Ability to view the enrolment report of all SEMCO user enrolments.
        // By default, this is allowed for the manager role.
        'enrol/semco:viewreport' => [
            'captype' => 'read',
            'riskbitmask' => RISK_PERSONAL,
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => [
                'manager' => CAP_ALLOW,
            ],
        ],
];
