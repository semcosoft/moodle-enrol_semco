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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice role capabilities (base class)
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Base class for the health checks which verify a set of capabilities of the SEMCO webservice role.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class rolecapabilities extends healthcheck {
    /** @var string Finding: The SEMCO webservice role does not hold a capability which it needs. */
    public const FINDING_MISSING = 'missing';

    /**
     * Return the capabilities which the SEMCO webservice role must hold in the system context.
     *
     * This is public as the capabilitiesexclusive health check item needs the list of the plugin's own webservice
     * capabilities as well.
     *
     * @return string[]
     */
    abstract public function get_capabilities(): array;

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
        // If the SEMCO webservice role does not exist, this check cannot be assessed.
        if ($this->get_semco_role() === null) {
            $this->add_finding(self::FINDING_NOROLE, get_string('healthcheck_findingnorole', 'enrol_semco'));
            return healthcheck::NA;
        }

        // Get the capabilities which are not allowed for the role.
        $missing = $this->get_missing_capabilities();

        // If there is at least one missing capability, the role does not work as intended. The capability is handed
        // over as context, so that the automatic fix knows what to assign without looking for it once more.
        if (count($missing) > 0) {
            foreach ($missing as $capability) {
                $this->add_finding(
                    self::FINDING_MISSING,
                    get_string('healthcheck_rolecapabilities_findingmissing', 'enrol_semco', $capability),
                    $capability
                );
            }
            return healthcheck::ERROR;

            // Otherwise, everything is fine.
        } else {
            return healthcheck::OK;
        }
    }

    /**
     * Return the capabilities of this health check item which are not set to CAP_ALLOW in the system context.
     *
     * Capabilities which do not exist in this Moodle instance at all are skipped as they cannot be assigned.
     *
     * @return string[]
     */
    protected function get_missing_capabilities(): array {
        global $DB;

        // If the SEMCO webservice role does not exist, there is nothing to report.
        $role = $this->get_semco_role();
        if ($role === null) {
            return [];
        }

        // Get the system context.
        $systemcontext = \context_system::instance();

        // Iterate over the capabilities of this health check item.
        $missing = [];
        foreach ($this->get_capabilities() as $capability) {
            // Skip capabilities which do not exist in this Moodle instance.
            if (get_capability_info($capability, false) === null) {
                continue;
            }

            // Get the role's permission for this capability in the system context.
            $permission = $DB->get_field(
                'role_capabilities',
                'permission',
                ['roleid' => $role->id, 'capability' => $capability, 'contextid' => $systemcontext->id]
            );

            // If the capability is not allowed, remember it.
            if ($permission === false || (int) $permission !== CAP_ALLOW) {
                $missing[] = $capability;
            }
        }

        // Return the missing capabilities.
        return $missing;
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
            self::FINDING_MISSING => ['autofix' => true, 'risky' => false, 'url' => $roleurl],
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
            // Allow the missing capabilities in the system context.
            case self::FINDING_MISSING:
                foreach ($contexts as $capability) {
                    autofix::assign_semco_role_capability($this->get_semco_role()->id, $capability);
                }
                break;
        }
    }
}
