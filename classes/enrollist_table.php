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
 * Enrolment method "SEMCO" - Enrol list class
 *
 * @package    enrol_semco
 * @copyright  2024 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Require plugin library.
require_once($CFG->dirroot . '/enrol/semco/locallib.php');

// Require table library.
require_once($CFG->dirroot . '/lib/tablelib.php');

/**
 * Class enrollist_table
 *
 * @package    enrol_semco
 * @copyright  2024 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollist_table extends \table_sql {
    /**
     * Override the constructor to construct a enrollist table instead of a simple table.
     *
     * @param string $uniqueid a string identifying this table. Used as a key in session vars.
     * @param string $download a string which defines the download format (or empty if no download is requested).
     */
    public function __construct($uniqueid, $download) {
        global $CFG;

        parent::__construct($uniqueid);

        // Define base URL.
        $this->define_baseurl($CFG->wwwroot . '/enrol/semco/enrolreport.php');

        // Allow and configure downloading.
        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);

        // If the table should be downloaded.
        if (!empty($download)) {
            // Set the download type and filename.
            $this->is_downloading($download, 'semco-enrolreport');
        }

        // Set the sql for the table (putting enrolid as first parameter to make it unique).
        $sqlfields = 'ue.id AS enrolid, u.id AS moodleuserid, uid.data AS semcouserid, u.username AS username,
                u.lastname AS lastname, u.firstname AS firstname, u.email AS email, u.suspended AS suspended,
                e.courseid AS courseid, c.fullname AS course, e.customchar1 AS semcobookingid,
                ue.timestart AS enrolstart, ue.timeend AS enrolend, ue.status AS enrolstatus';
        $sqlfrom = '{enrol} e
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid
                JOIN {course} c ON e.courseid = c.id
                LEFT JOIN {user_info_data} uid ON uid.userid = u.id AND uid.fieldid = (
                    SELECT uif.id FROM {user_info_field} uif WHERE uif.shortname = :uifshortname
                )';
        $sqlwhere = 'u.deleted = :deleted AND e.enrol = :enrol';
        $sqlparams['deleted'] = 0;
        $sqlparams['enrol'] = 'semco';
        $sqlparams['uifshortname'] = ENROL_SEMCO_USERFIELD1NAME;
        $this->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);

        // Define the table columns.
        $tablecolumns = ['moodleuserid', 'semcouserid', 'username', 'lastname', 'firstname', 'email', 'suspended',
                'enrolid', 'courseid', 'course', 'semcobookingid', 'enrolstart', 'enrolend', 'enrolstatus'];
        // Add the actions column if the table should not be downloaded.
        if (empty($download)) {
            $tablecolumns[] = 'actions';
        }
        // Set the table columns.
        $this->define_columns($tablecolumns);

        // Allow table sorting.
        $this->sortable(true, 'id', SORT_ASC);
        $this->no_sorting('actions');

        // Define the table headers.
        $tableheaders = [
                get_string('tableuserid', 'enrol_semco'),
                get_string('installer_userfield1fullname', 'enrol_semco'),
                get_string('tableusername', 'enrol_semco'),
                get_string('lastname'),
                get_string('firstname'),
                get_string('email'),
                get_string('tableuserstatus', 'enrol_semco'),
                get_string('tableenrolid', 'enrol_semco'),
                get_string('tablecourseid', 'enrol_semco'),
                get_string('tablecoursename', 'enrol_semco'),
                get_string('tablesemcobookingid', 'enrol_semco'),
                get_string('tableenrolstart', 'enrol_semco'),
                get_string('tableenrolend', 'enrol_semco'),
                get_string('tableenrolstatus', 'enrol_semco'),
        ];
        // Add the actions column if the table should not be downloaded.
        if (empty($download)) {
            $tableheaders[] = get_string('actions');
        }
        // Set the table headers.
        $this->define_headers($tableheaders);
    }

    /**
     * Override the other_cols function to inject content into columns which does not come directly from the database.
     *
     * @param string $column The column name.
     * @param stdClass $row The submission row.
     *
     * @return mixed string or null.
     */
    public function other_cols($column, $row) {
        global $OUTPUT;

        // Inject suspended column.
        // This column is labeled as "user status", but the column name is still "suspended" to allow sorting by this
        // column.
        if ($column === 'suspended') {
            if ($row->suspended == 1) {
                return get_string('suspended');
            } else {
                return get_string('active');
            }
        }

        // Inject enrolstart column.
        if ($column === 'enrolstart') {
            return userdate($row->enrolstart, get_string('strftimedatetime'));
        }

        // Inject enrolend column.
        if ($column === 'enrolend') {
            return userdate($row->enrolend, get_string('strftimedatetime'));
        }

        // Inject enrolstatus column.
        if ($column === 'enrolstatus') {
            if ($row->enrolstatus == ENROL_USER_SUSPENDED) {
                return get_string('suspended');
            } else {
                return get_string('active');
            }
        }

        // Inject actions column.
        if ($column === 'actions') {
            $buttonurl = new \moodle_url('/user/view.php', ['id' => $row->moodleuserid, 'course' => $row->courseid]);
            $buttonlabel = get_string('tableviewenrolment', 'enrol_semco');
            return $OUTPUT->single_button($buttonurl, $buttonlabel, 'get');
        }

        // Call parent function.
        parent::other_cols($column, $row);
    }

    /**
     * This function is not part of the public api.
     */
    public function print_nothing_to_display() {
        global $OUTPUT;

        // Render the dynamic table header.
        echo $this->get_dynamic_table_html_start();

        // Render button to allow user to reset table preferences.
        echo $this->render_reset_button();

        $this->print_initials_bar();

        echo $OUTPUT->notification(get_string('emptytable', 'enrol_semco'), 'info');

        // Render the dynamic table footer.
        echo $this->get_dynamic_table_html_end();
    }
}
