@enrol @enrol_semco
Feature: SEMCO plugin settings page
  In order to connect Moodle to SEMCO and to configure the enrolment behaviour
  As an administrator
  I need to see the connection information and the configuration options on the settings page

  # "Connection information" section

  Scenario: The settings page shows the connection information in the connection information section
    When I am on the "enrol_semco > Settings" page logged in as "admin"
    Then I should see "Connection information"
    # The "Moodle base URL" and "Webservice token" labels are shown within the "Connection information" section.
    And I should see "Moodle base URL" "text" in the "Connection information" settings section
    And I should see "Webservice token" "text" in the "Connection information" settings section
    # The shown values actually match the Moodle configuration and the database.
    And I should see the SEMCO Moodle base URL in the "Connection information" settings section
    And I should see the SEMCO webservice token in the "Connection information" settings section

  @javascript
  Scenario: The settings page shows an error message in the connection information section when no webservice token exists
    When I log in as "admin"
    And I navigate to "Server > Web services > Manage tokens" in site administration
    And I change the window size to "large"
    And I press "Delete" action in the "SEMCO Webservice" report row
    And I should see "Do you really want to delete this web service token"
    And I press "Delete"
    And "SEMCO Webservice" "table_row" should not exist
    When I am on the "enrol_semco > Settings" page
    Then I should see "Connection information"
    And I should see "No existing webservice token was found for the SEMCO webservice user." "text" in the "Connection information" settings section

  # "Enrolment report" section

  Scenario: The settings page shows a report button in the enrolment report section
    When I am on the "enrol_semco > Settings" page logged in as "admin"
    Then I should see "Enrolment report"
    # The "View report" link (which is styled as a button) is shown within the "Enrolment report" section.
    And I should see a "View report" "link" in the "Enrolment report" settings section

  # "Enrolment process" section

  Scenario: The settings page shows the enrolment process configuration
    When I am on the "enrol_semco > Settings" page logged in as "admin"
    Then I should see "Enrolment process"
    And I should see "Role" "select" in the "Enrolment process" settings section
    And the "Role" select box should contain "Student"

  Scenario: The configured role is really used for new SEMCO enrolments
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Apple    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    # Change the configured role away from the "Student" role which the plugin has set as default during its installation.
    When I am on the "enrol_semco > Settings" page logged in as "admin"
    And I set the field "Role" to "Non-editing teacher"
    And I press "Save changes"
    # The setting was saved within the webserver process, but the enrolment below is created from within the Behat process
    # which still holds the plugin configuration which it has read when the course above was created.
    And the SEMCO plugin configuration cache is purged
    # Creating this enrolment runs the enrol_user webservice as the SEMCO webservice user, just as SEMCO would do it in real
    # life. The webservice would throw an exception if the SEMCO webservice user was not allowed to assign the newly
    # configured role, so this step also proves that the role assignment permissions have followed the changed setting.
    And the following "enrol_semco > enrolments" exist:
      | user     | course | semcobookingid |
      | student1 | C1     | BOOK-0001      |
    And I am on the "Course 1" "enrolled users" page
    # The user is enrolled with the newly configured role and not with the previously configured "Student" role anymore.
    Then "Alice Apple" row "Roles" column of "participants" table should contain "Non-editing teacher"
    And "Alice Apple" row "Roles" column of "participants" table should not contain "Student"

  Scenario: Changing the configured role allows the SEMCO webservice role to assign this role
    When I am on the "enrol_semco > Settings" page logged in as "admin"
    And I set the field "Role" to "Non-editing teacher"
    And I press "Save changes"
    And I navigate to "Users > Permissions > Define roles" in site administration
    And I click on "Allow role assignments" "link"
    # In the role assignment matrix, the checkbox for the SEMCO webservice role and the newly configured role is ticked now.
    Then the field "Allow users with role SEMCO webservice to assign the role Non-editing teacher" matches value "1"
    # Cross-check: the permission for the "Student" role which was set during the plugin installation is still there as the
    # plugin only adds the permission for the newly configured role and does not revoke the previous one.
    And the field "Allow users with role SEMCO webservice to assign the role Student" matches value "1"

  # "Course completion" section

  Scenario Outline: The settings page reports in the course completion section whether local_recompletion is installed
    # The presence of the companion plugin is simulated as a Behat run cannot install or uninstall it on the fly. That way,
    # both notifications are covered no matter if local_recompletion is really present in this installation or not.
    Given local_recompletion is simulated to be "<state>"
    When I am on the "enrol_semco > Settings" page logged in as "admin"
    Then I should see "Course completion"
    And I should see a "<shownnotification>" "text" in the "Course completion" settings section
    And I should not see a "<hiddennotification>" "text" in the "Course completion" settings section

    Examples:
      | state         | shownnotification                                                | hiddennotification                                               |
      | installed     | The plugin local_recompletion is installed with at least version | Please install local_recompletion with at least version          |
      | not installed | Please install local_recompletion with at least version          | The plugin local_recompletion is installed with at least version |
