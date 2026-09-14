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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice role plugin capabilities
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

/**
 * Health check which verifies that the SEMCO webservice role holds the plugin's own capabilities.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rolecapabilitiessemco extends rolecapabilities {
    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'rolecapabilitiessemco';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_rolecapabilitiessemco_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_rolecapabilitiessemco_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_rolecapabilitiessemco_description', 'enrol_semco');
    }

    /**
     * Return the capabilities which the SEMCO webservice role must hold in the system context.
     *
     * This list must be kept in sync with the plugin's installation script db/install.php. It deliberately does not
     * contain the 'enrol/semco:viewreport' and 'enrol/semco:viewhealthcheck' capabilities as these two are meant for
     * humans and not for the webservice.
     *
     * @return string[]
     */
    public function get_capabilities(): array {
        return [
            'enrol/semco:usewebservice',
            'enrol/semco:enrol',
            'enrol/semco:unenrol',
            'enrol/semco:editenrolment',
            'enrol/semco:getenrolments',
            'enrol/semco:getcoursecompletions',
            'enrol/semco:resetcoursecompletion',
            'enrol/semco:checkuserexistence',
        ];
    }
}
