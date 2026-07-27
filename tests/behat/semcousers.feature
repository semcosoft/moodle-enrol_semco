@enrol @enrol_semco
Feature: SEMCO user management through the Moodle core webservices
  In order to keep its user base in sync with Moodle
  As SEMCO (through the Moodle core user webservices)
  I need creating, updating and deleting users (including their SEMCO profile fields) to work correctly

  Scenario: SEMCO creates a user with the Moodle core webservice core_user_create_users (and sets all SEMCO profile fields)
    # Before the creation, the user is not listed in the system-wide user list.
    Given I log in as "admin"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should not see "Sam Semco" in the "reportbuilder-table" "table"
    # SEMCO creates the user through the core user webservice, providing all five SEMCO profile fields.
    When the following "enrol_semco > users" exist:
      | username    | firstname | lastname | email                   | password  | semco_userid | semco_usercompany | semco_userbirthday | semco_userplaceofbirth | semco_branchtoken |
      | semcolerner | Sam       | Semco    | semcolerner@example.com | Test1234# | SEMCO-4711   | ACME Corporation  | 1990-01-01         | Springfield            | TENANT-01         |
    # Now the user is listed in the system-wide user list.
    When I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "Sam Semco" in the "reportbuilder-table" "table"
    # On the user's edit page (user/editadvanced.php), each SEMCO profile field directly holds its value in its own form
    # field, so the value can be verified against the exact field it belongs to. An administrator can see and edit these
    # (otherwise locked and invisible) fields thanks to the moodle/user:update capability.
    When I am on the "semcolerner" "user > editing" page logged in as "admin"
    Then the field "SEMCO User ID" matches value "SEMCO-4711"
    And the field "SEMCO User company" matches value "ACME Corporation"
    And the field "SEMCO User birthday" matches value "1990-01-01"
    And the field "SEMCO User place of birth" matches value "Springfield"
    And the field "SEMCO Tenant shortname" matches value "TENANT-01"
    # As the user does not have a SEMCO booking yet, he is not listed in the report.
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then I should see "There are not any SEMCO enrolments yet in this Moodle instance"

  Scenario: SEMCO updates a user with the Moodle core webservice core_user_update_users (and updates the SEMCO profile fields)
    # SEMCO first creates the user with an initial set of SEMCO profile field data.
    Given the following "enrol_semco > users" exist:
      | username    | firstname | lastname | email                   | password  | semco_userid | semco_usercompany | semco_userbirthday | semco_userplaceofbirth | semco_branchtoken |
      | semcolerner | Sam       | Semco    | semcolerner@example.com | Test1234# | SEMCO-4711   | Old Company       | 1990-01-01         | Old Town               | TENANT-01         |
    # SEMCO then updates the user with a new set of SEMCO profile field data.
    When the following users are updated by SEMCO:
      | username    | semco_userid | semco_usercompany | semco_userbirthday | semco_userplaceofbirth | semco_branchtoken |
      | semcolerner | SEMCO-8888   | New Company       | 1985-12-31         | New Town               | TENANT-02         |
    # The user still exists, i.e. is still listed in the system-wide user list.
    When I log in as "admin"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "Sam Semco" in the "reportbuilder-table" "table"
    # On the user's edit page, each SEMCO profile field now holds the updated value in its own form field. As the field can
    # only hold one value, matching it against the new value inherently proves that the old value is gone.
    When I am on the "semcolerner" "user > editing" page logged in as "admin"
    Then the field "SEMCO User ID" matches value "SEMCO-8888"
    And the field "SEMCO User company" matches value "New Company"
    And the field "SEMCO User birthday" matches value "1985-12-31"
    And the field "SEMCO User place of birth" matches value "New Town"
    And the field "SEMCO Tenant shortname" matches value "TENANT-02"

  Scenario: SEMCO suspends and unsuspends a user with the Moodle core webservice core_user_update_users
    # The user below gets its username as its password, as this is what the "I log in as" step expects. Such a password does
    # not satisfy Moodle's default password policy, though, and core_user_create_users enforces that policy. We therefore
    # switch the policy off for this scenario, which is the same thing that the Moodle core data generators do implicitly.
    Given the following config values are set as admin:
      | passwordpolicy | 0 |
    # SEMCO creates the user. Its password equals its username so that the login attempts below can be performed. The account
    # is active at this point.
    And the following "enrol_semco > users" exist:
      | username    | firstname | lastname | email                   | password    | semco_userid |
      | semcolerner | Sam       | Semco    | semcolerner@example.com | semcolerner | SEMCO-4711   |
    # While the account is active, the user can log in.
    When I log in as "semcolerner"
    Then I should see "Sam Semco"
    # SEMCO suspends the user account.
    When the following users are updated by SEMCO:
      | username    | suspended |
      | semcolerner | 1         |
    # The account is now marked as suspended in the system-wide user list.
    And I log in as "admin"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "Suspended" in the "Sam Semco" "table_row"
    # And the suspended user can no longer log in.
    When I log in as "semcolerner"
    Then I should see "User suspended"
    # SEMCO unsuspends the user account.
    When the following users are updated by SEMCO:
      | username    | suspended |
      | semcolerner | 0         |
    # The account is no longer marked as suspended in the system-wide user list.
    And I log in as "admin"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should not see "Suspended" in the "Sam Semco" "table_row"
    # And the user can log in again.
    When I log in as "semcolerner"
    Then I should see "Sam Semco"

  Scenario: SEMCO deletes a user with the Moodle core webservice core_user_delete_users
    # SEMCO creates the user, who then is listed in the system-wide user list.
    Given the following "enrol_semco > users" exist:
      | username    | firstname | lastname | email                   | password  | semco_userid |
      | semcolerner | Sam       | Semco    | semcolerner@example.com | Test1234# | SEMCO-4711   |
    When I log in as "admin"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "Sam Semco" in the "reportbuilder-table" "table"
    # SEMCO enrols the user into a course, so that the user appears in the report.
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "enrol_semco > enrolments" exist:
      | user        | course | semcobookingid |
      | semcolerner | C1     | BOOK-0001      |
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then the following should exist in the "enrolsemco_enrolreport" table:
      | Moodle Username | SEMCO booking ID |
      | semcolerner     | BOOK-0001        |
    # SEMCO deletes the user.
    When the following users are deleted by SEMCO:
      | username    |
      | semcolerner |
    # The user is deleted, i.e. no longer listed in the system-wide user list.
    When I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should not see "Sam Semco" in the "reportbuilder-table" "table"
    # And thus the user no longer appears in the report (which only lists non-deleted users).
    When I am on the "enrol_semco > report" page logged in as "admin"
    Then I should see "There are not any SEMCO enrolments yet in this Moodle instance"
