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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice user account state
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice user account is active.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class useractive extends healthcheck {
    /** @var string Finding: The SEMCO webservice user is suspended. */
    public const FINDING_SUSPENDED = 'suspended';

    /** @var string Finding: The SEMCO webservice user is not confirmed. */
    public const FINDING_UNCONFIRMED = 'unconfirmed';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'useractive';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_useractive_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_useractive_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_useractive_description', 'enrol_semco');
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

        // Check the account state. A suspended or unconfirmed account cannot authenticate at all, so the SEMCO
        // integration is down while this is the case.
        if (!empty($user->suspended)) {
            $this->add_finding(
                self::FINDING_SUSPENDED,
                get_string('healthcheck_useractive_findingsuspended', 'enrol_semco')
            );
            $status = healthcheck::ERROR;
        }
        if (empty($user->confirmed)) {
            $this->add_finding(
                self::FINDING_UNCONFIRMED,
                get_string('healthcheck_useractive_findingunconfirmed', 'enrol_semco')
            );
            $status = healthcheck::ERROR;
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
            self::FINDING_SUSPENDED => ['autofix' => true, 'risky' => false, 'url' => $userurl],
            self::FINDING_UNCONFIRMED => ['autofix' => true, 'risky' => false, 'url' => $userurl],
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
            // Unsuspend the account.
            case self::FINDING_SUSPENDED:
                autofix::update_semco_user($this->get_semco_user()->id, ['suspended' => 0]);
                break;

            // Confirm the account.
            case self::FINDING_UNCONFIRMED:
                autofix::update_semco_user($this->get_semco_user()->id, ['confirmed' => 1]);
                break;
        }
    }
}
