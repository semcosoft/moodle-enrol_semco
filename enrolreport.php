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
 * Enrolment method "SEMCO" - Enrolment report
 *
 * @package    enrol_semco
 * @copyright  2024 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_semco\enrollist_table;

// Include config.php.
require(__DIR__ . '/../../config.php');

// Globals.
global $CFG, $PAGE, $OUTPUT;

// Include tablelib.php.
require_once($CFG->libdir . '/tablelib.php');

// Get parameters.
$download = optional_param('download', '', PARAM_ALPHA);

// Get system context.
$context = context_system::instance();

// Access checks.
require_login();
require_capability('enrol/semco:viewreport', $context);

// Prepare page.
$PAGE->set_context($context);

// Prepare table.
$table = new enrollist_table('enrolsemco_enrolreport', $download);

// Compose table.
ob_start();
$table->out(50, true); // When the table is downloaded, the pagesize is ignored and the download file is directly sent out.
$tablehtml = ob_get_contents();
ob_end_clean();

// Further prepare page.
$title = get_string('reportpagetitle', 'enrol_semco');
$PAGE->set_title($title);
$PAGE->set_pagelayout('report');
$PAGE->set_url('/enrol/semco/enrolreport.php');
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Output table.
echo $tablehtml;

// Finish page.
echo $OUTPUT->footer();
