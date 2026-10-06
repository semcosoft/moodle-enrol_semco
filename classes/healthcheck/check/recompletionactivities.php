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
 * Enrolment method "SEMCO" - Health check item
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that local_recompletion resets the activities of a course.
 *
 * By default, local_recompletion does not reset any activity unless the teacher activates the activity type's reset in
 * his particular course. If no activity type is activated site-wide, a course completion reset does not delete anything
 * from the course at all.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompletionactivities extends healthcheck {
    /** @var string Finding: local_recompletion does not offer any site-wide setting which could be assessed. */
    public const FINDING_NOSETTINGS = 'nosettings';

    /** @var string Finding: The site-wide settings do not reset any activity type. */
    public const FINDING_NONE = 'none';

    /** @var string Finding: The site-wide settings do not reset some activity types. */
    public const FINDING_SOME = 'some';

    /** @var string Finding: Courses with SEMCO enrolments reset fewer activity types than the site-wide settings. */
    public const FINDING_COURSES = 'courses';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'recompletionactivities';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_recompletionactivities_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_recompletionactivities_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_recompletionactivities_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_RECOMPLETION;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        global $CFG, $DB;

        // If local_recompletion is not installed, this check cannot be assessed.
        if (enrol_semco_check_local_recompletion() != true) {
            $this->add_finding(
                self::FINDING_NORECOMPLETION,
                get_string('healthcheck_findingnorecompletion', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // We need the local_recompletion library for the list of supported activity types and for the
        // constant which marks an activity type as not being reset.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        // Collect the activity types which are not reset and, on the other hand, the settings of the activity
        // types which are reset. The latter are the ones which a course can weaken.
        $notreset = [];
        $resetsettings = [];
        $assessed = 0;
        foreach (local_recompletion_get_supported_plugins() as $component) {
            // The site-wide setting of an activity type is named after the module without its 'mod_' prefix.
            $settingname = preg_replace('/^mod_/', '', $component);
            $setting = get_config('local_recompletion', $settingname);

            // Not every supported activity type brings a site-wide setting along. Those which do not can only be
            // configured within a course and are therefore out of scope here.
            if ($setting === false) {
                continue;
            }
            $assessed++;

            // Remember the activity types which are set to do nothing.
            if ((int) $setting === LOCAL_RECOMPLETION_NOTHING) {
                $notreset[] = get_string('pluginname', $component);

                // And remember the setting names of the activity types which are reset.
            } else {
                $resetsettings[] = $settingname;
            }
        }

        // If there is not any assessable activity type, this check cannot be assessed either.
        if ($assessed < 1) {
            $this->add_finding(
                self::FINDING_NOSETTINGS,
                get_string('healthcheck_recompletionactivities_findingnosettings', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // Start with an intact state.
        $status = healthcheck::OK;

        // If no activity type is reset at all, a course completion reset does not have any effect on the course.
        if (count($notreset) === $assessed) {
            $this->add_finding(
                self::FINDING_NONE,
                get_string('healthcheck_recompletionactivities_findingnone', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);

            // Or if only some activity types are not reset, name them so that the admin is aware of them. The list
            // is bounded by the activity types which local_recompletion supports, thus it is not cut off.
        } else if (count($notreset) > 0) {
            $this->add_finding(
                self::FINDING_SOME,
                get_string('healthcheck_recompletionactivities_findingsome', 'enrol_semco', [
                    'count' => count($notreset),
                    'activities' => $this->format_list($notreset),
                ])
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // The site-wide settings only prefill the course settings, a teacher can weaken them in his course. Thus, get
        // the courses which hold SEMCO enrolments and which reset fewer activity types than the site wants, and name
        // them, as the admin has to look into each of them.
        $weakenedcourses = $this->get_weakened_semco_courses($resetsettings);
        if (count($weakenedcourses) > 0) {
            $this->add_finding(self::FINDING_COURSES, get_string(
                'healthcheck_recompletionactivities_findingcourses',
                'enrol_semco',
                [
                    'count' => count($weakenedcourses),
                    'total' => $this->count_semco_courses(true),
                    'courses' => $this->name_courses($weakenedcourses),
                ]
            ));
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // Return the status.
        return $status;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * None of the findings is fixed automatically: Each activity type offers its own set of reset strategies, for
     * example to delete the attempts or to grant an extra attempt, and picking one of them is a didactical decision
     * which we cannot make for the admin or the teachers.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        // Link to the settings page of local_recompletion which holds the site-wide defaults.
        $settingsurl = new \core\url('/admin/settings.php', ['section' => 'local_recompletion']);
        return [
            // This finding is reported along with the N/A status only, there is nothing to fix at all.
            self::FINDING_NOSETTINGS => ['autofix' => false, 'risky' => false, 'url' => null],
            self::FINDING_NONE => ['autofix' => false, 'risky' => false, 'url' => $settingsurl],
            self::FINDING_SOME => ['autofix' => false, 'risky' => false, 'url' => $settingsurl],
            self::FINDING_COURSES => ['autofix' => false, 'risky' => false, 'url' => $settingsurl],
        ];
    }

    /**
     * Get the courses which hold SEMCO enrolments and which reset fewer activity types than the site-wide settings.
     *
     * A course which resets more activity types than the site-wide settings is not picked, as that is a deliberate
     * decision of the teacher which does not endanger the course completion reset.
     *
     * @param array $resetsettings The names of the local_recompletion settings of the activity types which the
     *                             site-wide settings reset.
     * @return int[] The ids of the affected courses.
     */
    protected function get_weakened_semco_courses(array $resetsettings): array {
        global $DB;

        // If the site does not reset any activity type, a course cannot reset less than the site.
        if (count($resetsettings) < 1) {
            return [];
        }

        // Get the relevant course settings of all courses which hold SEMCO enrolments.
        [$insql, $inparams] = $DB->get_in_or_equal($resetsettings, SQL_PARAMS_NAMED, 'name');
        // Courses which do not have completion tracking enabled in their course settings are out of scope, as there is no
        // course completion which SEMCO could reset there.
        $sql = 'SELECT c.id AS courseid, rc.name AS settingname, rc.value AS settingvalue
                FROM {enrol} e
                JOIN {course} c ON c.id = e.courseid
                LEFT JOIN {local_recompletion_config} rc ON rc.course = c.id AND rc.name ' . $insql . '
                WHERE e.enrol = :enrol AND c.enablecompletion = :enablecompletion';
        $params = array_merge($inparams, ['enrol' => 'semco', 'enablecompletion' => 1]);

        // Pick the courses apart. A course which does not hold a setting at all does not reset the activity type
        // either, thus a missing setting counts just like a setting which is set to do nothing.
        $coursesettings = [];
        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $record) {
            if (!array_key_exists($record->courseid, $coursesettings)) {
                $coursesettings[$record->courseid] = [];
            }
            if ($record->settingname !== null) {
                $coursesettings[$record->courseid][$record->settingname] = $record->settingvalue;
            }
        }
        $recordset->close();

        // Pick the courses which do not reset at least one of the activity types which the site resets.
        $weakened = [];
        foreach ($coursesettings as $courseid => $settings) {
            foreach ($resetsettings as $settingname) {
                if ((int) ($settings[$settingname] ?? LOCAL_RECOMPLETION_NOTHING) === LOCAL_RECOMPLETION_NOTHING) {
                    $weakened[] = (int) $courseid;
                    break;
                }
            }
        }

        // Return the ids of the affected courses.
        return $weakened;
    }
}
