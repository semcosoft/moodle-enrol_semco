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
 * Enrolment method "SEMCO" - Health check: Course recompletion: Type
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the courses with SEMCO enrolments are prepared for course completion resets.
 *
 * SEMCO is only able to reset a user's course completion if the course's recompletion type is set to 'On demand'.
 * This has to be done by the individual teachers in their courses, thus this item can only raise the awareness that
 * there are courses which are not prepared yet.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompletionondemand extends healthcheck {
    /** @var string Finding: The site-wide default of the recompletion type is not 'On demand'. */
    public const FINDING_SITEDEFAULT = 'sitedefault';

    /** @var string Finding: Courses with SEMCO enrolments are not set to the recompletion type 'On demand'. */
    public const FINDING_COURSES = 'courses';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'recompletionondemand';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_recompletionondemand_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_recompletionondemand_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_recompletionondemand_description', 'enrol_semco');
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
        $ondemand = \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND;

        // Check the site-wide default which local_recompletion uses to prefill the course settings.
        $sitedefault = get_config('local_recompletion', 'recompletiontype');
        if ((string) $sitedefault !== (string) $ondemand) {
            $this->add_finding(
                self::FINDING_SITEDEFAULT,
                get_string('healthcheck_recompletionondemand_findingsitedefault', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // The site-wide default only prefills the course settings, it does not change the courses which exist
        // already. Thus, get the courses which hold SEMCO enrolments but which are not set to 'On demand'.
        // The value column is a text column, thus it has to be compared with sql_compare_text().
        $sql = 'SELECT DISTINCT c.id
                FROM {enrol} e
                JOIN {course} c ON c.id = e.courseid
                LEFT JOIN {local_recompletion_config} rc ON rc.course = e.courseid AND rc.name = :configname
                WHERE e.enrol = :enrol AND (rc.value IS NULL OR ' .
                    $DB->sql_compare_text('rc.value') . ' <> ' . $DB->sql_compare_text(':ondemand') . ')';
        $params = [
            'configname' => 'recompletiontype',
            'enrol' => 'semco',
            'ondemand' => $ondemand,
        ];
        $courseids = array_map('intval', $DB->get_fieldset_sql($sql, $params));

        // If there is such a course, every SEMCO webservice call which wants to reset a course completion in it
        // fails with an exception. This does not affect the other courses, thus it is a warning and not an error.
        // The courses are named, as an admin who fixes them himself has to look into each of them. The ids of the
        // courses are handed over as context, so that the automatic fix knows which courses to change.
        if (count($courseids) > 0) {
            $this->add_finding(
                self::FINDING_COURSES,
                get_string('healthcheck_recompletionondemand_findingcourses', 'enrol_semco', [
                    'count' => count($courseids),
                    'total' => $this->count_semco_courses(),
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
            // Changing the courses is not entirely harmless: A course which uses another recompletion type, for
            // example a periodical one, has been configured this way by its teacher and stops recompleting on its own
            // as soon as it is set to 'On demand'. The recompletion type is configured per course, thus there is no
            // single page to link to.
            self::FINDING_COURSES => [
                'autofix' => true,
                'risky' => true,
                'url' => null,
            ],
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
        $ondemand = \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND;

        switch ($findingid) {
            // Set the site-wide default to 'On demand'.
            case self::FINDING_SITEDEFAULT:
                autofix::set_recompletion_site_config('recompletiontype', $ondemand);
                break;

            // Set the affected courses to 'On demand'.
            case self::FINDING_COURSES:
                autofix::set_recompletion_course_config(array_merge(...$contexts), 'recompletiontype', $ondemand);
                break;
        }
    }
}
