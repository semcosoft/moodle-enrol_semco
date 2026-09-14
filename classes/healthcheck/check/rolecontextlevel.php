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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice role: Context
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice role can be assigned in the system context.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rolecontextlevel extends healthcheck {
    /** @var string Finding: The SEMCO webservice role cannot be assigned in the system context. */
    public const FINDING_NOSYSTEM = 'nosystem';

    /** @var string Finding: The SEMCO webservice role can be assigned in other contexts than the system context. */
    public const FINDING_OTHERCONTEXTS = 'othercontexts';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'rolecontextlevel';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_rolecontextlevel_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_rolecontextlevel_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_rolecontextlevel_description', 'enrol_semco');
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
        // Get the SEMCO webservice role.
        $role = $this->get_semco_role();

        // If the role does not exist, this check cannot be assessed.
        if ($role === null) {
            $this->add_finding(self::FINDING_NOROLE, get_string('healthcheck_findingnorole', 'enrol_semco'));
            return healthcheck::NA;
        }

        // Start with an intact state.
        $status = healthcheck::OK;
        $contextlevels = array_map('intval', get_role_contextlevels($role->id));

        // If the system context is not among the role's context levels, the SEMCO webservice user cannot hold the role
        // in the system context.
        if (!in_array(CONTEXT_SYSTEM, $contextlevels)) {
            $this->add_finding(
                self::FINDING_NOSYSTEM,
                get_string('healthcheck_rolecontextlevel_findingnosystem', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // If the role can be assigned in any other context as well, it could be handed out within a category or a
        // course. The plugin installer does not allow this, as the role is only meant for the SEMCO webservice user.
        $othercontextlevels = array_diff($contextlevels, [CONTEXT_SYSTEM]);
        if (count($othercontextlevels) > 0) {
            $names = [];
            foreach ($othercontextlevels as $contextlevel) {
                $names[] = \context_helper::get_level_name($contextlevel);
            }
            $this->add_finding(
                self::FINDING_OTHERCONTEXTS,
                get_string('healthcheck_rolecontextlevel_findingothercontexts', 'enrol_semco', $this->format_list($names))
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
        // Get the SEMCO webservice role to link to its definition page.
        $role = $this->get_semco_role();
        $roleurl = new \core\url('/admin/roles/define.php', ['action' => 'view', 'roleid' => ($role !== null) ? $role->id : 0]);

        return [
            self::FINDING_NOSYSTEM => ['autofix' => true, 'risky' => false, 'url' => $roleurl],
            self::FINDING_OTHERCONTEXTS => ['autofix' => true, 'risky' => false, 'url' => $roleurl],
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
            // Restore the context level which the plugin installer has set: the system context and nothing else. Both
            // findings share the very same fix, and applying it twice if both findings apply does not do any harm.
            case self::FINDING_NOSYSTEM:
            case self::FINDING_OTHERCONTEXTS:
                autofix::set_semco_role_contextlevels($this->get_semco_role()->id);
                break;
        }
    }
}
