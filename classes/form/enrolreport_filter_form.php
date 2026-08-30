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
 * Enrolment method "SEMCO" - Enrolment report filter form
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\form;

use enrol_semco\table\enrollist_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Require form library.
require_once($CFG->libdir . '/formslib.php');

/**
 * Class enrolreport_filter_form
 *
 * The form has to be instantiated with the 'get' method. The filters end up as URL parameters this way, which is what
 * the report table needs: It builds the URLs of its paging, sorting and download links from the page URL, so the filters
 * have to be readable from there.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolreport_filter_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        // Only offer the filters whose column the report really shows. The admin can switch off most of these columns in
        // the plugin settings, and a filter for a column which is not shown would leave the user with a filtered table
        // and without any clue why the rows were dropped.
        //
        // The filters are added in exactly the order in which the table gives them, which is the order of their columns
        // in the report. The menu therefore lists its filters the same way as the table lists its columns.
        $availablefilters = enrollist_table::get_available_filter_names();
        foreach ($availablefilters as $filtername => $paramname) {
            $this->add_filter_element($filtername, $paramname);
        }

        // Add the action buttons.
        // They are grouped so that they end up on one row which the stylesheet turns into the footer of the filter menu,
        // exactly the way the filter form of the Moodle core report builder does it. The group's name is part of that
        // deal: The stylesheet addresses the footer by the data-groupname attribute which the name ends up in.
        // Both labels are taken from Moodle core, so that the menu speaks the same language as the report builder filter
        // menu which it is modelled after.
        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'submitbutton', get_string('apply'));
        // The reset action is rendered as a link and not as a button. It needs the class override for that, as the form
        // element would otherwise compose the primary or secondary button classes on its own.
        $buttons[] = $mform->createElement(
            'submit',
            'resetfilters',
            get_string('resetall', 'core_reportbuilder'),
            null,
            null,
            ['customclassoverride' => 'btn-link ms-1']
        );
        // The group carries a label for screen readers, which the stylesheet hides visually.
        $mform->addGroup($buttons, 'buttonar', get_string('formactions', 'core_form'), '', false)
                ->setHiddenLabel(true);
    }

    /**
     * Add the form element of a single filter.
     *
     * Every filter is labelled with the string of the report column which it filters, as both mean the same thing.
     *
     * @param string $filtername The name of the filter.
     * @param string $paramname The name of the URL parameter which carries the filter, which is the element name as well.
     * @return void
     */
    private function add_filter_element(string $filtername, string $paramname): void {
        $mform = $this->_form;

        switch ($filtername) {
            // The filters which search within a text.
            // Their help text explains that a part of the value is enough, which is not obvious from the field itself.
            // The help text belongs to the filter and not to the column, so the help button is given the filter's own
            // identifier. This is why the language pack carries a string per filter which is not used as a label
            // anywhere: A help button always needs the string of its identifier as the heading of the help text.
            case 'email':
            case 'semcouserid':
            case 'semcobookingid':
                $mform->addElement('text', $paramname, $this->get_filter_label($filtername));
                $mform->setType($paramname, PARAM_TEXT);
                $mform->addHelpButton($paramname, $paramname, 'enrol_semco');
                break;

            // The course filter. Its list only holds the courses which really hold SEMCO enrolments.
            case 'course':
                $courseoptions = [0 => get_string('all')] + enrollist_table::get_filterable_courses();
                $mform->addElement('select', $paramname, $this->get_filter_label($filtername), $courseoptions);
                $mform->setType($paramname, PARAM_INT);
                break;

            // The enrolment status filter.
            // Its options are taken from the table, so that the filter offers exactly the labels which the column shows.
            case 'enrolstatus':
                $enrolstatusoptions = ['' => get_string('all')] + enrollist_table::get_enrolstatus_options();
                $mform->addElement('select', $paramname, $this->get_filter_label($filtername), $enrolstatusoptions);
                $mform->setType($paramname, PARAM_ALPHA);
                break;

            // The course completion status filter.
            // Its options are taken from the table, so that the filter offers exactly the labels which the column shows.
            case 'completionstatus':
                $completionoptions = ['' => get_string('all')] + enrollist_table::get_completionstatus_options();
                $mform->addElement('select', $paramname, $this->get_filter_label($filtername), $completionoptions);
                $mform->setType($paramname, PARAM_ALPHA);
                break;
        }
    }

    /**
     * Get the label of a single filter, which is the header of the report column which the filter filters.
     *
     * @param string $filtername The name of the filter.
     * @return string The label.
     */
    private function get_filter_label(string $filtername): string {
        $reportcolumns = enrollist_table::get_report_columns();
        $filtercolumns = enrollist_table::get_filter_columns();

        return $reportcolumns[$filtercolumns[$filtername]];
    }
}
