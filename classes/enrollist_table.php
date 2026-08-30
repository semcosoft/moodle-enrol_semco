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
class enrollist_table extends \core_table\sql_table {
    /*
     * The three values which the course completion status column can have.
     *
     * These are an internal detail of this table and not a course completion concept of Moodle: The SQL query composes
     * them, the query sorts by them and other_cols() turns them back into a label. They are deliberately not the core
     * COMPLETION_* constants, which do not cover this distinction at all. COMPLETION_ENABLED / COMPLETION_DISABLED only
     * tell whether course completion is switched on, and COMPLETION_INCOMPLETE / COMPLETION_COMPLETE describe the
     * completion state of an activity. Mixing both sets would even be ambiguous, as COMPLETION_DISABLED and
     * COMPLETION_INCOMPLETE share the value 0, which is exactly the distinction that this column has to make.
     *
     * Their numeric order matters: Sorting the column by these values groups the enrolments from 'not enabled' over
     * 'not completed' to 'completed'.
     */

    /**
     * @var int Course completion status: The course cannot be completed as course completion is not enabled.
     */
    private const COMPLETIONSTATUS_NOTENABLED = 0;

    /**
     * @var int Course completion status: The course can be completed, but the user has not completed it yet.
     */
    private const COMPLETIONSTATUS_NOTCOMPLETED = 1;

    /**
     * @var int Course completion status: The user has completed the course.
     */
    private const COMPLETIONSTATUS_COMPLETED = 2;

    /**
     * @var string The placeholder which is shown in a cell which does not have a value to show.
     *
     * This is the em dash character itself and not the &mdash; HTML entity, as the cell contents are used for the
     * downloaded files as well where an HTML entity would show up verbatim.
     *
     * In contrast to the completion status constants above, this one is public as the tests compare the rendered cells
     * against it.
     */
    public const EMPTYCELL = '—';

    /**
     * @var array The course grade items which have been fetched so far, keyed by the course ID.
     */
    private array $coursegradeitems = [];

