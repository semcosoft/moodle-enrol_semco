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

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that local_recompletion does not notify the users about a course completion reset.
 *
 * SEMCO resets a user's course completion on every SEMCO enrolment into a course, even on the very first one. A
 * notification about such a reset would confuse the user, thus the recompletion notification should be disabled.
 *
 * This item assesses the site-wide default of local_recompletion as well as the courses which hold SEMCO enrolments,
 * as the site-wide default only prefills the course settings and does not change the courses which exist already.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompletionnotify extends healthcheck {
    /** @var string Finding: The site-wide default of the recompletion notification is not disabled. */
    public const FINDING_SITEDEFAULT = 'sitedefault';

    /** @var string Finding: Courses with SEMCO enrolments notify their users about a course completion reset. */
    public const FINDING_COURSES = 'courses';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'recompletionnotify';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_recompletionnotify_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_recompletionnotify_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_recompletionnotify_description', 'enrol_semco');
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

        // The recompletion constants live in the autoloadable local_recompletion_recompletion_form class, but this
        // class extends moodleform, thus formslib has to be loaded before the class can be used. The local_recompletion
        // library takes care of that.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        // Start with an intact state.
        $status = healthcheck::OK;
        $disabled = \local_recompletion_recompletion_form::RECOMPLETION_NOTIFY_DISABLED;

        // Check the site-wide default which local_recompletion uses to prefill the course settings.
        // local_recompletion itself evaluates the setting with !empty(), see its check_recompletion task, thus a value of
        // 0 is just as disabled as the empty RECOMPLETION_NOTIFY_DISABLED constant and an unset setting.
        $sitedefault = get_config('local_recompletion', 'recompletionnotify');
        if (!empty($sitedefault)) {
            $this->add_finding(
                self::FINDING_SITEDEFAULT,
                get_string('healthcheck_recompletionnotify_findingsitedefault', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // The site-wide default only prefills the course settings, it does not change the courses which exist
        // already. Thus, get the courses which hold SEMCO enrolments and which notify their users on their own. The
        // courses are named, as an admin who fixes them himself has to look into each of them. The ids of the courses
        // are handed over as context, so that the automatic fix knows which courses to change.
        // Please note that local_recompletion's course settings page writes a value of 0 for every setting which the
        // form did not submit, see local/recompletion/recompletion.php, and evaluates the setting with !empty()
        // afterwards. A stored 0 therefore means 'disabled' as well and must not be reported.
        // The value column is a text column, thus it has to be compared with sql_compare_text().
        $sql = 'SELECT DISTINCT c.id
                FROM {enrol} e
                JOIN {course} c ON c.id = e.courseid
                JOIN {local_recompletion_config} rc ON rc.course = e.courseid AND rc.name = :configname
                WHERE e.enrol = :enrol AND rc.value IS NOT NULL
                    AND ' . $DB->sql_compare_text('rc.value') . ' <> ' . $DB->sql_compare_text(':disabled') . '
                    AND ' . $DB->sql_compare_text('rc.value') . ' <> ' . $DB->sql_compare_text(':zero');
        $params = [
            'configname' => 'recompletionnotify',
            'enrol' => 'semco',
            'disabled' => $disabled,
            'zero' => '0',
        ];
        $courseids = array_map('intval', $DB->get_fieldset_sql($sql, $params));
        if (count($courseids) > 0) {
            $this->add_finding(
                self::FINDING_COURSES,
                get_string('healthcheck_recompletionnotify_findingcourses', 'enrol_semco', [
                    'count' => count($courseids),
                    'total' => $this->count_semco_courses(),
                    'courses' => $this->name_courses($courseids),
                ]),
                $courseids
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // Return the status.
        return $status;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        $settingsurl = new \core\url('/admin/settings.php', ['section' => 'local_recompletion']);
        return [
            // The site-wide default only prefills the settings of the courses which get configured from now on, thus
            // changing it is harmless.
            self::FINDING_SITEDEFAULT => ['autofix' => true, 'risky' => false, 'url' => $settingsurl],
            // Changing the courses is not entirely harmless: A course which notifies its users has been configured
            // this way by its teacher, and the users of this course do not get any notification anymore afterwards.
            self::FINDING_COURSES => ['autofix' => true, 'risky' => true, 'url' => $settingsurl],
        ];
    }

    /**
     * Apply the automatic fix for one finding of this health check item.
     *
     * @param string $findingid The id of the finding to fix.
     * @param array $contexts The contexts which have been handed over to add_finding() for this finding id.
     * @return void
     */
    protected function apply_autofix(string $findingid, array $contexts): void {
        global $CFG;

        // The recompletion constants live in the autoloadable local_recompletion_recompletion_form class, but this
        // class extends moodleform, thus formslib has to be loaded before the class can be used. The local_recompletion
        // library takes care of that.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');
        $disabled = \local_recompletion_recompletion_form::RECOMPLETION_NOTIFY_DISABLED;

        switch ($findingid) {
            // Disable the notification in the site-wide default.
            case self::FINDING_SITEDEFAULT:
                autofix::set_recompletion_site_config('recompletionnotify', $disabled);
                break;

            // Disable the notification in the affected courses.
            case self::FINDING_COURSES:
                autofix::set_recompletion_course_config(array_merge(...$contexts), 'recompletionnotify', $disabled);
                break;
        }
    }
}
