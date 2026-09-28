@enrol @enrol_semco
Feature: SEMCO health check
  In order to make sure that the Moodle setup which SEMCO needs is still intact
  As an administrator
  I need to be able to view and to repair the SEMCO health check

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | manager  | Max       | Manager  | manager@example.com |
      | teacher  | Terry     | Teacher  | teacher@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "system role assigns" exist:
      | user    | course               | role    |
      | manager | Acceptance test site | manager |

  Scenario: An administrator reaches the health check from the plugin settings page
    When I log in as "admin"
    And I navigate to "Plugins > Enrolments > SEMCO" in site administration
    And I follow "View health check"
    Then I should see "SEMCO health check"
    And I should see "Webservice infrastructure"

  Scenario: An administrator finds the health check linked in the site administration
    When I log in as "admin"
    And I follow "Site administration"
    Then "SEMCO health check" "link" should exist
    When I follow "SEMCO health check"
    Then I should see "Webservice infrastructure"

  Scenario: A manager (and not only the admin) finds the health check linked in the site administration as well
    # In contrast to the enrolment report, the health check capability is not granted to the manager role by default,
    # see the capability chapter of the plugin's README. It therefore has to be granted explicitly here.
    Given the following "permission overrides" exist:
      | capability                  | permission | role    | contextlevel | reference |
      | enrol/semco:viewhealthcheck | Allow      | manager | System       |           |
    When I log in as "manager"
    And I follow "Site administration"
    Then "SEMCO health check" "link" should exist
    When I follow "SEMCO health check"
    Then I should see "Webservice infrastructure"

  Scenario: A user without the viewhealthcheck capability neither finds the health check link nor reaches the health check itself
    When I log in as "teacher"
    Then I should not see "Site administration"
    When I am on the "Course 1" course page
    Then "SEMCO health check" "link" should not exist
    And the SEMCO health check should refuse the access

  Scenario: A manager without the viewhealthcheck capability finds the enrolment report but not the health check
    # This is the consequence of the capability not being granted to the manager role by default: A manager reaches the
    # site administration and sees the enrolment report there, but the health check stays out of his reach.
    When I log in as "manager"
    And I follow "Site administration"
    Then "SEMCO enrolments" "link" should exist
    And "SEMCO health check" "link" should not exist
    And the SEMCO health check should refuse the access

  Scenario: The health check reports an intact installation right after the plugin installation
    # If this Behat site was set up with an initial Moodle installation, the plugin could not assign the REST
    # capability directly and queued an ad-hoc task instead. Running the ad-hoc tasks completes the installation, just
    # as the first cron run does on a real site.
    Given I run all adhoc tasks
    When I am on the "enrol_semco > healthcheck" page logged in as "admin"
    Then the following should exist in the "healthchecks-webservice" table:
      | Status | Check                                 |
      | OK     | Webservice subsystem                  |
      | OK     | Webservice REST protocol              |
      | OK     | Web services authentication           |
      | OK     | SEMCO external service                |
      | OK     | SEMCO external service: Configuration |
      | OK     | SEMCO external service: Functions     |
    And the following should exist in the "healthchecks-plugin" table:
      | Status | Check                                        |
      | OK     | SEMCO enrolment method enabled               |
      | OK     | SEMCO enrolment role                         |
      | OK     | SEMCO capabilities registered                |
      | OK     | Exclusiveness of the webservice capabilities |
    And the following should exist in the "healthchecks-role" table:
      | Status | Check                                           |
      | OK     | SEMCO webservice role                           |
      | OK     | SEMCO webservice role: Context                  |
      | OK     | SEMCO webservice role: Plugin capabilities      |
      | OK     | SEMCO webservice role: Moodle core capabilities |
      | OK     | SEMCO webservice role: REST capability          |
      | OK     | SEMCO webservice role: Allowed role assignments |
    And the following should exist in the "healthchecks-user" table:
      | Status | Check                                        |
      | OK     | SEMCO webservice user                        |
      | OK     | SEMCO webservice user: Authentication method |
      | OK     | SEMCO webservice user: Account state         |
      | OK     | SEMCO webservice user: Profile               |
      | OK     | SEMCO webservice user: Role assignment       |
      | OK     | SEMCO webservice user: Service authorisation |
      | OK     | SEMCO webservice user: IP restriction        |
    And the following should exist in the "healthchecks-token" table:
      | Status | Check                                  |
      | OK     | SEMCO webservice token                 |
      | OK     | SEMCO webservice token: IP restriction |
    And the following should exist in the "healthchecks-profilefields" table:
      | Status | Check                                      |
      | OK     | SEMCO user profile fields: Category        |
      | OK     | SEMCO user profile field: User ID          |
      | OK     | SEMCO user profile field: Company          |
      | OK     | SEMCO user profile field: Birthday         |
      | OK     | SEMCO user profile field: Place of birth   |
      | OK     | SEMCO user profile field: Tenant shortname |
    # On top of the rows above, none of the categories which cover the plugin installation may hold a notice, a warning
    # or an error badge at all. This also catches the items which are not listed by name above.
    # The recompletion and the recommendations categories are excluded on purpose: They check the configuration of a
    # companion plugin and global Moodle settings which a stock Moodle instance does not necessarily follow, so they
    # are expected to hold badges here.
    And "//table[starts-with(@id, 'healthchecks-') and @id != 'healthchecks-recompletion' and @id != 'healthchecks-recommendations']//span[contains(@class, 'text-bg-info')]" "xpath_element" should not exist
    And "//table[starts-with(@id, 'healthchecks-') and @id != 'healthchecks-recompletion' and @id != 'healthchecks-recommendations']//span[contains(@class, 'text-bg-warning')]" "xpath_element" should not exist
    And "//table[starts-with(@id, 'healthchecks-') and @id != 'healthchecks-recompletion' and @id != 'healthchecks-recommendations']//span[contains(@class, 'text-bg-danger')]" "xpath_element" should not exist

  Scenario: The health check reports the queued ad-hoc task to add the missing REST capability as notice and not as a broken installation
    # The state which this scenario needs - the REST capability missing while the ad-hoc task which is going to assign
    # it is still queued - cannot simply be assumed: It only exists if this Behat site was set up with an initial
    # Moodle installation. If the plugin was added to an existing Moodle instead, the installer assigned the capability
    # directly and never queued a task. The step below therefore establishes the state explicitly, so that this
    # scenario tests the ad-hoc task path on any Behat site.
    Given the SEMCO webservice role is in the state of an initial Moodle installation
    When I am on the "enrol_semco > healthcheck" page logged in as "admin"
    Then the following should exist in the "healthchecks-role" table:
      | Status | Check                                  |
      | Notice | SEMCO webservice role: REST capability |

  Scenario: The health check reports the missing REST capability without a pending ad-hoc task as error
    # If this Behat site was set up with an initial Moodle installation, the plugin could not assign the capability
    # directly and queued an ad-hoc task instead. Running the ad-hoc tasks empties that queue, so that revoking the
    # capability below really is a state which does not resolve itself anymore.
    Given I run all adhoc tasks
    And I log in as "admin"
    And I navigate to "Users > Permissions > Define roles" in site administration
    And I click on "Edit SEMCO webservice role" "link"
    And I fill the capabilities form with the following permissions:
      | capability          | permission |
      | webservice/rest:use | Inherit    |
    And I press "Save changes"
    When I am on the "enrol_semco > healthcheck" page
    Then the following should exist in the "healthchecks-role" table:
      | Status | Check                                  |
      | Error  | SEMCO webservice role: REST capability |

  Scenario: The health check allows an administrator to fix the missing REST capability automatically
    Given I run all adhoc tasks
    And I log in as "admin"
    And I navigate to "Users > Permissions > Define roles" in site administration
    And I click on "Edit SEMCO webservice role" "link"
    And I fill the capabilities form with the following permissions:
      | capability          | permission |
      | webservice/rest:use | Inherit    |
    And I press "Save changes"
    And I am on the "enrol_semco > healthcheck" page
    And the following should exist in the "healthchecks-role" table:
      | Status | Check                                  |
      | Error  | SEMCO webservice role: REST capability |
    When I click on "Fix the check automatically" "link" in the "SEMCO webservice role: REST capability" "table_row"
    Then I should see "The check has been fixed automatically."
    And the following should exist in the "healthchecks-role" table:
      | Status | Check                                  |
      | OK     | SEMCO webservice role: REST capability |

  Scenario: The health check reports an aspect which stops the SEMCO integration as error
    Given the following config values are set as admin:
      | enablewebservices | 0 |
    When I am on the "enrol_semco > healthcheck" page logged in as "admin"
    Then the following should exist in the "healthchecks-webservice" table:
      | Status | Check                    |
      | Error  | Webservice subsystem     |
      | OK     | Webservice REST protocol |

  @javascript
  Scenario: The health check reports an aspect which the SEMCO integration survives as warning
    # The SEMCO webservice role is already assigned to the SEMCO webservice user in the system context. Removing the
    # system context from the role's context types does not revoke that existing assignment, so the SEMCO integration
    # keeps working and the health check reports a warning instead of an error.
    #
    # This scenario needs JavaScript: The context type controls of the role definition page are checkboxes which each
    # carry a hidden input of the same name to submit the unchecked state. Without a real browser, the form handling
    # keeps only one field per name, the hidden input is lost and unchecking the box does not reach the server.
    Given I log in as "admin"
    And I navigate to "Users > Permissions > Define roles" in site administration
    And I click on "Edit SEMCO webservice role" "link"
    And I set the field "System" to ""
    And I press "Save changes"
    When I am on the "enrol_semco > healthcheck" page
    Then the following should exist in the "healthchecks-role" table:
      | Status  | Check                         |
      | Warning | SEMCO webservice role: Context |
    # The breakage must not escalate to an error anywhere in the plugin installation.
    And "//table[starts-with(@id, 'healthchecks-') and @id != 'healthchecks-recompletion' and @id != 'healthchecks-recommendations']//span[contains(@class, 'text-bg-danger')]" "xpath_element" should not exist

  Scenario: The health check reports an aspect which is only a recommendation as notice
    # In contrast to the two scenarios above, a recommendation never expresses a broken plugin installation. It only
    # points at a global Moodle setting which suits a SEMCO integration better, so it never exceeds a notice.
    # Unique email addresses are enforced by a stock Moodle, therefore this setting has to be changed explicitly here.
    Given the following config values are set as admin:
      | allowaccountssameemail | 1 |
    When I am on the "enrol_semco > healthcheck" page logged in as "admin"
    Then the following should exist in the "healthchecks-recommendations" table:
      | Status | Check                  |
      | Notice | Unique email addresses |

  Scenario: The plugin settings page raises the awareness if a check needs attention
    Given the following config values are set as admin:
      | enablewebservices | 0 |
    When I log in as "admin"
    And I navigate to "Plugins > Enrolments > SEMCO" in site administration
    Then I should see "Some checks of the SEMCO health check need your attention."

  Scenario: The plugin settings page stays quiet if no check needs attention
    # The health check covers the recommended global Moodle settings as well, and a stock Moodle does not follow all of
    # them. Thus, these checks have to be muted first, just as an admin would do who has decided against them.
    Given all SEMCO health checks which need attention are muted
    When I log in as "admin"
    And I navigate to "Plugins > Enrolments > SEMCO" in site administration
    Then I should see "View health check"
    And I should not see "Some checks of the SEMCO health check need your attention."

  Scenario: The health check is reported as fine to the Moodle system status report if no check needs attention
    # The health check covers the recommended global Moodle settings as well, and a stock Moodle does not follow all of
    # them. Thus, these checks have to be muted first, just as an admin would do who has decided against them.
    Given all SEMCO health checks which need attention are muted
    When I log in as "admin"
    And I navigate to "Reports > System status" in site administration
    Then the following should exist in the "statusreporttable" table:
      | Check              | Status |
      | SEMCO health check | OK     |
    And I should see "All aspects of the SEMCO setup are in the desired state." in the "SEMCO health check" "table_row"
    # The details of the check lead to the health check page.
    When I click on "More info" "link" in the "SEMCO health check" "table_row"
    Then I should see "Review the affected aspects on the SEMCO health check page."
    And I follow "SEMCO health check"
    And I should see "Webservice infrastructure"

  Scenario: A broken aspect of the plugin installation is reported as error to the Moodle system status report
    Given all SEMCO health checks which need attention are muted
    And the following config values are set as admin:
      | enablewebservices | 0 |
    When I log in as "admin"
    And I navigate to "Reports > System status" in site administration
    Then the following should exist in the "statusreporttable" table:
      | Check              | Status |
      | SEMCO health check | Error  |
    And I should see "At least one of them is broken, thus the SEMCO integration does not work at the moment." in the "SEMCO health check" "table_row"
    # The details name the affected check.
    When I click on "More info" "link" in the "SEMCO health check" "table_row"
    Then I should see "Webservice subsystem"

  Scenario: A recommendation which is not followed is reported as warning to the Moodle system status report until it is muted
    # The Moodle messaging system is enabled on a stock Moodle, thus this check is muted along with the others as a start
    # and has to be unmuted explicitly here.
    Given all SEMCO health checks which need attention are muted
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    And I click on "Unmute check" "link" in the "Moodle messaging system" "table_row"
    When I navigate to "Reports > System status" in site administration
    Then the following should exist in the "statusreporttable" table:
      | Check              | Status  |
      | SEMCO health check | Warning |
    And I should see "None of them is broken, thus the SEMCO integration still works at the moment." in the "SEMCO health check" "table_row"
    When I am on the "enrol_semco > healthcheck" page
    And I click on "Mute check" "link" in the "Moodle messaging system" "table_row"
    And I navigate to "Reports > System status" in site administration
    Then the following should exist in the "statusreporttable" table:
      | Check              | Status |
      | SEMCO health check | OK     |

  Scenario: A recommendation which only deserves a notice is reported as information to the Moodle system status report
    # A notice neither breaks nor endangers the SEMCO integration, thus it does not deserve a warning on the system status
    # report. Unique email addresses are enforced by a stock Moodle, therefore this setting has to be changed explicitly.
    Given all SEMCO health checks which need attention are muted
    And the following config values are set as admin:
      | allowaccountssameemail | 1 |
    When I log in as "admin"
    And I navigate to "Reports > System status" in site administration
    Then the following should exist in the "statusreporttable" table:
      | Check              | Status |
      | SEMCO health check | Info   |
    And I should see "None of them affects the SEMCO integration, they are worth a look nonetheless." in the "SEMCO health check" "table_row"

  Scenario: An administrator fixes a broken aspect of the plugin installation automatically
    Given the following config values are set as admin:
      | enablewebservices | 0 |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    When I click on "Fix the check automatically" "link" in the "Webservice subsystem" "table_row"
    Then I should see "The check has been fixed automatically."
    And I should not see "However, the automatic fix could not do everything for you:"
    And the following should exist in the "healthchecks-webservice" table:
      | Status | Check                |
      | OK     | Webservice subsystem |

  Scenario: An administrator is told what is left to do after an automatic fix which cannot do the whole job
    Given the SEMCO webservice token is deleted
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    And the following should exist in the "healthchecks-token" table:
      | Status | Check                  |
      | Error  | SEMCO webservice token |
    When I click on "Fix the check automatically" "link" in the "SEMCO webservice token" "table_row"
    Then I should see "The check has been fixed automatically."
    And I should see "However, the automatic fix could not do everything for you:"
    And I should see "A new webservice token has been created, but a new token does not connect SEMCO to Moodle on its own."
    And the following should exist in the "healthchecks-token" table:
      | Status | Check                  |
      | OK     | SEMCO webservice token |

  @javascript
  Scenario: The health check shows the details of a broken aspect in a modal
    Given the following config values are set as admin:
      | enablewebservices | 0 |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    When I click on "More info" "link" in the "Webservice subsystem" "table_row"
    Then I should see "Current status" in the "Webservice subsystem" "dialogue"
    And I should see "Possible solutions" in the "Webservice subsystem" "dialogue"
    And I should see "SEMCO communicates with Moodle through Moodle webservices" in the "Webservice subsystem" "dialogue"

  @javascript
  Scenario: The health check names the concrete finding of a broken aspect
    # A single finding is rendered as a paragraph below the singular heading, not as a one-item list.
    Given the following "permission overrides" exist:
      | capability         | permission | role            | contextlevel | reference |
      | moodle/user:create | Prevent    | semcowebservice | System       |           |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    Then the following should exist in the "healthchecks-role" table:
      | Status | Check                                           |
      | Error  | SEMCO webservice role: Moodle core capabilities |
    When I click on "More info" "link" in the "SEMCO webservice role: Moodle core capabilities" "table_row"
    Then I should see "Finding" in the "SEMCO webservice role: Moodle core capabilities" "dialogue"
    And I should see "The capability moodle/user:create is not allowed" in the "SEMCO webservice role: Moodle core capabilities" "dialogue"
    And "//div[contains(@class, 'enrol_semco-healthcheckdetails-findings')]" "xpath_element" should not exist

  @javascript
  Scenario: The health check names the concrete findings of a broken aspect as a list
    # As soon as there is more than one finding, the modal switches to the plural heading and a list.
    Given the following "permission overrides" exist:
      | capability         | permission | role            | contextlevel | reference |
      | moodle/user:create | Prevent    | semcowebservice | System       |           |
      | moodle/user:delete | Prevent    | semcowebservice | System       |           |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    When I click on "More info" "link" in the "SEMCO webservice role: Moodle core capabilities" "table_row"
    Then I should see "Findings" in the "SEMCO webservice role: Moodle core capabilities" "dialogue"
    And I should see "The capability moodle/user:create is not allowed" in the "SEMCO webservice role: Moodle core capabilities" "dialogue"
    And I should see "The capability moodle/user:delete is not allowed" in the "SEMCO webservice role: Moodle core capabilities" "dialogue"
    And "//ul[contains(@class, 'enrol_semco-healthcheckdetails-findings')]/li" "xpath_element" should exist

  Scenario: The health check also shows the recommended global Moodle settings
    # In contrast to the categories which cover the plugin installation, the recommendations are not expected to be
    # green on a stock Moodle: They check global Moodle settings which an administrator may well have decided against.
    # The statuses below are the ones which a stock Moodle yields, which is exactly why the intact-installation
    # scenario above excludes this category from its badge assertions.
    When I am on the "enrol_semco > healthcheck" page logged in as "admin"
    Then I should see "Recommended Moodle settings"
    And the following should exist in the "healthchecks-recommendations" table:
      | Status  | Check                                        |
      | OK      | Unique email addresses                       |
      | Notice  | Locked user profile fields                   |
      | OK      | Self enrolment                               |
      | Warning | Participant visibility of the enrolment role |
      | Warning | Moodle messaging system                      |
      | Notice  | Moodle course completion notification        |

  Scenario: The health check also shows the state of the companion plugin local_recompletion
    # Just like the recommendations, this category is not expected to be green on a stock Moodle: It checks the
    # configuration of a companion plugin which the SEMCO plugin installer does not touch. The statuses below are the
    # ones which a freshly installed local_recompletion yields.
    When I am on the "enrol_semco > healthcheck" page logged in as "admin"
    Then I should see "Recompletion plugin"
    And the following should exist in the "healthchecks-recompletion" table:
      | Status  | Check                                   |
      | OK      | Companion plugin local_recompletion     |
      | Warning | Course recompletion: Type               |
      | OK      | Course recompletion: Notification       |
      | Warning | Course recompletion: Activity reset     |
      | Warning | Course recompletion: Self-service reset |
      | Warning | Course recompletion: Settings access    |

  Scenario: A recommendation which is not followed raises an alert on the plugin settings page until it is muted
    Given all SEMCO health checks which need attention are muted
    And the following config values are set as admin:
      | allowaccountssameemail | 1 |
    When I log in as "admin"
    And I navigate to "Plugins > Enrolments > SEMCO" in site administration
    Then I should see "Some checks of the SEMCO health check need your attention."
    And I am on the "enrol_semco > healthcheck" page
    And I click on "Mute check" "link" in the "Unique email addresses" "table_row"
    And I navigate to "Plugins > Enrolments > SEMCO" in site administration
    And I should not see "Some checks of the SEMCO health check need your attention."

  Scenario: An administrator mutes and unmutes a check
    Given the following config values are set as admin:
      | enablewebservices | 0 |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    And "Open setting" "link" should exist in the "Webservice subsystem" "table_row"
    When I click on "Mute check" "link" in the "Webservice subsystem" "table_row"
    Then I should see "The check has been muted."
    And the following should exist in the "healthchecks-webservice" table:
      | Status | Check                |
      | Muted  | Webservice subsystem |
    # A muted check does not offer any way to fix it anymore, but it still explains itself and it can be unmuted again.
    And "Fix the check automatically" "link" should not exist in the "Webservice subsystem" "table_row"
    And "Contact SEMCO support" "link" should not exist in the "Webservice subsystem" "table_row"
    And "Open setting" "link" should not exist in the "Webservice subsystem" "table_row"
    And "Mute check" "link" should not exist in the "Webservice subsystem" "table_row"
    And "More info" "link" should exist in the "Webservice subsystem" "table_row"
    And I click on "Unmute check" "link" in the "Webservice subsystem" "table_row"
    And I should see "The check has been unmuted."
    And the following should exist in the "healthchecks-webservice" table:
      | Status | Check                |
      | Error  | Webservice subsystem |
    And "Fix the check automatically" "link" should exist in the "Webservice subsystem" "table_row"
    And "Open setting" "link" should exist in the "Webservice subsystem" "table_row"

  @javascript
  Scenario: The details modal of a muted check does not name the findings anymore
    Given the following config values are set as admin:
      | enablewebservices | 0 |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    # As long as the check is not muted, the modal names the finding and explains how to solve it.
    When I click on "More info" "link" in the "Webservice subsystem" "table_row"
    Then I should see "thus SEMCO cannot communicate with Moodle at all" in the "Webservice subsystem" "dialogue"
    And I should see "Possible solutions" in the "Webservice subsystem" "dialogue"
    And I click on "Close" "button" in the "Webservice subsystem" "dialogue"
    # As soon as it is muted, the modal only explains the check and its muted status.
    And I click on "Mute check" "link" in the "Webservice subsystem" "table_row"
    And I click on "More info" "link" in the "Webservice subsystem" "table_row"
    And I should see "SEMCO communicates with Moodle through Moodle webservices" in the "Webservice subsystem" "dialogue"
    And I should see "This check is currently muted" in the "Webservice subsystem" "dialogue"
    And I should not see "thus SEMCO cannot communicate with Moodle at all" in the "Webservice subsystem" "dialogue"
    And I should not see "Finding" in the "Webservice subsystem" "dialogue"
    And I should not see "Possible solutions" in the "Webservice subsystem" "dialogue"

  Scenario: A check which is fine does not offer anything to fix
    When I am on the "enrol_semco > healthcheck" page logged in as "admin"
    Then "More info" "link" should exist in the "Webservice subsystem" "table_row"
    And "Mute check" "link" should exist in the "Webservice subsystem" "table_row"
    And "Fix the check automatically" "link" should not exist in the "Webservice subsystem" "table_row"
    And "Contact SEMCO support" "link" should not exist in the "Webservice subsystem" "table_row"
    And "Open setting" "link" should not exist in the "Webservice subsystem" "table_row"

  @javascript
  Scenario: The automatic fix asks for a confirmation which names the finding
    Given the following config values are set as admin:
      | enablewebservices | 0 |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    When I click on "Fix the check automatically" "link" in the "Webservice subsystem" "table_row"
    Then I should see "The automatic fix is going to resolve this finding:" in the "Fix the check automatically" "dialogue"
    # The modal title is too short to hold the title of the check, thus the check is named in the modal body.
    And I should see "Webservice subsystem" in the "Fix the check automatically" "dialogue"
    And I should see "The Moodle webservice subsystem is disabled" in the "Fix the check automatically" "dialogue"
    And I should see "Do you really want to fix this check automatically?" in the "Fix the check automatically" "dialogue"
    # This fix is harmless and it repairs the plugin installation, thus the modal neither warns nor explains anything.
    And I should not see "not entirely harmless" in the "Fix the check automatically" "dialogue"
    And I should not see "not a fault of the SEMCO plugin" in the "Fix the check automatically" "dialogue"
    # Cancelling the modal does not fix anything.
    And I click on "Cancel" "button" in the "Fix the check automatically" "dialogue"
    And the following should exist in the "healthchecks-webservice" table:
      | Status | Check                |
      | Error  | Webservice subsystem |
    # Confirming it does.
    And I click on "Fix the check automatically" "link" in the "Webservice subsystem" "table_row"
    And I click on "Fix automatically" "button" in the "Fix the check automatically" "dialogue"
    And I should see "The check has been fixed automatically."
    And the following should exist in the "healthchecks-webservice" table:
      | Status | Check                |
      | OK     | Webservice subsystem |

  @javascript
  Scenario: The automatic fix confirmation names all findings which are going to be fixed
    Given the following "permission overrides" exist:
      | capability         | permission | role            | contextlevel | reference |
      | moodle/user:create | Prevent    | semcowebservice | System       |           |
      | moodle/user:delete | Prevent    | semcowebservice | System       |           |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    When I click on "Fix the check automatically" "link" in the "SEMCO webservice role: Moodle core capabilities" "table_row"
    Then I should see "The automatic fix is going to resolve these findings:" in the "Fix the check automatically" "dialogue"
    And I should see "The capability moodle/user:create is not allowed" in the "Fix the check automatically" "dialogue"
    And I should see "The capability moodle/user:delete is not allowed" in the "Fix the check automatically" "dialogue"
    And "//ul[contains(@class, 'enrol_semco-healthcheckautofix-findings')]/li" "xpath_element" should exist
    And I click on "Fix automatically" "button" in the "Fix the check automatically" "dialogue"
    And I should see "The check has been fixed automatically."
    And the following should exist in the "healthchecks-role" table:
      | Status | Check                                           |
      | OK     | SEMCO webservice role: Moodle core capabilities |

  @javascript
  Scenario: The automatic fix confirmation warns about a fix which is not entirely harmless and explains a recommendation
    # A stock Moodle has the messaging system enabled. Disabling it takes a feature away from all users of the Moodle
    # instance, and it does not repair the plugin installation but implements a recommendation from the README.
    Given I am on the "enrol_semco > healthcheck" page logged in as "admin"
    And the following should exist in the "healthchecks-recommendations" table:
      | Status  | Check                   |
      | Warning | Moodle messaging system |
    When I click on "Fix the check automatically" "link" in the "Moodle messaging system" "table_row"
    Then I should see "This automatic fix is not entirely harmless" in the "Fix the check automatically" "dialogue"
    And I should see "contact the SEMCO support" in the "Fix the check automatically" "dialogue"
    And I should see "This finding is not a fault of the SEMCO plugin" in the "Fix the check automatically" "dialogue"
    And ".alert-danger" "css_element" should exist in the "Fix the check automatically" "dialogue"
    And I click on "Fix automatically" "button" in the "Fix the check automatically" "dialogue"
    And I should see "The check has been fixed automatically."
    And the following should exist in the "healthchecks-recommendations" table:
      | Status | Check                   |
      | OK     | Moodle messaging system |

  @javascript
  Scenario: The automatic fix confirmation explains a recommendation for the companion plugin local_recompletion
    # A freshly installed local_recompletion does not use the 'On demand' recompletion type as site-wide default.
    # Changing this default is harmless, but it does not repair the plugin installation either: It configures the
    # companion plugin, which the plugin installer does not do.
    Given I am on the "enrol_semco > healthcheck" page logged in as "admin"
    And the following should exist in the "healthchecks-recompletion" table:
      | Status  | Check                     |
      | Warning | Course recompletion: Type |
    When I click on "Fix the check automatically" "link" in the "Course recompletion: Type" "table_row"
    Then I should see "Course recompletion: Type" in the "Fix the check automatically" "dialogue"
    And I should see "The plugin installer does not configure the companion plugin local_recompletion for you" in the "Fix the check automatically" "dialogue"
    And I should not see "changes a setting outside of the SEMCO plugin" in the "Fix the check automatically" "dialogue"
    And I should not see "not entirely harmless" in the "Fix the check automatically" "dialogue"
    And I click on "Fix automatically" "button" in the "Fix the check automatically" "dialogue"
    And I should see "The check has been fixed automatically."
    And the following should exist in the "healthchecks-recompletion" table:
      | Status | Check                     |
      | OK     | Course recompletion: Type |

  @javascript
  Scenario: A check which cannot be fixed automatically offers the SEMCO support instead
    # An enrolment role setting which points to a role which does not exist anymore cannot be fixed automatically,
    # as picking another role is a decision which only the admin can make.
    Given the following config values are set as admin:
      | role | 999999 | enrol_semco |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    And the following should exist in the "healthchecks-plugin" table:
      | Status | Check                |
      | Error  | SEMCO enrolment role |
    And "Fix the check automatically" "link" should not exist in the "SEMCO enrolment role" "table_row"
    And "Open setting" "link" should exist in the "SEMCO enrolment role" "table_row"
    When I click on "Contact SEMCO support" "link" in the "SEMCO enrolment role" "table_row"
    Then I should see "Unfortunately, this check cannot be fixed automatically." in the "Contact SEMCO support" "dialogue"
    And I should see "you could try to re-install the plugin" in the "Contact SEMCO support" "dialogue"
    And I should see "by using the check's cog icon" in the "Contact SEMCO support" "dialogue"
    And I should see "contact the SEMCO support who will assist you" in the "Contact SEMCO support" "dialogue"
    # The modal does nothing but to explain, thus the check is still in the same state afterwards.
    And I click on "OK" "button" in the "Contact SEMCO support" "dialogue"
    And the following should exist in the "healthchecks-plugin" table:
      | Status | Check                |
      | Error  | SEMCO enrolment role |

  @javascript
  Scenario: The details modal points to the SEMCO support if a check cannot be fixed automatically
    Given the following config values are set as admin:
      | role | 999999 | enrol_semco |
    And I am on the "enrol_semco > healthcheck" page logged in as "admin"
    When I click on "More info" "link" in the "SEMCO enrolment role" "table_row"
    Then I should see "click the phone icon to see how to get help" in the "SEMCO enrolment role" "dialogue"
