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
 * Enrolment method "SEMCO" - Health check: Registered capabilities
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that all capabilities of this plugin are registered in Moodle.
 *
 * The plugin installer calls update_capabilities() to register the capabilities which the plugin declares in
 * db/access.php. If a capability is missing from the capabilities table, it cannot be assigned to any role and every
 * capability check against it fails with a coding error.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capabilitiesregistered extends healthcheck {
    /** @var string Finding: A capability of this plugin is not registered in Moodle. */
    public const FINDING_MISSING = 'missing';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'capabilitiesregistered';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_capabilitiesregistered_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_capabilitiesregistered_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_capabilitiesregistered_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_PLUGIN;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // Get the capabilities which are not registered.
        $missing = $this->get_unregistered_capabilities();

        // If there is at least one unregistered capability, the plugin's permission handling is broken.
        if (count($missing) > 0) {
            foreach ($missing as $capability) {
                $this->add_finding(
                    self::FINDING_MISSING,
                    get_string('healthcheck_capabilitiesregistered_findingmissing', 'enrol_semco', $capability)
                );
            }
            return healthcheck::ERROR;

            // Otherwise, everything is fine.
        } else {
            return healthcheck::OK;
        }
    }

    /**
     * Return the capabilities which the plugin declares in db/access.php but which are not registered in Moodle.
     *
     * @return string[]
     */
    protected function get_unregistered_capabilities(): array {
        global $DB;

        // Get the capabilities which the plugin declares in db/access.php.
        $declared = array_keys(load_capability_def('enrol_semco'));

        // If the plugin does not declare any capability, there is nothing to report.
        if (count($declared) < 1) {
            return [];
        }

        // Get the capabilities of this plugin which are registered in Moodle.
        $registered = $DB->get_fieldset_select(
            'capabilities',
            'name',
            'component = :component',
            ['component' => 'enrol_semco']
        );

        // Return the declared capabilities which are not registered.
        return array_values(array_diff($declared, $registered));
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        return [
            // There is no page in Moodle where an admin could register a capability himself: Moodle only registers the
            // capabilities of a plugin during its installation or upgrade. Thus, this finding does not offer an URL, the
            // automatic fix is the way to go.
            self::FINDING_MISSING => [
                'autofix' => true,
                'risky' => false,
                'url' => null,
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
            // Register the plugin's capabilities, just as the plugin installer does. This registers all of them at
            // once, thus we do not need to know which ones are missing.
            case self::FINDING_MISSING:
                autofix::register_capabilities();
                break;
        }
    }
}
