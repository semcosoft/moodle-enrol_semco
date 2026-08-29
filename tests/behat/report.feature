@enrol @enrol_semco
Feature: SEMCO enrolment report
  In order to monitor which users have been enrolled by SEMCO
  As a manager or administrator
  I need to be able to view the SEMCO enrolment report

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
      | student2 | Bert      | Beer     | student2@example.com |
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
    And I should see "There are not any SEMCO enrolments yet in this Moodle instance"

  Scenario Outline: The report lists a SEMCO enrolment with all its details for every enrolment period
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | semcouserid | timestart   | timeend   | suspend   |
      | student1 | C1     | BOOK-0001      | SEMCO-4711  | <timestart> | <timeend> | <suspend> |
    When I am on the "enrol_semco > report" page logged in as "manager"
    Then I should see "SEMCO enrolments"
    And I should not see "There are not any SEMCO enrolments yet"
    # Each detail of the enrolment is shown in its dedicated column.
    # The ID columns are not checked here as they hold volatile database IDs. The enrolment start / end columns show either
    # the enrolment date or the "Unrestricted" label, depending on whether the enrolment has a start / end date.
    And the following should exist in the "enrolsemco_enrolreport" table:
      | SEMCO User ID | Moodle Username | Last name | First name | Email address        | Moodle User status | Course name | SEMCO booking ID | Enrolment start | Enrolment end | Enrolment status | Actions             |
      | SEMCO-4711    | student1        | Apple     | Alice      | student1@example.com | Active             | Course 1    | BOOK-0001        | <startshown>    | <endshown>    | <enrolstatus>    | View course profile |

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
    # Within Course 3, student1 and student3 have completed the course and student2 has not. Only student3 has been
    # graded, which makes him the one enrolment which shows a real course completion grade.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student3 | Carol     | Cherry   | student3@example.com |
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
      | student3 | C3     | BOOK-0004      |
    And the following "enrol_semco > completions" exist:
      | user     | course | timecompleted    |
      | student1 | C3     | ##1 March 2026## |
      | student3 | C3     | ##1 April 2026## |
    # The grade is given in the course's only activity, from where it is aggregated into the course total. The course
    # total is what the report shows as the course completion grade.
    And the following "grade grades" exist:
      | gradeitem   | user     | grade |
      | Assignment1 | student3 | 82    |
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
      | student3        | Course 3    | BOOK-0004        | Completed                 | ##1 April 2026##%d %B %Y, %I:%M %p## | 82.00                   |
    # Sorting by the column groups the enrolments by their completion status. This especially verifies that the status is
    # sorted as a status and not by the underlying course completion time, which would not be able to tell the two states
    # apart which do not have a completion time.
    When I click on "Course completion status" "link"
    Then "BOOK-0003" "text" should appear before "BOOK-0002" "text"
    And "BOOK-0002" "text" should appear before "BOOK-0001" "text"

  Scenario: The "View course profile" button opens the enrolled user's profile within the course
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    And I click on "View course profile" "button" in the "student1" "table_row"
    # The target page is the enrolled user's profile within the course. To be sure that we really navigated there, we assert
    # the page's body ID (which /user/view.php sets to the course view page type, so it depends on the course format being
    # topics) and content which only exists on that profile page (and not on the report itself, where "Course 1" and the name
    # parts are shown as well): the user's full name as the profile heading, the "User details" section and the "Course
    # details" section (the latter only exists on the in-course profile, not on the site-wide profile).
    Then "body#page-course-view-topics" "css_element" should exist
    And I should see "Alice Apple"
    And I should see "User details"
    And I should see "Course details"

  Scenario: A report column can be hidden and made visible again via the table preferences
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    Then I should see "BOOK-0001"
    # Hiding the SEMCO booking ID column removes its values from the report.
    When I click on "Hide SEMCO booking ID" "link"
    Then I should not see "BOOK-0001"
    # Resetting the table preferences makes the hidden column visible again.
    When I follow "Reset table preferences"
    Then I should see "BOOK-0001"

  Scenario: The report table can be downloaded
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "manager"
    # As the downloaded file cannot be inspected in Behat, clicking the download button without an exception being thrown is
    # considered a success (this is the same approach which the Moodle core reports use to test their download button).
    # This scenario therefore deliberately ends with an action and does not carry a dedicated assertion step.
    And I click on "Download" "button"
