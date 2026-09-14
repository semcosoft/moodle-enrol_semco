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
 * Enrolment method "SEMCO" - Health check: External service
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO external service exists and is configured as expected.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class externalserviceconfig extends healthcheck {
    /** @var string Finding: The SEMCO external service is disabled. */
    public const FINDING_DISABLED = 'disabled';

    /** @var string Finding: The SEMCO external service is not restricted to authorised users. */
    public const FINDING_UNRESTRICTED = 'unrestricted';

    /** @var string Finding: Other users than the SEMCO webservice user are authorised to use the service. */
    public const FINDING_OTHERUSERS = 'otherusers';

    /** @var int The maximum amount of other users which are named in the finding. */
    protected const MAX_NAMED_USERS = 10;

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'externalserviceconfig';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_externalserviceconfig_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_externalserviceconfig_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_externalserviceconfig_description', 'enrol_semco');
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
        global $DB;

        // Get the SEMCO external service.
        $service = $this->get_semco_service();

        // If the service does not exist, this check cannot be assessed.
        if ($service === null) {
            $this->add_finding(self::FINDING_NOSERVICE, get_string('healthcheck_findingnoservice', 'enrol_semco'));
            return healthcheck::NA;
        }

        // Start with an intact state.
        $status = healthcheck::OK;

        // A disabled service rejects every webservice call, thus SEMCO cannot work at all.
        if (empty($service->enabled)) {
            $this->add_finding(
                self::FINDING_DISABLED,
                get_string('healthcheck_externalserviceconfig_findingdisabled', 'enrol_semco')
            );
            $status = healthcheck::ERROR;
        }

        // A service which is not restricted to authorised users still works, but it is open to every user who holds
        // the necessary capabilities. This weakens the setup, it does not break it.
        if (empty($service->restrictedusers)) {
            $this->add_finding(
                self::FINDING_UNRESTRICTED,
                get_string('healthcheck_externalserviceconfig_findingunrestricted', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // Restricting the service to authorised users is only half of the job: The list of authorised users should
        // hold the SEMCO webservice user and nobody else. That the SEMCO webservice user is on the list at all is
        // verified by the 'SEMCO webservice user: Service authorisation' item, here we look for the other users.
        // If the SEMCO webservice user does not exist, every entry on the list belongs to somebody else.
        $user = $this->get_semco_user();
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $otherusers = $DB->get_records_sql(
            'SELECT DISTINCT esu.userid, u.username, ' . $namefields . '
             FROM {external_services_users} esu
             LEFT JOIN {user} u ON u.id = esu.userid
             WHERE esu.externalserviceid = :serviceid AND esu.userid <> :semcouserid
             ORDER BY u.lastname, u.firstname, esu.userid',
            ['serviceid' => $service->id, 'semcouserid' => ($user !== null) ? $user->id : 0]
        );
        if (count($otherusers) > 0) {
            // Name the users, as the automatic fix is going to revoke their authorisation and the admin has to know
            // whom he is about to lock out. The list is cut off to keep the finding readable. An authorisation can
            // outlive its user account, in that case the id of the user is all that is left to name. The names come
            // from the user accounts and have to be escaped, as findings are HTML.
            $names = [];
            foreach ($otherusers as $otheruser) {
                $names[] = ($otheruser->username !== null)
                    ? s(fullname($otheruser)) . ' (' . s($otheruser->username) . ')'
                    : '#' . $otheruser->userid;
            }

            // The ids of all other users are handed over as context, so that the automatic fix knows whose
            // authorisation to revoke.
            $this->add_finding(
                self::FINDING_OTHERUSERS,
                get_string('healthcheck_externalserviceconfig_findingotherusers', 'enrol_semco', [
                    'count' => count($otherusers),
                    'users' => $this->format_list($names, self::MAX_NAMED_USERS),
                ]),
                array_map('intval', array_keys($otherusers))
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
        // Get the SEMCO external service to link to its pages.
        $service = $this->get_semco_service();
        $serviceparams = ['id' => ($service !== null) ? $service->id : 0];

        return [
            self::FINDING_DISABLED => [
                'autofix' => true,
                'risky' => false,
                'url' => new \core\url('/admin/settings.php', ['section' => 'externalservices']),
            ],
            // The restriction is declared by this plugin and Moodle does not let an admin change it on the service
            // settings page. Thus, this finding does not offer an URL, the automatic fix is the way to go.
            self::FINDING_UNRESTRICTED => [
                'autofix' => true,
                'risky' => false,
                'url' => null,
            ],
            // Revoking the authorisation of another user is not entirely harmless, as an admin may have authorised
            // that user on purpose, for example for a monitoring tool.
            self::FINDING_OTHERUSERS => [
                'autofix' => true,
                'risky' => true,
                'url' => new \core\url('/admin/webservice/service_users.php', $serviceparams),
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
        // Get the SEMCO external service, which exists as the findings could not have been reported otherwise.
        $service = $this->get_semco_service();

        switch ($findingid) {
            // Enable the service.
            case self::FINDING_DISABLED:
                autofix::enable_semco_service($service->id);
                break;

            // Restrict the service to authorised users.
            case self::FINDING_UNRESTRICTED:
                autofix::restrict_semco_service($service->id);
                break;

            // Revoke the authorisation of all users but the SEMCO webservice user, for the SEMCO external service only.
            case self::FINDING_OTHERUSERS:
                foreach ($contexts as $otheruserids) {
                    autofix::revoke_semco_service_authorisation($service->id, $otheruserids);
                }
                break;
        }
    }
}
