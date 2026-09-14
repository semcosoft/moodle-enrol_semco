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
 * Enrolment method "SEMCO" - Health check: Participant visibility of the enrolment role
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO enrolment role cannot view the other course participants.
 *
 * SEMCO course participants should not be assumed to be members of the same class or cohort and do not necessarily know
 * each other. If they were able to see each other, this might even be a data protection leak. In contrast to the other
 * items of this category, this item therefore reports a warning and not just a notice.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolmentroleviewparticipants extends healthcheck {
    /** @var string Finding: The definition of the SEMCO enrolment role allows to view the course participants. */
    public const FINDING_ALLOWED = 'allowed';

    /** @var string Finding: The SEMCO enrolment role is allowed to view the course participants by permission overrides. */
    public const FINDING_OVERRIDE = 'override';

    /** @var string The capability which the SEMCO enrolment role should not hold. */
    protected const CAPABILITY = 'moodle/course:viewparticipants';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'enrolmentroleviewparticipants';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_enrolmentroleviewparticipants_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_enrolmentroleviewparticipants_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_enrolmentroleviewparticipants_description', 'enrol_semco');
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
        global $DB;

        // If the enrolment role is not configured, this check cannot be assessed. The 'SEMCO enrolment role' item
        // reports the missing role itself.
        $role = $this->get_configured_enrolment_role();
        if ($role === null) {
            $this->add_finding(
                self::FINDING_NOENROLMENTROLE,
                get_string('healthcheck_findingnoenrolmentrole', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // Start with an intact state.
        $status = healthcheck::OK;
        $rolenames = role_fix_names(get_all_roles(), \context_system::instance(), ROLENAME_ORIGINAL);
        $rolename = $rolenames[$role->id]->localname;

        // Get the role's permission for the capability in the system context, which is where roles are defined.
        $permission = $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $role->id,
            'capability' => self::CAPABILITY,
            'contextid' => \context_system::instance()->id,
        ]);

        // If the role is allowed to view the course participants, SEMCO course participants can see each other. The
        // id of the role is handed over as context, so that the automatic fix knows which role to change.
        if ($permission !== false && (int) $permission === CAP_ALLOW) {
            $this->add_finding(
                self::FINDING_ALLOWED,
                get_string('healthcheck_enrolmentroleviewparticipants_findingallowed', 'enrol_semco', $rolename),
                (int) $role->id
            );
            $status = $this->escalate($status, healthcheck::WARNING);

            // Otherwise, the role definition is not the only place which can grant the capability. Get the courses
            // which SEMCO actually uses and in which the role holds the capability nevertheless, and name them, as the
            // admin has to look into each of them.
        } else {
            $courseids = $this->get_semco_courses_with_capability(self::CAPABILITY, (int) $role->id);
            if (count($courseids) > 0) {
                $this->add_finding(
                    self::FINDING_OVERRIDE,
                    get_string('healthcheck_enrolmentroleviewparticipants_findingoverride', 'enrol_semco', [
                        'role' => $rolename,
                        'count' => count($courseids),
                        'total' => $this->count_semco_courses(),
                        'courses' => $this->name_courses($courseids),
                    ])
                );
                $status = $this->escalate($status, healthcheck::WARNING);
            }
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
        // Get the configured enrolment role to link to its definition page.
        $role = $this->get_configured_enrolment_role();
        $roleurl = new \core\url('/admin/roles/define.php', ['action' => 'view', 'roleid' => ($role !== null) ? $role->id : 0]);

        return [
            // Changing the role definition is not entirely harmless, as the role is not owned by this plugin: It is
            // most likely used in courses which are not connected to SEMCO as well, and the participants of these
            // courses lose the participants list along with the SEMCO course participants.
            self::FINDING_ALLOWED => ['autofix' => true, 'risky' => true, 'url' => $roleurl],
            // The permission overrides have been set within particular courses or categories, most likely on purpose
            // by the teachers, thus we do not remove them automatically. The role definition page does not show them
            // either, thus the admin is led to the capability overview report which lists every override of a
            // capability. The report cannot be preset from the URL, the admin has to pick the capability there.
            self::FINDING_OVERRIDE => [
                'autofix' => false,
                'risky' => false,
                'url' => new \core\url('/admin/tool/capability/index.php'),
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
        switch ($findingid) {
            // Remove the capability from the role definition.
            case self::FINDING_ALLOWED:
                foreach ($contexts as $roleid) {
                    autofix::revoke_capability(self::CAPABILITY, $roleid, \context_system::instance()->id);
                }
                break;
        }
    }
}