    /**
     * Override the constructor to construct a enrollist table instead of a simple table.
     *
     * @param string $uniqueid a string identifying this table. Used as a key in session vars.
     * @param string $download a string which defines the download format (or empty if no download is requested).
     */
    public function __construct($uniqueid, $download) {
        global $CFG;

        parent::__construct($uniqueid);

        // Set the table's HTML ID attribute. The unique ID is only used for the table's URL parameters and does not end up
        // in the markup, but a stable ID makes the table addressable for CSS, JS and acceptance tests.
        $this->set_attribute('id', $uniqueid);

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

        // Compose the SQL expression which yields the course completion status of an enrolment.
        // The status is deduced from exactly the same information which the enrol_semco_get_course_completions webservice
        // reports in its 'canbecompleted' and 'completed' fields: A course which does not have course completion enabled
        // cannot be completed at all, and a course is completed as soon as there is a course completion record which
        // carries a completion time. A missing record (which happens if the user has just been enrolled and cron has not
        // processed the course completions yet) as well as a record without a completion time both mean that the course is
        // not completed.
        // The status is deliberately computed by the database and not in other_cols() below. Only this way, the table can
        // be sorted by the status itself, which groups the enrolments by the three states. Sorting by the raw completion
        // time would not be able to tell the two states apart which do not have a completion time.
        // The site wide completion setting is not part of the expression as it is the same for all rows anyway. If course
        // completion is switched off site wide, no course can be completed and the expression collapses to a constant.
        if (empty($CFG->enablecompletion)) {
            $completionstatussql = (string) self::COMPLETIONSTATUS_NOTENABLED;
        } else {
            $completionstatussql = 'CASE
                    WHEN c.enablecompletion = 0 THEN ' . self::COMPLETIONSTATUS_NOTENABLED . '
                    WHEN cc.timecompleted > 0 THEN ' . self::COMPLETIONSTATUS_COMPLETED . '
                    ELSE ' . self::COMPLETIONSTATUS_NOTCOMPLETED . '
                END';
        }

        // Compose the SQL expression which selects the user's name fields.
        // The report does not show the first name and the last name in two separate columns, it shows the user's name in a
        // single column which is composed by fullname(). Depending on the site's full name format, fullname() does not only
        // need the first name and the last name, but may also need additional name fields like the middle name. All of
        // these fields are therefore selected here.
        $namefieldssql = implode(', ', array_map(static function ($namefield) {
            return 'u.' . $namefield . ' AS ' . $namefield;
        }, \core_user\fields::get_name_fields()));

        // Set the sql for the table (putting enrolid as first parameter to make it unique).
        $sqlfields = 'ue.id AS enrolid, u.id AS moodleuserid, uid.data AS semcouserid, u.username AS username,
                ' . $namefieldssql . ', u.email AS email, u.suspended AS suspended,
                e.courseid AS courseid, c.fullname AS course, e.customchar1 AS semcobookingid,
                ue.timestart AS enrolstart, ue.timeend AS enrolend, ue.status AS enrolstatus,
                ' . $completionstatussql . ' AS coursecompletionstatus, cc.timecompleted AS coursecompletiondate,
                gg.finalgrade AS coursecompletiongrade';
        $sqlfrom = '{enrol} e
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid
                JOIN {course} c ON e.courseid = c.id
                LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
                LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = :gradeitemtype
                LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
                LEFT JOIN {user_info_data} uid ON uid.userid = u.id AND uid.fieldid = (
                    SELECT uif.id FROM {user_info_field} uif WHERE uif.shortname = :uifshortname
                )';
        $sqlwhere = 'u.deleted = :deleted AND e.enrol = :enrol';
        $sqlparams['deleted'] = 0;
        $sqlparams['enrol'] = 'semco';
        $sqlparams['gradeitemtype'] = 'course';
        $sqlparams['uifshortname'] = ENROL_SEMCO_USERFIELD1NAME;
        $this->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);

        // Define the table columns.
        $tablecolumns = ['moodleuserid', 'semcouserid', 'username', 'fullname', 'email', 'suspended',
                'enrolid', 'courseid', 'course', 'semcobookingid', 'enrolstart', 'enrolend', 'enrolstatus',
                'coursecompletionstatus', 'coursecompletiondate', 'coursecompletiongrade'];
        // Add the actions column if the table should not be downloaded.
        if (empty($download)) {
            $tablecolumns[] = 'actions';
        }
        // Set the table columns.
        $this->define_columns($tablecolumns);

        // Prevent column wrapping.
        // This is applied to the header cells and to the body cells alike. The course column re-enables wrapping for its
        // body cells in col_course(), so that its header still stays on a single line.
        foreach ($tablecolumns as $tablecolumn) {
            $this->column_class($tablecolumn, 'text-nowrap');
        }

        // Allow table sorting.
        $this->sortable(true, 'id', SORT_ASC);
        $this->no_sorting('actions');

