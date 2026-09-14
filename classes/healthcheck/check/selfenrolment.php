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
 * Enrolment method "SEMCO" - Health check: Self enrolment
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that self enrolment is not offered in the Moodle instance.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class selfenrolment extends healthcheck {
    /** @var string The capability which a user needs to enrol himself into a course. */
    public const CAPABILITY = 'enrol/self:enrolself';

    /** @var string Finding: Courses with SEMCO enrolments offer an unguarded self enrolment. */
    public const FINDING_OPENCOURSES = 'opencourses';

    /** @var string Finding: Courses with SEMCO enrolments offer a self enrolment which is guarded by an enrolment key. */
    public const FINDING_GUARDEDCOURSES = 'guardedcourses';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'selfenrolment';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_selfenrolment_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_selfenrolment_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_selfenrolment_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_RECOMMENDATIONS;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // Start with an intact state.
        $status = healthcheck::OK;

        // If the self enrolment method is disabled altogether, nobody can enrol himself and there is nothing to check.
        if (!array_key_exists('self', \core\plugininfo\enrol::get_enabled_plugins())) {
            return $status;
        }

        // Pick the courses which hold SEMCO enrolments apart by the way their self enrolment can be used.
        [$opencourses, $guardedcourses] = $this->classify_self_enrolment_courses();

        // If a course offers a self enrolment instance which an ordinary user can use without any further ado, a user
        // which SEMCO has enrolled somewhere else can enrol into that course without paying for it via SEMCO. This
        // is a consequence which an admin should not accept unknowingly, thus this finding reports a warning and not
        // just a notice. The courses are named in both findings, as the admin has to look into each of them.
        if (count($opencourses) > 0) {
            $this->add_finding(self::FINDING_OPENCOURSES, get_string(
                'healthcheck_selfenrolment_findingopencourses',
                'enrol_semco',
                [
                    'count' => count($opencourses),
                    'total' => $this->count_semco_courses(),
                    'courses' => $this->name_courses($opencourses),
                ]
            ));
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // If a course offers a self enrolment instance which is guarded by an enrolment key, the user needs to know
        // that key. That is a hurdle, but not a wall, thus we just raise the admin's awareness here.
        if (count($guardedcourses) > 0) {
            $this->add_finding(self::FINDING_GUARDEDCOURSES, get_string(
                'healthcheck_selfenrolment_findingguardedcourses',
                'enrol_semco',
                [
                    'count' => count($guardedcourses),
                    'total' => $this->count_semco_courses(),
                    'courses' => $this->name_courses($guardedcourses),
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
     * None of the findings is fixed automatically: We would have to disable the self enrolment instances of the
     * affected courses, and these instances are in the hands of the teachers who may need them for their own purposes.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        $enrolsurl = new \core\url('/admin/settings.php', ['section' => 'manageenrols']);
        return [
            self::FINDING_OPENCOURSES => ['autofix' => false, 'risky' => false, 'url' => $enrolsurl],
            self::FINDING_GUARDEDCOURSES => ['autofix' => false, 'risky' => false, 'url' => $enrolsurl],
        ];
    }

    /**
     * Sort the courses which hold SEMCO enrolments by the way their self enrolment can be used.
     *
     * A user which SEMCO has enrolled into a course is an ordinary Moodle user everywhere else. Thus, this function
     * looks at the role which every authenticated user holds and checks whether that role is allowed to enrol itself
     * in the particular course.
     *
     * The enrolment key itself is not assessed, only its presence. A user might know the key, might have received it
     * from somebody else or might guess it, thus a course which asks for a key is still worth reporting.
     *
     * @return array An array with the ids of the courses which offer an unguarded self enrolment as first element and
     *               the ids of the courses which offer a self enrolment with an enrolment key as second element.
     */
    protected function classify_self_enrolment_courses(): array {
        global $CFG, $DB;

        // If there is not any default role for authenticated users, a user does not hold any capability in a course
        // which he is not enrolled into and can therefore not enrol himself into it either.
        $defaultuserroleid = (int) ($CFG->defaultuserroleid ?? 0);
        if ($defaultuserroleid < 1) {
            return [[], []];
        }

        // Get the active self enrolment instances of the courses which hold SEMCO enrolments. The context path is
        // fetched along with them as the capability is evaluated in the course context below.
        $sql = 'SELECT se.id, se.courseid, se.password, ctx.path
                FROM {enrol} se
                JOIN {context} ctx ON ctx.instanceid = se.courseid AND ctx.contextlevel = :contextlevel
                WHERE se.enrol = :selfenrol AND se.status = :status
                      AND EXISTS (SELECT 1
                                  FROM {enrol} semco
                                  WHERE semco.courseid = se.courseid AND semco.enrol = :semcoenrol)';
        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'selfenrol' => 'self',
            'status' => ENROL_INSTANCE_ENABLED,
            'semcoenrol' => 'semco',
        ];
        $instances = $DB->get_records_sql($sql, $params);

        // Sort the courses into the two buckets.
        $open = [];
        $guarded = [];
        foreach ($instances as $instance) {
            // Skip the instances which the role of the authenticated users is not allowed to use in this course. The
            // instance record carries the context path, which is all that the Moodle core function needs.
            [$needed, $forbidden] = get_roles_with_cap_in_context($instance, self::CAPABILITY);
            if (!isset($needed[$defaultuserroleid]) || isset($forbidden[$defaultuserroleid])) {
                continue;
            }

            // Sort the course into the matching bucket.
            if ((string) $instance->password !== '') {
                $guarded[$instance->courseid] = true;
            } else {
                $open[$instance->courseid] = true;
            }
        }

        // A course which offers an unguarded instance is not reported as guarded as well.
        $guarded = array_diff_key($guarded, $open);

        // Return the ids of the courses per bucket.
        return [array_map('intval', array_keys($open)), array_map('intval', array_keys($guarded))];
    }
}
