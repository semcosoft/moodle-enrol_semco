moodle-enrol_semco
==================

Changes
-------

### Unreleased

* 2026-10-10 - Improvement: The notification which the SEMCO enrolment report shows if it is empty does not carry a close button anymore, as the button stuck out of the opened filter menu.
* 2026-10-06 - Improvement: The 'Course recompletion: Activity reset' health check item offers an automatic fix now.
* 2026-10-06 - Improvement: The 'Course recompletion: Activity reset' health check item and the matching README recommendation no longer claim that a course completion reset does not delete _anything_ if no activity type is reset.
* 2026-09-30 - Improvement: The health check items of the 'Recompletion plugin' category only assess the courses which have completion tracking enabled in their course settings, as there is no course completion which SEMCO could reset in the other courses.
* 2026-09-30 - Improvement: Add three additional recommendations for local_recompletion to the README along with matching health check items.
* 2026-09-30 - Improvement: The health check refers to the SEMCO support ticketing system, if needed.
* 2026-09-28 - Bugfix: The plugin settings page and the cli/recreate_webservice_token.php script broke if the 'SEMCO webservice' user had more than one webservice token for the SEMCO external service. The settings page now shows the oldest token, as this is the token which has most probably been entered in SEMCO initially and which SEMCO keeps using even if another token is created in Moodle by accident. The 'SEMCO webservice token' health check items assess the oldest token instead of the newest one for the same reason. The CLI script refuses to recreate the token as long as there is more than one token and asks the admin to delete all but one token first.
* 2026-09-27 - Improvement: Add a recommendation to the README and a 'Course recompletion: Settings access' health check item which verify that no role is allowed to change the course recompletion settings of local_recompletion within a course, as such a role could weaken the site-wide recompletion rules. A teacher role which holds the capability is reported as a warning, any other role as a notice.
* 2026-09-27 - Improvement: A security audit flagged that the automatic fixes of the health check write to core and third-party database tables directly. The fixes which enable or restrict the SEMCO external service and which revoke or unrestrict the authorisation of a user now use the core webservice API and trigger the same events as the core administration pages, so that these changes show up in the logs. The remaining direct writes stay as Moodle and local_recompletion do not offer an API for them, the reasoning is documented in the code now.
* 2026-09-27 - Security: A security audit flagged that the installer and the health check passed a password which was generated with PHP's non-cryptographic rand() function to Moodle when creating the 'SEMCO webservice' user. As the 'webservice' authentication method does not use passwords, Moodle discards this password and stores a marker instead of a hash, so there was no exploitable weakness. Nevertheless, a cryptographically strong random password is passed now, the reasoning is documented in the code and the 'SEMCO webservice user: Authentication method' health check now also verifies that the account does not carry a password hash and offers an automatic fix.
* 2026-09-27 - Glitch: The 'SEMCO User company', 'SEMCO User birthday', 'SEMCO User place of birth' and 'SEMCO Tenant shortname' user profile fields were created as unique fields although several users legitimately share the same value there. However, SEMCO was always able to write these fields via the webservice, Moodle just refused to save the profile form of a user manually if another user had the same value. The installer, an upgrade step and the health check now take care that only the 'SEMCO User ID' field is unique.
* 2026-09-27 - Documentation: Swith the URL of local_recompletion to Github
* 2026-09-05 - Feature: Add a health check which verifies every aspect of the plugin's installation, offers an automatic fix for the aspects which can be restored unambiguously, allows to mute particular checks and is also reported to the Moodle Checks API.
* 2026-09-01 - Improvement: Support multilanguage course names and SEMCO profile field values when rendering the SEMCO enrolment report
* 2026-08-30 - Improvement: Add a filter for email address, SEMCO user ID, SEMCO booking ID, course, enrolment status and course completion status to the SEMCO enrolment report
* 2026-08-30 - Improvement: Convert the SEMCO enrolment report table into a dynamic table which updates its content with a webservice call instead of a page reload when it is paged or sorted
* 2026-08-30 - Improvement: Replace the 'View course profile' button in the SEMCO enrolment report table with a kebab menu which also offers the user's site wide profile and the user's course grades.
* 2026-08-30 - Improvement: Show the 'SEMCO User company', 'SEMCO User birthday', 'SEMCO User place of birth' and 'SEMCO Tenant shortname' user profile fields in own columns of the SEMCO enrolment report table
* 2026-08-30 - Improvement: Add the 'Optional report columns' setting which controls which of the optional columns the SEMCO enrolment report table shows. Doing this, the possibility to let the user hide particular columns from the report was removed for the sake of simplicity
* 2026-08-30 - Improvement: Add the 'Initial sorting column' setting which controls by which column the SEMCO enrolment report table is sorted initially
* 2026-08-30 - Improvement: Merge the 'First name' and 'Last name' columns of the SEMCO enrolment report table into a single 'Full name' column and add an initials filter bar to the table.
* 2026-08-29 - Improvement: Add course completion status, date and grade columns to the SEMCO enrolment report table
* 2026-08-29 - Improvement: Improve the column width of the SEMCO enrolment report table
* 2026-07-24 - Internal change: The webservice enrol_semco_reset_course_completion now checks the caller's capability before it checks the presence of local_recompletion.
* 2026-07-24 - Tests: Improve the PHPUnit test suite and verify that tests which cover the interaction with local_recompletion are skipped gracefully if local_recompletion is not installed.
* 2026-07-24 - Documentation: Complete the list of capabilities in README.md and add additional notes to the capability list.
* 2026-07-24 - Improvement: Declare the risk bitmasks of the plugin's webservice capabilities
* 2026-07-24 - Bugfix: The enrolment report showed the epoch start (01-01-1970) for unrestricted enrolment starts / ends
* 2026-07-24 - Tests: Add PHPUnit test for Privacy API
* 2026-07-24 - Tests: Add a suite of Behat tests which cover the plugin's functionality

