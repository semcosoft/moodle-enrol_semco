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

use enrol_semco\form\enrolreport_filter_form;
use enrol_semco\table\enrollist_table;
use enrol_semco\table\enrollist_table_filterset;

// Include config.php.
require(__DIR__ . '/../../config.php');

// Globals.
global $CFG, $PAGE, $OUTPUT;

// Include tablelib.php.
require_once($CFG->libdir . '/tablelib.php');

// Get parameters.
$download = optional_param('download', '', PARAM_ALPHA);
$resetfilters = optional_param('resetfilters', '', PARAM_RAW);

// Get the filter parameters.
// They are read with optional_param() and not from the form itself as they also arrive through the paging, sorting and
// download links of the table which carry them as plain URL parameters.
$filters = [
        'filteremail' => optional_param('filteremail', '', PARAM_TEXT),
        'filtersemcouserid' => optional_param('filtersemcouserid', '', PARAM_TEXT),
        'filtersemcobookingid' => optional_param('filtersemcobookingid', '', PARAM_TEXT),
        'filtercourse' => optional_param('filtercourse', 0, PARAM_INT),
        'filterenrolstatus' => optional_param('filterenrolstatus', '', PARAM_ALPHA),
        'filtercompletionstatus' => optional_param('filtercompletionstatus', '', PARAM_ALPHA),
];

// Get system context.
$context = context_system::instance();

// Access checks.
require_login();
require_capability('enrol/semco:viewreport', $context);

// If the user has pressed the button which resets all filters, send him to the unfiltered report.
if ($resetfilters !== '') {
    redirect(new core\url('/enrol/semco/enrolreport.php'));
}

// Prepare page.
$PAGE->set_context($context);

// Prepare table.
$table = new enrollist_table('enrolsemco_enrolreport', $download);

// Compose the filterset from the given filter parameters and hand it over to the table.
// The table is a dynamic table, so this is the way its filters have to be passed: The webservice which delivers the
// table content after a paging or sorting click builds the very same filterset from the filters which the table carries
// in its markup.
// Only the filters whose column the report really shows are taken over, so that a filter parameter which is left over in
// a bookmarked URL cannot filter the report by a column which the admin has switched off in the meantime.
$filterset = new enrollist_table_filterset();
foreach (enrollist_table::get_available_filter_names() as $filtername => $paramname) {
    if ($filters[$paramname] !== '' && $filters[$paramname] !== 0) {
        $filterset->add_filter_from_params($filtername, null, [$filters[$paramname]]);
    }
}
$table->set_filterset($filterset);

// Further prepare page.
// This has to happen before the table is composed below: A dynamic table reads $PAGE->url while it renders itself, so
// composing the table first would make it complain about a page which did not call $PAGE->set_url().
$title = get_string('reportpagetitle', 'enrol_semco');
$PAGE->set_title($title);
$PAGE->set_pagelayout('report');
$PAGE->set_url('/enrol/semco/enrolreport.php', $table->get_filter_params());

// Initialise the dynamic table, so that paging and sorting update the table via a webservice call instead of a page
// reload.
$PAGE->requires->js_call_amd('core_table/dynamic', 'init');

// Compose table.
ob_start();
$table->out(50, true); // When the table is downloaded, the pagesize is ignored and the download file is directly sent out.
$tablehtml = ob_get_contents();
ob_end_clean();

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Prepare the filter form and prefill it with the filters which are currently applied.
$filterform = new enrolreport_filter_form(new core\url('/enrol/semco/enrolreport.php'), null, 'get');
$filterform->set_data($filters);

// Output the filter menu and the table.
// Both are wrapped into a common element as the stylesheet lifts the filter menu next to the table's initials bar on
// wide screens, which needs an element to position it against.
echo html_writer::start_div('enrol_semco-reportheader mt-5');

// Output the filter menu, i.e. the button which opens a dropdown holding the filter form.
echo $OUTPUT->render_from_template('enrol_semco/enrolreport_filters', [
        'filtersform' => $filterform->render(),
        'filtersapplied' => count($table->get_filter_params()),
]);

// Output table.
echo $tablehtml;

echo html_writer::end_div();

// Finish page.
echo $OUTPUT->footer();
