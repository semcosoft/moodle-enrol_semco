@enrol @enrol_semco
Feature: SEMCO enrolment presentation on the course participants page
  In order to understand the SEMCO enrolments of a course
  As an administrator
  I need the SEMCO enrolments to be presented with their status, booking ID and enrolment details on the enrolled users page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
      | student2 | Bert      | Beer     | student2@example.com |
      | student3 | Carla     | Cherry   | student3@example.com |
      | student4 | Dora      | Date     | student4@example.com |
      | student5 | Emil      | Elder    | student5@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    # This covers all three enrolment statuses shown on the participants page, including both causes of the "Not current"
    # status: student1 has a currently running enrolment (Active), student2 has an enrolment which only starts in the future
    # (Not current), student3 has an enrolment which has already ended (Not current as well), student4 has an enrolment
    # without any start and end date (Active as well) and student5 has a suspended enrolment.
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | timestart          | timeend            | suspend |
      | student1 | C1     | BOOK-0001      | ##1 January 2020## | ##1 January 2035## | 0       |
      | student2 | C1     | BOOK-0002      | ##1 January 2035## | 0                  | 0       |
      | student3 | C1     | BOOK-0003      | 0                  | ##1 January 2021## | 0       |
      | student4 | C1     | BOOK-0004      | 0                  | 0                  | 0       |
      | student5 | C1     | BOOK-0005      | 0                  | 0                  | 1       |

  Scenario: SEMCO enrolments show the enrolment status
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    # The status cell shows the correct status badge for each enrolment state. The "Not current" status is checked for both
    # of its causes: an enrolment which has not started yet (Bert Beer) and an enrolment which has already ended (Carla Cherry).
    # The "Active" status is checked for an enrolment with a running date range (Alice Apple) and for an enrolment without
    # any dates (Dora Date).
    Then I should see "Active" in the "Alice Apple" "table_row"
    And I should see "Not current" in the "Bert Beer" "table_row"
    And I should see "Not current" in the "Carla Cherry" "table_row"
    And I should see "Active" in the "Dora Date" "table_row"
    And I should see "Suspended" in the "Emil Elder" "table_row"

  Scenario: SEMCO enrolments contain the booking ID in the tooltip of the info icon
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    # The info icon in the status cell carries the SEMCO enrolment label (including the booking ID) as its tooltip. This is
    # shown for every SEMCO enrolment, regardless of its enrolment status.
    Then "SEMCO [Booking ID: BOOK-0001]" "icon" should exist in the "Alice Apple" "table_row"
    And "SEMCO [Booking ID: BOOK-0002]" "icon" should exist in the "Bert Beer" "table_row"
    And "SEMCO [Booking ID: BOOK-0003]" "icon" should exist in the "Carla Cherry" "table_row"
    And "SEMCO [Booking ID: BOOK-0004]" "icon" should exist in the "Dora Date" "table_row"
    And "SEMCO [Booking ID: BOOK-0005]" "icon" should exist in the "Emil Elder" "table_row"

  # The enrolment details modal is covered with one scenario per enrolment state. The reason for not folding these scenarios
  # into a Scenario Outline is the way the two enrolment dates have to be checked: A date which the enrolment has is verified
  # inside its dedicated table cell, while a date which it does not have is verified by the absence of the respective row. As
  # the table cell does not exist at all in the latter case, the two checks differ in their whole structure and not just in
  # their values, so they cannot be expressed as the columns of an Examples table.

  @javascript
  Scenario: The info modal of a SEMCO enrolment with a start and an end date shows both dates
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    And I click on "SEMCO [Booking ID: BOOK-0001]" "icon" in the "Alice Apple" "table_row"
    # The enrolment details modal shows the SEMCO enrolment (including its booking ID) as the enrolment method, along with the
    # current enrolment status.
    Then I should see "SEMCO [Booking ID: BOOK-0001]" in the "Enrolment method" "table_row"
    And I should see "Active" in the "//td[@class='user-enrol-status']" "xpath_element"
    # The modal always shows the enrolment creation date. But we don't really care if the right date is shown.
    And I should see "Enrolment created"
    # Both dates are shown in their dedicated table cell (the "##...##" syntax renders them with the modal's date format).
    And I should see "##1 January 2020##%A, %d %B %Y, %I:%M %p##" in the "//td[@class='user-enrol-timestart']" "xpath_element"
    And I should see "##1 January 2035##%A, %d %B %Y, %I:%M %p##" in the "//td[@class='user-enrol-timeend']" "xpath_element"

  @javascript
  Scenario: The info modal of a SEMCO enrolment without an end date shows the start date only
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    And I click on "SEMCO [Booking ID: BOOK-0002]" "icon" in the "Bert Beer" "table_row"
    Then I should see "SEMCO [Booking ID: BOOK-0002]" in the "Enrolment method" "table_row"
    And I should see "Not current" in the "//td[@class='user-enrol-status']" "xpath_element"
    And I should see "Enrolment created"
    And I should see "##1 January 2035##%A, %d %B %Y, %I:%M %p##" in the "//td[@class='user-enrol-timestart']" "xpath_element"
    # Without an end date, the modal does not show the enrolment end row at all.
    And I should not see "Enrolment ends"

  @javascript
  Scenario: The info modal of a SEMCO enrolment without a start date shows the end date only
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    And I click on "SEMCO [Booking ID: BOOK-0003]" "icon" in the "Carla Cherry" "table_row"
    Then I should see "SEMCO [Booking ID: BOOK-0003]" in the "Enrolment method" "table_row"
    And I should see "Not current" in the "//td[@class='user-enrol-status']" "xpath_element"
    And I should see "Enrolment created"
    And I should see "##1 January 2021##%A, %d %B %Y, %I:%M %p##" in the "//td[@class='user-enrol-timeend']" "xpath_element"
    # Without a start date, the modal does not show the enrolment start row at all.
    And I should not see "Enrolment starts"

  @javascript
  Scenario: The info modal of an unrestricted SEMCO enrolment shows neither a start nor an end date
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    And I click on "SEMCO [Booking ID: BOOK-0004]" "icon" in the "Dora Date" "table_row"
    Then I should see "SEMCO [Booking ID: BOOK-0004]" in the "Enrolment method" "table_row"
    And I should see "Active" in the "//td[@class='user-enrol-status']" "xpath_element"
    And I should see "Enrolment created"
    And I should not see "Enrolment starts"
    And I should not see "Enrolment ends"

  @javascript
  Scenario: The info modal of a suspended SEMCO enrolment shows the suspended status
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    And I click on "SEMCO [Booking ID: BOOK-0005]" "icon" in the "Emil Elder" "table_row"
    Then I should see "SEMCO [Booking ID: BOOK-0005]" in the "Enrolment method" "table_row"
    And I should see "Suspended" in the "//td[@class='user-enrol-status']" "xpath_element"
    And I should see "Enrolment created"
    And I should not see "Enrolment starts"
    And I should not see "Enrolment ends"
