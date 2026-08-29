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

namespace enrol_semco;

/**
 * Enrolment method "SEMCO" - PHPUnit tests for the enrolment report table.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The enrollist_table_test class.
 *
 * @covers \enrol_semco\enrollist_table
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrollist_table_test extends \advanced_testcase {
    /**
     * @var \stdClass The SEMCO webservice user.
     */
    private \stdClass $semcouser;

    /**
     * @var \enrol_semco_generator The plugin's data generator.
     */
    private \enrol_semco_generator $semcogenerator;

    /**
     * Setup testcase.
     */
    public function setUp(): void {
        global $CFG, $DB;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Require completion library as the tests work with the course completion constants and API.
        require_once($CFG->libdir . '/completionlib.php');

        // Require grade library as the tests work with the course grades.
        require_once($CFG->libdir . '/gradelib.php');

        // Call the parent setup.
        parent::setUp();

        // Reset after the test.
        $this->resetAfterTest(true);

        // Get the plugin's data generator.
        $this->semcogenerator = $this->getDataGenerator()->get_plugin_generator('enrol_semco');

        // Get the SEMCO webservice user which was created during the plugin installation.
        $this->semcouser = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME], '*', MUST_EXIST);

        // Run the tests as SEMCO webservice user.
        $this->setUser($this->semcouser);
    }

    /**
     * Data provider for test_coursecompletion_columns_match_webservice.
     *
     * @return array
     */
    public static function coursecompletion_columns_provider(): array {
        return [
            'Course completion enabled site wide' => [
                'sitecompletion' => true,
            ],
            'Course completion disabled site wide' => [
                'sitecompletion' => false,
            ],
        ];
    }

    /**
     * Test that the course completion status column of the enrolment report tells exactly the same as the
     * enrol_semco_get_course_completions webservice.
     *
     * The report deduces the course completion status within its SQL query while the webservice deduces it in PHP.
     * Both have to agree in any case, as SEMCO and the Moodle administrator would look at contradicting information
     * otherwise. This test therefore does not hardcode any expectation about the shown status but compares the report
     * against the webservice, which pins the two implementations to each other.
     *
     * @param bool $sitecompletion Whether course completion is enabled site wide.
     * @dataProvider coursecompletion_columns_provider
     * @covers \enrol_semco\enrollist_table::other_cols
     */
    public function test_coursecompletion_columns_match_webservice(bool $sitecompletion): void {
        global $CFG;

        // Create a course which has course completion enabled and one which has it disabled.
        $completioncourse = $this->getDataGenerator()->create_course([
            'fullname' => 'Completion course',
            'shortname' => 'cc1',
            'enablecompletion' => COMPLETION_ENABLED,
        ]);
        $nocompletioncourse = $this->getDataGenerator()->create_course([
            'fullname' => 'No completion course',
            'shortname' => 'nc1',
            'enablecompletion' => COMPLETION_DISABLED,
        ]);

        // Create a user for each of the three states which the course completion status can have.
        $completeduser = $this->getDataGenerator()->create_user();
        $notcompleteduser = $this->getDataGenerator()->create_user();
        $nocompletionuser = $this->getDataGenerator()->create_user();

        // Enrol the users the SEMCO way.
        $enrolids = [];
        $enrolids[] = $this->semcogenerator->create_enrolment([
            'userid' => $completeduser->id,
            'courseid' => $completioncourse->id,
            'semcobookingid' => 'BOOK-0001',
        ])['enrolid'];
        $enrolids[] = $this->semcogenerator->create_enrolment([
            'userid' => $notcompleteduser->id,
            'courseid' => $completioncourse->id,
            'semcobookingid' => 'BOOK-0002',
        ])['enrolid'];
        $enrolids[] = $this->semcogenerator->create_enrolment([
            'userid' => $nocompletionuser->id,
            'courseid' => $nocompletioncourse->id,
            'semcobookingid' => 'BOOK-0003',
        ])['enrolid'];

        // Let one of the users complete the course which has course completion enabled.
        // The other user in that course deliberately does not get any course completion record at all, which is the state
        // of an enrolment whose course completions have not been processed by cron yet.
        $this->semcogenerator->create_completion([
            'userid' => $completeduser->id,
            'courseid' => $completioncourse->id,
        ]);

        // Give the user who has completed the course a grade in that course, so that the completion grade column has
        // something to show.
        $this->grade_user($completioncourse, $completeduser, 82);

        // Set the site wide course completion setting.
        // This is done after the test data has been created, so that the setting is the only thing which differs between
        // the two runs of this test. If course completion is switched off site wide, no course can be completed at all,
        // no matter what the courses themselves are configured to.
        $CFG->enablecompletion = ($sitecompletion == true) ? 1 : 0;

        // Get the course completions from the webservice.
        $wscompletions = external::get_course_completions($enrolids);
        $this->assertCount(count($enrolids), $wscompletions);

        // Turn the webservice return into the cells which the report is expected to show for each enrolment.
        $expectedcells = [];
        foreach ($wscompletions as $wscompletion) {
            // The status column reflects the 'canbecompleted' and 'completed' fields.
            if ($wscompletion['canbecompleted'] == false) {
                $status = get_string('completionnotenabled', 'completion');
            } else if ($wscompletion['completed'] == true) {
                $status = get_string('completed', 'completion');
            } else {
                $status = get_string('notcompleted', 'completion');
            }

            // The date column reflects the 'timecompleted' field, the grade column the (already formatted) 'finalgrade'
            // field. Both columns show a placeholder as long as the course is not completed.
            $iscompleted = ($wscompletion['canbecompleted'] == true && $wscompletion['completed'] == true);
            $date = $iscompleted
                    ? userdate($wscompletion['timecompleted'], get_string('strftimedatetime'))
                    : enrollist_table::EMPTYCELL;
            $grade = ($iscompleted == true && $wscompletion['finalgrade'] !== null)
                    ? $wscompletion['finalgrade']
                    : enrollist_table::EMPTYCELL;

            $expectedcells[$wscompletion['enrolid']] = [
                'status' => $status,
                'date' => $date,
                'grade' => $grade,
            ];
        }

        // Get the cells which the report shows for each enrolment.
        $reportcells = $this->get_report_completion_cells();

        // The report has to show exactly the same as the webservice reports, for every single enrolment.
        ksort($expectedcells);
        ksort($reportcells);
        $this->assertEquals($expectedcells, $reportcells);

        // Additionally, make sure that the test data really covered all three states (and did not just compare two
        // identical sets of cells which happen to hold the same status everywhere by accident).
        $shownstatuses = array_column($reportcells, 'status');
        $expectedstatescount = ($sitecompletion == true) ? 3 : 1;
        $this->assertCount($expectedstatescount, array_unique($shownstatuses));

        // If the course could be completed at all, the completed enrolment has to show a real date and grade and not
        // just the placeholder. This makes sure that the comparison above did not compare two sets of placeholders.
        if ($sitecompletion == true) {
            $completedcells = $reportcells[$enrolids[0]];
            $this->assertNotEquals(enrollist_table::EMPTYCELL, $completedcells['date']);
            $this->assertNotEquals(enrollist_table::EMPTYCELL, $completedcells['grade']);
        }
    }

    /**
     * Give a user a grade in the course grade item of the given course.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user The user.
     * @param float $grade The grade to be given.
     * @return void
     */
    private function grade_user(\stdClass $course, \stdClass $user, float $grade): void {
        // Create an activity in the course. Without any activity, the course grade item would not have anything to
        // aggregate and the grade which is set below would not survive a regrade of the course.
        $assigngenerator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $assigngenerator->create_instance(['course' => $course]);

        // Configure the course grade item.
        $gradeitem = \grade_item::fetch_course_item($course->id);
        $gradeitem->grademin = 0;
        $gradeitem->grademax = 100;
        $gradeitem->gradepass = 30;
        $gradeitem->update();

        // Set the user's grade.
        $gradeitem->update_final_grade($user->id, $grade);
    }

    /**
     * Data provider for test_coursecompletiongrade_column_matches_webservice.
     *
     * The display types are given as identifiers and not as the GRADE_DISPLAY_TYPE_* constants themselves. PHPUnit
     * evaluates the data providers when it builds the test suite, i.e. before setUp() had the chance to require the
     * grade library which defines these constants.
     *
     * The expected pattern is not there to pin the shown grade to a particular text. It only makes sure that the
     * configured display type really reached the formatter, so that a run cannot pass by both sides ignoring the
     * setting in the same way.
     *
     * @return array
     */
    public static function coursecompletiongrade_displaytype_provider(): array {
        return [
            'Real (points)' => [
                'displaytype' => 'real',
                'expectedpattern' => '/^\d+\.\d+$/',
            ],
            'Percentage' => [
                'displaytype' => 'percentage',
                'expectedpattern' => '/^\d+\.\d+ %$/',
            ],
            'Letter' => [
                'displaytype' => 'letter',
                'expectedpattern' => '/^[A-F][+-]?$/',
            ],
            'Real and percentage' => [
                'displaytype' => 'realpercentage',
                'expectedpattern' => '/^\d+\.\d+ \(\d+\.\d+ %\)$/',
            ],
            'Letter and real' => [
                'displaytype' => 'letterreal',
                'expectedpattern' => '/^[A-F][+-]? \(\d+\.\d+\)$/',
            ],
        ];
    }

    /**
     * Test that the course completion grade column of the enrolment report shows exactly the same grade as the
     * enrol_semco_get_course_completions webservice, for every grade display type.
     *
     * The grade is not stored in a readable form anywhere: The gradebook holds a raw number which has to be formatted
     * according to the course's grade display type, i.e. as points, as a percentage, as a letter or as a combination of
     * them. The report and the webservice format the grade in two different places, so this test makes sure that they
     * really arrive at the same text and that neither of them silently falls back to a different display type.
     *
     * @param string $displaytype The identifier of the grade display type to be configured in the course.
     * @param string $expectedpattern A pattern which the shown grade has to match for this display type.
     * @dataProvider coursecompletiongrade_displaytype_provider
     * @covers \enrol_semco\enrollist_table::format_coursegrade
     */
    public function test_coursecompletiongrade_column_matches_webservice(string $displaytype, string $expectedpattern): void {
        // Create a course which has course completion enabled.
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Completion course',
            'shortname' => 'cc1',
            'enablecompletion' => COMPLETION_ENABLED,
        ]);

        // Enrol a user the SEMCO way, let him complete the course and give him a grade.
        $user = $this->getDataGenerator()->create_user();
        $enrolid = $this->semcogenerator->create_enrolment([
            'userid' => $user->id,
            'courseid' => $course->id,
            'semcobookingid' => 'BOOK-0001',
        ])['enrolid'];
        $this->semcogenerator->create_completion([
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);
        $this->grade_user($course, $user, 82);

        // Configure the grade display type for the whole course.
        grade_set_setting($course->id, 'displaytype', $this->resolve_displaytype($displaytype));

        // Get the grade which the webservice reports.
        $wscompletions = external::get_course_completions([$enrolid]);
        $wsgrade = $wscompletions[0]['finalgrade'];

        // Get the grade which the report shows.
        $reportcells = $this->get_report_completion_cells();
        $reportgrade = $reportcells[$enrolid]['grade'];

        // Both have to show the very same grade.
        $this->assertSame($wsgrade, $reportgrade);

        // And that grade has to be formatted according to the configured display type. Without this assertion, the
        // comparison above would also pass if both sides showed the placeholder or an empty string.
        $this->assertMatchesRegularExpression($expectedpattern, $reportgrade);
    }

    /**
     * Resolve a grade display type identifier into the matching GRADE_DISPLAY_TYPE_* constant.
     *
     * @param string $identifier The display type identifier.
     * @return int The display type constant.
     */
    private function resolve_displaytype(string $identifier): int {
        $displaytypes = [
            'real' => GRADE_DISPLAY_TYPE_REAL,
            'percentage' => GRADE_DISPLAY_TYPE_PERCENTAGE,
            'letter' => GRADE_DISPLAY_TYPE_LETTER,
            'realpercentage' => GRADE_DISPLAY_TYPE_REAL_PERCENTAGE,
            'letterreal' => GRADE_DISPLAY_TYPE_LETTER_REAL,
        ];

        // Throw an exception if the given identifier is unknown.
        if (!array_key_exists($identifier, $displaytypes)) {
            throw new \coding_exception('The grade display type \'' . $identifier . '\' is unknown, it has to be one of: ' .
                    implode(', ', array_keys($displaytypes)));
        }

        return $displaytypes[$identifier];
    }

    /**
     * Test that the course completion status column can be sorted by the status itself.
     *
     * The status has to be deduced within the SQL query and not only when the cell is rendered. Only this way, sorting
     * the report by this column groups the enrolments by their status. Sorting by the raw course completion time would
     * not be able to tell the two states apart which do not have a completion time.
     *
     * @covers \enrol_semco\enrollist_table::__construct
     */
    public function test_coursecompletionstatus_column_is_sortable_by_status(): void {
        // Create a course which has course completion enabled and one which has it disabled.
        $completioncourse = $this->getDataGenerator()->create_course([
            'fullname' => 'Completion course',
            'shortname' => 'cc1',
            'enablecompletion' => COMPLETION_ENABLED,
        ]);
        $nocompletioncourse = $this->getDataGenerator()->create_course([
            'fullname' => 'No completion course',
            'shortname' => 'nc1',
            'enablecompletion' => COMPLETION_DISABLED,
        ]);

        // Create a user for each of the three states which the course completion status can have.
        $completeduser = $this->getDataGenerator()->create_user();
        $notcompleteduser = $this->getDataGenerator()->create_user();
        $nocompletionuser = $this->getDataGenerator()->create_user();

        // Enrol the users the SEMCO way. They are deliberately enrolled in an order which does not match the order of
        // their course completion status, so that a passing test really proves that the sorting did something.
        $enrolids = [];
        $enrolids['notcompleted'] = $this->semcogenerator->create_enrolment([
            'userid' => $notcompleteduser->id,
            'courseid' => $completioncourse->id,
            'semcobookingid' => 'BOOK-0002',
        ])['enrolid'];
        $enrolids['completed'] = $this->semcogenerator->create_enrolment([
            'userid' => $completeduser->id,
            'courseid' => $completioncourse->id,
            'semcobookingid' => 'BOOK-0001',
        ])['enrolid'];
        $enrolids['notenabled'] = $this->semcogenerator->create_enrolment([
            'userid' => $nocompletionuser->id,
            'courseid' => $nocompletioncourse->id,
            'semcobookingid' => 'BOOK-0003',
        ])['enrolid'];

        // Let one of the users complete the course which has course completion enabled.
        $this->semcogenerator->create_completion([
            'userid' => $completeduser->id,
            'courseid' => $completioncourse->id,
        ]);

        // Get the enrolment IDs in the order in which the report shows them when it is sorted by the course completion
        // status. The enrolments have to be grouped by their status, from 'not enabled' over 'not completed' to
        // 'completed'.
        $sortedenrolids = array_keys($this->get_report_completion_cells('coursecompletionstatus', SORT_ASC));
        $this->assertEquals(
            [$enrolids['notenabled'], $enrolids['notcompleted'], $enrolids['completed']],
            $sortedenrolids
        );
    }

    /**
     * Build the enrolment report table and pick the course completion cells which it shows for each enrolment.
     *
     * The table is built and queried the very same way as enrolreport.php does it, so that the returned cells really
     * are the ones which an administrator sees in the report.
     *
     * @param string|null $sortby The column to sort the report by, or null to keep the report's default order.
     * @param int $sortorder The sort order to apply to the given column.
     * @return array The shown course completion status, date and grade of each enrolment, keyed by the enrolment ID.
     */
    private function get_report_completion_cells(?string $sortby = null, int $sortorder = SORT_ASC): array {
        // Build the report table.
        $table = new enrollist_table('enrol_semco_enrolreport_test', '');

        // Apply the given sort order, if any.
        // This has to happen before the table is set up as the sort order is evaluated during the setup.
        if ($sortby !== null) {
            $table->set_sortdata([['sortby' => $sortby, 'sortorder' => $sortorder]]);
        }

        // Set the table up and run the report's query.
        $table->setup();
        $table->query_db(100, false);

        // Pick the rendered course completion cells of each row.
        $cells = [];
        foreach ($table->rawdata as $row) {
            $formattedrow = $table->format_row($row);
            $cells[$row->enrolid] = [
                'status' => $formattedrow['coursecompletionstatus'],
                'date' => $formattedrow['coursecompletiondate'],
                'grade' => $formattedrow['coursecompletiongrade'],
            ];
        }

        return $cells;
    }
}
