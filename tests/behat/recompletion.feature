@enrol @enrol_semco @local_recompletion
Feature: SEMCO course completion reset with local_recompletion
  In order to let a user attend the same course again
  As SEMCO (through the enrolment webservices)
  I need to reset the user's course completion with the companion plugin local_recompletion

  # Please note: All scenarios in this feature file require the optional companion plugin local_recompletion to be
  # installed. If you run the Behat tests of this plugin in an installation without local_recompletion, exclude this
  # feature file by adding --tags=~@local_recompletion to your Behat run.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 1 | C1        | topics | 1                |
    And the following "activities" exist:
      | activity | name        | course | idnumber | completion |
      | assign   | Assignment1 | C1     | assign1  | 1          |
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |

  @javascript
  Scenario: SEMCO resets the completion and the grade of a user who has worked in the course
    Given the following SEMCO course recompletion settings are set:
      | course | recompletiontype | deletegradedata |
      | C1     | ondemand         | 1               |
    And the following "grade grades" exist:
      | gradeitem   | user     | grade |
      | Assignment1 | student1 | 42    |
    # The user completes the activity in the course.
    When I am on the "Course 1" course page logged in as "student1"
    And I toggle the manual completion state of "Assignment1"
    Then the manual completion button of "Assignment1" is displayed as "Done"
    # The completion has arrived in the course's activity completion report (which only staff can see, so we have to
    # switch to the admin for this check as well as for the grade check below).
    And I am on the "Course 1" course page logged in as "admin"
    And "Alice Apple" user has completed "Assignment1" activity
    # And the user has a grade.
    And I am on the "Course 1" "grades > Grader report > View" page
    And I should see "42.00" in the "Alice Apple" "table_row"
    # SEMCO resets the course completion (which is what it does when the user is enrolled into the course once more).
    When the following SEMCO course completions are reset:
      | semcobookingid |
      | BOOK-0001      |
    # The activity completion is gone.
    Then I am on the "Course 1" course page
    And "Alice Apple" user has not completed "Assignment1" activity
    # And the grade is gone as well as we have enabled the grade deletion in the recompletion settings.
    And I am on the "Course 1" "grades > Grader report > View" page
    And I should not see "42.00" in the "Alice Apple" "table_row"
    # The user sees the activity as not completed again.
    And I am on the "Course 1" course page logged in as "student1"
    And the manual completion button of "Assignment1" is displayed as "Mark as done"

  @javascript
  Scenario: SEMCO keeps the grade of a user if the recompletion settings say so
    Given the following SEMCO course recompletion settings are set:
      | course | recompletiontype | deletegradedata |
      | C1     | ondemand         | 0               |
    And the following "grade grades" exist:
      | gradeitem   | user     | grade |
      | Assignment1 | student1 | 42    |
    # The user completes the activity in the course.
    When I am on the "Course 1" course page logged in as "student1"
    And I toggle the manual completion state of "Assignment1"
    Then the manual completion button of "Assignment1" is displayed as "Done"
    # SEMCO resets the course completion.
    When the following SEMCO course completions are reset:
      | semcobookingid |
      | BOOK-0001      |
    # The activity completion is gone.
    Then I am on the "Course 1" course page logged in as "admin"
    And "Alice Apple" user has not completed "Assignment1" activity
    # But the grade is still there as we have disabled the grade deletion in the recompletion settings.
    And I am on the "Course 1" "grades > Grader report > View" page
    And I should see "42.00" in the "Alice Apple" "table_row"

  @javascript
  Scenario: SEMCO resets the completion of a user who has completed the whole course
    Given the following SEMCO course recompletion settings are set:
      | course | recompletiontype |
      | C1     | ondemand         |
    # Make the course completable by declaring the activity completion as the course completion criterion. Without such a
    # criterion, Moodle would never consider the course as completed and the report below would stay empty.
    And I am on the "Course 1" course page logged in as "admin"
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the field "Assignment - Assignment1" to "1"
    And I press "Save changes"
    # The user completes the activity, which completes the whole course. Moodle aggregates the course completion in a
    # scheduled task, which is run twice as the criterion completion and the course completion are aggregated one after
    # the other.
    And I am on the "Course 1" course page logged in as "student1"
    And I toggle the manual completion state of "Assignment1"
    And I run the scheduled task "core\task\completion_regular_task"
    And I wait "1" seconds
    And I run the scheduled task "core\task\completion_regular_task"
    # The course completion report shows the user as having completed the course.
    When I am on the "Course 1" course page logged in as "admin"
    And I navigate to "Reports" in current page administration
    And I click on "Course completion" "link" in the "region-main" "region"
    Then "Alice Apple, Course complete: Completed" "icon" should exist in the "completionreport" "table"
    # SEMCO resets the course completion.
    When the following SEMCO course completions are reset:
      | semcobookingid |
      | BOOK-0001      |
    # The report shows the user as not having completed the course anymore.
    And I am on the "Course 1" course page
    And I navigate to "Reports" in current page administration
    And I click on "Course completion" "link" in the "region-main" "region"
    Then "Alice Apple, Course complete: Not completed" "icon" should exist in the "completionreport" "table"
    And "Alice Apple, Course complete: Completed" "icon" should not exist in the "completionreport" "table"

  Scenario: SEMCO is told that course recompletion is not enabled in the course at all
    # The course does not have any recompletion settings, which is the state of a course where the teacher has never
    # touched the course recompletion settings page.
    When SEMCO tries to reset the course completion of the SEMCO enrolment "BOOK-0001"
    Then the SEMCO course completion reset should have failed with a message containing "Course recompletion is not enabled at all"

  Scenario Outline: SEMCO is told that course recompletion is not set to 'On demand' in the course
    Given the following SEMCO course recompletion settings are set:
      | course | recompletiontype   |
      | C1     | <recompletiontype> |
    When SEMCO tries to reset the course completion of the SEMCO enrolment "BOOK-0001"
    Then the SEMCO course completion reset should have failed with a message containing "Course recompletion is not set to 'On demand'"

    Examples:
      | recompletiontype |
      | period           |
      | schedule         |

  Scenario: SEMCO is told that course recompletion is disabled in the course
    Given the following SEMCO course recompletion settings are set:
      | course | recompletiontype |
      | C1     | disabled         |
    When SEMCO tries to reset the course completion of the SEMCO enrolment "BOOK-0001"
    Then the SEMCO course completion reset should have failed with a message containing "Course recompletion is not enabled at all"

  Scenario: SEMCO enrols a user into a course which requires an enabled course recompletion
    # A second user is needed here as the plugin rejects a second enrolment of the same user into the same course with an
    # overlapping enrolment period, and the user from the background is already enrolled without any period restriction.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Bob       | Banana   | student2@example.com |
    And the following SEMCO course recompletion settings are set:
      | course | recompletiontype |
      | C1     | ondemand         |
    # The enrolment webservice accepts the enrolment as the course has an on-demand recompletion. If it did not, the
    # generator below would throw an exception and this scenario would fail.
    When the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid | requirerecompletion |
      | student2 | C1     | BOOK-0002      | 1                   |
    Then I am on the "Course 1" "enrolment methods" page logged in as "admin"
    And I should see "SEMCO [Booking ID: BOOK-0002]"
