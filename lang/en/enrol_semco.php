<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Enrolment method "SEMCO" - Language pack
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Let codechecker ignore some sniffs for this file as it is perfectly well ordered, just not alphabetically.
// phpcs:disable moodle.Files.LangFilesOrdering.UnexpectedComment
// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder

$string['pluginname'] = 'SEMCO';

// Enrolment instances.
$string['instance_namewithbookingid'] = 'SEMCO [Booking ID: {$a}]';
$string['instance_namewithoutbookingid'] = 'SEMCO';

// Admin settings.
$string['settings_connectioninfoheading'] = 'Connection information';
$string['settings_coursecompletionheading'] = 'Course completion';
$string['settings_coursecompletionlrcintro'] = '<p>The SEMCO enrolment plugin is able to reset a user\'s course completion if he gets enrolled into a particular course by SEMCO once more.<br />
To realize this course completion reset and to avoid to re-invent the wheel, this plugin has a soft dependency to <a href="https://moodle.org/plugins/local_recompletion">local_recompletion</a> by Dan Marsden.</p>';
$string['settings_coursecompletionlrcfound'] = '<p>The plugin local_recompletion is installed with at least version 2024071103. You are able to use subsequent user enrolments into the same course and to reset course completion.</p>';
$string['settings_coursecompletionlrcnotfound'] = '<p>Please install local_recompletion with at least version 2024071103 alongside this plugin if you plan to use subsequent user enrolments into the same course and need to reset course completion.<br />
If you do not need plan to reset course completion, you do not need to install local_recompletion.</p>';
$string['settings_coursecompletionnote'] = '<p>Please note: SEMCO just triggers the on-demand course recompletion which is provided by the <a href="{$a}">course recompletion plugin</a>. It\'s still up to the individual teachers to configure course recompletion in their courses according to the individual needs and to set the course\'s recompletion type to \'On demand\'.</p>';
$string['settings_enrolmentheading'] = 'Enrolment process';
$string['settings_enrolmentreportheading'] = 'Enrolment report';
$string['settings_enrolmentreportheading_desc'] = 'There is a site report where you can see all enrolments which have been made by SEMCO.';
$string['settings_enrolmentreportbutton'] = 'View report';
$string['settings_role'] = 'Role';
$string['settings_role_desc'] = 'With this setting, you control with which role SEMCO enrols users into courses. The configured role is mandatory for all users who are enrolled from SEMCO and cannot be overridden with the SEMCO enrolment webservice endpoint. Please note as well that changes of this setting will not have any effect on existing enrolments.';
$string['settings_tokeninfo'] = 'Webservice token';
$string['settings_tokeninfofound'] = 'The webservice token for the SEMCO webservice user is:<br /><strong>{$a}</strong><br />Please use this webservice token to configure the Moodle connection in SEMCO.';
$string['settings_tokeninfononefound'] = 'No existing webservice token was found for the SEMCO webservice user. Please create a token manually.';
$string['settings_wwwrootinfo'] = 'Moodle base URL';
$string['settings_wwwrootinfofound'] = 'The Moodle base URL for the SEMCO webservice connection is:<br /><strong>{$a}</strong><br />Please use this Moodle base URL to configure the Moodle connection in SEMCO.';

// Enrolment report.
$string['reportpagetitle'] = 'SEMCO enrolments';
$string['emptytable'] = 'There are not any SEMCO enrolments yet in this Moodle instance.';
$string['tablecourseid'] = 'Course ID';
$string['tablecoursename'] = 'Course name';
$string['tableenrolend'] = 'Enrolment end';
$string['tableenrolid'] = 'Enrolment ID';
$string['tableenrolstart'] = 'Enrolment start';
$string['tableenrolstatus'] = 'Enrolment status';
$string['tablesemcobookingid'] = 'SEMCO booking ID';
$string['tableuserid'] = 'Moodle User ID';
$string['tableusername'] = 'Moodle Username';
$string['tableuserstatus'] = 'Moodle User status';
$string['tableviewenrolment'] = 'View course profile';

