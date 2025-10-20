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
 * Enrolment method "SEMCO" - Local library
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('ENROL_SEMCO_ROLEANDUSERNAME', 'semcowebservice');
define('ENROL_SEMCO_AUTH', 'webservice');
define('ENROL_SEMCO_SERVICENAME', 'enrol_semco');
define('ENROL_SEMCO_USERFIELDCATEGORY', 'SEMCO');
define('ENROL_SEMCO_USERFIELD1NAME', 'semco_userid');
define('ENROL_SEMCO_USERFIELD2NAME', 'semco_usercompany');
define('ENROL_SEMCO_USERFIELD3NAME', 'semco_userbirthday');
define('ENROL_SEMCO_USERFIELD4NAME', 'semco_userplaceofbirth');
define('ENROL_SEMCO_USERFIELD5NAME', 'semco_branchtoken');
define('ENROL_SEMCO_GET_COURSE_COMPLETIONS_MAXREQUEST', 100);
define('ENROL_SEMCO_COURSERESETRESULT_SUCCESS', 1);
define('ENROL_SEMCO_COURSERESETRESULT_SKIPPED', -1);
define('ENROL_SEMCO_COURSERESETRESULT_FAILED', -2);

/**
 * Helper function to get the first student archetype role id.
 * This algorithm is needed two times during the plugin installation.
 *
 * @return int The first student archetype role id.
 */
function enrol_semco_get_firststudentroleid() {
    $studentarchetype = get_archetype_roles('student');
    if ($studentarchetype != false && count($studentarchetype) > 0) {
        $firststudentrole = array_shift($studentarchetype);
        $firststudentroleid = $firststudentrole->id;
    } else {
        $firststudentroleid = '';
    }

    return $firststudentroleid;
}

/**
 * Helper function to detect an enrolment period overlap with existing user enrolments.
 * This algorithm is needed in two different webservices.
 *
 * @param int $courseid The course ID
 * @param int $userid The user ID
 * @param int $timestart The enrolment start of the new enrolment
 * @param int $timeend The enrolment end of the new enrolment
 * @param int $ignoreoriginalid The enrolment ID which should be ignored during overlap check (necessary for editing enrolments).
 * @return bool
 */
