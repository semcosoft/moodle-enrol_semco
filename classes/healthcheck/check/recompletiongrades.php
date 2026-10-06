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
 * Enrolment method "SEMCO" - Health check: local_recompletion grade deletion
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that local_recompletion deletes the grades of a user when SEMCO resets the user's course
 * completion.
 *
 * A course completion reset which leaves the gradebook grades of the user in place produces an inconsistent course:
 * The activity attempts are gone, but the gradebook still shows the grades of these attempts.
 *
 * This item assesses the site-wide default of local_recompletion as well as the courses which hold SEMCO enrolments,
 * as the site-wide default only prefills the course settings and does not change the courses which exist already. Only
 * courses with completion tracking enabled are assessed, as there is no course completion to reset in the other courses.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompletiongrades extends healthcheck {
    /** @var string Finding: The site-wide default does not delete the grades of the user. */
    public const FINDING_SITEDEFAULT = 'sitedefault';

    /** @var string Finding: Courses with SEMCO enrolments do not delete the grades of the user. */
    public const FINDING_COURSES = 'courses';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'recompletiongrades';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_recompletiongrades_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_recompletiongrades_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_recompletiongrades_description', 'enrol_semco');
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
        global $DB;

        // If local_recompletion is not installed, this check cannot be assessed.
        if (enrol_semco_check_local_recompletion() != true) {
            $this->add_finding(
                self::FINDING_NORECOMPLETION,
                get_string('healthcheck_findingnorecompletion', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // Start with an intact state.
        $status = healthcheck::OK;

        // Check the site-wide default which local_recompletion uses to prefill the course settings.
        // local_recompletion itself evaluates the setting with a truthiness check, see its check_recompletion task,
        // thus an unset setting is just as disabled as a value of 0.
        if (empty(get_config('local_recompletion', 'deletegradedata'))) {
            $this->add_finding(
                self::FINDING_SITEDEFAULT,
                get_string('healthcheck_recompletiongrades_findingsitedefault', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // The site-wide default only prefills the course settings, it does not change the courses which exist
        // already. Thus, get the courses which hold SEMCO enrolments but which do not delete the grades of the user.
        // Courses which do not have completion tracking enabled in their course settings are out of scope, as there is no
        // course completion which SEMCO could reset there.
        // A course which does not hold the setting at all does not delete the grades either, see
        // local_recompletion_get_config() in the local_recompletion library, thus a missing setting is reported
        // as well. The courses are named, as an admin who fixes them himself has to look into each of them. The ids
        // of the courses are handed over as context, so that the automatic fix knows which courses to change.
        // The value column is a text column, thus it has to be compared with sql_compare_text().
        $sql = 'SELECT DISTINCT c.id
                FROM {enrol} e
                JOIN {course} c ON c.id = e.courseid
                LEFT JOIN {local_recompletion_config} rc ON rc.course = e.courseid AND rc.name = :configname
                WHERE e.enrol = :enrol AND c.enablecompletion = :enablecompletion AND (rc.value IS NULL OR ' .
                    $DB->sql_compare_text('rc.value') . ' <> ' . $DB->sql_compare_text(':enabled') . ')';
        $params = [
            'configname' => 'deletegradedata',
            'enrol' => 'semco',
            'enablecompletion' => 1,
            'enabled' => '1',
        ];
        $courseids = array_map('intval', $DB->get_fieldset_sql($sql, $params));
        if (count($courseids) > 0) {
            $this->add_finding(
                self::FINDING_COURSES,
                get_string('healthcheck_recompletiongrades_findingcourses', 'enrol_semco', [
                    'count' => count($courseids),
                    'total' => $this->count_semco_courses(true),
                    'courses' => $this->name_courses($courseids),
                ]),
                $courseids
            );
            $status = $this->escalate($status, healthcheck::WARNING);
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
        return [
            // The site-wide default only prefills the settings of the courses which get configured from now on, thus
            // changing it is harmless.
            self::FINDING_SITEDEFAULT => [
                'autofix' => true,
                'risky' => false,
                'url' => new \core\url('/admin/settings.php', ['section' => 'local_recompletion']),
            ],
            // Changing the courses is not entirely harmless: A course which keeps the grades has been configured this
            // way by its teacher, and the next course completion reset deletes the grades of the users afterwards. The
            // setting is configured per course, thus there is no single page to link to.
            self::FINDING_COURSES => ['autofix' => true, 'risky' => true, 'url' => null],
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
        switch ($findingid) {
            // Enable the grade deletion in the site-wide default.
            case self::FINDING_SITEDEFAULT:
                autofix::set_recompletion_site_config('deletegradedata', '1');
                break;

            // Enable the grade deletion in the affected courses.
            case self::FINDING_COURSES:
                autofix::set_recompletion_course_config(array_merge(...$contexts), 'deletegradedata', '1');
                break;
        }
    }
}