// Webservice errors.
$string['bookingidduplicate'] = 'There is already an enrolment instance with this SEMCO booking ID ({$a}).';
$string['bookingidduplicatemustchange'] = 'There is already an enrolment instance with this SEMCO booking ID ({$a}). If you want to edit the enrolment without changing the SEMCO booking ID, simply do not pass the booking ID as parameter. If you want to edit the enrolment and change the SEMCO booking ID, make sure that you do not set it to an ID which exists somewhere else in the system already.';
$string['bookingidempty'] = 'The SEMCO booking ID field must not be empty.';
$string['bookingoverlap'] = 'There is already an enrolment instance with an enrolment period which overlaps with the given enrolment period. However, overlapping enrolment periods are not supported.';
$string['checkuserexistenceinvalidfield'] = 'The given field ({$a}) is not valid. Allowed values are: idnumber, username, email.';
$string['coursenotexist'] = 'The given course ({$a}) does not exist.';
$string['enrolnoinstance'] = 'The SEMCO enrolment plugin instance associated to the given user enrolment instance ({$a}) does not exist.';
$string['enrolnouserinstance'] = 'The given user enrolment instance ({$a}) does not exist.';
$string['getcoursecompletionsmaxrequest'] = 'You passed more than the maximum amount of enrolment IDs (which is {$a}).';
$string['semcopluginnotenabled'] = 'The SEMCO enrolment plugin is not enabled currently.';
$string['semcopluginnotinstalled'] = 'The SEMCO enrolment plugin has not yet been installed.';
$string['localrecompletionnotexpectable'] = 'The local_recompletion plugin is not installed or too old. Please install / update local_recompletion before you use the requirerecompletion parameter of this webservice function.';
$string['localrecompletionnotinstalled'] = 'The local_recompletion plugin is not installed or too old. Please install / update local_recompletion to allow this webservice function to do its job.';
$string['localrecompletionnotenabled'] = 'Course recompletion is not enabled at all in the course\'s recompletion settings. Please go to {$a} and set the \'Recompletion type\' to \'On demand\'.';
$string['localrecompletionnotondemand'] = 'Course recompletion is not set to \'On demand\' in the course\'s recompletion settings. Please go to {$a} and set the \'Recompletion type\' to \'On demand\'.';
$string['timeendinvalid'] = 'The Timeend field must be greater than or equal to zero.';
$string['timestartinvalid'] = 'The Timestart field must be greater than or equal to zero.';
$string['timestartendorder'] = 'The Timestart field must not be greater than the Timeend field.';
$string['usernotexist'] = 'The given user ({$a}) does not exist.';
$string['wsusercannotassign'] = 'You don\'t have the permission to assign this role ({$a->roleid}) to this user ({$a->userid}) in this course ({$a->courseid}).';

