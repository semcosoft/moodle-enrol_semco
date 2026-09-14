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
 * Enrolment method "SEMCO" - Health check: External service functions
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO external service offers all webservice functions which SEMCO needs.
 *
 * The plugin installer calls external_update_descriptions() to register the webservice functions which the plugin
 * declares in db/services.php and to add them to the SEMCO external service. If a function is missing from the service,
 * the corresponding SEMCO webservice call fails.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class externalservicefunctions extends healthcheck {
    /** @var string Finding: The plugin does not declare any webservice function for the SEMCO external service. */
    public const FINDING_NODEFINITIONS = 'nodefinitions';

    /** @var string Finding: A webservice function of this plugin is not registered in Moodle at all. */
    public const FINDING_NOTREGISTERED = 'notregistered';

    /** @var string Finding: A webservice function is not offered by the SEMCO external service. */
    public const FINDING_NOTOFFERED = 'notoffered';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'externalservicefunctions';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_externalservicefunctions_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_externalservicefunctions_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_externalservicefunctions_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_WEBSERVICE;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        global $DB;

        // If the SEMCO external service does not exist, this check cannot be assessed.
        $service = $this->get_semco_service();
        if ($service === null) {
            $this->add_finding(self::FINDING_NOSERVICE, get_string('healthcheck_findingnoservice', 'enrol_semco'));
            return healthcheck::NA;
        }

        // Get the functions which the plugin declares for the SEMCO external service.
        $expected = $this->get_expected_functions();

        // If the plugin does not declare any function, there is nothing to check.
        if (count($expected) < 1) {
            $this->add_finding(
                self::FINDING_NODEFINITIONS,
                get_string('healthcheck_externalservicefunctions_findingnodefinitions', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // Get the functions which the SEMCO external service offers.
        $offered = $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = :serviceid',
            ['serviceid' => $service->id]
        );

        // Get the webservice functions of this plugin which are registered in Moodle.
        $registered = $DB->get_fieldset_select(
            'external_functions',
            'name',
            'component = :component',
            ['component' => 'enrol_semco']
        );

        // Report every function which the service does not offer.
        foreach (array_diff($expected, $offered) as $function) {
            $this->add_finding(
                self::FINDING_NOTOFFERED,
                get_string('healthcheck_externalservicefunctions_findingnotoffered', 'enrol_semco', $function)
            );
        }

        // Report every function of this plugin which is not registered in Moodle at all.
        foreach (array_diff($this->get_plugin_functions(), $registered) as $function) {
            $this->add_finding(
                self::FINDING_NOTREGISTERED,
                get_string('healthcheck_externalservicefunctions_findingnotregistered', 'enrol_semco', $function)
            );
        }

        // If there is at least one finding, SEMCO cannot use the affected webservice functions.
        if (count($this->findings) > 0) {
            return healthcheck::ERROR;

            // Otherwise, everything is fine.
        } else {
            return healthcheck::OK;
        }
    }

    /**
     * Return all webservice functions which the SEMCO external service is supposed to offer.
     *
     * These are the Moodle core functions which db/services.php adds to the service plus the plugin's own functions
     * which declare themselves as part of the service.
     *
     * @return string[]
     */
    protected function get_expected_functions(): array {
        // Get the service and function definitions of the plugin.
        [$functions, $services] = $this->get_service_definitions();

        // Start with the functions which the service definition lists directly.
        $expected = [];
        foreach ($services as $service) {
            if (($service['shortname'] ?? '') === ENROL_SEMCO_SERVICENAME && !empty($service['functions'])) {
                $expected = $service['functions'];
            }
        }

        // Add the plugin's own functions which declare themselves as part of the service.
        foreach ($functions as $name => $definition) {
            if (!empty($definition['services']) && in_array(ENROL_SEMCO_SERVICENAME, $definition['services'])) {
                $expected[] = $name;
            }
        }

        // Return the deduplicated list.
        return array_values(array_unique($expected));
    }

    /**
     * Return the webservice functions which this plugin implements itself.
     *
     * @return string[]
     */
    protected function get_plugin_functions(): array {
        // Get the function definitions of the plugin and return their names.
        [$functions] = $this->get_service_definitions();
        return array_keys($functions);
    }

    /**
     * Read the webservice definitions from the plugin's db/services.php file.
     *
     * @return array An array with the function definitions as first and the service definitions as second element.
     */
    protected function get_service_definitions(): array {
        global $CFG;

        // Initialize the variables which the definition file fills.
        $functions = [];
        $services = [];

        // Read the definition file.
        $defpath = $CFG->dirroot . '/enrol/semco/db/services.php';
        if (file_exists($defpath)) {
            include($defpath);
        }

        // Return the definitions.
        return [$functions, $services];
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        // Get the SEMCO external service to link to its function list.
        $service = $this->get_semco_service();
        $functionsurl = new \core\url('/admin/webservice/service_functions.php', ['id' => ($service !== null) ? $service->id : 0]);

        return [
            // The plugin files are incomplete, this cannot be fixed from within Moodle at all.
            self::FINDING_NODEFINITIONS => [
                'autofix' => false,
                'risky' => false,
                'url' => null,
            ],
            // A function which is not registered in Moodle at all cannot be added to the service on its function list
            // either, and there is no page where an admin could register it himself. Thus, this finding does not offer
            // an URL, the automatic fix is the way to go.
            self::FINDING_NOTREGISTERED => [
                'autofix' => true,
                'risky' => false,
                'url' => null,
            ],
            self::FINDING_NOTOFFERED => [
                'autofix' => true,
                'risky' => false,
                'url' => $functionsurl,
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
            // Register the plugin's webservice functions and services. Both findings share the very same fix, and
            // applying it twice if both findings apply does not do any harm.
            case self::FINDING_NOTREGISTERED:
            case self::FINDING_NOTOFFERED:
                autofix::update_semco_service();
                break;
        }
    }
}
