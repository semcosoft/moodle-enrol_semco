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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice token
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that a usable webservice token exists for the SEMCO webservice user.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usertoken extends healthcheck {
    /** @var string Finding: There is not any webservice token. */
    public const FINDING_MISSING = 'missing';

    /** @var string Finding: The webservice token has expired. */
    public const FINDING_EXPIRED = 'expired';

    /** @var string Finding: The webservice token is going to expire soon. */
    public const FINDING_EXPIRING = 'expiring';

    /** @var string Finding: The webservice token is not a permanent token. */
    public const FINDING_NOTPERMANENT = 'notpermanent';

    /** @var string Finding: There is more than one webservice token. */
    public const FINDING_MULTIPLE = 'multiple';

    /** @var string Finding: The webservice token does not have a creator. */
    public const FINDING_NOCREATOR = 'nocreator';

    /** @var int The period before a token expires within which the admin should be warned. */
    protected const EXPIRY_WARNING_PERIOD = 30 * DAYSECS;

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'usertoken';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_usertoken_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_usertoken_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_usertoken_description', 'enrol_semco');
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

        // Get the tokens of the SEMCO webservice user for the SEMCO external service, newest first.
        $tokens = $DB->get_records(
            'external_tokens',
            ['externalserviceid' => $service->id, 'userid' => $user->id],
            'timecreated DESC, id DESC'
        );

        // If there is no token at all, SEMCO cannot connect to Moodle.
        if (count($tokens) < 1) {
            $this->add_finding(self::FINDING_MISSING, get_string('healthcheck_usertoken_findingmissing', 'enrol_semco'));
            return healthcheck::ERROR;
        }

        // Start with an intact state.
        $status = healthcheck::OK;

        // If there is more than one token, it is unclear which one SEMCO uses.
        // We report this fact and assess the most recently created token below.
        if (count($tokens) > 1) {
            $this->add_finding(
                self::FINDING_MULTIPLE,
                get_string('healthcheck_usertoken_findingmultiple', 'enrol_semco', count($tokens))
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // Get the most recently created token.
        $token = reset($tokens);

        // If the token is not a permanent token, it might vanish unexpectedly.
        if ((int) $token->tokentype !== EXTERNAL_TOKEN_PERMANENT) {
            $this->add_finding(
                self::FINDING_NOTPERMANENT,
                get_string('healthcheck_usertoken_findingnotpermanent', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // If the token has expired, SEMCO cannot connect to Moodle anymore.
        if (!empty($token->validuntil) && $token->validuntil < time()) {
            $this->add_finding(
                self::FINDING_EXPIRED,
                get_string('healthcheck_usertoken_findingexpired', 'enrol_semco', userdate($token->validuntil))
            );
            $status = $this->escalate($status, healthcheck::ERROR);

            // If the token is going to expire soon, SEMCO keeps working until that date but cannot connect to Moodle
            // anymore afterwards. An outage which is that close is not a cosmetic issue, thus we report a warning. A
            // token which expires later than that is not reported at all, as an expiry date is a legitimate way to
            // harden the token, see cli/recreate_webservice_token.php.
        } else if (!empty($token->validuntil) && $token->validuntil < time() + self::EXPIRY_WARNING_PERIOD) {
            $this->add_finding(
                self::FINDING_EXPIRING,
                get_string('healthcheck_usertoken_findingexpiring', 'enrol_semco', userdate($token->validuntil))
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // If the token does not have a creator, it is not shown on the Moodle webservice tokens page. The id of the
        // token is handed over as context, so that the automatic fix knows which token to repair.
        if (empty($token->creatorid)) {
            $this->add_finding(
                self::FINDING_NOCREATOR,
                get_string('healthcheck_usertoken_findingnocreator', 'enrol_semco'),
                (int) $token->id
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // Return the status.
        return $status;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * A token which is gone is created again automatically, just as the plugin installer does. This is harmless, but it
     * is only half of the job: The token is a secret which SEMCO has to know, thus the admin has to enter the new token
     * in SEMCO afterwards, and the follow-up tells him so. For the very same reason, a token which exists is never
     * replaced automatically. The plugin ships cli/recreate_webservice_token.php for this purpose.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        $tokensurl = new \core\url('/admin/webservice/tokens.php');
        return [
            self::FINDING_MISSING => [
                'autofix' => true,
                'risky' => false,
                'url' => $tokensurl,
                'followup' => get_string('healthcheck_usertoken_followupmissing', 'enrol_semco'),
            ],
            self::FINDING_EXPIRED => ['autofix' => false, 'risky' => false, 'url' => $tokensurl],
            self::FINDING_EXPIRING => ['autofix' => false, 'risky' => false, 'url' => $tokensurl],
            self::FINDING_NOTPERMANENT => ['autofix' => false, 'risky' => false, 'url' => $tokensurl],
            self::FINDING_MULTIPLE => ['autofix' => false, 'risky' => false, 'url' => $tokensurl],
            // A token without a creator is not even shown on the tokens page, which is exactly what this finding is
            // about. Thus, this finding does not offer an URL, the automatic fix is the way to go.
            self::FINDING_NOCREATOR => ['autofix' => true, 'risky' => false, 'url' => null],
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
            // Generate a permanent token for the SEMCO webservice user and the SEMCO external service and set the SEMCO
            // webservice user as its creator, just as the plugin installer does.
            case self::FINDING_MISSING:
                autofix::create_semco_token($this->get_semco_service()->id, $this->get_semco_user()->id);
                break;

            // Set the SEMCO webservice user as creator of the token, just as the plugin installer does.
            case self::FINDING_NOCREATOR:
                foreach ($contexts as $tokenid) {
                    autofix::set_semco_token_creator($tokenid, $this->get_semco_user()->id);
                }
                break;
        }
    }
}
