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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice role existence
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice role exists.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roleexists extends healthcheck {
    /** @var string Finding: The SEMCO webservice role does not exist. */
    public const FINDING_MISSING = 'missing';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'roleexists';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_roleexists_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_roleexists_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_roleexists_description', 'enrol_semco');
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
        // If the SEMCO webservice role exists, everything is fine.
        if ($this->get_semco_role() !== null) {
            return healthcheck::OK;

            // Otherwise, the whole permission setup of the plugin is gone.
        } else {
            $this->add_finding(
                self::FINDING_MISSING,
                get_string('healthcheck_roleexists_findingmissing', 'enrol_semco', ENROL_SEMCO_ROLEANDUSERNAME)
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
        return [
            // Recreating the role is harmless, it is exactly what the plugin installer does. Everything which makes up
            // the role - its context level, its capabilities, the roles which it is allowed to assign and its
            // assignment to the SEMCO webservice user - is not restored here, it is reported by the items which cover
            // these aspects as soon as the role exists again, and the follow-up tells the admin so.
            // An admin who prefers to create the role himself is led to the form which creates a new role.
            self::FINDING_MISSING => [
                'autofix' => true,
                'risky' => false,
                'url' => new \core\url('/admin/roles/define.php', ['action' => 'add']),
                'followup' => get_string('healthcheck_roleexists_followupmissing', 'enrol_semco'),
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
            // Create the SEMCO webservice role, just as the plugin installer does.
            case self::FINDING_MISSING:
                autofix::create_semco_role();
                break;
        }
    }
}