### v5.2-r1

* 2026-04-20 - Prepare compatibility for Moodle 5.2.

### v5.1-r2

* 2026-04-20 - Internal improvement: Add enrol_semco_check_user_existence_by_field webservice as preparation for next SEMCO release.
* 2026-03-20 - Feature: Add CLI script to re-create and harden the webservice token.

### v5.1-r1

* 2025-10-26 - Prepare compatibility for Moodle 5.1.

### v5.0-r2

* 2025-10-15 - Make codechecker happy again
* 2025-10-15 - Release: Switch lead maintainer from lern.link GmbH to SEMCO Software Engineering GmbH
* 2025-10-15 - Tests: Switch Github actions workflows to reusable workflows by Moodle an Hochschulen e.V.

### v5.0-r1

* 2025-04-14 - Prepare compatibility for Moodle 5.0.

### v4.5-r2

* 2025-06-08 - Bugfix: Upgrading Moodle core with enrol_semco in place could have triggered a fatal error in Moodle core, resolves #2.

### v4.5-r1

* 2025-03-17 - Development: Rename master branch to main, please update your clones.
* 2025-01-10 - Add PHPUnit tests which cover the webservice functionality.
* 2025-01-10 - Add coursenotexist exception to enrol_user webservice.
* 2025-01-10 - Upgrade: Adopt Moodle core change from MDL-76583 and replace usage of lib/externallib.php.
* 2024-10-20 - Upgrade: Adopt changes from MDL-82183 and use several new class names.
* 2024-10-20 - Upgrade: Adopt changes from MDL-81960 and use new \core\url class.
* 2024-10-20 - Upgrade: Adopt changes from MDL-81031 and use new \core\user class.
* 2024-10-07 - Prepare compatibility for Moodle 4.5.

### v4.4-r4

* 2024-09-23 - Documentation: Add a note about the removal of the local/recompletion:resetmycompletion capability to README.md
* 2024-07-19 - Release: Remove Boost Union theme from Moodle-Plugin-CI config which was clearly wrong

### v4.4-r3

* 2024-07-18 - Raise version requirement of soft dependency to local_recompletion due to fixed bugs there.

### v4.4-r2

* 2024-06-01 - Raise Moodle core requirements which had been forgotten during the upgrade to Moodle 4.4.

### v4.4-r1

* 2024-06-01 - Prepare compatibility for Moodle 4.4.

### v4.3-r3

