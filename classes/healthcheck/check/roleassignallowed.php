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
 * Enrolment method "SEMCO" - Health check: Allowed role assignments
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice role is allowed to assign the configured enrolment role.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roleassignallowed extends healthcheck {
    /** @var string Finding: The SEMCO webservice role is not allowed to assign the configured enrolment role. */
    public const FINDING_NOTALLOWED = 'notallowed';

    /** @var string Finding: The configured enrolment role cannot be assigned in the course context. */
    public const FINDING_NOCOURSECONTEXT = 'nocoursecontext';

    /** @var string Finding: The SEMCO webservice role is allowed to assign roles which it does not need. */
    public const FINDING_SUPERFLUOUS = 'superfluous';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'roleassignallowed';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_roleassignallowed_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_roleassignallowed_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_roleassignallowed_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_ROLE;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        global $DB;

        // If the SEMCO webservice role does not exist, this check cannot be assessed.
        $role = $this->get_semco_role();
        if ($role === null) {
            $this->add_finding(self::FINDING_NOROLE, get_string('healthcheck_findingnorole', 'enrol_semco'));
            return healthcheck::NA;
        }

        // If the enrolment role is not configured, this check cannot be assessed either. The 'SEMCO enrolment role'
        // item reports the missing role itself.
        $enrolmentrole = $this->get_configured_enrolment_role();
        if ($enrolmentrole === null) {
            $this->add_finding(
                self::FINDING_NOENROLMENTROLE,
                get_string('healthcheck_findingnoenrolmentrole', 'enrol_semco')
            );
            return healthcheck::NA;
        }
        $enrolmentroleid = (int) $enrolmentrole->id;

        // Start with an intact state.
        $status = healthcheck::OK;
        $rolenames = role_fix_names(get_all_roles(), \context_system::instance(), ROLENAME_ORIGINAL);

        // If the SEMCO webservice role is not allowed to assign the configured enrolment role, SEMCO cannot enrol
        // anyone with that role.
        if (!$DB->record_exists('role_allow_assign', ['roleid' => $role->id, 'allowassign' => $enrolmentroleid])) {
            $this->add_finding(
                self::FINDING_NOTALLOWED,
                get_string(
                    'healthcheck_roleassignallowed_findingnotallowed',
                    'enrol_semco',
                    $rolenames[$enrolmentroleid]->localname
                ),
                $enrolmentroleid
            );
            $status = $this->escalate($status, healthcheck::ERROR);
        }

        // The permission above is only half of the job: Moodle only lets a role be assigned in the context levels
        // which the role definition allows. The enrolment webservice asks get_assignable_roles() for the roles which
        // the SEMCO webservice user can assign in the course, and this function filters by the context level as well.
        // If the enrolment role cannot be assigned in the course context, SEMCO cannot enrol anyone with that role.
        if (!in_array(CONTEXT_COURSE, array_map('intval', get_role_contextlevels($enrolmentroleid)))) {
            $this->add_finding(
                self::FINDING_NOCOURSECONTEXT,
                get_string(
                    'healthcheck_roleassignallowed_findingnocoursecontext',
                    'enrol_semco',
                    $rolenames[$enrolmentroleid]->localname
                ),
                $enrolmentroleid
            );
            $status = $this->escalate($status, healthcheck::ERROR);
        }

        // The SEMCO webservice role does not need to assign any other role. Such a permission usually is a leftover of
        // an earlier enrolment role which has been replaced in the plugin settings, as the plugin grants the permission
        // for a newly chosen role but does not revoke the one for the previous role.
        $allowed = $DB->get_fieldset_select('role_allow_assign', 'allowassign', 'roleid = :roleid', [
            'roleid' => $role->id,
        ]);
        $others = array_values(array_diff(array_map('intval', $allowed), [$enrolmentroleid]));
        if (count($others) > 0) {
            // The role names are HTML-safe already as role_fix_names() runs them through format_string().
            $names = [];
            foreach ($others as $otherroleid) {
                $names[] = isset($rolenames[$otherroleid]) ? $rolenames[$otherroleid]->localname : '#' . $otherroleid;
            }
            $this->add_finding(
                self::FINDING_SUPERFLUOUS,
                get_string('healthcheck_roleassignallowed_findingsuperfluous', 'enrol_semco', $this->format_list($names)),
                $others
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
        // Get the configured enrolment role to link to its definition page.
        $enrolmentrole = $this->get_configured_enrolment_role();
        $enrolmentroleurl = new \core\url('/admin/roles/define.php', [
            'action' => 'view',
            'roleid' => ($enrolmentrole !== null) ? $enrolmentrole->id : 0,
        ]);
        $allowurl = new \core\url('/admin/roles/allow.php', ['mode' => 'assign']);

        return [
            self::FINDING_NOTALLOWED => ['autofix' => true, 'risky' => false, 'url' => $allowurl],
            // Making the enrolment role assignable in courses is not entirely harmless: The role is not owned by this
            // plugin, and if it cannot be assigned in courses, the better fix might be to pick another enrolment role.
            self::FINDING_NOCOURSECONTEXT => ['autofix' => true, 'risky' => true, 'url' => $enrolmentroleurl],
            self::FINDING_SUPERFLUOUS => ['autofix' => true, 'risky' => false, 'url' => $allowurl],
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
        // Get the SEMCO webservice role, which exists as the findings could not have been reported otherwise.
        $role = $this->get_semco_role();

        switch ($findingid) {
            // Allow the SEMCO webservice role to assign the configured enrolment role, just as the plugin installer
            // does for the default enrolment role.
            case self::FINDING_NOTALLOWED:
                foreach ($contexts as $enrolmentroleid) {
                    autofix::allow_semco_role_to_assign($role->id, $enrolmentroleid);
                }
                break;

            // Make the enrolment role assignable in the course context.
            case self::FINDING_NOCOURSECONTEXT:
                foreach ($contexts as $enrolmentroleid) {
                    autofix::allow_role_in_course_context($enrolmentroleid);
                }
                break;

            // Revoke the permissions which the SEMCO webservice role does not need.
            case self::FINDING_SUPERFLUOUS:
                foreach (array_merge(...$contexts) as $otherroleid) {
                    autofix::disallow_semco_role_to_assign($role->id, $otherroleid);
                }
                break;
        }
    }
}
