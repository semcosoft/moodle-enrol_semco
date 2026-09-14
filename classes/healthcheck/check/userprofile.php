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
 * Health check which verifies the profile data of the SEMCO webservice user.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userprofile extends healthcheck {
    /** @var string Finding: The SEMCO webservice user does not have an email address. */
    public const FINDING_NOEMAIL = 'noemail';

    /** @var string Finding: The email address of the SEMCO webservice user deviates from the installer's value. */
    public const FINDING_DEVIATINGEMAIL = 'deviatingemail';

    /** @var string Finding: The SEMCO webservice user does not have a first name. */
    public const FINDING_MISSINGFIRSTNAME = 'missingfirstname';

    /** @var string Finding: The first name of the SEMCO webservice user deviates from the installer's value. */
    public const FINDING_DEVIATINGFIRSTNAME = 'deviatingfirstname';

    /** @var string Finding: The SEMCO webservice user does not have a last name. */
    public const FINDING_MISSINGLASTNAME = 'missinglastname';

    /** @var string Finding: The last name of the SEMCO webservice user deviates from the installer's value. */
    public const FINDING_DEVIATINGLASTNAME = 'deviatinglastname';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'userprofile';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_userprofile_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_userprofile_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_userprofile_description', 'enrol_semco');
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

        // Check the email address. The webservice calls still work without it, but a user account without an email
        // address breaks several Moodle core code paths. The expected value is handed over as context with every
        // finding of this item, so that the automatic fix knows what to restore. The current values come from the
        // user account and are escaped, as findings are HTML.
        $expectedemail = autofix::get_semco_user_email();
        if (empty($user->email)) {
            $this->add_finding(
                self::FINDING_NOEMAIL,
                get_string('healthcheck_userprofile_findingnoemail', 'enrol_semco'),
                $expectedemail
            );
            $status = healthcheck::WARNING;

            // Or if it deviates from the address which the plugin installer has set. This does not break anything, it
            // is just worth knowing, as the address is derived from the site URL and thus changes when the site moves.
        } else if ($user->email !== $expectedemail) {
            $this->add_finding(
                self::FINDING_DEVIATINGEMAIL,
                get_string('healthcheck_userprofile_findingdeviatingemail', 'enrol_semco', [
                    'current' => s($user->email),
                    'expected' => $expectedemail,
                ]),
                $expectedemail
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // Check the first name and the last name against the values which the plugin installer has set. A deviating
        // name does not break anything, but the account should stay recognizable in the user list.
        $expected = [
            'firstname' => get_string('installer_userfirstname', 'enrol_semco'),
            'lastname' => get_string('installer_userlastname', 'enrol_semco'),
        ];
        $findingids = [
            'firstname' => ['missing' => self::FINDING_MISSINGFIRSTNAME, 'deviating' => self::FINDING_DEVIATINGFIRSTNAME],
            'lastname' => ['missing' => self::FINDING_MISSINGLASTNAME, 'deviating' => self::FINDING_DEVIATINGLASTNAME],
        ];
        foreach ($expected as $field => $expectedvalue) {
            // If the name part is not set at all.
            if (empty($user->$field)) {
                $this->add_finding(
                    $findingids[$field]['missing'],
                    get_string('healthcheck_userprofile_findingmissing' . $field, 'enrol_semco'),
                    $expectedvalue
                );
                $status = $this->escalate($status, healthcheck::NOTICE);

                // Or if it deviates from the value which the plugin installer has set.
            } else if ($user->$field !== $expectedvalue) {
                $this->add_finding(
                    $findingids[$field]['deviating'],
                    get_string(
                        'healthcheck_userprofile_findingdeviating' . $field,
                        'enrol_semco',
                        ['current' => s($user->$field), 'expected' => $expectedvalue]
                    ),
                    $expectedvalue
                );
                $status = $this->escalate($status, healthcheck::NOTICE);
            }
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
        $definition = [
            'autofix' => true,
            'risky' => false,
            'url' => new \core\url('/user/editadvanced.php', ['id' => ($user !== null) ? $user->id : 0]),
        ];

        return [
            self::FINDING_NOEMAIL => $definition,
            self::FINDING_DEVIATINGEMAIL => $definition,
            self::FINDING_MISSINGFIRSTNAME => $definition,
            self::FINDING_DEVIATINGFIRSTNAME => $definition,
            self::FINDING_MISSINGLASTNAME => $definition,
            self::FINDING_DEVIATINGLASTNAME => $definition,
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
        // Each finding hands the value which the plugin installer has set over as its one and only context.
        $userid = $this->get_semco_user()->id;
        $expectedvalue = reset($contexts);

        switch ($findingid) {
            // Restore the email address.
            case self::FINDING_NOEMAIL:
            case self::FINDING_DEVIATINGEMAIL:
                autofix::update_semco_user($userid, ['email' => $expectedvalue]);
                break;

            // Restore the first name.
            case self::FINDING_MISSINGFIRSTNAME:
            case self::FINDING_DEVIATINGFIRSTNAME:
                autofix::update_semco_user($userid, ['firstname' => $expectedvalue]);
                break;

            // Restore the last name.
            case self::FINDING_MISSINGLASTNAME:
            case self::FINDING_DEVIATINGLASTNAME:
                autofix::update_semco_user($userid, ['lastname' => $expectedvalue]);
                break;
        }
    }
}
