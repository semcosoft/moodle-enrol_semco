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
 * Enrolment method "SEMCO" - Health check: Course recompletion: Settings access
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that no role is allowed to change the course recompletion settings within a course.
 *
 * By default, local_recompletion grants the local/recompletion:manage capability to the teacher and the manager roles.
 * A role which holds this capability can change the recompletion settings of a course and thereby weaken the site-wide
 * rules which the other items of this category ask for. Within a SEMCO-Moodle setup, the course recompletion settings
 * should be governed by the site-wide defaults only, thus the capability should not be held by any role.
 *
 * Site administrators are not affected by this as they hold every capability anyway.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompletionmanage extends healthcheck {
    /** @var string Finding: The definition of a role allows to change the course recompletion settings. */
    public const FINDING_ROLE = 'role';

    /** @var string Finding: A role is allowed to change the course recompletion settings by permission overrides. */
    public const FINDING_OVERRIDE = 'override';

    /** @var string The capability which no role should hold. */
    protected const CAPABILITY = 'local/recompletion:manage';

    /** @var string The role archetype whose roles are reported as a warning instead of a notice. */
    protected const ARCHETYPE_WARNING = 'editingteacher';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'recompletionmanage';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_recompletionmanage_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_recompletionmanage_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_recompletionmanage_description', 'enrol_semco');
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

        // If local_recompletion is not installed, the capability does not exist and this check cannot be assessed.
        if (enrol_semco_check_local_recompletion() != true || get_capability_info(self::CAPABILITY, false) === null) {
            $this->add_finding(
                self::FINDING_NORECOMPLETION,
                get_string('healthcheck_findingnorecompletion', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // Start with an intact state.
        $status = healthcheck::OK;

        // Get all roles along with their names and archetypes. The archetype decides how severe a finding is.
        $roles = get_all_roles();
        $rolenames = role_fix_names($roles, \context_system::instance(), ROLENAME_ORIGINAL);

        // Get the roles which hold the capability in the system context, which is where roles are defined.
        // Unlike the 'Self-service reset' item, this item does not look at the SEMCO enrolment role only: The
        // capability is meant to be held by nobody, thus every role is a candidate.
        $definitionroleids = $DB->get_fieldset_select(
            'role_capabilities',
            'roleid',
            'capability = :capability AND contextid = :contextid AND permission = :permission',
            [
                'capability' => self::CAPABILITY,
                'contextid' => \context_system::instance()->id,
                'permission' => CAP_ALLOW,
            ]
        );
        $definitionroleids = array_map('intval', $definitionroleids);
        sort($definitionroleids);

        // Report each of these roles. The id of the role is handed over as context, so that the automatic fix knows
        // which role to change.
        foreach ($definitionroleids as $roleid) {
            // Skip the role if it has vanished in the meantime.
            if (!isset($roles[$roleid])) {
                continue;
            }
            $this->add_finding(
                self::FINDING_ROLE,
                get_string('healthcheck_recompletionmanage_findingrole', 'enrol_semco', $rolenames[$roleid]->localname),
                $roleid
            );
            $status = $this->escalate($status, $this->get_severity($roles[$roleid]));
        }

        // The role definition is not the only place which can grant the capability. Get the roles which hold the
        // capability in the courses which SEMCO actually uses nevertheless, and name the courses, as the admin has to
        // look into each of them. The roles whose definition grants the capability have been reported already.
        $overrides = $this->get_roles_with_capability_in_semco_courses(self::CAPABILITY, true);
        foreach ($overrides as $roleid => $courseids) {
            // Skip the roles which have been reported already or which have vanished in the meantime.
            if (in_array($roleid, $definitionroleids) || !isset($roles[$roleid])) {
                continue;
            }
            $this->add_finding(
                self::FINDING_OVERRIDE,
                get_string('healthcheck_recompletionmanage_findingoverride', 'enrol_semco', [
                    'role' => $rolenames[$roleid]->localname,
                    'count' => count($courseids),
                    'total' => $this->count_semco_courses(true),
                    'courses' => $this->name_courses($courseids),
                ])
            );
            $status = $this->escalate($status, $this->get_severity($roles[$roleid]));
        }

        // Return the status.
        return $status;
    }

    /**
     * Return the status with which a finding about the given role is reported.
     *
     * Teachers are the ones who configure their own courses, thus a teacher role which is allowed to change the course
     * recompletion settings is the most likely reason for a course which deviates from the site-wide rules. Such a role
     * is reported as a warning, every other role is reported as a notice.
     *
     * @param \stdClass $role The role record.
     * @return string The status.
     */
    protected function get_severity(\stdClass $role): string {
        if ($role->archetype === self::ARCHETYPE_WARNING) {
            return healthcheck::WARNING;
        }
        return healthcheck::NOTICE;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        // Both findings link to the capability overview report which allows to inspect one capability across all
        // roles. The report cannot be preset from the URL, the admin has to pick the capability there.
        $reporturl = new \core\url('/admin/tool/capability/index.php');

        return [
            // Changing the role definitions is not entirely harmless, as the roles are not owned by this plugin: They
            // are most likely used in courses which are not connected to SEMCO as well, and the teachers and managers
            // of these courses lose the access to the course recompletion settings along with the SEMCO courses.
            self::FINDING_ROLE => ['autofix' => true, 'risky' => true, 'url' => $reporturl],
            // The permission overrides have been set within particular courses or categories, most likely on purpose,
            // thus we do not remove them automatically.
            self::FINDING_OVERRIDE => ['autofix' => false, 'risky' => false, 'url' => $reporturl],
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
            // Remove the capability from the role definitions.
            case self::FINDING_ROLE:
                foreach ($contexts as $roleid) {
                    autofix::revoke_capability(self::CAPABILITY, $roleid, \context_system::instance()->id);
                }
                break;
        }
    }
}