// Installer.
$string['installer_addedusertorole'] = 'The role \'SEMCO webservice\' was assigned to the user \'SEMCO webservice\' automatically.';
$string['installer_addedusertoservice'] = 'The user \'SEMCO webservice\' was added to the SEMCO webservice as allowed user automatically.';
$string['installer_createdrole'] = 'The role \'SEMCO webservice\' was created and properly configured automatically. This role is used for the SEMCO webservice user in Moodle.';
$string['installer_createdprofilefield1'] = 'The user profile field \'SEMCO user ID\' was created and properly configured automatically. This user profile field is used for Moodle users which are created by the SEMCO webservice.';
$string['installer_createdprofilefield2'] = 'The user profile field \'SEMCO user company\' was created and properly configured automatically. This user profile field is used for Moodle users which are created by the SEMCO webservice.';
$string['installer_createdprofilefield3'] = 'The user profile field \'SEMCO user birthday\' was created and properly configured automatically. This user profile field is used for Moodle users which are created by the SEMCO webservice.';
$string['installer_createdprofilefield4'] = 'The user profile field \'SEMCO user place of birth\' was created and properly configured automatically. This user profile field is used for Moodle users which are created by the SEMCO webservice.';
$string['installer_createdprofilefield5'] = 'The user profile field \'SEMCO tenant shortname\' was created and properly configured automatically. This user profile field is used for Moodle users which are created by the SEMCO webservice.';
$string['installer_createdprofilefieldcategory'] = 'The user profile field category \'SEMCO\' was created and properly configured automatically. This user profile field category is used to hold multiple user profile fields related to Moodle users which are created by the SEMCO webservice.';
$string['installer_createduser'] = 'The user \'SEMCO webservice\' was created automatically. This user is used to create the webservice token for SEMCO.';
$string['installer_createdusertoken'] = 'A webservice token was created automatically for the user \'SEMCO webservice\'. You can view it on the plugin\'s settings page.';
$string['installer_enabledauth'] = 'Moodle\'s webservice auth method has been enabled automatically to allow SEMCO to communicate with Moodle via webservices.';
$string['installer_enabledrest'] = 'Moodle\'s webservice REST protocol has been enabled automatically to allow SEMCO to communicate with Moodle via webservices.';
$string['installer_enabledws'] = 'Moodle\'s webservice subsystem has been enabled automatically to allow SEMCO to communicate with Moodle via webservices.';
$string['installer_enabledplugin'] = 'The SEMCO enrolment plugin has been enabled automatically.';
$string['installer_finalnotenoproblems'] = 'SEMCO should be able to communicate with Moodle now.';
$string['installer_finalnotewithproblems'] = 'As there were issues with the automatic configuration in the previous steps, SEMCO might not be able to communicate with Moodle yet. Please double-check all configurations manually.';
$string['installer_notcreatedprofilefield1'] = 'The user profile field \'SEMCO user ID\' could not be created and properly configured automatically as it seems to exist already. Please verify the user field configuration manually.';
$string['installer_notcreatedprofilefield2'] = 'The user profile field \'SEMCO user company\' could not be created and properly configured automatically as it seems to exist already. Please verify the user field configuration manually.';
$string['installer_notcreatedprofilefield3'] = 'The user profile field \'SEMCO user birthday\' could not be created and properly configured automatically as it seems to exist already. Please verify the user field configuration manually.';
$string['installer_notcreatedprofilefield4'] = 'The user profile field \'SEMCO user place of birth\' could not be created and properly configured automatically as it seems to exist already. Please verify the user field configuration manually.';
$string['installer_notcreatedprofilefield5'] = 'The user profile field \'SEMCO tenant shortname\' could not be created and properly configured automatically as it seems to exist already. Please verify the user field configuration manually.';
$string['installer_notcreatedrole'] = 'The role \'SEMCO webservice\' could not be created and properly configured automatically as it seems to exist already. Please verify the role configuration manually.';
$string['installer_notcreateduser'] = 'The user \'SEMCO webservice\' could not be created automatically as it seems to exist already. Please verify the user configuration manually.';
$string['installer_queuedcapabilitytask'] = 'The necessary capability \'webservice/rest:use\' could not be added to the role \'SEMCO webservice\' during the initial installation of Moodle as this capability did not exist yet (the webservice subsystem will be installed after this plugin). An ad-hoc task was queued to add this capability automatically as soon as the Moodle cron is running for the first time.';
$string['installer_roledescription'] = 'This is an internal role which has the single purpose to assign all necessary capabilities to the SEMCO webservice user. Do not assign this role to any other (especially not human) user.';
$string['installer_rolename'] = 'SEMCO webservice';
$string['installer_userfield1fullname'] = 'SEMCO User ID';
$string['installer_userfield2fullname'] = 'SEMCO User company';
$string['installer_userfield3fullname'] = 'SEMCO User birthday';
$string['installer_userfield4fullname'] = 'SEMCO User place of birth';
$string['installer_userfield5fullname'] = 'SEMCO Tenant shortname';
$string['installer_userfirstname'] = 'SEMCO';
$string['installer_userlastname'] = 'Webservice';
$string['uninstaller_remainenabled'] = 'The SEMCO enrolment plugin is removed and will not need Moodle\'s webservice subsystem and webservice auth method anymore. However, as the plugin uninstaller does not know, if any other plugins or features still need it, both will remain enabled. Please disable them manually if you do not need them anymore.';
$string['uninstaller_removedrole'] = 'The role \'SEMCO webservice\' was removed automatically.';
$string['uninstaller_removeduser'] = 'The user \'SEMCO webservice\' was removed automatically.';
$string['uninstaller_removedprofilefields'] = 'The user profile fields for \'SEMCO\' were removed automatically.';

