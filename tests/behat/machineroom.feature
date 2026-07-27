@enrol @enrol_semco
Feature: SEMCO plugin machine room
  In order to have a fully functional SEMCO plugin installation
  As an administrator
  I need to be sure that the machine room features work as well

  Scenario: The queued ad-hoc task assigns the webservice protocol capability to the SEMCO webservice role
    # To start with, make sure that the ad-hoc task is queued, just like after a fresh installation.
    Given the SEMCO webservice role is in the state of an initial Moodle installation
    # Thus, the ad-hoc task is waiting in the queue.
    When I log in as "admin"
    And I navigate to "Server > Tasks > Ad hoc tasks" in site administration
    Then "set_webservice_capability" "table_row" should exist
    # And the webservice protocol capability is not assigned to the SEMCO webservice role yet.
    When I navigate to "Users > Permissions > Capability overview" in site administration
    And I set the following fields to these values:
      | Capability: | webservice/rest:use |
      | Roles:      | SEMCO webservice    |
    And I click on "Get the overview" "button"
    Then I should see "Not set" in the "comparisontable" "table"
    # Cross-check: a capability which the plugin was able to assign directly during the installation is assigned already.
    When I set the following fields to these values:
      | Capability: | enrol/semco:usewebservice |
      | Roles:      | SEMCO webservice          |
    And I click on "Get the overview" "button"
    Then I should see "Allow" in the "comparisontable" "table"
    # After the ad-hoc task has run, the webservice protocol capability is assigned to the SEMCO webservice role.
    When I run all adhoc tasks
    And I navigate to "Users > Permissions > Capability overview" in site administration
    And I set the following fields to these values:
      | Capability: | webservice/rest:use |
      | Roles:      | SEMCO webservice    |
    And I click on "Get the overview" "button"
    Then I should see "Allow" in the "comparisontable" "table"
    # And the ad-hoc task has left the queue as it has done its job.
    When I navigate to "Server > Tasks > Ad hoc tasks" in site administration
    Then "set_webservice_capability" "table_row" should not exist

  @javascript
  Scenario: The scheduled task cleans up SEMCO enrolment instances which were orphaned by a user deletion
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    # To start with, the SEMCO enrolment instance exists in the course.
    When I am on the "Course 1" "enrolment methods" page logged in as "admin"
    Then "SEMCO [Booking ID: BOOK-0001]" "table_row" should exist
    # SEMCO enrolment instances are only removed properly when the user is unenrolled through the SEMCO unenrolment
    # webservice. Deleting the enrolled user in Moodle removes the user enrolment, but it leaves the enrolment instance
    # behind as an orphan.
    When I navigate to "Users > Accounts > Browse list of users" in site administration
    And I press "Delete" action in the "Alice Apple" report row
    And I should see "Are you sure you want to delete user Alice Apple" in the "Delete user" "dialogue"
    And I click on "Delete" "button" in the "Delete user" "dialogue"
    And I am on the "Course 1" "enrolment methods" page
    Then "SEMCO [Booking ID: BOOK-0001]" "table_row" should exist
    # After the scheduled task has run, the orphaned enrolment instance is gone.
    When I run the scheduled task "\enrol_semco\task\cleanup_orphaned_enrolment_instances"
    And I am on the "Course 1" "enrolment methods" page
    Then "SEMCO [Booking ID: BOOK-0001]" "table_row" should not exist
    # Cross-check: the manual enrolment instance which every course has is not touched by the task.
    And "Manual enrolments" "table_row" should exist

  Scenario: The CLI script recreates the webservice token
    # To start with, the webservice token which was created during the installation is shown on the settings page.
    Given I remember the SEMCO webservice token
    When I am on the "enrol_semco > Settings" page logged in as "admin"
    Then I should see the SEMCO webservice token in the "Connection information" settings section
    # And it is listed on the manage tokens page without any expiry date.
    When I navigate to "Server > Web services > Manage tokens" in site administration
    Then I should see "This token has no expiry date." in the "SEMCO Webservice" "table_row"
    # After the CLI script has run, the SEMCO webservice user has a new token.
    When I run the SEMCO webservice token CLI script with the options "--until=2099-12-31 --ip=192.168.1.0/24"
    Then the SEMCO CLI script output should contain "Success: Old webservice token has been deleted."
    And the SEMCO CLI script output should contain "Success: New webservice token has been created."
    And the SEMCO webservice token has changed
    # The new token is shown on the settings page.
    When I am on the "enrol_semco > Settings" page
    Then I should see the SEMCO webservice token in the "Connection information" settings section
    # And it is listed on the manage tokens page with the restrictions which were passed to the CLI script.
    # The fact that the token is listed there at all is worth checking as the token would be invisible on this page if the
    # CLI script did not set the token's creator ID explicitly after having generated the token.
    When I navigate to "Server > Web services > Manage tokens" in site administration
    Then I should see "192.168.1.0/24" in the "SEMCO Webservice" "table_row"
    And I should see "##2099-12-31 23:59:59##%d %B %Y, %I:%M %p##" in the "SEMCO Webservice" "table_row"
