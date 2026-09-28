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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice token IP restriction
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice token is not restricted to particular IP addresses.
 *
 * This is one of the two places where Moodle evaluates an IP restriction for a webservice call, see webservice/lib.php.
 * The other one, the authorisation of the user for the external service, is covered by the 'SEMCO webservice user: IP
 * restriction' item.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usertokeniprestriction extends healthcheck {
    /** @var string Finding: The webservice token is restricted to particular IP addresses. */
    public const FINDING_RESTRICTED = 'restricted';

    /** @var string Finding: There is not any webservice token, thus the check cannot be assessed. */
    public const FINDING_NOTOKEN = 'notoken';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'usertokeniprestriction';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_usertokeniprestriction_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_usertokeniprestriction_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_usertokeniprestriction_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_TOKEN;
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

        // Get the oldest token of the SEMCO webservice user for the SEMCO external service. This is the token which
        // the 'SEMCO webservice token' item assesses as well - it is the token which SEMCO most probably uses - and it
        // is that item which reports if there is more than one token.
        $tokens = $DB->get_records(
            'external_tokens',
            ['externalserviceid' => $service->id, 'userid' => $user->id],
            'timecreated ASC, id ASC',
            '*',
            0,
            1
        );

        // If there is no token at all, there is nothing which could be restricted. The missing token itself is reported
        // and fixed by the 'SEMCO webservice token' item, thus this check cannot be assessed.
        if (count($tokens) < 1) {
            $this->add_finding(
                self::FINDING_NOTOKEN,
                get_string('healthcheck_usertokeniprestriction_findingnotoken', 'enrol_semco')
            );
            return healthcheck::NA;
        }
        $token = reset($tokens);

        // If the token is restricted to particular IP addresses, SEMCO can only connect from these addresses. This
        // plugin does not know the IP addresses of SEMCO and thus cannot tell whether the restriction covers them, thus
        // this is a warning and not an error. The restriction comes from the admin and is escaped for that reason. The
        // id of the token is handed over as context, so that the automatic fix knows which token to change.
        if (!empty($token->iprestriction)) {
            $this->add_finding(
                self::FINDING_RESTRICTED,
                get_string(
                    'healthcheck_usertokeniprestriction_findingrestricted',
                    'enrol_semco',
                    s($token->iprestriction)
                ),
                (int) $token->id
            );
            return healthcheck::WARNING;
        }

        // Otherwise, everything is fine.
        return healthcheck::OK;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * The restriction can be removed automatically, which restores the state which the plugin installer has created.
     * Nevertheless, this is not entirely harmless: An admin may have set the restriction on purpose to harden the
     * webservice access, and removing it takes this hardening away. Such an admin should rather mute this item, the
     * description tells him so.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        return [
            self::FINDING_RESTRICTED => [
                'autofix' => true,
                'risky' => true,
                'url' => new \core\url('/admin/webservice/tokens.php'),
            ],
            // This finding is reported along with the N/A status only, see determine_status(). The missing token is the
            // problem which is reported and fixed by the 'SEMCO webservice token' item, thus there is nothing to fix here
            // and no URL to offer.
            self::FINDING_NOTOKEN => ['autofix' => false, 'risky' => false, 'url' => null],
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
            // Remove the IP restriction from the token.
            case self::FINDING_RESTRICTED:
                foreach ($contexts as $tokenid) {
                    autofix::remove_semco_token_iprestriction($tokenid);
                }
                break;
        }
    }
}
