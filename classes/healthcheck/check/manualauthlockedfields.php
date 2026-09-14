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
 * Health check which verifies that the profile fields which SEMCO owns are locked for manual accounts.
 *
 * SEMCO acts as leading system for the users which it creates in Moodle and overwrites the first name, the last name
 * and the email address if necessary. As long as these fields are not locked, a Moodle user can change them himself
 * and might be confused when the change is reverted by SEMCO sometime later.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manualauthlockedfields extends healthcheck {
    /** @var string Finding: A profile field which SEMCO owns is not locked for manual accounts. */
    public const FINDING_UNLOCKED = 'unlocked';

    /** @var string[] The user profile fields which SEMCO owns and which should therefore be locked. */
    protected const FIELDS = ['firstname', 'lastname', 'email'];

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'manualauthlockedfields';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_manualauthlockedfields_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_manualauthlockedfields_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_manualauthlockedfields_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_RECOMMENDATIONS;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // Start with an intact state.
        $status = healthcheck::OK;

        // Check each of the profile fields which SEMCO owns.
        foreach (self::FIELDS as $field) {
            // Get the lock setting of the manual authentication method. Moodle uses 'unlocked' as default.
            $lock = get_config('auth_manual', 'field_lock_' . $field);
            if (empty($lock)) {
                $lock = 'unlocked';
            }

            // If the field is not locked completely, we raise the admin's awareness.
            // The 'unlockedifempty' setting is not enough here as SEMCO always fills these fields, which means that
            // they are never empty and thus never locked in practice. The field is handed over as context, so that the
            // automatic fix knows which field to lock.
            if ($lock !== 'locked') {
                $this->add_finding(
                    self::FINDING_UNLOCKED,
                    get_string('healthcheck_manualauthlockedfields_findingunlocked', 'enrol_semco', [
                        'field' => get_string($field),
                        // The lock options carry their labels in the core 'auth' language file.
                        'setting' => get_string($lock, 'auth'),
                        'expected' => get_string('locked', 'auth'),
                    ]),
                    $field
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
        return [
            // Locking the fields is not entirely harmless, as it affects all existing users with manual authentication
            // and not only the users which SEMCO has created, see the plugin's README.
            self::FINDING_UNLOCKED => [
                'autofix' => true,
                'risky' => true,
                'url' => new \core\url('/admin/settings.php', ['section' => 'authsettingmanual']),
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
            // Lock the affected fields for manual accounts.
            case self::FINDING_UNLOCKED:
                foreach ($contexts as $field) {
                    autofix::lock_manual_auth_field($field);
                }
                break;
        }
    }
}
