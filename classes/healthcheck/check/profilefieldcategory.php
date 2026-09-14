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
 * Enrolment method "SEMCO" - Health check: SEMCO user profile field category
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO user profile field category exists.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profilefieldcategory extends healthcheck {
    /** @var string Finding: The SEMCO user profile field category does not exist. */
    public const FINDING_MISSING = 'missing';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'profilefieldcategory';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_profilefieldcategory_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_profilefieldcategory_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_profilefieldcategory_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_PROFILEFIELDS;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // If the SEMCO user profile field category exists, everything is fine.
        if ($this->get_semco_profilefield_category() !== null) {
            return healthcheck::OK;

            // Otherwise, the SEMCO user profile fields are not grouped as intended.
        } else {
            $this->add_finding(
                self::FINDING_MISSING,
                get_string('healthcheck_profilefieldcategory_findingmissing', 'enrol_semco', ENROL_SEMCO_USERFIELDCATEGORY)
            );
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
            // Recreating the category is harmless, it is exactly what the plugin installer does. The SEMCO user
            // profile fields are not moved into the new category, the items of the fields report where they are.
            self::FINDING_MISSING => [
                'autofix' => true,
                'risky' => false,
                'url' => new \core\url('/user/profile/index.php'),
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
            // Create the SEMCO user profile field category, just as the plugin installer does.
            case self::FINDING_MISSING:
                autofix::create_semco_profilefield_category();
                break;
        }
    }
}
