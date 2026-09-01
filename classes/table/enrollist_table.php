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

namespace enrol_semco\table;

use core_table\local\filter\filterset;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Require plugin library.
require_once($CFG->dirroot . '/enrol/semco/locallib.php');

// Require table library.
require_once($CFG->dirroot . '/lib/tablelib.php');

// Require user library.
// It holds user_can_view_profile() which the actions menu needs to decide which profile pages it may link to.
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Class enrollist_table
 *
 * This is a dynamic table, i.e. it updates its content via a webservice call instead of a page reload when the user
 * pages or sorts it. The Table API imposes three things on such a table which are worth knowing when this class is
 * touched:
 * # The class has to live in the \enrol_semco\table namespace and its filterset has to be the same class name with a
 *   '_filterset' suffix. The webservice composes both class names from the component and the handler name.
 * # The webservice instantiates the class with the unique ID as the only argument, so every other constructor argument
 *   needs a default value.
 * # The filters do not reach the constructor but arrive afterwards through set_filterset(). The table's SQL is therefore
 *   not composed in the constructor but in set_table_sql(), which is called from both places.
 *
 * @package    enrol_semco
 * @copyright  2024 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollist_table extends \core_table\sql_table implements \core_table\dynamic {
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
     * @var array The courses which have been fetched so far, keyed by the course ID.
     */
    private array $courses = [];

    /**
     * Override the constructor to construct a enrollist table instead of a simple table.
     *
     * @param string $uniqueid a string identifying this table. Used as a key in session vars.
     * @param string $download a string which defines the download format (or empty if no download is requested).
     *                         This argument needs a default value as the dynamic table webservice instantiates the table
     *                         with the unique ID as its only argument.
     */
    public function __construct($uniqueid, $download = '') {
        parent::__construct($uniqueid);

        // Set the table's HTML ID attribute. The unique ID is only used for the table's URL parameters and does not end up
        // in the markup, but a stable ID makes the table addressable for CSS, JS and acceptance tests.
        $this->set_attribute('id', $uniqueid);

        // Define base URL.
        $this->guess_base_url();

        // Allow and configure downloading.
        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);

        // If the table should be downloaded.
        if (!empty($download)) {
            // Set the download type and filename.
            $this->is_downloading($download, 'semco-enrolreport');
        }

        // Compose the table's SQL without any filter.
        // As soon as a filterset is set, set_filterset() composes it once more, then including the filter conditions.
        $this->set_table_sql();

        // Get the table columns with their headers, in the order in which the report shows them.
        $tablecolumns = self::get_report_columns();

        // Get the initial sorting column from the plugin settings.
        // The setting's default is used as long as the setting does not hold a supported value, which is the case as long
        // as it has not been stored at all.
        $sortingcolumn = get_config('enrol_semco', 'reportinitialsortingcolumn');
        if (!array_key_exists($sortingcolumn, enrol_semco_get_report_sortingcolumns())) {
            $sortingcolumn = ENROL_SEMCO_REPORT_SORTINGCOLUMN_DEFAULT;
        }

        // Add the actions column if the table should not be downloaded.
        // This is done after the re-ordering above as the actions column always stays the last column of the table.
        if (empty($download)) {
            // The column does not have a visible header, just as it is done on /admin/user.php. The header is still there
            // for screen readers, though, as an empty column header would leave them without any clue what the column is
            // about.
            $tablecolumns['actions'] = \core\output\html_writer::span(get_string('actions'), 'visually-hidden');
        }

        // Set the table columns.
        $this->define_columns(array_keys($tablecolumns));

        // Prevent column wrapping.
        // This is applied to the header cells and to the body cells alike. The course column re-enables wrapping for its
        // body cells in col_course(), so that its header still stays on a single line.
        foreach (array_keys($tablecolumns) as $tablecolumn) {
            $this->column_class($tablecolumn, 'text-nowrap');
        }

        // Prevent the user from hiding columns.
        // The report is meant to show the full picture of a SEMCO enrolment, and a hidden column would silently stay
        // hidden on every subsequent visit as the table remembers this preference.
        // Two calls are needed for this: collapsible() removes the show / hide links from the column headers, and the
        // empty list of hidden columns makes the table discard every column collapse preference which still reaches it
        // otherwise. Such a preference can still reach the table in two ways: The user may have hidden a column back
        // when this report still offered the show / hide links, in which case the column would stay hidden forever as
        // there is no link anymore to bring it back. And the table picks the column to hide from a URL parameter, which
        // anyone can add to the report URL by hand.
        $this->collapsible(false);
        $this->set_hidden_columns([]);

        // Allow table sorting, starting with the initial sorting column from the plugin settings.
        // The sorting column is passed as it is stored and is deliberately not replaced with the full name column: The
        // full name column is sorted by one of its name fields and not by the column name itself.
        $this->sortable(true, $sortingcolumn, SORT_ASC);
        $this->no_sorting('actions');

        // Set the table headers.
        $this->define_headers(array_values($tablecolumns));
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
     * Override set_filterset() to rebuild the table's SQL as soon as the filters are known.
     *
     * The filters do not reach the constructor: The dynamic table webservice instantiates the table first and hands the
     * filterset over afterwards. This is therefore the earliest point at which the filter conditions can be added to the
     * query.
     *
     * @param filterset $filterset The filterset to apply to the table.
     */
    public function set_filterset(filterset $filterset): void {
        // Take over the filterset.
        parent::set_filterset($filterset);

        // Compose the table's SQL once more, this time including the filter conditions.
        $this->set_table_sql();

        // The filters have to be part of the base URL as well. The table builds the URLs of its download links from it,
        // so a download would deliver the unfiltered table otherwise.
        $this->guess_base_url();
    }

    /**
     * Compose the table's SQL query, including the conditions of the currently set filters.
     */
    protected function set_table_sql(): void {
        global $CFG, $DB;

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

        // Compose the SQL expressions which select the SEMCO user profile fields.
        // Every user profile field value lives in its own user_info_data row, so every field which the report shows needs
        // a join of its own. The fields are addressed by their shortname as their IDs differ from installation to
        // installation. The subqueries which look up these IDs do not reference the outer query, so the database can
        // evaluate each of them once instead of once per row.
        // The expression which a field ends up in is remembered as well, as the filters below cannot address a field by
        // its column alias: SQL does not allow a column alias of the SELECT list to be used in a WHERE clause.
        $sqlparams = [];
        $userfieldselects = [];
        $userfieldexpressions = [];
        $userfieldjoins = '';
        $userfieldindex = 0;
        foreach (enrol_semco_get_report_userfieldcolumns() as $userfieldcolumn => $userfieldshortname) {
            $dataalias = 'uid' . $userfieldindex;
            $fieldalias = 'uif' . $userfieldindex;
            $shortnameparam = 'uifshortname' . $userfieldindex;
            $userfieldselects[] = $dataalias . '.data AS ' . $userfieldcolumn;
            $userfieldexpressions[$userfieldcolumn] = $dataalias . '.data';
            $userfieldjoins .= '
                LEFT JOIN {user_info_data} ' . $dataalias . ' ON ' . $dataalias . '.userid = u.id
                        AND ' . $dataalias . '.fieldid = (
                            SELECT ' . $fieldalias . '.id FROM {user_info_field} ' . $fieldalias . '
                            WHERE ' . $fieldalias . '.shortname = :' . $shortnameparam . '
                        )';
            $sqlparams[$shortnameparam] = $userfieldshortname;
            $userfieldindex++;
        }
        $userfieldssql = implode(', ', $userfieldselects);

        // Set the sql for the table (putting enrolid as first parameter to make it unique).
        $sqlfields = 'ue.id AS enrolid, u.id AS moodleuserid, ' . $userfieldssql . ', u.username AS username,
                ' . $namefieldssql . ', u.email AS email, u.suspended AS suspended,
                e.courseid AS courseid, c.fullname AS course, e.customchar1 AS semcobookingid,
                c.showgrades AS courseshowgrades,
                ue.timestart AS enrolstart, ue.timeend AS enrolend, ue.status AS enrolstatus,
                ' . $completionstatussql . ' AS coursecompletionstatus, cc.timecompleted AS coursecompletiondate,
                gg.finalgrade AS coursecompletiongrade';
        $sqlfrom = '{enrol} e
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid
                JOIN {course} c ON e.courseid = c.id
                LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
                LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = :gradeitemtype
                LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id' . $userfieldjoins;
        $sqlwhere = 'u.deleted = :deleted AND e.enrol = :enrol';
        $sqlparams['deleted'] = 0;
        $sqlparams['enrol'] = 'semco';
        $sqlparams['gradeitemtype'] = 'course';

        // Add a condition for the course filter.
        $courseids = $this->get_filter_values('course');
        if (!empty($courseids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'filtercourse');
            $sqlwhere .= ' AND e.courseid ' . $insql;
            $sqlparams += $inparams;
        }

        // Add a condition for the course completion status filter.
        $completionstatuses = $this->get_filter_values('completionstatus');
        if (!empty($completionstatuses)) {
            $statusvalues = array_map(function ($identifier) {
                return self::resolve_completionstatus($identifier);
            }, $completionstatuses);
            [$insql, $inparams] = $DB->get_in_or_equal($statusvalues, SQL_PARAMS_NAMED, 'filtercompletionstatus');
            // The status expression has to be repeated here as SQL does not allow a column alias of the SELECT list to be
            // used in a WHERE clause.
            $sqlwhere .= ' AND (' . $completionstatussql . ') ' . $insql;
            $sqlparams += $inparams;
        }

        // Add a condition for the enrolment status filter.
        $enrolstatuses = $this->get_filter_values('enrolstatus');
        if (!empty($enrolstatuses)) {
            $statusvalues = array_map(function ($enrolstatus) {
                return ($enrolstatus === 'suspended') ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
            }, $enrolstatuses);
            [$insql, $inparams] = $DB->get_in_or_equal($statusvalues, SQL_PARAMS_NAMED, 'filterenrolstatus');
            $sqlwhere .= ' AND ue.status ' . $insql;
            $sqlparams += $inparams;
        }

        // Add the conditions for the filters which search within a text.
        // Their value is matched as a substring and case insensitively, so that the user does not have to know the full
        // value to find an enrolment.
        $textfilters = [
                'email' => 'u.email',
                'semcouserid' => $userfieldexpressions['semcouserid'] ?? null,
                'semcobookingid' => 'e.customchar1',
        ];
        foreach ($textfilters as $textfilter => $fieldsql) {
            // A user profile field which does not exist in this installation cannot be filtered by.
            if ($fieldsql === null) {
                continue;
            }
            foreach (array_values($this->get_filter_values($textfilter)) as $index => $searchterm) {
                $paramname = 'filter' . $textfilter . $index;
                $sqlwhere .= ' AND ' . $DB->sql_like($fieldsql, ':' . $paramname, false, false);
                $sqlparams[$paramname] = '%' . $DB->sql_like_escape($searchterm) . '%';
            }
        }

        $this->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);
    }

    /**
     * Get the values of a single filter of the currently set filterset.
     *
     * @param string $filtername The name of the filter.
     * @return array The filter values, or an empty array if the filter is not set at all.
     */
    protected function get_filter_values(string $filtername): array {
        // A filter whose column the report does not show is ignored. The filter menu does not offer such a filter, but
        // its value can still reach the table through a bookmarked report URL or through the webservice which delivers
        // the table content, so it is dropped here where every filter value passes through.
        if (!array_key_exists($filtername, self::get_available_filter_names())) {
            return [];
        }

        // If there is no filterset yet or if the filterset does not carry this filter, there is nothing to return.
        if ($this->filterset === null || !$this->filterset->has_filter($filtername)) {
            return [];
        }

        // Get the filter values, dropping the ones which are empty. An empty value is what the report's filter form
        // submits for a filter which the user has left on 'All'.
        $values = array_filter($this->filterset->get_filter($filtername)->get_filter_values(), function ($value) {
            return $value !== null && $value !== '';
        });

        // Drop the values which the filter does not know at all.
        // This can only be checked for the filters which offer a fixed set of options, the ones which search within a
        // text accept anything which the user types. Such an unknown value does not come from the filter menu, but it
        // can still reach the table through a bookmarked report URL or through the webservice which delivers the table
        // content, in both cases with an arbitrary value. It is dropped instead of being passed on, just as an unknown
        // filter is dropped above: The report then simply ignores it and shows the enrolments which the remaining
        // filters keep.
        $allowedvalues = self::get_filter_allowedvalues($filtername);
        if ($allowedvalues !== null) {
            $values = array_filter($values, function ($value) use ($allowedvalues) {
                return in_array($value, $allowedvalues, true);
            });
        }

        return $values;
    }

    /**
     * Get the values which a single filter of the report accepts.
     *
     * @param string $filtername The name of the filter.
     * @return array|null The accepted values, or null if the filter does not have a fixed set of values.
     */
    protected static function get_filter_allowedvalues(string $filtername): ?array {
        switch ($filtername) {
            case 'enrolstatus':
                return array_keys(self::get_enrolstatus_options());

            case 'completionstatus':
                return array_keys(self::get_completionstatus_options());

            // The remaining filters do not have a fixed set of values: The text filters accept any search term, and the
            // course filter accepts any course ID as a course which does not hold any SEMCO enrolment simply leaves the
            // report empty.
            default:
                return null;
        }
    }

    /**
     * Override can_be_reset() to suppress the link which resets the table preferences.
     *
     * The report does not need that link: The initials bar offers an 'All' entry to drop its filter again, and the
     * sorting is changed by clicking a column header, so there is nothing which the user could get stuck with. On top of
     * that, the link is rendered into the same corner of the report as the filter menu, where it would compete with the
     * filter menu button for the space.
     *
     * Returning false here is enough to remove the link completely, as this is the only thing which the parent class
     * uses this function for.
     *
     * @return bool
     */
    protected function can_be_reset(): bool {
        return false;
    }

    /**
     * Check the capability which is needed to see the table.
     *
     * This is required by the dynamic table interface. It is called by the webservice which delivers the table content,
     * i.e. it is the access check of every dynamic update of the table.
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('enrol/semco:viewreport', $this->get_context());
    }

    /**
     * Get the context of the table.
     *
     * This is required by the dynamic table interface. The report is a site wide report, so it lives in the system
     * context.
     *
     * @return \core\context
     */
    public function get_context(): \core\context {
        return \core\context\system::instance();
    }

    /**
     * Set the base URL of the table.
     *
     * This is required by the dynamic table interface. The active filters are part of the URL, as the table builds the
     * URLs of its download links from it and a download would deliver the unfiltered table otherwise.
     */
    public function guess_base_url(): void {
        $this->baseurl = new \core\url('/enrol/semco/enrolreport.php', $this->get_filter_params());
    }

    /**
     * Override the col_course function to allow the course name to wrap within a restricted width.
     *
     * @param stdClass $row The submission row.
     *
     * @return string The cell content.
     */
    public function col_course($row) {
        // Format the course name the same way as the course filter formats the course names which it offers. Without
        // this, a course name which carries a multilang span would show all of its language variants at once and any
        // other markup within the name would be rendered as markup instead of being shown as it is.
        $coursename = format_string($row->course, true, ['context' => \core\context\course::instance($row->courseid)]);

        // If the table is downloaded, return the plain course name as the downloaded files must not contain any markup.
        // The formatting above still has to happen for a download, as this is what resolves a multilang course name into
        // the language which the downloading user reads. Whatever markup is left over afterwards is removed by tablelib
        // itself, which strips the tags and decodes the HTML entities of every cell before it writes the file.
        if ($this->is_downloading()) {
            return $coursename;
        }

        // The course column carries the text-nowrap class like all other columns to keep its header on a single line.
        // Course names can become arbitrarily long, though, so wrapping is re-enabled on an element around the cell
        // content and this element is restricted to a maximum width.
        // The overflow-wrap property makes sure that the maximum width also holds for course names which consist of a
        // single long word without any spaces to break at.
        $style = 'white-space: normal; min-width: 200px; max-width: 300px; overflow-wrap: break-word;';
        return \html_writer::div($coursename, '', ['style' => $style]);
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
        // Inject the columns which show a SEMCO user profile field.
        // The raw field value is formatted the same way as Moodle core formats the value of a text user profile field,
        // i.e. it is put through format_string() with the context of the page which shows it. Without this, a field
        // value which carries a multilang span would show all of its language variants at once and any other markup
        // within the value would be rendered as markup instead of being shown as it is.
        if (array_key_exists($column, enrol_semco_get_report_userfieldcolumns())) {
            $fieldvalue = format_string($row->$column, true, ['context' => $this->get_context()]);

            // A user who does not have a value in the field at all yields null here, which format_string() turns into
            // an empty string. Such a cell shows the placeholder instead of staying empty, just as the course
            // completion columns do it: An empty cell leaves the reader wondering whether the value is missing or
            // whether the report failed to show it.
            if ($fieldvalue === '') {
                return self::EMPTYCELL;
            }

            return $fieldvalue;
        }

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
            $statuslabels = self::get_completionstatus_options();
            switch ($row->coursecompletionstatus) {
                case self::COMPLETIONSTATUS_COMPLETED:
                    return $statuslabels['completed'];
                case self::COMPLETIONSTATUS_NOTCOMPLETED:
                    return $statuslabels['notcompleted'];
                default:
                    return $statuslabels['notenabled'];
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
            return $this->build_actionsmenu($row);
        }

        // Call parent function.
        parent::other_cols($column, $row);
    }

    /**
     * Build the actions menu of a report row.
     *
     * The menu is a kebab menu with one item per page which the report links to, just as it is done on /admin/user.php.
     *
     * An item is only added if the current user is really allowed to see the page which it links to. The report is not
     * only shown to administrators but to everyone with the report capability, and such a user does not necessarily hold
     * the capabilities which the linked pages require. Without these checks, the menu would offer items which only lead
     * to an error page.
     *
     * @param stdClass $row The submission row.
     *
     * @return string The cell content.
     */
    private function build_actionsmenu($row) {
        global $OUTPUT;

        // Compose the user object which the profile checks below need.
        // The report never lists deleted users as its SQL query filters them out, so the flag can be set right away
        // instead of fetching the whole user record for every single row.
        $user = (object) ['id' => $row->moodleuserid, 'deleted' => 0];

        // Get the course. It is needed as a whole record and not just as an ID, as the checks below look at the course's
        // group mode and at its 'Show gradebook to students' setting.
        $course = $this->get_course($row->courseid);

        // Create the menu.
        $menu = new \core\output\action_menu();
        $menu->set_kebab_trigger(get_string('actions'));

        // Add the item which leads to the user's site wide profile.
        if (user_can_view_profile($user)) {
            $menu->add(new \core\output\action_menu\link_secondary(
                new \core\url('/user/profile.php', ['id' => $row->moodleuserid]),
                new \core\output\pix_icon('viewuserprofile', '', 'enrol_semco'),
                get_string('tableviewuserprofile', 'enrol_semco')
            ));
        }

        // Add the item which leads to the user's profile within the enrolled course.
        if (user_can_view_profile($user, $course)) {
            $menu->add(new \core\output\action_menu\link_secondary(
                new \core\url('/user/view.php', ['id' => $row->moodleuserid, 'course' => $row->courseid]),
                new \core\output\pix_icon('viewcourseprofile', '', 'enrol_semco'),
                get_string('tableviewenrolment', 'enrol_semco')
            ));
        }

        // Add the item which leads to the user's grades within the enrolled course.
        if ($this->can_view_coursegrades($course, $row->moodleuserid, $row->courseshowgrades)) {
            $menu->add(new \core\output\action_menu\link_secondary(
                new \core\url(
                    '/course/user.php',
                    ['mode' => 'grade', 'id' => $row->courseid, 'user' => $row->moodleuserid]
                ),
                new \core\output\pix_icon('viewcoursegrades', '', 'enrol_semco'),
                get_string('tableviewcoursegrades', 'enrol_semco')
            ));
        }

        // If the user is not allowed to reach any of the pages, the menu is not shown at all. Rendering it anyway would
        // leave an inviting kebab trigger which opens an empty menu.
        if ($menu->is_empty()) {
            return '';
        }

        return $OUTPUT->render($menu);
    }

    /**
     * Check whether the current user is allowed to see the given user's grades within the given course.
     *
     * These are exactly the conditions under which /course/user.php offers its 'grade' mode, which is the page which the
     * actions menu links to.
     *
     * @param stdClass $course The course.
     * @param int $userid The ID of the user whose grades should be shown.
     * @param int $showgrades Whether the course shows its gradebook to students.
     *
     * @return bool Whether the grades can be seen.
     */
    private function can_view_coursegrades($course, int $userid, $showgrades): bool {
        global $USER;

        $coursecontext = \core\context\course::instance($course->id);
        $usercontext = \core\context\user::instance($userid);

        // Everyone who can see all grades of the course can see this user's grades as well.
        if (has_capability('moodle/grade:viewall', $coursecontext)) {
            return true;
        }

        // All remaining cases need the course to show its gradebook at all.
        if (empty($showgrades)) {
            return false;
        }

        // Users can see their own grades if the course lets them.
        if ($userid == $USER->id && has_capability('moodle/grade:view', $coursecontext)) {
            return true;
        }

        // And the grades of a particular user can be seen by those who are allowed to look at this very user, which are
        // typically the user's parents.
        return has_capability('moodle/grade:viewall', $usercontext) ||
                has_capability('moodle/user:viewuseractivitiesreport', $usercontext);
    }

    /**
     * Get a course record.
     *
     * The report shows many rows which stem from just a few courses, so the courses are remembered instead of being
     * fetched for every single row.
     *
     * @param int $courseid The course ID.
     *
     * @return stdClass The course record.
     */
    private function get_course(int $courseid) {
        if (!array_key_exists($courseid, $this->courses)) {
            $this->courses[$courseid] = get_course($courseid);
        }

        return $this->courses[$courseid];
    }

    /**
     * Get the enrolment statuses and their labels.
     *
     * The statuses are identified by a string and not by the core ENROL_USER_* constants, so that the report URL stays
     * readable.
     *
     * This is the single place where the two statuses are listed. Both the enrolment status filter and the check which
     * decides whether a given filter value is accepted at all use it.
     *
     * @return array The status labels, keyed by the status identifier.
     */
    public static function get_enrolstatus_options(): array {
        return [
            'active' => get_string('active'),
            'suspended' => get_string('suspended'),
        ];
    }

    /**
     * Get the course completion statuses and their labels.
     *
     * The statuses are identified by a string and not by the class constants above, so that the report URL stays
     * readable and the constants can remain an internal detail of this class.
     *
     * This is the single place where the three statuses are labelled. Both the course completion status column and the
     * filter of the report use it, which makes sure that the filter offers exactly the labels which the column shows.
     *
     * @return array The status labels, keyed by the status identifier.
     */
    public static function get_completionstatus_options(): array {
        return [
            'notenabled' => get_string('completionnotenabled', 'completion'),
            'notcompleted' => get_string('notcompleted', 'completion'),
            'completed' => get_string('completed', 'completion'),
        ];
    }

    /**
     * Resolve a course completion status filter identifier into the matching status value.
     *
     * The identifier is expected to be a known one: get_filter_values() has already dropped the values which the filter
     * does not know, so an unknown identifier which reaches this function is a programming error and not a filter value
     * which someone has put into the report URL by hand. This is what the exception below is about.
     *
     * @param string $identifier The filter identifier.
     * @return int The course completion status value.
     */
    private static function resolve_completionstatus(string $identifier): int {
        $statuses = [
            'notenabled' => self::COMPLETIONSTATUS_NOTENABLED,
            'notcompleted' => self::COMPLETIONSTATUS_NOTCOMPLETED,
            'completed' => self::COMPLETIONSTATUS_COMPLETED,
        ];

        // Throw an exception if the given identifier is unknown.
        if (!array_key_exists($identifier, $statuses)) {
            throw new \coding_exception('The course completion status \'' . $identifier .
                    '\' is unknown, it has to be one of: ' . implode(', ', array_keys($statuses)));
        }

        return $statuses[$identifier];
    }

    /**
     * Get the columns which the report shows, with their headers, in the order in which the report shows them.
     *
     * The columns and their headers are kept in a single array as both of them are filtered and re-ordered here and as a
     * column which loses its header (or vice versa) would shift the whole table.
     *
     * The actions column is not part of the result: It is not a column of the report's data but a column which the table
     * appends for the HTML output only.
     *
     * Besides the table itself, the report's filter menu uses this list as well, so that it can offer its filters in the
     * same order as the report shows the matching columns.
     *
     * @return array The column headers, keyed by the column name.
     */
    public static function get_report_columns(): array {
        $tablecolumns = [
                // The full name column is a special column in tablelib: Its header is replaced with the sort links of the
                // name fields which the site's full name format uses, just as it is done on /admin/user.php. The header
                // which is defined here is still needed as tablelib falls back to it if the full name format does not
                // yield any sortable name field.
                'fullname' => get_string('fullname'),
                'email' => get_string('email'),
                'moodleuserid' => get_string('tableuserid', 'enrol_semco'),
                'username' => get_string('tableusername', 'enrol_semco'),
                'semcouserid' => get_string('installer_userfield1fullname', 'enrol_semco'),
                'semcousercompany' => get_string('installer_userfield2fullname', 'enrol_semco'),
                'semcouserbirthday' => get_string('installer_userfield3fullname', 'enrol_semco'),
                'semcouserplaceofbirth' => get_string('installer_userfield4fullname', 'enrol_semco'),
                'semcotenantshortname' => get_string('installer_userfield5fullname', 'enrol_semco'),
                'semcobookingid' => get_string('tablesemcobookingid', 'enrol_semco'),
                'enrolid' => get_string('tableenrolid', 'enrol_semco'),
                'courseid' => get_string('tablecourseid', 'enrol_semco'),
                'course' => get_string('tablecoursename', 'enrol_semco'),
                'enrolstart' => get_string('tableenrolstart', 'enrol_semco'),
                'enrolend' => get_string('tableenrolend', 'enrol_semco'),
                'enrolstatus' => get_string('tableenrolstatus', 'enrol_semco'),
                'coursecompletionstatus' => get_string('tablecoursecompletionstatus', 'enrol_semco'),
                'coursecompletiondate' => get_string('tablecoursecompletiondate', 'enrol_semco'),
                'coursecompletiongrade' => get_string('tablecoursecompletiongrade', 'enrol_semco'),
                'suspended' => get_string('tableuserstatus', 'enrol_semco'),
        ];

        // Drop the optional columns which the admin has disabled in the plugin settings.
        $enabledoptionalcolumns = self::get_enabled_optionalcolumns();
        foreach (array_keys(enrol_semco_get_report_optionalcolumns()) as $optionalcolumn) {
            if (!in_array($optionalcolumn, $enabledoptionalcolumns)) {
                unset($tablecolumns[$optionalcolumn]);
            }
        }

        // Get the initial sorting column from the plugin settings.
        // The setting's default is used as long as the setting does not hold a supported value, which is the case as long
        // as it has not been stored at all.
        $sortingcolumn = get_config('enrol_semco', 'reportinitialsortingcolumn');
        if (!array_key_exists($sortingcolumn, enrol_semco_get_report_sortingcolumns())) {
            $sortingcolumn = ENROL_SEMCO_REPORT_SORTINGCOLUMN_DEFAULT;
        }

        // Move the columns which are shown at the front of the table to the front.
        // The full name column is pinned to the very front of the report as this is the column which tells the rows apart
        // for the human eye. The column which the report is sorted by follows directly after it.
        // The sorting setting offers the first name and the last name separately, but the report shows them in a single
        // full name column, so picking either of the two names means that the full name column is the sorting column
        // itself and there is no second column to move.
        $sortingtablecolumn = in_array($sortingcolumn, ['lastname', 'firstname']) ? 'fullname' : $sortingcolumn;
        $frontcolumns = ['fullname' => $tablecolumns['fullname']];
        if ($sortingtablecolumn !== 'fullname') {
            $frontcolumns[$sortingtablecolumn] = $tablecolumns[$sortingtablecolumn];
        }

        return array_merge($frontcolumns, array_diff_key($tablecolumns, $frontcolumns));
    }

    /**
     * Get the optional columns which the admin has enabled in the plugin settings.
     *
     * The setting stores the enabled columns as a comma separated list. As long as it has not been stored at all, all
     * optional columns are enabled, which is what the setting's default says.
     *
     * @return array The names of the enabled optional columns.
     */
    public static function get_enabled_optionalcolumns(): array {
        $optionalcolumnsconfig = get_config('enrol_semco', 'reportoptionalcolumns');
        if ($optionalcolumnsconfig === false) {
            return array_keys(enrol_semco_get_report_optionalcolumns());
        }

        return explode(',', $optionalcolumnsconfig);
    }

    /**
     * Get the names of all filters which the report knows, mapped to the URL parameters which carry them.
     *
     * The filterset knows the filters under their plain names, while the report page and its filter form pass them
     * around as URL parameters with a 'filter' prefix. This map is the single place which connects the two.
     *
     * @return array The URL parameter names, keyed by the filter name.
     */
    public static function get_filter_names(): array {
        return [
            'email' => 'filteremail',
            'semcouserid' => 'filtersemcouserid',
            'semcobookingid' => 'filtersemcobookingid',
            'course' => 'filtercourse',
            'enrolstatus' => 'filterenrolstatus',
            'completionstatus' => 'filtercompletionstatus',
        ];
    }

    /**
     * Get the report columns which the filters of the report work on, keyed by the filter name.
     *
     * Most of these columns are optional columns which the admin can switch off in the plugin settings. A filter whose
     * column is switched off is not offered at all, see get_available_filter_names(): Filtering the report by something
     * which it does not show would leave the user with a filtered table and without any clue why the rows were dropped.
     *
     * @return array The column names, keyed by the filter name.
     */
    public static function get_filter_columns(): array {
        return [
            'email' => 'email',
            'semcouserid' => 'semcouserid',
            'semcobookingid' => 'semcobookingid',
            'course' => 'course',
            'enrolstatus' => 'enrolstatus',
            'completionstatus' => 'coursecompletionstatus',
        ];
    }

    /**
     * Get the names of the filters which the report currently offers, mapped to the URL parameters which carry them.
     *
     * These are the filters whose column the report really shows, see get_filter_columns().
     *
     * They are returned in the order in which the report shows their columns, so that the filter menu lists its filters
     * in the same order as the table lists its columns. That order is not fixed: The admin can re-order the columns with
     * the initial sorting column setting, and the filters follow along.
     *
     * @return array The URL parameter names, keyed by the filter name.
     */
    public static function get_available_filter_names(): array {
        $reportcolumns = array_keys(self::get_report_columns());
        $filtercolumns = self::get_filter_columns();

        // Keep the filters whose column the report shows, remembering the position of that column.
        $available = [];
        foreach (self::get_filter_names() as $filtername => $paramname) {
            $columnposition = array_search($filtercolumns[$filtername], $reportcolumns, true);
            if ($columnposition !== false) {
                $available[$filtername] = ['param' => $paramname, 'position' => $columnposition];
            }
        }

        // Bring them into the order of their columns.
        uasort($available, function ($filtera, $filterb) {
            return $filtera['position'] <=> $filterb['position'];
        });

        return array_map(function ($filter) {
            return $filter['param'];
        }, $available);
    }

    /**
     * Get the URL parameters of the filters which are currently set.
     *
     * The result is used as the URL parameters of the report, so that the filters survive the download of the table and
     * a reload of the page. Filters which are not set are left out to keep the URL as short as possible.
     *
     * Only the first value of each filter ends up in the URL. The report's filter form offers a single value per filter,
     * while the filterset itself would be able to carry several ones.
     *
     * @return array The URL parameters of the filters which are set.
     */
    public function get_filter_params(): array {
        $params = [];
        foreach (self::get_available_filter_names() as $filtername => $paramname) {
            $values = $this->get_filter_values($filtername);
            if (!empty($values)) {
                $params[$paramname] = reset($values);
            }
        }

        return $params;
    }

    /**
     * Get the courses which currently hold SEMCO enrolments.
     *
     * These are the only courses which the report can show at all, so the course filter is restricted to them instead of
     * offering the whole course list of the Moodle instance.
     *
     * @return array The course full names, keyed by the course ID and sorted by the course full name.
     */
    public static function get_filterable_courses(): array {
        global $DB;

        // Get the courses which hold SEMCO enrolments.
        $courses = $DB->get_records_sql('SELECT DISTINCT c.id, c.fullname
                FROM {enrol} e
                JOIN {course} c ON c.id = e.courseid
                WHERE e.enrol = :enrol
                ORDER BY c.fullname ASC', ['enrol' => 'semco']);

        // Compose the list of course names.
        $courselist = [];
        foreach ($courses as $course) {
            $courselist[$course->id] = format_string(
                $course->fullname,
                true,
                ['context' => \core\context\course::instance($course->id)]
            );
        }

        return $courselist;
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
        // picked in one of the initials bars above or a filter is applied in the report's filter menu, the table may
        // just as well be empty because no enrolment matches what was picked. Telling the user that there aren't any
        // SEMCO enrolments yet would be plainly wrong then.
        // The initials getters return null if the initials bars are not used at all and an empty string if they are used
        // but no initial is picked, so both cases have to be covered here.
        $initialfirst = $this->get_initial_first();
        $initiallast = $this->get_initial_last();
        $filterparams = $this->get_filter_params();
        if (!empty($initialfirst) || !empty($initiallast) || !empty($filterparams)) {
            $emptymessage = get_string('emptytablefiltered', 'enrol_semco');
        } else {
            $emptymessage = get_string('emptytable', 'enrol_semco');
        }
        echo $OUTPUT->notification($emptymessage, 'info');

        // Render the dynamic table footer.
        echo $this->get_dynamic_table_html_end();
    }
}
