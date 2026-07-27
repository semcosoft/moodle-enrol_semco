@enrol @enrol_semco
Feature: SEMCO enrolment processes in a course
  In order to manage its enrolments throughout their lifecycle
  As SEMCO (through the enrolment webservices)
  I need enrolling, unenrolling and editing SEMCO enrolments to take effect in the course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |

  Scenario: SEMCO enrols a user into a course
    # Before the enrolment, the user cannot access the course. Reaching the in-course page is verified via the course view
    # page's body ID (which depends on the course format being topics): if the user is not (actively) enrolled, Moodle
    # redirects to the enrolment page instead, where this body ID does not exist.
    When I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should not exist
    # And the user does not appear in the report yet.
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then I should see "There are not any SEMCO enrolments yet in this Moodle instance"
    # SEMCO enrols the user (which the generator does through the very same enrolment webservice that SEMCO uses).
    When the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # Now an individual enrolment instance exists for the booking.
    And I am on the "Course 1" "enrolment methods" page logged in as "admin"
    Then I should see "SEMCO [Booking ID: BOOK-0001]"
    # The user appears on the participants page.
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    Then I should see "Alice Apple"
    # The user appears in the report.
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | Course name | SEMCO booking ID |
      | student1        | Course 1    | BOOK-0001        |
    # And the user can now access the course.
    When I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should exist

  Scenario: SEMCO unenrols a user from a course
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # The enrolled user can access the course.
    When I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should exist
    # And the user appears in the report.
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | SEMCO booking ID |
      | student1        | BOOK-0001        |
    # SEMCO unenrols the user (through the very same unenrolment webservice that SEMCO uses).
    When the following SEMCO enrolments are unenrolled:
      | semcobookingid |
      | BOOK-0001      |
    # The enrolment instance is gone again.
    And I am on the "Course 1" "enrolment methods" page logged in as "admin"
    Then I should not see "SEMCO [Booking ID: BOOK-0001]"
    # The user no longer appears on the participants page.
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    Then I should not see "Alice Apple"
    # The report is empty again.
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then I should see "There are not any SEMCO enrolments yet in this Moodle instance"
    # And the user cannot access the course anymore.
    When I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should not exist

  Scenario Outline: SEMCO edits an enrolment by moving its enrolment period
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # SEMCO moves the enrolment into the given enrolment period (a value of 0 removes the respective date).
    When the following SEMCO enrolments are edited:
      | semcobookingid | timestart   | timeend   |
      | BOOK-0001      | <timestart> | <timeend> |
    # The user can access the course exactly if the (edited) enrolment period is currently running. Reaching the in-course
    # page is verified via the course view page's body ID (which depends on the course format being topics): if the user is
    # not (actively) enrolled, Moodle redirects to the enrolment page instead, where this body ID does not exist.
    And I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" <access>

    # The scenario is run for every permutation of a set / unset enrolment start and end date, covering both enrolment
    # statuses which result from it: an enrolment which runs from the past into the future is currently running (Active,
    # access granted), an enrolment which only starts in the future is not current (access denied), an enrolment which has
    # already ended is not current as well (access denied) and an enrolment without any start and end date is unrestricted
    # (Active, access granted).
    Examples:
      | timestart          | timeend            | access           |
      | ##1 January 2020## | ##1 January 2035## | should exist     |
      | ##1 January 2035## | 0                  | should not exist |
      | 0                  | ##1 January 2021## | should not exist |
      | 0                  | 0                  | should exist     |

  Scenario: SEMCO edits an enrolment by suspending it
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # With an active enrolment, the user can access the course.
    When I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should exist
    # SEMCO suspends the enrolment.
    When the following SEMCO enrolments are edited:
      | semcobookingid | suspend |
      | BOOK-0001      | 1       |
    # Now the user cannot access the course anymore.
    And I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should not exist
    # SEMCO unsuspends the enrolment.
    When the following SEMCO enrolments are edited:
      | semcobookingid | suspend |
      | BOOK-0001      | 0       |
    # Now the user can access the course again.
    And I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should exist

  Scenario: SEMCO enrols a user into a course multiple times
    # SEMCO first creates a booking whose enrolment period lies completely in the past.
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | timestart          | timeend              |
      | student1 | C1     | BOOK-0001      | ##1 January 2018## | ##31 December 2019## |
    # As this enrolment has already ended, the user cannot access the course yet.
    When I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should not exist
    # The (expired) enrolment does appear in the report already, though: the report lists every SEMCO enrolment,
    # regardless of its enrolment status.
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | SEMCO booking ID |
      | student1        | BOOK-0001        |
    # SEMCO then creates a second booking (with its own booking ID) whose enrolment period is currently running.
    When the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | timestart          | timeend            |
      | student1 | C1     | BOOK-0002      | ##1 January 2020## | ##1 January 2035## |
    # Both bookings result in their own enrolment instance.
    When I am on the "Course 1" "enrolment methods" page logged in as "admin"
    Then I should see "SEMCO [Booking ID: BOOK-0001]"
    And I should see "SEMCO [Booking ID: BOOK-0002]"
    # On the participants page, the user is listed in a single row (the participants page groups by user), but that row
    # carries both SEMCO enrolment badges.
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    Then "SEMCO [Booking ID: BOOK-0001]" "icon" should exist in the "Alice Apple" "table_row"
    And "SEMCO [Booking ID: BOOK-0002]" "icon" should exist in the "Alice Apple" "table_row"
    # The report lists both bookings.
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | SEMCO booking ID |
      | student1        | BOOK-0001        |
      | student1        | BOOK-0002        |
    # Thanks to the second, currently running booking, the user can now access the course.
    When I am on the "Course 1" course page logged in as "student1"
    Then "body#page-course-view-topics" "css_element" should exist
