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
 * Enrolment method "SEMCO" - Health check: Surplus capabilities
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice role does not hold more capabilities than it needs.
 *
 * The counterpart of this item are the rolecapabilities items which verify that the role holds all the capabilities
 * which the plugin installer has placed in it.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rolecapabilitiessurplus extends healthcheck {
    /** @var string Finding: The SEMCO webservice role holds capabilities which the plugin installer has not placed in it. */
    public const FINDING_SURPLUS = 'surplus';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'rolecapabilitiessurplus';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_rolecapabilitiessurplus_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_rolecapabilitiessurplus_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_rolecapabilitiessurplus_description', 'enrol_semco');
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

        // Compose the list of capabilities which the plugin installer has placed in the role. The lists of the
        // rolecapabilities items are reused for this so that there is only one place which knows them.
        $expected = array_merge(
            (new rolecapabilitiessemco())->get_capabilities(),
            (new rolecapabilitiesmoodle())->get_capabilities(),
            (new rolecapabilityrest())->get_capabilities()
        );

        // Get the capabilities which the role grants in the system context. A capability which is set to 'Prevent' or
        // to 'Prohibit' does not make the role more powerful and is therefore not of interest here.
        $granted = $DB->get_fieldset_select(
            'role_capabilities',
            'capability',
            'roleid = :roleid AND contextid = :contextid AND permission = :allow',
            [
                'roleid' => $role->id,
                'contextid' => \context_system::instance()->id,
                'allow' => CAP_ALLOW,
            ]
        );

        // Pick the capabilities which the plugin installer has not placed in the role.
        $surplus = array_diff($granted, $expected);
        sort($surplus);

        // If there is not any surplus capability, everything is fine.
        if (count($surplus) < 1) {
            return healthcheck::OK;
        }

        // Otherwise, name all surplus capabilities so that the admin can decide about each of them. The list is
        // bounded by the capabilities which somebody has added to the role, thus it cannot grow out of hand. Each
        // capability is handed over as context, so that the automatic fix knows what to remove.
        $this->add_finding(
            self::FINDING_SURPLUS,
            get_string('healthcheck_rolecapabilitiessurplus_findingsurplus', 'enrol_semco', count($surplus))
        );
        foreach ($surplus as $capability) {
            $this->add_finding(self::FINDING_SURPLUS, $capability, $capability);
        }
        return healthcheck::WARNING;
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
            // Removing the surplus capabilities is not entirely harmless, as somebody may have added them on purpose,
            // for example because a third party plugin hooks into the user creation and needs them.
            self::FINDING_SURPLUS => ['autofix' => true, 'risky' => true, 'url' => $roleurl],
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
            // Remove the surplus capabilities from the role definition.
            case self::FINDING_SURPLUS:
                foreach ($contexts as $capability) {
                    autofix::revoke_capability($capability, $this->get_semco_role()->id, \context_system::instance()->id);
                }
                break;
        }
    }
}