        // Define the table headers.
        $tableheaders = [
                get_string('tableuserid', 'enrol_semco'),
                get_string('installer_userfield1fullname', 'enrol_semco'),
                get_string('tableusername', 'enrol_semco'),
                // The full name column is a special column in tablelib: Its header is replaced with the sort links of the
                // name fields which the site's full name format uses, just as it is done on /admin/user.php. The header
                // which is defined here is still needed as it is used for the column's show / hide link.
                get_string('fullname'),
                get_string('email'),
                get_string('tableuserstatus', 'enrol_semco'),
                get_string('tableenrolid', 'enrol_semco'),
                get_string('tablecourseid', 'enrol_semco'),
                get_string('tablecoursename', 'enrol_semco'),
                get_string('tablesemcobookingid', 'enrol_semco'),
                get_string('tableenrolstart', 'enrol_semco'),
                get_string('tableenrolend', 'enrol_semco'),
                get_string('tableenrolstatus', 'enrol_semco'),
                get_string('tablecoursecompletionstatus', 'enrol_semco'),
                get_string('tablecoursecompletiondate', 'enrol_semco'),
                get_string('tablecoursecompletiongrade', 'enrol_semco'),
        ];
        // Add the actions column if the table should not be downloaded.
        if (empty($download)) {
            $tableheaders[] = get_string('actions');
        }
        // Set the table headers.
        $this->define_headers($tableheaders);
    }

    /**
     * Override the col_fullname function to show the user's full name as plain text.
     *
     * The parent function turns the full name into a link to the user's profile. This is not wanted here as none of the
     * other report columns is a link and as the report already offers this navigation in its actions column. On top of
     * that, the parent function would not even be able to compose the link as it expects the user ID in a field which is
     * named differently than the one which this table's SQL query provides.
     *
     * @param stdClass $row The submission row.
     *
     * @return string The cell content.
     */
    public function col_fullname($row) {
        return fullname($row, has_capability('moodle/site:viewfullnames', $this->get_context()));
    }

    /**
     * Override the col_course function to allow the course name to wrap within a restricted width.
     *
     * @param stdClass $row The submission row.
     *
     * @return string The cell content.
     */
    public function col_course($row) {
        // If the table is downloaded, return the plain course name as the downloaded files must not contain any markup.
        if ($this->is_downloading()) {
            return $row->course;
        }

        // The course column carries the text-nowrap class like all other columns to keep its header on a single line.
        // Course names can become arbitrarily long, though, so wrapping is re-enabled on an element around the cell
        // content and this element is restricted to a maximum width.
        // The overflow-wrap property makes sure that the maximum width also holds for course names which consist of a
        // single long word without any spaces to break at.
        $style = 'white-space: normal; min-width: 200px; max-width: 300px; overflow-wrap: break-word;';
        return \html_writer::div($row->course, '', ['style' => $style]);
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
        // A value of 0 means that the enrolment does not have a start date, so we show a dedicated label instead of the
        // (misleading) Unix epoch date which userdate() would return for the value 0.
        if ($column === 'enrolstart') {
            if (empty($row->enrolstart)) {
                return get_string('tableenrolunrestricted', 'enrol_semco');
            }
            return userdate($row->enrolstart, get_string('strftimedatetime'));
        }

        // Inject enrolend column.
        // A value of 0 means that the enrolment does not have an end date, so we show a dedicated label instead of the
        // (misleading) Unix epoch date which userdate() would return for the value 0.
        if ($column === 'enrolend') {
            if (empty($row->enrolend)) {
                return get_string('tableenrolunrestricted', 'enrol_semco');
            }
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

        // Inject coursecompletionstatus column.
        // The status itself has already been deduced by the SQL query in the constructor, this function only turns it into
        // a label.
        if ($column === 'coursecompletionstatus') {
            switch ($row->coursecompletionstatus) {
                case self::COMPLETIONSTATUS_COMPLETED:
                    return get_string('completed', 'completion');
                case self::COMPLETIONSTATUS_NOTCOMPLETED:
                    return get_string('notcompleted', 'completion');
                default:
                    return get_string('completionnotenabled', 'completion');
            }
        }

        // Inject coursecompletiondate column.
        // The completion date is the timestamp which the enrol_semco_get_course_completions webservice returns in its
        // 'timecompleted' field.
        if ($column === 'coursecompletiondate') {
            // Only a completed course has a completion date. The status is checked instead of the timestamp itself as a
            // course which cannot be completed anymore may still carry a stale completion record. Such a record must not
            // leak into this column, otherwise the report would show a completion date next to a status which says that
            // the course cannot be completed at all.
            if ($row->coursecompletionstatus != self::COMPLETIONSTATUS_COMPLETED) {
                return self::EMPTYCELL;
            }
            return userdate($row->coursecompletiondate, get_string('strftimedatetime'));
        }

        // Inject coursecompletiongrade column.
        // The grade is the one which the enrol_semco_get_course_completions webservice returns in its 'finalgrade' field,
        // i.e. the user's grade in the course grade item, formatted according to the grade item's settings.
        if ($column === 'coursecompletiongrade') {
            // Only a completed course has a completion grade (see the note on the completion date above). A completed
            // course does not necessarily have a grade, though, as the user may simply not have been graded at all.
            if ($row->coursecompletionstatus != self::COMPLETIONSTATUS_COMPLETED || $row->coursecompletiongrade === null) {
                return self::EMPTYCELL;
            }
            return $this->format_coursegrade($row->courseid, (float) $row->coursecompletiongrade);
        }

        // Inject actions column.
        if ($column === 'actions') {
            $buttonurl = new \core\url('/user/view.php', ['id' => $row->moodleuserid, 'course' => $row->courseid]);
            $buttonlabel = get_string('tableviewenrolment', 'enrol_semco');
            return $OUTPUT->single_button($buttonurl, $buttonlabel, 'get');
        }

        // Call parent function.
        parent::other_cols($column, $row);
    }

    /**
     * Format a raw course grade the way the course's grade item is configured.
     *
     * This is the same formatting which the enrol_semco_get_course_completions webservice applies to its 'finalgrade'
     * field, so that the report and SEMCO show the same grade.
     *
     * In contrast to the webservice, the report deliberately does not trigger grade_regrade_final_grades() beforehand.
     * Regrading a course is a write operation which would be run for every course on the shown page, which is far too
     * expensive for a report. The report therefore shows the grade as it is currently stored in the gradebook.
     *
     * @param int $courseid The course ID.
     * @param float $finalgrade The raw final grade.
     * @return string The formatted grade.
     */
    private function format_coursegrade(int $courseid, float $finalgrade): string {
        global $CFG;

        // Require grade library.
        require_once($CFG->libdir . '/gradelib.php');

        // Fetch the course's grade item if we have not fetched it yet.
        // The report shows many rows which stem from just a few courses, so the grade items are remembered instead of
        // being fetched for every single row.
        if (!array_key_exists($courseid, $this->coursegradeitems)) {
            $this->coursegradeitems[$courseid] = \grade_item::fetch_course_item($courseid);
        }
        $gradeitem = $this->coursegradeitems[$courseid];

        // If the course does not have a course grade item, there is nothing which we could format.
        if (empty($gradeitem)) {
            return self::EMPTYCELL;
        }

        // Format the grade according to the grade item's settings.
        $formattedgrade = grade_format_gradevalue($finalgrade, $gradeitem, true);

        // If the grade item is not graded at all (i.e. it does not have a grade type), fall back to the placeholder.
        if ($formattedgrade === '') {
            return self::EMPTYCELL;
        }

        return $formattedgrade;
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

        // Pick the notification which describes why the table is empty.
        // The table is not necessarily empty because there aren't any SEMCO enrolments at all: As soon as an initial is
        // picked in one of the initials bars above, the table may just as well be empty because no enrolled user matches
        // the picked initial. Telling the user that there aren't any SEMCO enrolments yet would be plainly wrong then.
        // The getters return null if the initials bars are not used at all and an empty string if they are used but no
        // initial is picked, so both cases have to be covered here.
        $initialfirst = $this->get_initial_first();
        $initiallast = $this->get_initial_last();
        if (!empty($initialfirst) || !empty($initiallast)) {
            $emptymessage = get_string('emptytablefiltered', 'enrol_semco');
        } else {
            $emptymessage = get_string('emptytable', 'enrol_semco');
        }
        echo $OUTPUT->notification($emptymessage, 'info');

        // Render the dynamic table footer.
        echo $this->get_dynamic_table_html_end();
    }
}
