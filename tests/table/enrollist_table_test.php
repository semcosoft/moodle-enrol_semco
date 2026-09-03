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

namespace enrol_semco\table;

use enrol_semco\external;

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
 * @covers \enrol_semco\table\enrollist_table
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
     * @covers \enrol_semco\table\enrollist_table::other_cols
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
     * @covers \enrol_semco\table\enrollist_table::format_coursegrade
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
     * @covers \enrol_semco\table\enrollist_table::__construct
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
     * Create a fixture of SEMCO enrolments which differ in every aspect which the report can be filtered by.
     *
     * The enrolments are told apart by their SEMCO booking ID, which is what the filter tests below assert on.
     *
     * @return \stdClass The courses of the fixture.
     */
    private function create_filter_fixture(): \stdClass {
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

        // BOOK-0001: completed, active, in the completion course.
        $completeduser = $this->getDataGenerator()->create_user(['email' => 'anna@example.com']);
        $this->semcogenerator->create_enrolment([
            'userid' => $completeduser->id,
            'courseid' => $completioncourse->id,
            'semcobookingid' => 'BOOK-0001',
            'semcouserid' => 'SEMCO-1001',
        ]);
        $this->semcogenerator->create_completion([
            'userid' => $completeduser->id,
            'courseid' => $completioncourse->id,
        ]);

        // BOOK-0002: not completed, suspended, in the completion course.
        $suspendeduser = $this->getDataGenerator()->create_user(['email' => 'BERND@example.com']);
        $this->semcogenerator->create_enrolment([
            'userid' => $suspendeduser->id,
            'courseid' => $completioncourse->id,
            'semcobookingid' => 'BOOK-0002',
            'semcouserid' => 'SEMCO-1002',
            'suspend' => true,
        ]);

        // BOOK-0003: course completion not enabled, active, in the other course.
        $othercourseuser = $this->getDataGenerator()->create_user(['email' => 'clara@elsewhere.org']);
        $this->semcogenerator->create_enrolment([
            'userid' => $othercourseuser->id,
            'courseid' => $nocompletioncourse->id,
            'semcobookingid' => 'BOOK-0003',
            'semcouserid' => 'OTHER-2003',
        ]);

        return (object) ['completioncourse' => $completioncourse, 'nocompletioncourse' => $nocompletioncourse];
    }

    /**
     * Data provider for test_filters_narrow_the_report.
     *
     * The course filter is not part of it as its value is a course ID which is only known once the fixture has been
     * created, see test_the_course_filter_narrows_the_report().
     *
     * @return array
     */
    public static function filter_provider(): array {
        return [
            'Email address, full value' => ['email', 'anna@example.com', ['BOOK-0001']],
            'Email address, substring' => ['email', 'example.com', ['BOOK-0001', 'BOOK-0002']],
            'Email address, other case' => ['email', 'bernd@EXAMPLE.com', ['BOOK-0002']],
            'Email address, no match' => ['email', 'nobody@example.com', []],
            'SEMCO user ID, full value' => ['semcouserid', 'SEMCO-1001', ['BOOK-0001']],
            'SEMCO user ID, substring' => ['semcouserid', 'SEMCO-100', ['BOOK-0001', 'BOOK-0002']],
            'SEMCO booking ID, full value' => ['semcobookingid', 'BOOK-0002', ['BOOK-0002']],
            'SEMCO booking ID, substring' => ['semcobookingid', 'BOOK-000', ['BOOK-0001', 'BOOK-0002', 'BOOK-0003']],
            'Enrolment status, active' => ['enrolstatus', 'active', ['BOOK-0001', 'BOOK-0003']],
            'Enrolment status, suspended' => ['enrolstatus', 'suspended', ['BOOK-0002']],
            'Completion status, completed' => ['completionstatus', 'completed', ['BOOK-0001']],
            'Completion status, not completed' => ['completionstatus', 'notcompleted', ['BOOK-0002']],
            'Completion status, not enabled' => ['completionstatus', 'notenabled', ['BOOK-0003']],
        ];
    }

    /**
     * Test that each filter of the report narrows the shown enrolments as expected.
     *
     * @param string $filtername The name of the filter.
     * @param string $filtervalue The value to filter by.
     * @param array $expectedbookingids The SEMCO booking IDs which the report is expected to show.
     * @dataProvider filter_provider
     * @covers \enrol_semco\table\enrollist_table::set_table_sql
     */
    public function test_filters_narrow_the_report(string $filtername, string $filtervalue, array $expectedbookingids): void {
        $this->create_filter_fixture();

        // Without any filter, the report shows all enrolments of the fixture.
        $this->assertEqualsCanonicalizing(['BOOK-0001', 'BOOK-0002', 'BOOK-0003'], $this->get_report_bookingids());

        // With the filter, it shows the expected ones.
        $this->assertEqualsCanonicalizing($expectedbookingids, $this->get_report_bookingids([$filtername => $filtervalue]));
    }

    /**
     * Test that the course filter narrows the shown enrolments to the picked course.
     *
     * @covers \enrol_semco\table\enrollist_table::set_table_sql
     */
    public function test_the_course_filter_narrows_the_report(): void {
        $courses = $this->create_filter_fixture();

        $this->assertEqualsCanonicalizing(
            ['BOOK-0001', 'BOOK-0002'],
            $this->get_report_bookingids(['course' => (int) $courses->completioncourse->id])
        );
        $this->assertEqualsCanonicalizing(
            ['BOOK-0003'],
            $this->get_report_bookingids(['course' => (int) $courses->nocompletioncourse->id])
        );
    }

    /**
     * Test that several filters are combined, i.e. that only the enrolments which match all of them are shown.
     *
     * @covers \enrol_semco\table\enrollist_table::set_table_sql
     */
    public function test_filters_are_combined(): void {
        $courses = $this->create_filter_fixture();

        // Each filter on its own keeps two enrolments, together they keep the one which matches both.
        $this->assertEqualsCanonicalizing(
            ['BOOK-0001', 'BOOK-0002'],
            $this->get_report_bookingids(['course' => (int) $courses->completioncourse->id])
        );
        $this->assertEqualsCanonicalizing(
            ['BOOK-0001', 'BOOK-0003'],
            $this->get_report_bookingids(['enrolstatus' => 'active'])
        );
        $this->assertEqualsCanonicalizing(
            ['BOOK-0001'],
            $this->get_report_bookingids(['course' => (int) $courses->completioncourse->id, 'enrolstatus' => 'active'])
        );

        // A combination which no enrolment matches leaves the report empty.
        $this->assertEmpty($this->get_report_bookingids([
            'course' => (int) $courses->nocompletioncourse->id,
            'enrolstatus' => 'suspended',
        ]));
    }

    /**
     * Test that the two report columns which show a particularly sensitive piece of personal data are switched off out
     * of the box.
     *
     * The enrolment report is a site wide report which can be downloaded as a file as well, so the user's birthday and
     * the user's place of birth are not spread through it unless the admin really wants them there.
     *
     * @covers \enrol_semco\table\enrollist_table::get_enabled_optionalcolumns
     */
    public function test_the_sensitive_report_columns_are_disabled_by_default(): void {
        // Drop the stored setting, so that the default is what decides. The setting is stored while the site is being
        // installed, which is why looking at a test site without dropping it would not look at the default at all.
        unset_config('reportoptionalcolumns', 'enrol_semco');

        // The two sensitive columns are the only optional columns which are not enabled.
        $sensitivecolumns = ['semcouserbirthday', 'semcouserplaceofbirth'];
        $this->assertEqualsCanonicalizing(
            array_diff(array_keys(enrol_semco_get_report_optionalcolumns()), $sensitivecolumns),
            enrollist_table::get_enabled_optionalcolumns()
        );

        // And the report really leaves them out while it keeps the other user profile field columns.
        $reportcolumns = enrollist_table::get_report_columns();
        foreach ($sensitivecolumns as $sensitivecolumn) {
            $this->assertArrayNotHasKey($sensitivecolumn, $reportcolumns);
        }
        $this->assertArrayHasKey('semcouserid', $reportcolumns);
        $this->assertArrayHasKey('semcousercompany', $reportcolumns);
        $this->assertArrayHasKey('semcotenantshortname', $reportcolumns);
    }

    /**
     * Test that a filter is not offered anymore as soon as the admin has switched off the column which it filters.
     *
     * @covers \enrol_semco\table\enrollist_table::get_available_filter_names
     */
    public function test_available_filters_follow_the_optional_columns_setting(): void {
        // As long as the setting has not been stored, all filters are offered.
        $this->assertSame(
            array_keys(enrollist_table::get_filter_names()),
            array_keys(enrollist_table::get_available_filter_names())
        );

        // Enabling just one of the optional columns which a filter works on leaves the filters of the other optional
        // columns out. The SEMCO booking ID and the email address filters stay as their columns are not optional.
        set_config('reportoptionalcolumns', 'course', 'enrol_semco');
        $this->assertEqualsCanonicalizing(
            ['email', 'semcouserid', 'semcobookingid', 'course'],
            array_keys(enrollist_table::get_available_filter_names())
        );

        // Disabling all optional columns leaves the filters of the non optional columns only.
        set_config('reportoptionalcolumns', '', 'enrol_semco');
        $this->assertEqualsCanonicalizing(
            ['email', 'semcouserid', 'semcobookingid'],
            array_keys(enrollist_table::get_available_filter_names())
        );
    }

    /**
     * Test that a filter of a switched off column does not narrow the report either.
     *
     * The filter menu does not offer such a filter anymore, but its parameter could still be left over in a bookmarked
     * report URL.
     *
     * @covers \enrol_semco\table\enrollist_table::set_table_sql
     */
    public function test_a_filter_of_a_disabled_column_does_not_narrow_the_report(): void {
        $this->create_filter_fixture();

        // With the enrolment status column enabled, the filter narrows the report.
        $this->assertEqualsCanonicalizing(['BOOK-0002'], $this->get_report_bookingids(['enrolstatus' => 'suspended']));

        // With the column switched off, the very same filter is ignored.
        set_config('reportoptionalcolumns', '', 'enrol_semco');
        $this->assertEqualsCanonicalizing(
            ['BOOK-0001', 'BOOK-0002', 'BOOK-0003'],
            $this->get_report_bookingids(['enrolstatus' => 'suspended'])
        );
    }

    /**
     * Data provider for test_an_unknown_filter_value_is_ignored.
     *
     * @return array
     */
    public static function unknown_filter_value_provider(): array {
        return [
            'Enrolment status' => ['filtername' => 'enrolstatus'],
            'Completion status' => ['filtername' => 'completionstatus'],
        ];
    }

    /**
     * Test that a filter value which the filter does not know at all is ignored instead of breaking the report.
     *
     * The filter menu only offers the values which the matching filter knows, but an arbitrary value can still reach the
     * table through a bookmarked report URL or through the webservice which delivers the table content. Such a value has
     * to leave the report untouched, just as an unknown filter does.
     *
     * @param string $filtername The name of the filter.
     * @dataProvider unknown_filter_value_provider
     * @covers \enrol_semco\table\enrollist_table::get_filter_values
     */
    public function test_an_unknown_filter_value_is_ignored(string $filtername): void {
        $this->create_filter_fixture();

        // The report still shows all enrolments of the fixture.
        $this->assertEqualsCanonicalizing(
            ['BOOK-0001', 'BOOK-0002', 'BOOK-0003'],
            $this->get_report_bookingids([$filtername => 'thisisnotastatus'])
        );

        // And the value does not end up in the report URL either, as it does not filter anything.
        $table = $this->build_report_table([$filtername => 'thisisnotastatus']);
        $this->assertSame([], $table->get_filter_params());
    }

    /**
     * Test that the course name and the SEMCO user profile fields are put through Moodle's string formatting.
     *
     * These are the only report columns which show a value that someone has typed in: The course name is typed by a
     * teacher and the profile fields are filled by SEMCO. Everything which such a value carries beyond plain text has
     * to be resolved by format_string() before the report shows it, otherwise the report would render it as markup.
     *
     * The multilang filter is what makes this visible in a test: A value which holds several language variants has to
     * end up in the report with the variant of the reader's language only.
     *
     * @covers \enrol_semco\table\enrollist_table::col_course
     * @covers \enrol_semco\table\enrollist_table::other_cols
     */
    public function test_the_course_name_and_the_user_profile_fields_are_formatted(): void {
        global $CFG, $DB;

        // Enable all optional report columns, including the two which are switched off by default, as the test checks
        // every SEMCO user profile field column.
        set_config('reportoptionalcolumns', implode(',', array_keys(enrol_semco_get_report_optionalcolumns())),
                'enrol_semco');

        // Switch the multilang filter on site wide and let it filter strings as well, which is what the report's values
        // are formatted as.
        $CFG->filterall = true;
        $CFG->stringfilters = 'multilang';
        filter_set_global_state('multilang', TEXTFILTER_ON);

        // Compose a value which holds an English and a German variant. The tests run in English, so only the English
        // variant may end up in the report.
        $multilang = '<span lang="en" class="multilang">English</span>' .
                '<span lang="de" class="multilang">Deutsch</span>';

        // Create an enrolment whose course name carries the multilang markup.
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course ' . $multilang]);
        $user = $this->getDataGenerator()->create_user();
        $this->semcogenerator->create_enrolment([
            'userid' => $user->id,
            'courseid' => $course->id,
            'semcobookingid' => 'BOOK-0001',
        ]);

        // Fill each of the SEMCO user profile fields with the multilang markup as well. The value is prefixed with the
        // column name, so that a value which ends up in the wrong column would be noticed.
        // The values are written straight into the user_info_data table and not through profile_save_data(). Two of the
        // five fields are only 16 characters long, and profile_save_data() truncates a value to the field's maximum
        // length, which would cut the multilang markup in the middle. Writing the table directly is what the report
        // reads anyway, as its SQL query picks the field values from there.
        $userfieldcolumns = enrol_semco_get_report_userfieldcolumns();
        foreach ($userfieldcolumns as $userfieldcolumn => $userfieldshortname) {
            $DB->insert_record('user_info_data', [
                'userid' => $user->id,
                'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => $userfieldshortname], MUST_EXIST),
                'data' => $userfieldcolumn . ' ' . $multilang,
                'dataformat' => FORMAT_MOODLE,
            ]);
        }

        // Render the report row.
        $formattedrow = $this->get_report_row();

        // The course column shows the English variant of the course name.
        $this->assertStringContainsString('Course English', $formattedrow['course']);

        // And so does each of the SEMCO user profile field columns.
        foreach (array_keys($userfieldcolumns) as $userfieldcolumn) {
            $this->assertSame($userfieldcolumn . ' English', $formattedrow[$userfieldcolumn]);
        }

        // None of these columns leaks the other language variant or the multilang markup itself.
        foreach (array_merge(['course'], array_keys($userfieldcolumns)) as $column) {
            $this->assertStringNotContainsString('Deutsch', $formattedrow[$column]);
            $this->assertStringNotContainsString('multilang', $formattedrow[$column]);
        }
    }

    /**
     * Test that a SEMCO user profile field which the enrolled user does not have a value in shows the placeholder.
     *
     * The report fills such a cell with the same placeholder as the course completion columns use. An empty cell would
     * leave the reader wondering whether the value is missing or whether the report failed to show it.
     *
     * @covers \enrol_semco\table\enrollist_table::other_cols
     */
    public function test_an_empty_user_profile_field_shows_the_placeholder(): void {
        // Enable all optional report columns, including the two which are switched off by default, as the test checks
        // every SEMCO user profile field column.
        set_config('reportoptionalcolumns', implode(',', array_keys(enrol_semco_get_report_optionalcolumns())),
                'enrol_semco');

        $course = $this->getDataGenerator()->create_course();

        // BOOK-0001: A user who does not have a value in any of the SEMCO user profile fields. The enrolment is
        // deliberately created without a SEMCO user ID, which is the only one of the five fields which the plugin's
        // data generator fills.
        $this->semcogenerator->create_enrolment([
            'userid' => $this->getDataGenerator()->create_user()->id,
            'courseid' => $course->id,
            'semcobookingid' => 'BOOK-0001',
        ]);

        // BOOK-0002: A user who has a SEMCO user ID, but no value in the four remaining fields.
        $this->semcogenerator->create_enrolment([
            'userid' => $this->getDataGenerator()->create_user()->id,
            'courseid' => $course->id,
            'semcobookingid' => 'BOOK-0002',
            'semcouserid' => 'SEMCO-4711',
        ]);

        // Pick the two rows by their booking ID, as the order in which the report shows them is not what is tested here.
        $rows = [];
        foreach ($this->get_report_rows() as $formattedrow) {
            $rows[$formattedrow['semcobookingid']] = $formattedrow;
        }
        $this->assertCount(2, $rows);

        // The user without any value shows the placeholder in each of the five columns.
        foreach (array_keys(enrol_semco_get_report_userfieldcolumns()) as $userfieldcolumn) {
            $this->assertSame(enrollist_table::EMPTYCELL, $rows['BOOK-0001'][$userfieldcolumn]);
        }

        // The other user shows the value which the filled field holds and the placeholder in the empty ones.
        $this->assertSame('SEMCO-4711', $rows['BOOK-0002']['semcouserid']);
        $this->assertSame(enrollist_table::EMPTYCELL, $rows['BOOK-0002']['semcousercompany']);
        $this->assertSame(enrollist_table::EMPTYCELL, $rows['BOOK-0002']['semcotenantshortname']);
    }

    /**
     * Test that the applied filters end up in the report URL, so that they survive a download of the table.
     *
     * @covers \enrol_semco\table\enrollist_table::get_filter_params
     */
    public function test_the_applied_filters_are_part_of_the_report_url(): void {
        $courses = $this->create_filter_fixture();

        // Without any filter, the report URL does not carry any filter parameter.
        $table = $this->build_report_table();
        $this->assertSame([], $table->get_filter_params());
        $this->assertStringNotContainsString('filter', $table->baseurl->out(false));

        // With filters, the URL carries them under their parameter names.
        $table = $this->build_report_table([
            'course' => (int) $courses->completioncourse->id,
            'enrolstatus' => 'suspended',
        ]);
        $this->assertSame(
            ['filtercourse' => (int) $courses->completioncourse->id, 'filterenrolstatus' => 'suspended'],
            $table->get_filter_params()
        );
        $this->assertStringContainsString('filtercourse=' . $courses->completioncourse->id, $table->baseurl->out(false));
        $this->assertStringContainsString('filterenrolstatus=suspended', $table->baseurl->out(false));
    }

    /**
     * Build the enrolment report table with the given filters, the same way as enrolreport.php does it.
     *
     * @param array $filters The filter values, keyed by the filter name.
     * @return enrollist_table The table.
     */
    private function build_report_table(array $filters = []): enrollist_table {
        $table = new enrollist_table('enrol_semco_enrolreport_test', '');

        // Hand the filters over as a filterset, which is the way a dynamic table takes them.
        $filterset = new enrollist_table_filterset();
        foreach ($filters as $filtername => $filtervalue) {
            $filterset->add_filter_from_params($filtername, null, [$filtervalue]);
        }
        $table->set_filterset($filterset);

        return $table;
    }

    /**
     * Build the enrolment report table and pick the single formatted row which it shows.
     *
     * The test fails if the report shows anything else than exactly one row, as the caller expects a fixture which
     * yields a single enrolment.
     *
     * @return array The formatted row, keyed by the column name.
     */
    private function get_report_row(): array {
        $formattedrows = $this->get_report_rows();

        $this->assertCount(1, $formattedrows);

        return $formattedrows[0];
    }

    /**
     * Build the enrolment report table and pick all formatted rows which it shows.
     *
     * The rows are formatted the very same way as enrolreport.php formats them, so that the returned cells really are
     * the ones which an administrator sees in the report.
     *
     * @return array The formatted rows, each of them keyed by the column name.
     */
    private function get_report_rows(): array {
        $table = new enrollist_table('enrol_semco_enrolreport_test', '');
        $table->setup();
        $table->query_db(100, false);

        $formattedrows = [];
        foreach ($table->rawdata as $row) {
            $formattedrows[] = $table->format_row($row);
        }

        return $formattedrows;
    }

    /**
     * Build the enrolment report table with the given filters and pick the SEMCO booking IDs which it shows.
     *
     * @param array $filters The filter values, keyed by the filter name.
     * @return array The shown SEMCO booking IDs.
     */
    private function get_report_bookingids(array $filters = []): array {
        $table = $this->build_report_table($filters);
        $table->setup();
        $table->query_db(100, false);

        $bookingids = [];
        foreach ($table->rawdata as $row) {
            $bookingids[] = $row->semcobookingid;
        }

        return $bookingids;
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
