moodle-enrol_semco
==================

Changes
-------

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
