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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice user service authorisation
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice user is an authorised user of the SEMCO external service.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userserviceauthorised extends healthcheck {
    /** @var string Finding: The SEMCO external service is not restricted to authorised users. */
    public const FINDING_UNRESTRICTED = 'unrestricted';

    /** @var string Finding: The SEMCO webservice user is not authorised to use the SEMCO external service. */
    public const FINDING_MISSING = 'missing';

    /** @var string Finding: The authorisation of the SEMCO webservice user has expired. */
    public const FINDING_EXPIRED = 'expired';

    /** @var string Finding: The authorisation of the SEMCO webservice user is going to expire. */
    public const FINDING_EXPIRING = 'expiring';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'userserviceauthorised';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_userserviceauthorised_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_userserviceauthorised_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_userserviceauthorised_description', 'enrol_semco');
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
        global $DB;

        // If the SEMCO webservice user or the SEMCO external service does not exist, this check cannot be assessed.
        $user = $this->get_semco_user();
        $service = $this->get_semco_service();
        if ($user === null || $service === null) {
            $this->add_finding(
                self::FINDING_NOUSERORSERVICE,
                get_string('healthcheck_findingnouserorservice', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // Moodle evaluates the list of authorised users only if the service is restricted to them, see
        // webservice/lib.php. As long as it is not, the list does not decide anything and this check cannot be
        // assessed. The 'SEMCO external service: Configuration' item reports the unrestricted service itself.
        if (empty($service->restrictedusers)) {
            $this->add_finding(
                self::FINDING_UNRESTRICTED,
                get_string('healthcheck_userserviceauthorised_findingunrestricted', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // Get the authorisation record.
        $authorisation = $DB->get_record(
            'external_services_users',
            ['externalserviceid' => $service->id, 'userid' => $user->id]
        );

        // If there is no authorisation record, the user cannot use the webservice.
        if ($authorisation === false) {
            $this->add_finding(
                self::FINDING_MISSING,
                get_string('healthcheck_userserviceauthorised_findingmissing', 'enrol_semco')
            );
            return healthcheck::ERROR;
        }

        // If the authorisation has expired, the user cannot use the webservice either.
        if (!empty($authorisation->validuntil) && $authorisation->validuntil < time()) {
            $this->add_finding(
                self::FINDING_EXPIRED,
                get_string(
                    'healthcheck_userserviceauthorised_findingexpired',
                    'enrol_semco',
                    userdate($authorisation->validuntil)
                )
            );
            return healthcheck::ERROR;
        }

        // If the authorisation is going to expire, SEMCO keeps working until that date but loses its access
        // afterwards. The plugin installer does not set such a date, thus this is a state which we do not want.
        if (!empty($authorisation->validuntil)) {
            $this->add_finding(
                self::FINDING_EXPIRING,
                get_string(
                    'healthcheck_userserviceauthorised_findingexpiring',
                    'enrol_semco',
                    userdate($authorisation->validuntil)
                )
            );
            return healthcheck::WARNING;
        }

        // Otherwise, everything is fine.
        return healthcheck::OK;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * Only a completely missing authorisation is fixed automatically. An expired or restricted authorisation is left
     * untouched as an admin might have restricted it on purpose.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        // Get the SEMCO external service to link to its authorised users page.
        $service = $this->get_semco_service();
        $usersurl = new \core\url('/admin/webservice/service_users.php', ['id' => ($service !== null) ? $service->id : 0]);

        return [
            // This finding is reported along with the N/A status only, see determine_status(). The unrestricted service
            // is the problem which is reported and fixed by the 'SEMCO external service: Configuration' item, thus
            // there is nothing to fix here and no URL to offer, as the list of authorised users is not the place to
            // restrict the service.
            self::FINDING_UNRESTRICTED => ['autofix' => false, 'risky' => false, 'url' => null],
            self::FINDING_MISSING => ['autofix' => true, 'risky' => false, 'url' => $usersurl],
            self::FINDING_EXPIRED => ['autofix' => false, 'risky' => false, 'url' => $usersurl],
            self::FINDING_EXPIRING => ['autofix' => false, 'risky' => false, 'url' => $usersurl],
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
            // Add the user as authorised user of the service, just as the plugin installer does.
            case self::FINDING_MISSING:
                autofix::authorise_semco_user($this->get_semco_service()->id, $this->get_semco_user()->id);
                break;
        }
    }
}