* 2024-06-01 - Bugfix: Remove debug warning in enrol_semco_enrol_user webservice endpoint.
* 2024-06-01 - Upgrade: Migrate the enrol_semco_before_standard_top_of_body_html() function to the new hook callback on Moodle 4.4.
* 2024-06-01 - Release: Let codechecker ignore some sniffs in the language pack.

### v4.3-r2

* 2024-03-11 - Improvement: Add description of data transfer between SEMCO and Moodle to Privacy API, resolves #1.
* 2024-03-11 - Release: Remove german language pack after the strings have been imported into AMOS.
* 2024-03-08 - Feature: Add a site report which shows a list of existing SEMCO enrolment instances.
* 2024-01-31 - Improvement: The webservice enrol_semco_enrol_user got an additional optional parameter which will process the enrolment only if local_recompletion is enabled in the course.
* 2024-01-30 - Feature change: Resetting the course completion with local_recompletion is now trigged directly by SEMCO and not with a scheduled task within Moodle anymore.

### v4.3-r1

* 2024-01-19 - Prepare compatibility for Moodle 4.3.

### v4.2-r3

* 2024-01-18 - Bugfix: For installations of this plugin which have been upgraded to v4.2-r2 (and not freshly installed on v4.2-r2), the user profile field "SEMCO User place of birth" was created with an incorrect shortname. This resulted in the fact that SEMCO could not write into this new user profile field. The shortname of the field was changed with an upgrade step now.

### v4.2-r2

* 2023-11-16 - Feature: The plugin will add new user profile field "SEMCO Tenant shortname" which will be filled by SEMCO with the SEMCO tenant shortname.
* 2023-11-10 - Feature: Reset the course completion with local_recompletion if a user gets enrolled into a course again.
* 2023-11-10 - Bugfix: Get rid of a "This page did not call $PAGE->set_url(...)" debug message during the plugin installation via CLI.
* 2023-10-30 - Feature: The plugin will add new user profile fields "SEMCO User birthday" and "SEMCO User place of birth" which will be filled by SEMCO with the user's birthday and place of birth.
* 2023-10-30 - Improvement: Use a dedicated semcowebservice mail address for the SEMCO webservice user as the noreply address which has been used up to now may be empty.
* 2023-10-12 - Improvement: Remove the user profile fields and user profile field category when the plugin is uninstalled.
* 2023-10-12 - Feature: The plugin will add a new user profile field "SEMCO User company" which will be filled by SEMCO with the user's company.
* 2023-09-27 - Improvement: Add a scheduled task which cleans up orphaned SEMCO enrolment instances which were not removed when a user was deleted (as SEMCO enrolment instances are only properly removed when a user is unenrolled via webservice).
* 2023-09-26 - Feature: The new webservice enrol_semco_get_course_completions will return the course completions for given SEMCO user enrolments.
* 2023-09-26 - Bugfix: The webservice enrol_semco_edit_enrolment didn't process enrolment period changes with given timeend dates but without given timestart dates.
* 2023-09-26 - Improvement: The webservices enrol_semco_enrol_user and enrol_semco_edit_enrolment won't accept timeend values which are smaller than the timestart value anymore.
* 2023-09-26 - Improvement: The webservices enrol_semco_enrol_user and enrol_semco_edit_enrolment will now return an error if a user should get enrolled into the same course multiple times with overlapping enrolment periods.
* 2023-09-26 - Improvement: The Webservice enrol_semco_get_enrolments will only return SEMCO enrolment instances from now on (instead of all enrolments).

### v4.2-r1

* 2023-09-26 - Upgrade: Replace function call to external_generate_token() with core_external\util::generate_token() as the library function was moved in Moodle core.
* 2023-09-26 - Prepare compatibility for Moodle 4.2.
* 2023-09-26 - Make codechecker happy again
* 2023-09-26 - Updated Moodle Plugin CI to latest upstream recommendations

### v4.1-r1

* 2023-08-02 - Tests: Updated Moodle Plugin CI to use PHP 8.1 and Postgres 13 from Moodle 4.1 on.
* 2023-08-02 - Prepare compatibility for Moodle 4.1.

### v4.0-r1

* 2022-12-01 - Initial version.