function enrol_semco_detect_enrolment_overlap($courseid, $userid, $timestart, $timeend, $ignoreoriginalid = null) {
    global $DB;

    // If we haven't a given start date and end date at all.
    if ($timestart == 0 && $timeend == 0) {
        // Check if there are any other enrolment instances
        // which would directly conflict with this one.
        $overlapunlimitedexistssql = 'SELECT ue.id
                FROM {user_enrolments} ue
                JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = :courseid
                WHERE e.enrol = :enrol AND
                    ue.userid = :userid';
        $overlapunlimitedparams = ['courseid' => $courseid,
                'userid' => $userid,
                'enrol' => 'semco',
        ];
        if ($ignoreoriginalid != null) {
            $overlapunlimitedexistssql .= ' AND e.id != :ignoreid';
            $overlapunlimitedparams['ignoreid'] = $ignoreoriginalid;
        }
        $overlapunlimitedexists = $DB->record_exists_sql($overlapunlimitedexistssql, $overlapunlimitedparams);
    }

    // If we have a given start date, but no end date.
    if ($timestart > 0 && $timeend == 0) {
        // Check if there are any enrolment instances
        // which do neither have a start date nor end date
        // OR which do not have a start date and end after this one starts
        // OR which do not have an end date
        // OR which start after this one starts.
        $overlapstartexistssql = 'SELECT ue.id
                FROM {user_enrolments} ue
                JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = :courseid
                WHERE e.enrol = :enrol AND
                    ue.userid = :userid AND
                    (ue.timestart = 0 AND ue.timeend = 0
                        OR ue.timestart = 0 AND ue.timeend >= :timestart1
                        OR ue.timeend = 0
                        OR ue.timestart >= :timestart2)';
        $overlapstartparams = ['courseid' => $courseid,
                'userid' => $userid,
                'enrol' => 'semco',
                'timestart1' => $timestart,
                'timestart2' => $timestart, // For a strange reason, Moodle does not allow to reuse SQL parameters.
                'timeend' => $timeend,
        ];
        if ($ignoreoriginalid != null) {
            $overlapstartexistssql .= ' AND e.id != :ignoreid';
            $overlapstartparams['ignoreid'] = $ignoreoriginalid;
        }
        $overlapstartexists = $DB->record_exists_sql($overlapstartexistssql, $overlapstartparams);
    }

    // If we have a given end date, but no start date.
    if ($timeend > 0 && $timestart == 0) {
        // Check if there are any enrolment instances
        // which do neither have a start date nor end date
        // OR which do not have an end date and start before this one ends
        // OR which do not have a start date
        // OR which end before this one ends.
        $overlapendexistssql = 'SELECT ue.id
                FROM {user_enrolments} ue
                JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = :courseid
                WHERE e.enrol = :enrol AND
                    ue.userid = :userid AND
                    (ue.timestart = 0 AND ue.timeend = 0
                        OR ue.timeend = 0 AND ue.timestart <= :timeend1
                        OR ue.timestart = 0
                        OR ue.timeend > 0 AND ue.timeend <= :timeend2)';
        $overlapendparams = ['courseid' => $courseid,
                'userid' => $userid,
                'enrol' => 'semco',
                'timeend1' => $timeend,
                'timeend2' => $timeend, // For a strange reason, Moodle does not allow to reuse SQL parameters.
                'timestart' => $timestart,
        ];
        if ($ignoreoriginalid != null) {
            $overlapendexistssql .= ' AND e.id != :ignoreid';
            $overlapendparams['ignoreid'] = $ignoreoriginalid;
        }
        $overlapendexists = $DB->record_exists_sql($overlapendexistssql, $overlapendparams);
    }

    // If we have a given end date and a given start date.
    if ($timeend > 0 && $timestart > 0) {
        // Check if there are any enrolment instances
        // which do neither have a start date nor end date
        // OR which start after this one starts and start before this one ends
        // OR which end before this one ends and end after this one starts
        // OR which start before this one starts and end after this one ends.
        $overlapbothexistssql = 'SELECT ue.id
                FROM {user_enrolments} ue
                JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = :courseid
                WHERE e.enrol = :enrol AND
                    ue.userid = :userid AND
                    (ue.timestart = 0 AND ue.timeend = 0
                        OR ue.timestart >= :timestart1 AND ue.timestart <= :timeend1
                        OR ue.timeend > 0 AND ue.timeend <= :timeend2 AND ue.timeend >= :timestart2
                        OR ue.timestart <= :timestart3 AND ue.timeend >= :timeend3)';
        $overlapbothparams = ['courseid' => $courseid,
                'userid' => $userid,
                'enrol' => 'semco',
                'timeend1' => $timeend,
                'timeend2' => $timeend, // For a strange reason, Moodle does not allow to reuse SQL parameters.
                'timeend3' => $timeend, // For a strange reason, Moodle does not allow to reuse SQL parameters.
                'timestart1' => $timestart,
                'timestart2' => $timestart, // For a strange reason, Moodle does not allow to reuse SQL parameters.
                'timestart3' => $timestart, // For a strange reason, Moodle does not allow to reuse SQL parameters.
        ];
        if ($ignoreoriginalid != null) {
            $overlapbothexistssql .= ' AND e.id != :ignoreid';
            $overlapbothparams['ignoreid'] = $ignoreoriginalid;
        }
        $overlapbothexists = $DB->record_exists_sql($overlapbothexistssql, $overlapbothparams);
    }

    // If any overlap exists.
    if (
        (isset($overlapunlimitedexists) && $overlapunlimitedexists == true) ||
            (isset($overlapstartexists) && $overlapstartexists == true) ||
            (isset($overlapendexists) && $overlapendexists == true) ||
            (isset($overlapbothexists) && $overlapbothexists == true)
    ) {
        return true;

        // Otherwise.
    } else {
        return false;
    }
}

