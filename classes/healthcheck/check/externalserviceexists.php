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
 * Enrolment method "SEMCO" - Health check item
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO external service exists.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class externalserviceexists extends healthcheck {
    /** @var string Finding: The SEMCO external service does not exist. */
    public const FINDING_MISSING = 'missing';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'externalserviceexists';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_externalserviceexists_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_externalserviceexists_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_externalserviceexists_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_WEBSERVICE;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // If the SEMCO external service exists, everything is fine.
        if ($this->get_semco_service() !== null) {
            return healthcheck::OK;

            // Otherwise, the plugin's webservices are not available.
        } else {
            $this->add_finding(
                self::FINDING_MISSING,
                get_string('healthcheck_externalserviceexists_findingmissing', 'enrol_semco')
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
            // The service is shipped with the plugin and is created by the Moodle plugin installer. Recreating it is
            // harmless, it is exactly what Moodle does during the installation of the plugin. The authorisation of the
            // SEMCO webservice user and its webservice token belong to the service which is gone, they are not restored
            // here but reported by the items which cover these aspects as soon as the service exists again, and the
            // follow-up tells the admin so.
            // The admin cannot recreate the service himself: The external services page only lets him create custom
            // services, and a custom service is not the service which this plugin declares. Thus, this finding does not
            // offer an URL.
            self::FINDING_MISSING => [
                'autofix' => true,
                'risky' => false,
                'url' => null,
                'followup' => get_string('healthcheck_externalserviceexists_followupmissing', 'enrol_semco'),
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
            // Let Moodle create the service which the plugin declares in db/services.php and add the plugin's own
            // functions to it, just as it does during the installation of the plugin. See the note about this in the
            // externalservicefunctions item.
            case self::FINDING_MISSING:
                autofix::update_semco_service();
                break;
        }
    }
}
