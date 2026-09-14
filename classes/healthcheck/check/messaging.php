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
 * Enrolment method "SEMCO" - Health check: Moodle messaging system
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the Moodle messaging system is disabled.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messaging extends healthcheck {
    /** @var string Finding: The Moodle messaging system is enabled. */
    public const FINDING_ENABLED = 'enabled';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'messaging';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_messaging_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_messaging_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_messaging_description', 'enrol_semco');
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
        global $CFG;

        // If the messaging system is disabled, everything is fine.
        if (empty($CFG->messaging)) {
            return healthcheck::OK;

            // Otherwise, SEMCO course participants can get in touch with each other.
        } else {
            $this->add_finding(self::FINDING_ENABLED, get_string('healthcheck_messaging_findingenabled', 'enrol_semco'));
            return healthcheck::WARNING;
        }
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        return [
            // Disabling the messaging system is not entirely harmless, as it takes a feature away from all users of
            // the Moodle instance and not only from the users which SEMCO has created.
            self::FINDING_ENABLED => [
                'autofix' => true,
                'risky' => true,
                'url' => new \core\url('/admin/settings.php', ['section' => 'optionalsubsystems']),
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
            // Disable the messaging system.
            case self::FINDING_ENABLED:
                autofix::disable_messaging();
                break;
        }
    }
}
