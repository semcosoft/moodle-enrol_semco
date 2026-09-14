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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice user role assignment
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice user holds the SEMCO webservice role.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userroleassignment extends healthcheck {
    /** @var string Finding: The SEMCO webservice user does not hold the SEMCO webservice role in the system context. */
    public const FINDING_NOTASSIGNED = 'notassigned';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'userroleassignment';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_userroleassignment_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_userroleassignment_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_userroleassignment_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_USER;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // If the SEMCO webservice user or the SEMCO webservice role does not exist, this check cannot be assessed.
        $user = $this->get_semco_user();
        $role = $this->get_semco_role();
        if ($user === null || $role === null) {
            $this->add_finding(
                self::FINDING_NOUSERORROLE,
                get_string('healthcheck_findingnouserorrole', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // If the user holds the role in the system context, everything is fine.
        if (user_has_role_assignment($user->id, $role->id, \context_system::instance()->id)) {
            return healthcheck::OK;

            // Otherwise, the user does not have any of the necessary permissions.
        } else {
            $this->add_finding(
                self::FINDING_NOTASSIGNED,
                get_string('healthcheck_userroleassignment_findingnotassigned', 'enrol_semco')
            );
            return healthcheck::ERROR;
        }
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        // Get the SEMCO webservice role to link to its role assignment page in the system context.
        $role = $this->get_semco_role();

        return [
            self::FINDING_NOTASSIGNED => [
                'autofix' => true,
                'risky' => false,
                'url' => new \core\url('/admin/roles/assign.php', [
                    'contextid' => \context_system::instance()->id,
                    'roleid' => ($role !== null) ? $role->id : 0,
                ]),
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
            // Assign the role to the user in the system context.
            case self::FINDING_NOTASSIGNED:
                autofix::assign_semco_role_to_user($this->get_semco_role()->id, $this->get_semco_user()->id);
                break;
        }
    }
}
