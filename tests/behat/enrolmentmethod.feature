@enrol @enrol_semco
Feature: SEMCO enrolment method behaviour in a course
  In order to keep SEMCO in control of its enrolments
  As an administrator
  I need the SEMCO enrolment method to be protected and not manageable manually

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
      | student2 | Bert      | Beer     | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |

  Scenario: A SEMCO enrolment instance cannot be added to a course manually (unlike self enrolment instances)
    When I am on the "Course 1" "enrolment methods" page logged in as "admin"
    # Cross-check: the self enrolment instance is addable to the course, but the SEMCO instance is not.
    Then the "Add method" select box should contain "Self enrolment"
    And the "Add method" select box should not contain "SEMCO"

  Scenario: A SEMCO enrolment instance cannot be deleted manually (unlike manual enrolment instances)
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "Course 1" "enrolment methods" page logged in as "admin"
    # Cross-check: the manual enrolment instance which every course has does offer a "Delete" link, but the SEMCO instance does not.
    Then "Delete" "link" should exist in the "Manual enrolments" "table_row"
    And "Delete" "link" should not exist in the "SEMCO [Booking ID: BOOK-0001]" "table_row"

  Scenario: A SEMCO enrolment instance cannot be edited manually (unlike manual enrolment instances)
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "Course 1" "enrolment methods" page logged in as "admin"
    # Cross-check: the manual enrolment instance which every course has does offer an "Edit" link, but the SEMCO instance does not.
    Then "Edit" "link" should exist in the "Manual enrolments" "table_row"
    And "Edit" "link" should not exist in the "SEMCO [Booking ID: BOOK-0001]" "table_row"

  Scenario: A SEMCO enrolment instance cannot be hidden manually (unlike manual enrolment instances)
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "Course 1" "enrolment methods" page logged in as "admin"
    # In the enrolment methods UI, hiding an enrolment instance is offered as the "Disable" action.
    # Cross-check: the manual enrolment instance which every course has does offer a "Disable" link, but the SEMCO instance does not.
    Then "Disable" "link" should exist in the "Manual enrolments" "table_row"
    And "Disable" "link" should not exist in the "SEMCO [Booking ID: BOOK-0001]" "table_row"

  Scenario: For SEMCO enrolment instances, there is no 'Enrol users' icon (unlike manual enrolment instances)
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    When I am on the "Course 1" "enrolment methods" page logged in as "admin"
    # The manual enrolment instance which every course has offers an "Enrol users" icon, but the SEMCO instance does not.
    Then "Enrol users" "link" should exist in the "Manual enrolments" "table_row"
    And "Enrol users" "link" should not exist in the "SEMCO [Booking ID: BOOK-0001]" "table_row"

  Scenario: For SEMCO enrolment instances, users cannot self enrol in any way
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # A user who is not enrolled by SEMCO is not offered any way to self enrol, even though a SEMCO enrolment instance exists
    # in the course. Being redirected to the enrolment page with this message proves that no self enrolment option is offered.
    When I am on the "Course 1" course page logged in as "student2"
    Then I should see "You cannot enrol yourself in this course."

  Scenario: For SEMCO enrolment instances, users cannot self unenrol from the course
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # A SEMCO enrolled user is not offered a self unenrolment link (which self enrolment, for example, would offer).
    When I am on the "Course 1" course page logged in as "student1"
    Then "Unenrol me from this course" "link" should not exist

  Scenario: For each SEMCO booking, there is an individual enrolment instance created in the course
    Given the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | timestart          | timeend             |
      | student1 | C1     | BOOK-0001      | ##1 January 2030## | ##31 January 2030## |
      | student1 | C1     | BOOK-0002      | ##1 March 2030##   | ##31 March 2030##   |
    When I am on the "Course 1" "enrolment methods" page logged in as "admin"
    # Although both bookings belong to the same user and the same course, each booking results in its own enrolment instance,
    # each labelled with its own SEMCO booking ID.
    Then I should see "SEMCO [Booking ID: BOOK-0001]"
    And I should see "SEMCO [Booking ID: BOOK-0002]"
