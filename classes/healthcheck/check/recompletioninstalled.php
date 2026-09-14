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

use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the companion plugin local_recompletion is installed.
 *
 * This is the entry point of the recompletion category: All other items of this category can only be assessed if this
 * one reports an intact state.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompletioninstalled extends healthcheck {
    /** @var string Finding: The companion plugin local_recompletion is not installed or too old. */
    public const FINDING_MISSING = 'missing';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'recompletioninstalled';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_recompletioninstalled_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_recompletioninstalled_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_recompletioninstalled_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_RECOMPLETION;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // If local_recompletion is installed in a usable version, everything is fine. This item accepts the
        // simulated state of an automated test as it does nothing but report the state of the companion plugin.
        if (enrol_semco_check_local_recompletion(true) == true) {
            return healthcheck::OK;
        }

        // Otherwise, SEMCO cannot reset course completions. This does not stop the SEMCO integration as a whole,
        // thus we do not report a broken installation here.
        $this->add_finding(
            self::FINDING_MISSING,
            get_string('healthcheck_recompletioninstalled_findingmissing', 'enrol_semco')
        );
        return healthcheck::WARNING;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        return [
            // Installing a plugin is nothing which we can do from here, thus we just link to the plugin overview page.
            self::FINDING_MISSING => [
                'autofix' => false,
                'risky' => false,
                'url' => new \core\url('/admin/plugins.php'),
            ],
        ];
    }
}
