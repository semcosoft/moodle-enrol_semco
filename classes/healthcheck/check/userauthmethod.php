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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice user authentication method
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice user uses the expected authentication method.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userauthmethod extends healthcheck {
    /** @var string Finding: The SEMCO webservice user uses the manual authentication method. */
    public const FINDING_MANUALAUTH = 'manualauth';

    /** @var string Finding: The SEMCO webservice user uses an authentication method which it must not use. */
    public const FINDING_WRONGAUTH = 'wrongauth';

    /** @var string Finding: The SEMCO webservice user carries a password hash although it must not use passwords. */
    public const FINDING_PASSWORDHASH = 'passwordhash';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'userauthmethod';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_userauthmethod_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_userauthmethod_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_userauthmethod_description', 'enrol_semco');
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
        // If the SEMCO webservice user does not exist, this check cannot be assessed.
        $user = $this->get_semco_user();
        if ($user === null) {
            $this->add_finding(self::FINDING_NOUSER, get_string('healthcheck_findingnouser', 'enrol_semco'));
            return healthcheck::NA;
        }

        // Start with an intact state.
        $status = healthcheck::OK;

        // Check the authentication method. If the user uses the expected authentication method, this aspect is fine.
        // If the user uses the manual authentication method, the SEMCO integration is most likely still working:
        // A manual account can use the webservices as well as long as everything else is configured properly.
        // It is not what this account is meant to be, though, thus we do not report a broken installation here.
        if ($user->auth === 'manual') {
            $this->add_finding(
                self::FINDING_MANUALAUTH,
                get_string('healthcheck_userauthmethod_findingmanualauth', 'enrol_semco', [
                    'expected' => ENROL_SEMCO_AUTH,
                    'found' => s($user->auth),
                ])
            );
            $status = $this->escalate($status, healthcheck::WARNING);

            // With any other authentication method, we report a broken installation.
            // Please note that this is a logical and not a technical verdict: The token login which SEMCO uses only
            // refuses the 'nologin' authentication method, see webservice/lib.php, thus the webservice calls
            // technically keep working with most of the other authentication methods. However, the SEMCO webservice
            // user is a technical account which must not be handed over to an authentication method which manages
            // its accounts on its own (and which may suspend, update or delete them on its next sync run). That SEMCO
            // still gets through is a side effect of the token login which we must not rely on, thus the integration
            // has to be considered as broken.
        } else if ($user->auth !== ENROL_SEMCO_AUTH) {
            $this->add_finding(
                self::FINDING_WRONGAUTH,
                get_string('healthcheck_userauthmethod_findingwrongauth', 'enrol_semco', [
                    'expected' => ENROL_SEMCO_AUTH,
                    'found' => s($user->auth),
                ])
            );
            $status = $this->escalate($status, healthcheck::ERROR);
        }

        // Check the password hash. Moodle stores the AUTH_PASSWORD_NOT_CACHED marker instead of a hash for this
        // authentication method, see the security note in db/install.php. A hash may have sneaked in nevertheless, for
        // example if an admin has switched the authentication method to 'manual', has set a password and has switched
        // the method back. The authentication method alone does not tell whether this has happened, thus we check the
        // stored hash explicitly.
        if ($user->password !== AUTH_PASSWORD_NOT_CACHED) {
            $this->add_finding(
                self::FINDING_PASSWORDHASH,
                get_string('healthcheck_userauthmethod_findingpasswordhash', 'enrol_semco')
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
        // Get the SEMCO webservice user to link to its profile editing page.
        $user = $this->get_semco_user();
        $userurl = new \core\url('/user/editadvanced.php', ['id' => ($user !== null) ? $user->id : 0]);

        return [
            self::FINDING_WRONGAUTH => ['autofix' => true, 'risky' => false, 'url' => $userurl],
            self::FINDING_MANUALAUTH => ['autofix' => true, 'risky' => false, 'url' => $userurl],
            self::FINDING_PASSWORDHASH => ['autofix' => true, 'risky' => false, 'url' => $userurl],
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
            // Restore the expected authentication method, regardless of the method which the user uses instead.
            case self::FINDING_WRONGAUTH:
            case self::FINDING_MANUALAUTH:
                autofix::update_semco_user($this->get_semco_user()->id, ['auth' => ENROL_SEMCO_AUTH]);
                break;

            // Remove the password hash and store the marker which Moodle stores for such accounts itself.
            case self::FINDING_PASSWORDHASH:
                autofix::remove_semco_user_password($this->get_semco_user()->id);
                break;
        }
    }
}
