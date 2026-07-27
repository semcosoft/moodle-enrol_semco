@enrol @enrol_semco
Feature: SEMCO user enrolment behaviour in a course
  In order to keep SEMCO in control of its enrolments
  As an administrator
  I need the individual SEMCO user enrolments to be protected and not manageable manually

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
      | student2 | Bert      | Beer     | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    # student1 is enrolled by SEMCO, student2 is enrolled manually.
    # This lets each scenario cross-check the SEMCO enrolment against a manual enrolment on the same page.
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1     | student |

  @javascript
  Scenario: A user enrolled via SEMCO cannot be unenrolled manually (unlike manual enrolment instances)
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    # Cross-check: the manually enrolled user offers an "Unenrol" action, but the SEMCO enrolled user does not.
    Then "Unenrol" "icon" should exist in the "Bert Beer" "table_row"
    And "Unenrol" "icon" should not exist in the "Alice Apple" "table_row"

  @javascript
  Scenario: A SEMCO user enrolment cannot be edited manually (unlike manual enrolment instances)
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    # Cross-check: the manually enrolled user offers an "Edit enrolment" action, but the SEMCO enrolled user does not.
    Then "Edit enrolment" "icon" should exist in the "Bert Beer" "table_row"
    And "Edit enrolment" "icon" should not exist in the "Alice Apple" "table_row"

  @javascript
  Scenario: The SEMCO user enrolment is not listed in the "With selected users..." select box in any way (unlike manual enrolment instances)
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    # The bulk action select groups its operations by enrolment method. The manual enrolment method appears there as its own
    # option group, but SEMCO does not appear in any way (it defines no bulk operations and forbids manual management).
    Then "#formactionid optgroup[label='Manual enrolments']" "css_element" should exist
    And "#formactionid optgroup[label*='SEMCO']" "css_element" should not exist

  @javascript
  Scenario: A SEMCO enrolment instance's roles cannot be removed manually (unlike manual enrolment instances)
    When I am on the "Course 1" "enrolled users" page logged in as "admin"
    # Attempt to remove the "Student" role from the SEMCO enrolled user:
    # As the SEMCO enrolment protects its roles, the role remains assigned even after saving the emptied role list.
    And I click on "Alice Apple's role assignments" "link"
    And I click on "Student" "autocomplete_selection"
    And I click on "Save changes" "link"
    Then I should see "Student" in the "Alice Apple" "table_row"
    # Cross-check: the manually enrolled user's roles are not protected, so removing the role actually removes it.
    When I click on "Bert Beer's role assignments" "link"
    And I click on "Student" "autocomplete_selection"
    And I click on "Save changes" "link"
    Then I should see "No roles" in the "Bert Beer" "table_row"
