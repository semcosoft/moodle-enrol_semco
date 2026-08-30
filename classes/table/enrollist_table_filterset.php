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
 * Enrolment method "SEMCO" - Enrol list table filterset
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace enrol_semco\table;

use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;

/**
 * Class enrollist_table_filterset
 *
 * This is the filterset of the enrolment report table. The Table API requires it to be named like the table class with a
 * '_filterset' suffix, as the dynamic table webservice composes the class name that way.
 *
 * All filters are optional: The report is meaningful without any filter at all, it then simply shows every SEMCO
 * enrolment of the Moodle instance.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollist_table_filterset extends filterset {
    /**
     * Get the required filters.
     *
     * The report does not have any required filter.
     *
     * @return array
     */
    public function get_required_filters(): array {
        return [];
    }

    /**
     * Get the optional filters.
     *
     * These are, in the order in which the report shows the matching columns:
     * - email: A part of an email address;
     * - semcouserid: A part of a SEMCO user ID;
     * - semcobookingid: A part of a SEMCO booking ID;
     * - course: The ID of a course which holds SEMCO enrolments;
     * - enrolstatus: Either 'active' or 'suspended'; and
     * - completionstatus: One of the course completion status identifiers of the table.
     *
     * @return array
     */
    public function get_optional_filters(): array {
        return [
            'email' => string_filter::class,
            'semcouserid' => string_filter::class,
            'semcobookingid' => string_filter::class,
            'course' => integer_filter::class,
            'enrolstatus' => string_filter::class,
            'completionstatus' => string_filter::class,
        ];
    }
}