// Updater.
$string['updater_2023092601_addcapability'] = 'The capabilities \'enrol/semco:getcoursecompletions\', \'moodle/course:viewhiddencourses\' and \'moodle/grade:viewall\' were added to the role \'SEMCO webservice\' during the plugin update.';
$string['updater_2023092605_addprofilefield'] = 'The profile field \'SEMCO User company\' was created and properly configured automatically during the plugin update.';
$string['updater_2023092606_addprofilefield3'] = 'The profile field \'SEMCO User birthday\' was created and properly configured automatically during the plugin update.';
$string['updater_2023092606_addprofilefield4'] = 'The profile field \'SEMCO User place of birth\' was created and properly configured automatically during the plugin update.';
$string['updater_2023092608_addprofilefield5'] = 'The profile field \'SEMCO Tenant shortname\' was created and properly configured automatically during the plugin update.';
$string['updater_2023092610_fixprofilefield4'] = 'The profile field \'SEMCO User place of birth\' was created with an incorrect shortname during a previous update of this plugin. This resulted in the fact that SEMCO could not write into this new user profile field.';
$string['updater_2023092610_fixprofilefield4succ'] = 'The shortname of the field was changed with an upgrade step now.';
$string['updater_2023092610_fixprofilefield4fail'] = 'The installer has tried to change the shortname of the field with an upgrade step now, but it failed. Please go to the user profile fields management page, search for the \'SEMCO User place of birth\' field and change the shortname to \'semco_userplaceofbirth\'';
$string['updater_2023100902_addcapability'] = 'The capability \'enrol/semco:resetcoursecompletion\' was added to the role \'SEMCO webservice\' during the plugin update.';
$string['updater_2024100703_addcapability'] = 'The capability \'enrol/semco:checkuserexistence\' was added to the role \'SEMCO webservice\' during the plugin update.';

// Capabilities.
$string['semco:checkuserexistence'] = 'Check the existence of a Moodle user by a given field';
$string['semco:editenrolment'] = 'Edit an existing SEMCO user enrolment';
$string['semco:enrol'] = 'Enrol SEMCO users into a course';
$string['semco:getenrolments'] = 'Get the existing SEMCO user enrolments from a course';
$string['semco:getcoursecompletions'] = 'Get the course completions for given SEMCO user enrolments';
$string['semco:resetcoursecompletion'] = 'Reset the course completion for the given SEMCO user enrolment';
$string['semco:unenrol'] = 'Unenrol SEMCO users from a course';
$string['semco:usewebservice'] = 'Use the SEMCO enrolment webservices';
$string['semco:viewreport'] = 'View the SEMCO enrolment report';

// Tasks.
$string['task_cleanorphaned'] = 'Clean orphaned SEMCO enrolment instances.';

// Privacy API.
$string['privacy:metadata:enrol_semco:SEMCO'] = 'SEMCO is a course management system which is connected to Moodle for organizing course enrolments.';
$string['privacy:metadata:enrol_semco:SEMCO:user_profile'] = 'User profile data like the username, firstname, lastname and email are shared between SEMCO and Moodle. The data flows from SEMCO to Moodle.';
$string['privacy:metadata:enrol_semco:SEMCO:course_enrolments'] = 'Course enrolment metadata like course memberships and enrolment dates are shared between SEMCO and Moodle. The data flows from SEMCO to Moodle.';
$string['privacy:metadata:enrol_semco:SEMCO:course_completions'] = 'Course completion data including completion dates, grades and passing states are shared between SEMCO and Moodle. The data flows from Moodle to SEMCO.';
