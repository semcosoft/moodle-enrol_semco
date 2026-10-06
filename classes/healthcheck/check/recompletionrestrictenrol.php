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
 * Enrolment method "SEMCO" - Health check: local_recompletion enrolment method restriction
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that local_recompletion does not exclude SEMCO enrolments from a course completion reset.
 *
 * local_recompletion can restrict a course completion reset to users who are enrolled with particular enrolment
 * methods. If this restriction is set but does not include the SEMCO enrolment method, local_recompletion refuses to
 * reset the course completion of a SEMCO user, the webservice function enrol_semco_reset_course_completion reports the
 * reset as unsuccessful and the course completion stays in place.
 *
 * This item assesses the site-wide default of local_recompletion as well as the courses which hold SEMCO enrolments,
 * as the site-wide default only prefills the course settings and does not change the courses which exist already.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompletionrestrictenrol extends healthcheck {
    /** @var string Finding: The site-wide default restricts the reset to enrolment methods other than SEMCO. */
    public const FINDING_SITEDEFAULT = 'sitedefault';

    /** @var string Finding: Courses with SEMCO enrolments restrict the reset to enrolment methods other than SEMCO. */
    public const FINDING_COURSES = 'courses';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'recompletionrestrictenrol';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_recompletionrestrictenrol_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_recompletionrestrictenrol_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_recompletionrestrictenrol_description', 'enrol_semco');
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
        // An unset or empty setting means that the reset is not restricted at all, which is fine.
        $sitedefault = get_config('local_recompletion', 'restrictenrol');
        if (self::excludes_semco($sitedefault)) {
            $this->add_finding(
                self::FINDING_SITEDEFAULT,
                get_string('healthcheck_recompletionrestrictenrol_findingsitedefault', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // The site-wide default only prefills the course settings, it does not change the courses which exist
        // already. Thus, get the courses which hold SEMCO enrolments and which restrict the reset on their own.
        // The setting holds a comma-separated list of enrolment methods, thus the list is picked apart in PHP rather
        // than in SQL to match the enrolment method as a whole and not as a substring. The amount of courses with
        // SEMCO enrolments is bounded anyway. The courses are named, as an admin who fixes them himself has to look
        // into each of them. The ids of the courses are handed over as context, so that the automatic fix knows
        // which courses to change.
        // Courses which do not have completion tracking enabled in their course settings are out of scope, as there is no
        // course completion which SEMCO could reset there.
        $sql = 'SELECT DISTINCT c.id, rc.value
                FROM {enrol} e
                JOIN {course} c ON c.id = e.courseid
                JOIN {local_recompletion_config} rc ON rc.course = e.courseid AND rc.name = :configname
                WHERE e.enrol = :enrol AND c.enablecompletion = :enablecompletion AND rc.value IS NOT NULL';
        $params = ['configname' => 'restrictenrol', 'enrol' => 'semco', 'enablecompletion' => 1];
        $courseids = [];
        foreach ($DB->get_records_sql_menu($sql, $params) as $courseid => $value) {
            if (self::excludes_semco($value)) {
                $courseids[] = (int) $courseid;
            }
        }
        if (count($courseids) > 0) {
            $this->add_finding(
                self::FINDING_COURSES,
                get_string('healthcheck_recompletionrestrictenrol_findingcourses', 'enrol_semco', [
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
     * Both fixes only add the SEMCO enrolment method to the restriction and keep the other enrolment methods which
     * the admin or the teacher has picked, thus they are harmless.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        $settingsurl = new \core\url('/admin/settings.php', ['section' => 'local_recompletion']);
        return [
            self::FINDING_SITEDEFAULT => ['autofix' => true, 'risky' => false, 'url' => $settingsurl],
            // The restriction is configured per course, thus there is no single page to link to.
            self::FINDING_COURSES => ['autofix' => true, 'risky' => false, 'url' => null],
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
        global $DB;

        switch ($findingid) {
            // Add the SEMCO enrolment method to the site-wide default.
            case self::FINDING_SITEDEFAULT:
                $value = get_config('local_recompletion', 'restrictenrol');
                autofix::set_recompletion_site_config('restrictenrol', self::include_semco($value));
                break;

            // Add the SEMCO enrolment method to the restriction of the affected courses. Each course holds its own
            // list, thus the courses are fixed one by one.
            case self::FINDING_COURSES:
                foreach (array_merge(...$contexts) as $courseid) {
                    $value = $DB->get_field('local_recompletion_config', 'value', [
                        'course' => $courseid,
                        'name' => 'restrictenrol',
                    ]);
                    autofix::set_recompletion_course_config([$courseid], 'restrictenrol', self::include_semco($value));
                }
                break;
        }
    }

    /**
     * Pick a comma-separated list of enrolment methods apart.
     *
     * @param mixed $value The raw setting value.
     * @return string[] The enrolment methods, without duplicates and empty entries.
     */
    protected static function parse(mixed $value): array {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $methods = array_map('trim', explode(',', $value));
        return array_values(array_unique(array_filter($methods, fn(string $method): bool => $method !== '')));
    }

    /**
     * Check if a restriction excludes the SEMCO enrolment method.
     *
     * @param mixed $value The raw setting value.
     * @return bool True if the restriction is set and does not include the SEMCO enrolment method.
     */
    protected static function excludes_semco(mixed $value): bool {
        $methods = self::parse($value);
        return count($methods) > 0 && !in_array('semco', $methods, true);
    }

    /**
     * Add the SEMCO enrolment method to a restriction.
     *
     * @param mixed $value The raw setting value.
     * @return string The setting value with the SEMCO enrolment method included.
     */
    protected static function include_semco(mixed $value): string {
        $methods = self::parse($value);
        if (!in_array('semco', $methods, true)) {
            $methods[] = 'semco';
        }
        return implode(',', $methods);
    }
}
