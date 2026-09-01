@enrol @enrol_semco
Feature: SEMCO enrolment report
  In order to monitor which users have been enrolled by SEMCO
  As a manager or administrator
  I need to be able to view the SEMCO enrolment report

  Background:
    # The three students are the users which the scenarios below enrol with SEMCO. Their names and email addresses are
    # deliberately not in the same alphabetical order: Zoe Ant comes last by her first name, first by her last name and
    # first by her email address. This is what makes the report's sorting columns and its initials bars distinguishable.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
      | student2 | Bert      | Beer     | student2@example.com |
      | student3 | Zoe       | Ant      | ant@example.com      |
      | manager  | Max       | Manager  | manager@example.com  |
      | teacher  | Terry     | Teacher  | teacher@example.com  |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
      | Course 2 | C2        | topics |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
      | teacher | C2     | editingteacher |
    And the following "system role assigns" exist:
      | user    | course               | role    |
      | manager | Acceptance test site | manager |

  Scenario: An administrator reaches the report from the plugin settings page
    When I log in as "admin"
    And I navigate to "Plugins > Enrolments > SEMCO" in site administration
    And I follow "View report"
    Then I should see "SEMCO enrolments"
    And I should see "There are not any SEMCO enrolments yet in this Moodle instance"

  Scenario: An administrator finds the report linked in the site administration
    When I log in as "admin"
    And I follow "Site administration"
    Then "SEMCO enrolments" "link" should exist
    When I follow "SEMCO enrolments"
    Then I should see "There are not any SEMCO enrolments yet in this Moodle instance"

  Scenario: A manager (and not only the admin) finds the report linked in the site administration as well
    When I log in as "manager"
    And I follow "Site administration"
    Then "SEMCO enrolments" "link" should exist
    When I follow "SEMCO enrolments"
    Then I should see "There are not any SEMCO enrolments yet in this Moodle instance"

  Scenario: A user without the viewreport capability neither finds the report link nor reaches the report itself
    When I log in as "teacher"
    Then I should not see "Site administration"
    When I am on the "Course 1" course page
    Then "SEMCO enrolments" "link" should not exist
    And the SEMCO enrolment report should refuse the access

  Scenario: The report shows an empty state when there are no SEMCO enrolments
    When I am on the "enrol_semco > report" page logged in as "manager"
    Then I should see "SEMCO enrolments"
    # As neither an initial is picked in the initials bars nor a filter is applied in the filter menu, the report states
    # that there aren't any SEMCO enrolments at all and not that a filter is the reason for the empty table.
    And I should see "There are not any SEMCO enrolments yet in this Moodle instance"
    And I should not see "There are not any SEMCO enrolments which match the selected filters"

  Scenario Outline: The report lists a SEMCO enrolment with all its details for every enrolment period
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | semcouserid | timestart   | timeend   | suspend   |
      | student1 | C1     | BOOK-0001      | SEMCO-4711  | <timestart> | <timeend> | <suspend> |
    When I am on the "enrol_semco > report" page logged in as "manager"
    Then I should see "SEMCO enrolments"
    And I should not see "There are not any SEMCO enrolments yet"
    # Each detail of the enrolment is shown in its dedicated column.
    # The ID columns are not checked here as they hold volatile database IDs, and the actions column is not checked either
    # as its kebab menu has its own scenarios below. The enrolment start / end columns show either the enrolment date or
    # the "Unrestricted" label, depending on whether the enrolment has a start / end date.
    And the following should exist in the "enrolsemco_enrolreport" table:
      | SEMCO User ID | Moodle Username | First name / Last name | Email address        | Moodle User status | Course name | SEMCO booking ID | Enrolment start | Enrolment end | Enrolment status |
      | SEMCO-4711    | student1        | Alice Apple            | student1@example.com | Active             | Course 1    | BOOK-0001        | <startshown>    | <endshown>    | <enrolstatus>    |

    # The scenario is run for every permutation of a set / unset enrolment start and end date, once for an active and once for
    # a suspended enrolment. Note that suspending the enrolment only affects the "Enrolment status" column: the "Moodle User
    # status" column stays "Active" as the user account itself is not suspended.
    Examples:
      | timestart          | timeend             | startshown                             | endshown                                | suspend | enrolstatus |
      | 0                  | 0                   | Unrestricted                           | Unrestricted                            | 0       | Active      |
      | ##1 January 2030## | 0                   | ##1 January 2030##%d %B %Y, %I:%M %p## | Unrestricted                            | 0       | Active      |
      | 0                  | ##1 February 2030## | Unrestricted                           | ##1 February 2030##%d %B %Y, %I:%M %p## | 0       | Active      |
      | ##1 January 2030## | ##1 February 2030## | ##1 January 2030##%d %B %Y, %I:%M %p## | ##1 February 2030##%d %B %Y, %I:%M %p## | 0       | Active      |
      | 0                  | 0                   | Unrestricted                           | Unrestricted                            | 1       | Suspended   |
      | ##1 January 2030## | ##1 February 2030## | ##1 January 2030##%d %B %Y, %I:%M %p## | ##1 February 2030##%d %B %Y, %I:%M %p## | 1       | Suspended   |

  Scenario: The report lists SEMCO enrolments from several courses and users
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
      | student2 | C1     | BOOK-0002      |
      | student1 | C2     | BOOK-0003      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # Each enrolment's SEMCO booking ID and course name are shown in the row of the enrolled student. This especially makes
    # sure that a student who is enrolled into several courses gets a separate row per course with the matching booking ID.
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | Course name | SEMCO booking ID |
      | student1        | Course 1    | BOOK-0001        |
      | student2        | Course 1    | BOOK-0002        |
      | student1        | Course 2    | BOOK-0003        |

  Scenario: The report shows the course completion status of each enrolment
    # Course 3 is added with course completion enabled, while Course 2 from the background has it disabled.
    # Within Course 3, student1 and student4 have completed the course and student2 has not. Only student4 has been
    # graded, which makes him the one enrolment which shows a real course completion grade.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student4 | Carol     | Cherry   | student4@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 3 | C3        | topics | 1                |
    And the following "activities" exist:
      | activity | course | name        | idnumber | grade |
      | assign   | C3     | Assignment1 | a1       | 100   |
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C3     | BOOK-0001      |
      | student2 | C3     | BOOK-0002      |
      | student1 | C2     | BOOK-0003      |
      | student4 | C3     | BOOK-0004      |
    And the following "enrol_semco > completions" exist:
      | user     | course | timecompleted    |
      | student1 | C3     | ##1 March 2026## |
      | student4 | C3     | ##1 April 2026## |
    # The grade is given in the course's only activity, from where it is aggregated into the course total. The course
    # total is what the report shows as the course completion grade.
    And the following "grade grades" exist:
      | gradeitem   | user     | grade |
      | Assignment1 | student4 | 82    |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # The status column shows the same three states which the enrol_semco_get_course_completions webservice reports: a
    # course without course completion cannot be completed at all, while a course with course completion is either
    # completed or not completed for the enrolled user. The date column shows the completion date of a completed course
    # and a placeholder otherwise. The grade column shows the course total of a completed course, but only if the user
    # has been graded at all - student1 has completed the course without ever being graded.
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | Course name | SEMCO booking ID | Course completion status  | Course completion date               | Course completion grade |
      | student1        | Course 3    | BOOK-0001        | Completed                 | ##1 March 2026##%d %B %Y, %I:%M %p## | —                       |
      | student2        | Course 3    | BOOK-0002        | Not completed             | —                                    | —                       |
      | student1        | Course 2    | BOOK-0003        | Completion is not enabled | —                                    | —                       |
      | student4        | Course 3    | BOOK-0004        | Completed                 | ##1 April 2026##%d %B %Y, %I:%M %p## | 82.00                   |
    # Sorting by the column groups the enrolments by their completion status. This especially verifies that the status is
    # sorted as a status and not by the underlying course completion time, which would not be able to tell the two states
    # apart which do not have a completion time.
    When I click on "Course completion status" "link"
    Then "BOOK-0003" "text" should appear before "BOOK-0002" "text"
    And "BOOK-0002" "text" should appear before "BOOK-0001" "text"

  Scenario: The report shows the user's first name and last name in a single full name column
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
      | student2 | C1     | BOOK-0002      |
      | student3 | C1     | BOOK-0003      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # The report does not show the first name and the last name in two separate columns, it composes them into a single
    # full name column, just as it is done on /admin/user.php.
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | First name / Last name | SEMCO booking ID |
      | student1        | Alice Apple            | BOOK-0001        |
      | student2        | Bert Beer              | BOOK-0002        |
      | student3        | Zoe Ant                | BOOK-0003        |
    # Even though the two names share a single column, the column header still offers a dedicated sort link for each of
    # them. Out of the box, the report is sorted by the last name, which the plugin settings can change.
    And "First name" "link_exact" should exist in the "#enrolsemco_enrolreport thead th:first-child" "css_element"
    And "Last name" "link_exact" should exist in the "#enrolsemco_enrolreport thead th:first-child" "css_element"
    And "Zoe Ant" "text" should appear before "Alice Apple" "text"
    And "Alice Apple" "text" should appear before "Bert Beer" "text"
    # The first name link really sorts by the first name and not by the last name, which the three users can be told
    # apart by as their first names and last names are in a different alphabetical order.
    When I click on "First name" "link_exact" in the "enrolsemco_enrolreport" "table"
    Then "Alice Apple" "text" should appear before "Bert Beer" "text"
    And "Bert Beer" "text" should appear before "Zoe Ant" "text"

  Scenario: The report can be filtered with the first name and last name initials bars
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
      | student2 | C1     | BOOK-0002      |
      | student3 | C1     | BOOK-0003      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # The report offers an initials bar for each name field of the full name column. Tablelib only shows these bars for
    # tables which have a full name column at all, which is why they appeared together with that column.
    Then "First name" "core_course > initials bar" should exist
    And "Last name" "core_course > initials bar" should exist
    # Picking an initial in the first name bar keeps only the enrolments of the users whose first name starts with it.
    When I click on "A" "link_exact" in the "First name" "core_course > initials bar"
    Then I should see "Alice Apple"
    And I should not see "Bert Beer"
    And I should not see "Zoe Ant"
    # Picking "All" again resets the filter and brings all enrolments back.
    When I click on "All" "link_exact" in the "First name" "core_course > initials bar"
    Then I should see "Alice Apple"
    And I should see "Bert Beer"
    And I should see "Zoe Ant"
    # The second bar really filters by the last name and not by the first name, which is why it keeps Zoe Ant next to
    # Alice Apple and drops Bert Beer.
    When I click on "A" "link_exact" in the "Last name" "core_course > initials bar"
    Then I should see "Alice Apple"
    And I should see "Zoe Ant"
    And I should not see "Bert Beer"
    # Both bars filter independently from each other, so combining them narrows the report down to the one enrolment
    # which matches both initials.
    When I click on "A" "link_exact" in the "First name" "core_course > initials bar"
    Then I should see "Alice Apple"
    And I should not see "Zoe Ant"
    And I should not see "Bert Beer"
    # Picking an initial which no enrolled user matches empties the report. The report then explains that the filter is
    # the reason for the empty table and does not claim that there aren't any SEMCO enrolments at all.
    When I click on "All" "link_exact" in the "Last name" "core_course > initials bar"
    And I click on "Q" "link_exact" in the "First name" "core_course > initials bar"
    Then I should not see "Alice Apple"
    And I should not see "Bert Beer"
    And I should not see "Zoe Ant"
    And I should see "There are not any SEMCO enrolments which match the selected filters"
    And I should not see "There are not any SEMCO enrolments yet in this Moodle instance"
    # Resetting the filter shows all enrolments again.
    When I click on "All" "link_exact" in the "First name" "core_course > initials bar"
    Then I should see "Alice Apple"
    And I should see "Bert Beer"
    And I should see "Zoe Ant"

  Scenario: The report can be filtered with the filter menu
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | semcouserid |
      | student1 | C1     | BOOK-0001      | SEMCO-4711  |
      | student2 | C2     | BOOK-0002      | SEMCO-4712  |
      | student3 | C2     | BOOK-0003      | SEMCO-4713  |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # As long as no filter is applied, the menu button does not carry a number.
    Then "Filters" "button" should exist
    And I should not see "Filters (1)"
    And I should see "BOOK-0001"
    And I should see "BOOK-0002"
    And I should see "BOOK-0003"
    # Filtering by a course keeps the enrolments of that course only, and the button tells how many filters are applied.
    When I set the field "Course name" to "Course 2"
    And I press "Apply"
    Then I should see "Filters (1)"
    And I should not see "BOOK-0001"
    And I should see "BOOK-0002"
    And I should see "BOOK-0003"
    # A second filter narrows the report further. The SEMCO booking ID filter matches a part of the ID, so the full ID
    # does not have to be known.
    When I set the field "SEMCO booking ID" to "0003"
    And I press "Apply"
    Then I should see "Filters (2)"
    And I should not see "BOOK-0001"
    And I should not see "BOOK-0002"
    And I should see "BOOK-0003"
    # The filters survive a click on a column header, which is what the report needs them to do as the table builds its
    # sorting links from the report URL.
    When I click on "Moodle Username" "link" in the "enrolsemco_enrolreport" "table"
    Then I should see "Filters (2)"
    And I should not see "BOOK-0001"
    And I should see "BOOK-0003"
    # A filter which no enrolment matches empties the report. The report then explains that a filter is the reason for
    # the empty table and does not claim that there aren't any SEMCO enrolments at all.
    When I set the field "SEMCO booking ID" to "0815"
    And I press "Apply"
    Then I should not see "BOOK-0001"
    And I should not see "BOOK-0002"
    And I should not see "BOOK-0003"
    And I should see "There are not any SEMCO enrolments which match the selected filters"
    And I should not see "There are not any SEMCO enrolments yet in this Moodle instance"
    # Resetting brings all enrolments back and empties the menu button again.
    When I press "Reset all"
    Then "Filters" "button" should exist
    And I should not see "Filters (1)"
    And I should see "BOOK-0001"
    And I should see "BOOK-0002"
    And I should see "BOOK-0003"

  Scenario: The report filters by the enrolment status and by the course completion status
    Given the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 3 | C3        | topics | 1                |
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | suspend |
      | student1 | C3     | BOOK-0001      | 0       |
      | student2 | C3     | BOOK-0002      | 1       |
      | student3 | C2     | BOOK-0003      | 0       |
    And the following "enrol_semco > completions" exist:
      | user     | course |
      | student1 | C3     |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # Filtering by the enrolment status keeps the suspended enrolment only.
    And I set the field "Enrolment status" to "Suspended"
    And I press "Apply"
    Then I should not see "BOOK-0001"
    And I should see "BOOK-0002"
    And I should not see "BOOK-0003"
    # Filtering by the course completion status offers the three states which the matching column shows.
    When I press "Reset all"
    And I set the field "Course completion status" to "Completed"
    And I press "Apply"
    Then I should see "BOOK-0001"
    And I should not see "BOOK-0002"
    And I should not see "BOOK-0003"
    When I set the field "Course completion status" to "Not completed"
    And I press "Apply"
    Then I should not see "BOOK-0001"
    And I should see "BOOK-0002"
    And I should not see "BOOK-0003"
    # Course 2 does not have course completion enabled, so its enrolment is the one which cannot be completed at all.
    When I set the field "Course completion status" to "Completion is not enabled"
    And I press "Apply"
    Then I should not see "BOOK-0001"
    And I should not see "BOOK-0002"
    And I should see "BOOK-0003"

  Scenario: The filter menu only offers the filters whose column the report shows
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # With all optional columns enabled, the menu offers a filter for each of them.
    When I am on the "enrol_semco > report" page logged in as "manager"
    Then "Course name" "field" should exist
    And "Enrolment status" "field" should exist
    And "Course completion status" "field" should exist
    And "SEMCO booking ID" "field" should exist
    # Switching off the optional columns takes their filters with them. The SEMCO booking ID column is not optional, so
    # its filter stays.
    Given the following config values are set as admin:
      | reportoptionalcolumns |  | enrol_semco |
    When I am on the "enrol_semco > report" page
    Then "Course name" "field" should not exist
    And "Enrolment status" "field" should not exist
    And "Course completion status" "field" should not exist
    And "SEMCO booking ID" "field" should exist

  Scenario: The report does not offer the link which resets the table preferences
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    Then "Reset table preferences" "link" should not exist
    # The link would be offered by tablelib as soon as a table preference is set, which is why an initial is picked here.
    When I click on "A" "link_exact" in the "First name" "core_course > initials bar"
    Then I should see "Alice Apple"
    And "Reset table preferences" "link" should not exist

  Scenario Outline: The admin controls the column by which the report is sorted initially
    # The SEMCO IDs of the three enrolments are picked so that every sorting column yields an order of its own, just as
    # the three users from the background differ in the order of their names and email addresses.
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | semcouserid |
      | student1 | C1     | BOOK-0002      | SEMCO-0003  |
      | student2 | C1     | BOOK-0003      | SEMCO-0001  |
      | student3 | C1     | BOOK-0001      | SEMCO-0002  |
    And the following config values are set as admin:
      | reportinitialsortingcolumn | <setting> | enrol_semco |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # The full name column stays the first column of the report, no matter which sorting column is configured. Its header
    # is checked by its two sort links, as the header text itself carries the sort direction icon between the two names.
    Then "First name" "link_exact" should exist in the "#enrolsemco_enrolreport thead th:first-child" "css_element"
    And "Last name" "link_exact" should exist in the "#enrolsemco_enrolreport thead th:first-child" "css_element"
    # The configured column follows directly after it.
    And I should see "<secondheader>" in the "#enrolsemco_enrolreport thead th:nth-child(2)" "css_element"
    # And the report is sorted by the configured column, which is verified with the booking IDs of the three enrolments.
    And "<first>" "text" should appear before "<second>" "text"
    And "<second>" "text" should appear before "<third>" "text"

    # The scenario is run for every column which the setting offers. The first two examples show that the first name and
    # the last name are offered separately even though the report shows them in a single full name column: Neither of
    # them moves a second column to the front as the full name column is the sorting column itself, but each of them
    # sorts the report by its own name field. The second column is the first regular column in these two cases.
    Examples:
      | setting        | secondheader     | first     | second    | third     |
      | lastname       | Email address    | BOOK-0001 | BOOK-0002 | BOOK-0003 |
      | firstname      | Email address    | BOOK-0002 | BOOK-0003 | BOOK-0001 |
      | email          | Email address    | BOOK-0001 | BOOK-0002 | BOOK-0003 |
      | moodleuserid   | Moodle User ID   | BOOK-0002 | BOOK-0003 | BOOK-0001 |
      | username       | Moodle Username  | BOOK-0002 | BOOK-0003 | BOOK-0001 |
      | semcouserid    | SEMCO User ID    | BOOK-0003 | BOOK-0001 | BOOK-0002 |
      | semcobookingid | SEMCO booking ID | BOOK-0001 | BOOK-0002 | BOOK-0003 |

  Scenario: The admin controls which optional columns the report shows
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    And the following config values are set as admin:
      | reportoptionalcolumns | course,enrolstatus | enrol_semco |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # The two optional columns which are still enabled are shown.
    Then I should see "Course name"
    And I should see "Enrolment status"
    # The optional columns which have been disabled are gone.
    And I should not see "Moodle User status"
    And I should not see "Enrolment ID"
    And I should not see "Course ID"
    And I should not see "Enrolment start"
    And I should not see "Enrolment end"
    And I should not see "Course completion status"
    And I should not see "Course completion date"
    And I should not see "Course completion grade"
    # The columns which the initial sorting setting offers are not optional at all, so they are still shown. The actions
    # column is not optional either.
    And I should see "Moodle User ID"
    And I should see "SEMCO User ID"
    And I should see "Moodle Username"
    And "First name" "link_exact" should exist in the "#enrolsemco_enrolreport thead th:first-child" "css_element"
    And "Last name" "link_exact" should exist in the "#enrolsemco_enrolreport thead th:first-child" "css_element"
    And I should see "Email address"
    And I should see "SEMCO booking ID"
    And "Actions" "link" should exist in the "student1" "table_row"
    # Disabling all optional columns leaves the report with its non-optional columns only.
    Given the following config values are set as admin:
      | reportoptionalcolumns |  | enrol_semco |
    When I am on the "enrol_semco > report" page
    Then I should not see "Course name"
    And I should not see "Enrolment status"
    And I should see "SEMCO booking ID"
    And "Actions" "link" should exist in the "student1" "table_row"

  Scenario: The report shows the SEMCO user profile fields of the enrolled user
    # The five SEMCO user profile fields are created by this plugin during its installation, but they are filled by SEMCO
    # through the core webservices. The user generator fills them here, which is why this scenario brings its own user
    # instead of using one of the three students from the background.
    Given the following "users" exist:
      | username | firstname | lastname | email                | profile_field_semco_userid | profile_field_semco_usercompany | profile_field_semco_userbirthday | profile_field_semco_userplaceofbirth | profile_field_semco_branchtoken |
      | student4 | Carl      | Cook     | student4@example.com | SEMCO-4711                 | ACME Corp                       | 1980-01-23                       | Springfield                          | TENANT-42                       |
    # Student1 from the background is enrolled as well. He does not have any of the five fields filled, which is what
    # shows how the report renders an empty field.
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student4 | C1     | BOOK-0001      |
      | student1 | C1     | BOOK-0002      |
    # Four of the five profile field columns are optional columns, so they are enabled explicitly here. The setting is
    # stored with all optional columns enabled when the site is installed, but a site which was installed before these
    # four columns existed does not know about them, which is exactly the case for the Behat test site.
    And the following config values are set as admin:
      | reportoptionalcolumns | semcousercompany,semcouserbirthday,semcouserplaceofbirth,semcotenantshortname | enrol_semco |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # Each of the five profile fields is shown in a column of its own. A field which the user does not have a value in
    # shows the same placeholder as an empty course completion column, so that the cell does not stay blank.
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | SEMCO User ID | SEMCO User company | SEMCO User birthday | SEMCO User place of birth | SEMCO Tenant shortname |
      | student4        | SEMCO-4711    | ACME Corp          | 1980-01-23          | Springfield               | TENANT-42              |
      | student1        | —             | —                  | —                   | —                         | —                      |
    # Disabling the four optional columns removes them from the report. The SEMCO user ID is not optional as the report
    # can be sorted by it, so it stays.
    Given the following config values are set as admin:
      | reportoptionalcolumns |  | enrol_semco |
    When I am on the "enrol_semco > report" page
    Then I should see "SEMCO User ID"
    And I should see "SEMCO-4711"
    And I should not see "SEMCO User company"
    And I should not see "ACME Corp"
    And I should not see "SEMCO User birthday"
    And I should not see "1980-01-23"
    And I should not see "SEMCO User place of birth"
    And I should not see "Springfield"
    And I should not see "SEMCO Tenant shortname"
    And I should not see "TENANT-42"

  Scenario: The actions column offers a kebab menu with the pages of the enrolled user
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # The actions column does not carry a visible header, just as it is done on /admin/user.php. The header text is still
    # in the markup for screen readers, which is why it is looked for in the header cell and not on the page.
    Then I should see "Actions" in the "#enrolsemco_enrolreport thead th:last-child" "css_element"
    # The cell itself holds a kebab menu with one item per page which the report links to. The items are looked for in
    # the table row and not on the page, as they are behind the kebab trigger and are not visible right away.
    And "Actions" "link" should exist in the "student1" "table_row"
    And "View user profile" "link" should exist in the "student1" "table_row"
    And "View course profile" "link" should exist in the "student1" "table_row"
    And "View course grades" "link" should exist in the "student1" "table_row"

  Scenario: The kebab menu leads to the enrolled user's site wide profile
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    And I click on "Actions" "link" in the "student1" "table_row"
    And I click on "View user profile" "link" in the "student1" "table_row"
    # The target page is the user's site wide profile. To be sure that we really navigated there, we assert the page's
    # body ID which /user/profile.php sets, together with the user's name and the profile heading.
    Then "body#page-user-profile" "css_element" should exist
    And I should see "Alice Apple"
    And I should see "User profile"

  Scenario: The kebab menu leads to the enrolled user's profile within the course
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    And I click on "Actions" "link" in the "student1" "table_row"
    And I click on "View course profile" "link" in the "student1" "table_row"
    # The target page is the enrolled user's profile within the course. To be sure that we really navigated there, we assert
    # the page's body ID (which /user/view.php sets to the course view page type, so it depends on the course format being
    # topics) and content which only exists on that profile page (and not on the report itself, where "Course 1" and the
    # user's full name are shown as well): the "User details" section and the "Course details" section (the latter only
    # exists on the in-course profile, not on the site-wide profile).
    Then "body#page-course-view-topics" "css_element" should exist
    And I should see "Alice Apple"
    And I should see "User details"
    And I should see "Course details"

  Scenario: The kebab menu leads to the enrolled user's grades within the course
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    And I click on "Actions" "link" in the "student1" "table_row"
    And I click on "View course grades" "link" in the "student1" "table_row"
    # The target page is the user's grade report within the course, which /course/user.php renders for the 'grade' mode.
    Then "body#page-course-user" "css_element" should exist
    And I should see "Alice Apple"
    And I should see "Course 1"
    And I should see "Grades"

  Scenario: The kebab menu only offers the pages which the user is allowed to see
    # The report viewer is allowed to open the report, but that is all: The role does not hold any other capability and
    # the user is not enrolled anywhere, so none of the pages which the kebab menu links to is within reach.
    Given the following "users" exist:
      | username     | firstname | lastname | email                    |
      | reportviewer | Rita      | Viewer   | reportviewer@example.com |
    And the following "roles" exist:
      | shortname      | name          |
      | semcoreportvie | Report viewer |
    And the following "role capabilities" exist:
      | role           | enrol/semco:viewreport |
      | semcoreportvie | allow                  |
    And the following "system role assigns" exist:
      | user         | role           |
      | reportviewer | semcoreportvie |
    # Profiles are only guarded by capabilities if Moodle is configured to force a login for them, which is the default.
    # The setting is set explicitly here as the whole scenario depends on it.
    And the following config values are set as admin:
      | forceloginforprofiles | 1 |
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # The manager is allowed to reach all three pages and gets all three items.
    When I am on the "enrol_semco > report" page logged in as "manager"
    Then "View user profile" "link" should exist in the "student1" "table_row"
    And "View course profile" "link" should exist in the "student1" "table_row"
    And "View course grades" "link" should exist in the "student1" "table_row"
    # The report viewer sees the enrolment but does not get a kebab menu at all, as there is not a single item to offer.
    When I am on the "enrol_semco > report" page logged in as "reportviewer"
    Then I should see "BOOK-0001"
    And "Actions" "link" should not exist in the "student1" "table_row"
    And "View user profile" "link" should not exist in the "student1" "table_row"
    And "View course profile" "link" should not exist in the "student1" "table_row"
    And "View course grades" "link" should not exist in the "student1" "table_row"

  Scenario: The report table can be downloaded
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # As the downloaded file cannot be inspected in Behat, clicking the download button without an exception being thrown is
    # considered a success (this is the same approach which the Moodle core reports use to test their download button).
    # This scenario therefore deliberately ends with an action and does not carry a dedicated assertion step.
    And I click on "Download" "button"
