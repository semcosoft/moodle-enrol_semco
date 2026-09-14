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
 * Enrolment method "SEMCO" - Health check: SEMCO tenant shortname user profile field
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

/**
 * Health check which verifies the SEMCO tenant shortname user profile field.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profilefield_branchtoken extends profilefield {
    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'profilefield_branchtoken';
    }

    /**
     * Return the shortname of the user profile field which this health check item verifies.
     *
     * @return string
     */
    protected function get_shortname(): string {
        return ENROL_SEMCO_USERFIELD5NAME;
    }

    /**
     * Return the human readable name of the user profile field which this health check item verifies.
     *
     * @return string
     */
    protected function get_name(): string {
        return get_string('healthcheck_profilefield_name_branchtoken', 'enrol_semco');
    }

    /**
     * Return the expected display size (param1) of the user profile field.
     *
     * @return int
     */
    protected function get_expected_param_size(): int {
        return 16;
    }

    /**
     * Return the expected maximum length (param2) of the user profile field.
     *
     * @return int
     */
    protected function get_expected_param_length(): int {
        return 16;
    }
}
