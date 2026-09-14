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
 * Enrolment method "SEMCO" - Health check: Unique user email addresses
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that Moodle user email addresses are unique.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allowaccountssameemail extends healthcheck {
    /** @var string Finding: Moodle allows more than one user account with the same email address. */
    public const FINDING_ALLOWED = 'allowed';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'allowaccountssameemail';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_allowaccountssameemail_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_allowaccountssameemail_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_allowaccountssameemail_description', 'enrol_semco');
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
        global $CFG;

        // If Moodle enforces unique email addresses, everything is fine.
        if (empty($CFG->allowaccountssameemail)) {
            return healthcheck::OK;

            // Otherwise, we raise the admin's awareness.
        } else {
            $this->add_finding(
                self::FINDING_ALLOWED,
                get_string('healthcheck_allowaccountssameemail_findingallowed', 'enrol_semco')
            );
            return healthcheck::NOTICE;
        }
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        return [
            // Enforcing unique email addresses is not entirely harmless: SEMCO is able to deal with multiple tenants
            // where more than one user account shares an email address, and such a setup needs this setting to stay
            // as it is, see the plugin's README.
            self::FINDING_ALLOWED => [
                'autofix' => true,
                'risky' => true,
                'url' => new \core\url('/admin/search.php', ['query' => 'allowaccountssameemail']),
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
            // Enforce unique email addresses.
            case self::FINDING_ALLOWED:
                autofix::disallow_accounts_same_email();
                break;
        }
    }
}
