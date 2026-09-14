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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice user existence
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice user exists.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userexists extends healthcheck {
    /** @var string Finding: The SEMCO webservice user does not exist. */
    public const FINDING_MISSING = 'missing';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'userexists';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_userexists_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_userexists_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_userexists_description', 'enrol_semco');
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
        // If the SEMCO webservice user exists, everything is fine.
        if ($this->get_semco_user() !== null) {
            return healthcheck::OK;

            // Otherwise, SEMCO cannot authenticate against Moodle at all.
        } else {
            $this->add_finding(
                self::FINDING_MISSING,
                get_string('healthcheck_userexists_findingmissing', 'enrol_semco', ENROL_SEMCO_ROLEANDUSERNAME)
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
            // Recreating the user is harmless, it is exactly what the plugin installer does. Everything which depends
            // on the user - its role assignment, its authorisation for the SEMCO external service and its webservice
            // token - is gone along with the user, as Moodle removes all of that when a user is deleted. These aspects
            // are not restored here, they are reported by their own items as soon as the user exists again, and the
            // follow-up tells the admin so.
            // An admin who prefers to create the user himself is led to the form which creates a new user.
            self::FINDING_MISSING => [
                'autofix' => true,
                'risky' => false,
                'url' => new \core\url('/user/editadvanced.php', ['id' => -1]),
                'followup' => get_string('healthcheck_userexists_followupmissing', 'enrol_semco'),
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
            // Create the SEMCO webservice user, just as the plugin installer does.
            case self::FINDING_MISSING:
                autofix::create_semco_user();
                break;
        }
    }
}