/**
 * Callback function to update the role-assignment permissions as soon as the enrol_semco/role was changed.
 */
function enrol_semco_roleassign_updatecallback() {
    global $DB;

    // Get the new setting value.
    $newsemcoroleid = get_config('enrol_semco', 'role');

    // Get the SEMCO webservice role ID from the database.
    $semcoroleid = $DB->get_field('role', 'id', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME]);

    // If we have found a role ID.
    if (is_numeric($newsemcoroleid) && is_numeric($semcoroleid)) {
        // Check if the SEMCO webservice user is already allowed to assign the new setting's role.
        // (We have to check that because otherwise core_role_set_assign_allowed() would throw a 'duplicate key value violation').
        $alreadyallowed = $DB->record_exists('role_allow_assign', ['roleid' => $semcoroleid,
                'allowassign' => $newsemcoroleid, ]);

        // If the role is not allowed yet.
        if ($alreadyallowed == false) {
            // Allow the SEMCO webservice role to assign the new role.
            core_role_set_assign_allowed($semcoroleid, $newsemcoroleid);
        }
    }
}

/**
 * Helper function to check if our companion plugin local_recompletion is installed.
 *
 * @return boolean
 */
function enrol_semco_check_local_recompletion() {
    global $CFG;

    // Use a static variable, just to be sure if this function gets called multiple times.
    static $localrecompletioninstalled;

    // If the check has not been done yet.
    if (!isset($localrecompletioninstalled)) {
        // Check if local_recompletion is installed.
        if (file_exists($CFG->dirroot . '/local/recompletion/version.php')) {
            // Get the plugin version.
            $pluginversion = core_plugin_manager::instance()->get_plugin_info('local_recompletion')->versiondb;

            // If the version is high enough.
            if ($pluginversion >= 2023112707) {
                // Remember the check result.
                $localrecompletioninstalled = true;

                // Otherwise.
            } else {
                // Remember the check result.
                $localrecompletioninstalled = false;
            }

            // Otherwise.
        } else {
            // Remember the check result.
            $localrecompletioninstalled = false;
        }
    }

    return $localrecompletioninstalled;
}

/**
 * Callback to modify the page navigation.
 * This function is implemented here and used from another location:
 * -> function enrol_semco_before_standard_top_of_body_html in lib.php (for releases up to Moodle 4.3)
 *
 * We use this callback as the enrol plugin type, unfortunately, does not include settings.php for non-admins.
 * So we have to use a nasty workaround to add the SEMCO enrolment report link to the site administration
 * where managers will find it.
 * This is done here by hooking into the page navigation manually before the page output is started.
 */
function enrol_semco_callbackimpl_before_standard_top_of_body_html() {
    global $PAGE;

    // If we are in the installation process or a major upgrade is required,
    // we cannot do anything here and return immediately.
    if (during_initial_install() || moodle_needs_upgrading()) {
        return;
    }

    // Allow admins and users with the enrol/semco:viewreport capability to access the report.
    $context = context_system::instance();
    if (
        has_capability('moodle/site:config', $context) ||
            has_capability('enrol/semco:viewreport', $context)
    ) {
        // Create new navigation node for enrolment report.
        $reportnode = navigation_node::create(
            get_string('reportpagetitle', 'enrol_semco', null, true),
            new moodle_url('/enrol/semco/enrolreport.php'),
            navigation_node::TYPE_SETTING,
            null,
            'enrol_semco_enrolreport'
        );

        // Find the reports container in navigation.
        $reports = $PAGE->settingsnav->find('reports', navigation_node::TYPE_SETTING);

        // If the reports container was found.
        if ($reports != false) {
            // Add our report node to the list of reports.
            $reports->add_node($reportnode);
        }
    }
}
