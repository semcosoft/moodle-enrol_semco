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
To realize this course completion reset and to avoid to re-invent the wheel, this plugin has a soft dependency to <a href="https://github.com/danmarsden/moodle-local_recompletion">local_recompletion</a> by Dan Marsden.</p>';
$string['settings_coursecompletionlrcfound'] = '<p>The plugin local_recompletion is installed with at least version 2024071103. You are able to use subsequent user enrolments into the same course and to reset course completion.</p>';
$string['settings_coursecompletionlrcnotfound'] = '<p>Please install local_recompletion with at least version 2024071103 alongside this plugin if you plan to use subsequent user enrolments into the same course and need to reset course completion.<br />
If you do not need plan to reset course completion, you do not need to install local_recompletion.</p>';
$string['settings_coursecompletionnote'] = '<p>Please note: SEMCO just triggers the on-demand course recompletion which is provided by the <a href="{$a}">course recompletion plugin</a>. It\'s still up to the individual teachers to configure course recompletion in their courses according to the individual needs and to set the course\'s recompletion type to \'On demand\'.</p>';
$string['settings_enrolmentheading'] = 'Enrolment process';
$string['settings_enrolmentreportheading'] = 'Enrolment report';
$string['settings_enrolmentreportheading_desc'] = 'There is a site report where you can see all enrolments which have been made by SEMCO.';
$string['settings_enrolmentreportbutton'] = 'View report';
$string['settings_healthcheckheading'] = 'Health check';
$string['settings_healthcheckheading_desc'] = 'There is a site report where you can check if the Moodle setup which this plugin needs is in the desired state.';
$string['settings_healthcheckbutton'] = 'View health check';
$string['settings_healthcheckattention'] = 'Some checks of the SEMCO health check need your attention.';
$string['settings_reportinitialsortingcolumn'] = 'Initial sorting column';
$string['settings_reportinitialsortingcolumn_desc'] = 'With this setting, you control by which column the enrolment report is sorted when it is opened.';
$string['settings_reportoptionalcolumns'] = 'Optional report columns';
$string['settings_reportoptionalcolumns_desc'] = 'With this setting, you control which of the optional columns are shown in the enrolment report. The columns which are offered in the \'Initial sorting column\' setting above are always shown and are therefore not offered here.';
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
$string['emptytablefiltered'] = 'There are not any SEMCO enrolments which match the selected filters.';
// The strings of the text filters are not used as labels anywhere as these filters are labelled with the string of the
// matching report column. They are the headings of the help texts below them, which a help button always needs.
$string['filteremail'] = 'Email address';
$string['filteremail_help'] = 'Enter a part of an email address to show only the enrolments whose user has an email address which contains the entered text. Leave the field empty to show the enrolments of all users.';
$string['filtersappliedx'] = 'Filters ({$a})';
$string['filtersemcobookingid'] = 'SEMCO booking ID';
$string['filtersemcobookingid_help'] = 'Enter a part of a SEMCO booking ID to show only the enrolments whose booking ID contains the entered text. Leave the field empty to show the enrolments of all booking IDs.';
$string['filtersemcouserid'] = 'SEMCO User ID';
$string['filtersemcouserid_help'] = 'Enter a part of a SEMCO user ID to show only the enrolments whose user has a SEMCO user ID which contains the entered text. Leave the field empty to show the enrolments of all users.';
$string['filtersreset'] = 'Reset all';
$string['tablecoursecompletiondate'] = 'Course completion date';
$string['tablecoursecompletiongrade'] = 'Course completion grade';
$string['tablecoursecompletionstatus'] = 'Course completion status';
$string['tablecourseid'] = 'Course ID';
$string['tablecoursename'] = 'Course name';
$string['tableenrolend'] = 'Enrolment end';
$string['tableenrolid'] = 'Enrolment ID';
$string['tableenrolstart'] = 'Enrolment start';
$string['tableenrolstatus'] = 'Enrolment status';
$string['tableenrolunrestricted'] = 'Unrestricted';
$string['tablesemcobookingid'] = 'SEMCO booking ID';
$string['tableuserid'] = 'Moodle User ID';
$string['tableusername'] = 'Moodle Username';
$string['tableuserstatus'] = 'Moodle User status';
$string['tableviewenrolment'] = 'View course profile';
$string['tableviewcoursegrades'] = 'View course grades';
$string['tableviewuserprofile'] = 'View user profile';

// Health check.
$string['healthcheckpagetitle'] = 'SEMCO health check';
$string['healthcheckpage_desc'] = 'The SEMCO enrolment plugin needs a particular Moodle setup to work properly. This setup has been created in a working state when the plugin was installed. On this page, you find a check of every aspect of this setup and you see if it is still in the desired state.<br />The last two sections on this page go one step further: They check the settings of the companion plugin local_recompletion, which SEMCO needs to reset course completions, and the global Moodle settings which are recommended for a SEMCO-Moodle integration. These settings are not touched by the plugin installer, thus they are recommendations and not requirements. Please read the plugin documentation and decide for your Moodle instance if you want to follow them. Where this is possible, the wand icon applies a recommendation for you. If you have decided against a recommendation, you can mute the particular check with the bell icon so that it does not bother you anymore.';
$string['healthcheckstatusheader'] = 'Status';
$string['healthcheckcheckheader'] = 'Check';
$string['healthchecksummaryheader'] = 'Summary';
$string['healthcheckactionsheader'] = 'Actions';
$string['healthcheckcategory_webservice'] = 'Webservice infrastructure';
$string['healthcheckcategory_plugin'] = 'SEMCO enrolment plugin';
$string['healthcheckcategory_role'] = 'SEMCO webservice role';
$string['healthcheckcategory_user'] = 'SEMCO webservice user';
$string['healthcheckcategory_token'] = 'SEMCO webservice token';
$string['healthcheckcategory_profilefields'] = 'SEMCO user profile fields';
$string['healthcheckcategory_recompletion'] = 'Recompletion plugin';
$string['healthcheckcategory_recommendations'] = 'Recommended Moodle settings';
$string['healthcheckstatus_ok'] = 'OK';
$string['healthcheckstatus_ok_description'] = 'With this check, everything is perfectly fine. No action is required on your side.';
$string['healthcheckstatus_notice'] = 'Notice';
$string['healthcheckstatus_notice_description_installation'] = 'This check should raise your awareness. The SEMCO integration works as expected, but the checked aspect deviates from the state which the plugin installer has created.';
$string['healthcheckstatus_notice_description_recompletion'] = 'This check should raise your awareness. The SEMCO integration works as expected, but the checked setting of the companion plugin local_recompletion is not in the state which a SEMCO-Moodle integration needs. Please be aware that the plugin installer does not make these settings for you, they have to be made after the installation.';
$string['healthcheckstatus_notice_description_recommendation'] = 'This check should raise your awareness. The SEMCO integration works as expected, but the checked global Moodle setting does not follow a recommendation for a SEMCO-Moodle integration.';
$string['healthcheckstatus_error'] = 'Error';
$string['healthcheckstatus_error_description_installation'] = 'This check needs your immediate attention. The checked aspect of the plugin installation is broken and the SEMCO integration does not work at the moment.';
$string['healthcheckstatus_warning'] = 'Warning';
$string['healthcheckstatus_warning_description_installation'] = 'This check needs your attention. The SEMCO integration still works, but the checked aspect deviates from the state which the plugin installer has created.';
$string['healthcheckstatus_warning_description_recompletion'] = 'This check needs your attention. The SEMCO integration still works, but the checked setting of the companion plugin local_recompletion is not in the state which a SEMCO-Moodle integration needs. Please be aware that the plugin installer does not make these settings for you, they have to be made after the installation.';
$string['healthcheckstatus_warning_description_recommendation'] = 'This check needs your attention. The SEMCO integration still works, but the checked global Moodle setting does not follow a recommendation for a SEMCO-Moodle integration and this has a consequence which you should not accept unknowingly.';
$string['healthcheckstatus_na'] = 'N/A';
$string['healthcheckstatus_na_description'] = 'This check could not be assessed as one of its prerequisites is missing. Please resolve the other checks first and have a look at this check again afterwards.';
$string['healthcheckstatus_muted'] = 'Muted';
$string['healthcheckstatus_muted_description'] = 'This check is currently muted. Regardless of its original status, it will not bother you - neither on this page, nor on the plugin settings page, nor on the Moodle system status page - unless you unmute it again.';
$string['healthcheckmoreinfo'] = 'More info';
$string['healthcheckopensetting'] = 'Open setting';
$string['healthcheckautofix'] = 'Fix the check automatically';
$string['healthcheckautofixconfirmtitle'] = 'Fix the check automatically';
$string['healthcheckautofixconfirmbutton'] = 'Fix automatically';
$string['healthcheckautofixconfirmcheck'] = 'Affected check:';
$string['healthcheckautofixconfirmfinding'] = 'The automatic fix is going to resolve this finding:';
$string['healthcheckautofixconfirmfindings'] = 'The automatic fix is going to resolve these findings:';
$string['healthcheckautofixconfirmrecommendation'] = 'Please note: This finding is not a fault of the SEMCO plugin or of its installation. The automatic fix changes a setting outside of the SEMCO plugin to implement a recommendation for a SEMCO-Moodle integration which is described in the README of the plugin.';
$string['healthcheckautofixconfirmrecompletion'] = 'Please note: This finding is not a fault of the SEMCO plugin or of its installation. The plugin installer does not configure the companion plugin local_recompletion for you, this has to be done after the installation. The automatic fix changes a setting which belongs to local_recompletion to implement a recommendation for a SEMCO-Moodle integration which is described in the README of the plugin.';
$string['healthcheckautofixconfirmrisky'] = '<strong>Warning:</strong> This automatic fix is not entirely harmless. It changes something which may have been set on purpose or which may affect more than the SEMCO integration. If you are in doubt, please do not proceed. You can also fix the finding yourself with the cog icon of the check or contact the SEMCO support who will assist you.';
$string['healthcheckautofixconfirmquestion'] = 'Do you really want to fix this check automatically?';
$string['healthcheckautofixsuccess'] = 'The check has been fixed automatically.';
$string['healthcheckautofixfollowup'] = 'However, the automatic fix could not do everything for you:';
$string['healthcheckautofixerror'] = 'The check could not be fixed automatically.';
$string['healthcheckcurrentstatus'] = 'Current status';
$string['healthcheckfinding'] = 'Finding';
$string['healthcheckfindings'] = 'Findings';
$string['healthcheckpossiblesolutions'] = 'Possible solutions';
$string['healthchecksupport'] = 'Contact SEMCO support';
$string['healthchecksupport_desc'] = '<p>Unfortunately, this check cannot be fixed automatically.</p><p>If you do not use the SEMCO-Moodle connection in production yet, you could try to re-install the plugin and to re-configure everything from scratch.</p><p>You can also try to fix the check by looking at the affected settings yourself by using the check\'s cog icon.</p><p>Lastly, you can also contact the SEMCO support who will assist you to fix the finding.</p>';
$string['healthcheckmute'] = 'Mute check';
$string['healthcheckunmute'] = 'Unmute check';
$string['healthcheckmutesuccess'] = 'The check has been muted.<br />You will not be bothered by it from now on, but you can always unmute it again.';
$string['healthcheckunmutesuccess'] = 'The check has been unmuted.';
$string['healthchecksolution_both'] = 'You can either click the wand icon to let the plugin fix this check automatically or click the cog icon to review the affected setting yourself.';
$string['healthchecksolution_autofixonly'] = 'You can click the wand icon to let the plugin fix this check automatically.';
$string['healthchecksolution_actionurlonly'] = 'You can click the cog icon to review the affected setting, but unfortunately the plugin cannot fix this check automatically. If you need assistance, click the phone icon to see how to get help.';
$string['healthchecksolution_supportonly'] = 'Unfortunately, the plugin can neither fix this check automatically nor point you to a particular setting. Click the phone icon to see how to get help.';

// Health check items: Shared findings.
$string['healthcheck_findingnorole'] = 'The SEMCO webservice role does not exist, thus this check cannot be assessed. Please have a look at the \'SEMCO webservice role\' item first.';
$string['healthcheck_findingnouser'] = 'The SEMCO webservice user does not exist, thus this check cannot be assessed. Please have a look at the \'SEMCO webservice user\' item first.';
$string['healthcheck_findingnoservice'] = 'The SEMCO external service does not exist, thus this check cannot be assessed. Please have a look at the \'SEMCO external service\' item first.';
$string['healthcheck_findingnouserorrole'] = 'The SEMCO webservice user or the SEMCO webservice role does not exist, thus this check cannot be assessed. Please have a look at the \'SEMCO webservice user\' and the \'SEMCO webservice role\' items first.';
$string['healthcheck_findingnouserorservice'] = 'The SEMCO webservice user or the SEMCO external service does not exist, thus this check cannot be assessed. Please have a look at the \'SEMCO webservice user\' and the \'SEMCO external service\' items first.';
$string['healthcheck_findingnoenrolmentrole'] = 'There is not any valid role configured as SEMCO enrolment role in the plugin settings, thus this check cannot be assessed. Please have a look at the \'SEMCO enrolment role\' item first.';
$string['healthcheck_findingnorecompletion'] = 'The plugin local_recompletion is not installed or its version is too old, thus this check cannot be assessed. Please have a look at the \'Companion plugin local_recompletion\' item first.';
$string['healthcheck_listmore'] = 'and {$a} more';

// Health check items: Webservice infrastructure.
$string['healthcheck_webservicesenabled_title'] = 'Webservice subsystem';
$string['healthcheck_webservicesenabled_summary'] = 'The Moodle webservice subsystem must be enabled.';
$string['healthcheck_webservicesenabled_description'] = 'SEMCO communicates with Moodle through Moodle webservices. If the webservice subsystem is disabled, SEMCO cannot reach Moodle at all. The plugin installer has enabled the webservice subsystem during the installation of this plugin and it has to stay enabled.';
$string['healthcheck_webservicesenabled_findingdisabled'] = 'The Moodle webservice subsystem is disabled, thus SEMCO cannot communicate with Moodle at all.';
$string['healthcheck_restprotocol_title'] = 'Webservice REST protocol';
$string['healthcheck_restprotocol_summary'] = 'The Moodle webservice REST protocol must be enabled.';
$string['healthcheck_restprotocol_description'] = 'SEMCO uses the REST protocol to talk to the Moodle webservices. If this protocol is disabled, SEMCO cannot call any webservice function. The plugin installer has enabled the REST protocol during the installation of this plugin and it has to stay enabled.';
$string['healthcheck_restprotocol_findingdisabled'] = 'The Moodle webservice REST protocol is disabled, thus SEMCO cannot call any webservice function.';
$string['healthcheck_authmethod_title'] = 'Web services authentication';
$string['healthcheck_authmethod_summary'] = 'The authentication method which the SEMCO webservice user uses must be enabled.';
$string['healthcheck_authmethod_description'] = 'The SEMCO webservice user account uses the web services authentication method. If this authentication method is disabled, the SEMCO webservice user is not supposed to work anymore, even if SEMCO may still get through with its webservice token. The plugin installer has enabled this authentication method during the installation of this plugin and it has to stay enabled.';
$string['healthcheck_authmethod_findingdisabled'] = 'The Moodle web services authentication method is disabled, thus the SEMCO webservice user is not supposed to work anymore, even if SEMCO may still get through with its webservice token.';
$string['healthcheck_externalserviceexists_title'] = 'SEMCO external service';
$string['healthcheck_externalserviceexists_summary'] = 'The SEMCO external service must exist.';
$string['healthcheck_externalserviceexists_description'] = 'This plugin ships an external service which bundles all webservice functions which SEMCO needs. The service is declared in the plugin\'s db/services.php file and the Moodle plugin installer creates it. Without this service, SEMCO cannot call any webservice function of this plugin.';
$string['healthcheck_externalserviceexists_findingmissing'] = 'The external service does not exist.';
$string['healthcheck_externalserviceexists_followupmissing'] = 'The SEMCO external service has been recreated, but the authorisation of the SEMCO webservice user for this service and its webservice token have not. They belonged to the previous service and are gone. Please continue with the checks which report these aspects.';
$string['healthcheck_externalserviceconfig_title'] = 'SEMCO external service: Configuration';
$string['healthcheck_externalserviceconfig_summary'] = 'The SEMCO external service must be enabled and must be restricted to the SEMCO webservice user.';
$string['healthcheck_externalserviceconfig_description'] = 'The SEMCO external service has to be enabled to be usable and it has to be restricted to authorised users, as it would be open to every user who holds the necessary capabilities otherwise. The restriction is declared by this plugin and Moodle does not let you change it on the service settings page, so an unrestricted service means that somebody has changed the database directly. On top of that, this item verifies that the list of authorised users does not hold anybody but the SEMCO webservice user. Whether that user is on the list at all is verified by the \'SEMCO webservice user: Service authorisation\' item.';
$string['healthcheck_externalserviceconfig_findingdisabled'] = 'The external service is disabled.';
$string['healthcheck_externalserviceconfig_findingunrestricted'] = 'The external service is not restricted to authorised users.';
$string['healthcheck_externalserviceconfig_findingotherusers'] = '{$a->count} user(s) other than the SEMCO webservice user are authorised to use the external service: {$a->users} They are able to call the SEMCO webservice functions as soon as they hold the necessary capabilities.';
$string['healthcheck_externalservicefunctions_title'] = 'SEMCO external service: Functions';
$string['healthcheck_externalservicefunctions_summary'] = 'The SEMCO external service must offer all webservice functions which SEMCO needs.';
$string['healthcheck_externalservicefunctions_description'] = 'This plugin declares its own webservice functions and adds several Moodle core webservice functions to the SEMCO external service. The Moodle plugin installer registers them and adds them to the service. If a function is missing from the service, the corresponding SEMCO webservice call fails.';
$string['healthcheck_externalservicefunctions_findingnotoffered'] = 'The webservice function {$a} is not offered by the SEMCO external service.';
$string['healthcheck_externalservicefunctions_findingnotregistered'] = 'The webservice function {$a} is not registered in Moodle at all.';
$string['healthcheck_externalservicefunctions_findingnodefinitions'] = 'The plugin\'s db/services.php file does not declare any webservice function for the SEMCO external service, thus this check cannot be assessed. Please verify that the plugin files are complete.';

// Health check items: SEMCO enrolment plugin.
$string['healthcheck_enrolpluginenabled_title'] = 'SEMCO enrolment method enabled';
$string['healthcheck_enrolpluginenabled_summary'] = 'The SEMCO enrolment method must be enabled.';
$string['healthcheck_enrolpluginenabled_description'] = 'Even though this plugin is installed, it has to be enabled as enrolment method to be usable. If it is disabled, SEMCO cannot enrol anyone into courses. The plugin installer has enabled the enrolment method during the installation of this plugin and it has to stay enabled.';
$string['healthcheck_enrolpluginenabled_findingdisabled'] = 'The SEMCO enrolment method is disabled, thus SEMCO cannot enrol anyone into a course.';
$string['healthcheck_enrolmentroleconfigured_title'] = 'SEMCO enrolment role';
$string['healthcheck_enrolmentroleconfigured_summary'] = 'A valid role must be configured as SEMCO enrolment role in the plugin settings.';
$string['healthcheck_enrolmentroleconfigured_description'] = 'When SEMCO enrols a user into a course, it assigns the role which is configured as SEMCO enrolment role in the plugin settings. If there is not any role configured or if the configured role does not exist anymore, SEMCO cannot enrol anyone into courses. Please note that Moodle does not touch the plugin settings when a role is deleted, thus the setting can still point to a role which is long gone.';
$string['healthcheck_enrolmentroleconfigured_findingempty'] = 'There is not any role configured as SEMCO enrolment role in the plugin settings, thus SEMCO cannot enrol anyone into a course.';
$string['healthcheck_enrolmentroleconfigured_followupempty'] = 'The plugin\'s default role has been configured as SEMCO enrolment role, which is the first role with the \'Student\' archetype. Please verify on the plugin settings page that this is the role which SEMCO should assign to the users it enrols, and pick another role if not.';
$string['healthcheck_enrolmentroleconfigured_findingdeleted'] = 'The role with the ID {$a} which is configured as SEMCO enrolment role in the plugin settings does not exist anymore, thus SEMCO cannot enrol anyone into a course.';
$string['healthcheck_capabilitiesregistered_title'] = 'SEMCO capabilities registered';
$string['healthcheck_capabilitiesregistered_summary'] = 'All capabilities of this plugin must be registered in Moodle.';
$string['healthcheck_capabilitiesregistered_description'] = 'This plugin declares its capabilities in its db/access.php file and the Moodle plugin installer registers them in Moodle. A capability which is not registered cannot be assigned to any role and every permission check against it fails with a coding error. This can happen if the plugin files have been updated without running the Moodle upgrade afterwards.';
$string['healthcheck_capabilitiesregistered_findingmissing'] = 'The capability {$a} is declared by the plugin but is not registered in Moodle.';
$string['healthcheck_capabilitiesexclusive_title'] = 'Exclusiveness of the webservice capabilities';
$string['healthcheck_capabilitiesexclusive_summary'] = 'The webservice capabilities of this plugin must be held by the SEMCO webservice role only.';
$string['healthcheck_capabilitiesexclusive_description'] = 'The webservice capabilities of this plugin are not granted to any role archetype by default and the plugin installer assigns them to the SEMCO webservice role only. They allow to enrol and unenrol users, to read course completions and to look up user accounts. They must not be granted to a role which is used by humans in the Moodle GUI.';
$string['healthcheck_capabilitiesexclusive_findingrole'] = 'The capability {$a->capability} is allowed for the role "{$a->role}".';
$string['healthcheck_capabilitiesexclusive_findingoverride'] = 'The capability {$a->capability} is allowed for the role "{$a->role}" with a permission override in {$a->count} course or category context(s).';

// Health check items: SEMCO webservice role.
$string['healthcheck_roleexists_title'] = 'SEMCO webservice role';
$string['healthcheck_roleexists_summary'] = 'A dedicated system role for the SEMCO webservice user must exist.';
$string['healthcheck_roleexists_description'] = 'The plugin installer has created a dedicated system role which holds all capabilities which the SEMCO webservice user needs. Without this role, the SEMCO webservice user does not have any permission in Moodle.';
$string['healthcheck_roleexists_findingmissing'] = 'A role with the shortname "{$a}" does not exist.';
$string['healthcheck_roleexists_followupmissing'] = 'The SEMCO webservice role has been recreated, but its context type, its capabilities, the roles which it is allowed to assign and its assignment to the SEMCO webservice user have not. Please continue with the checks which report these aspects.';
$string['healthcheck_rolecontextlevel_title'] = 'SEMCO webservice role: Context';
$string['healthcheck_rolecontextlevel_summary'] = 'The SEMCO webservice role must be assignable in the system context only.';
$string['healthcheck_rolecontextlevel_description'] = 'The SEMCO webservice user holds the SEMCO webservice role in the system context. To make this possible, the role has to allow the system context as context type. As the role is only meant for the SEMCO webservice user, the plugin installer has configured the system context as its one and only context type, so that the role cannot be handed out within a category or a course.';
$string['healthcheck_rolecontextlevel_findingnosystem'] = 'The SEMCO webservice role cannot be assigned in the system context anymore.';
$string['healthcheck_rolecontextlevel_findingothercontexts'] = 'The SEMCO webservice role can be assigned in these context types as well: {$a}';
$string['healthcheck_rolecapabilities_findingmissing'] = 'The capability {$a} is not allowed for the SEMCO webservice role in the system context.';
$string['healthcheck_rolecapabilitiessemco_title'] = 'SEMCO webservice role: Plugin capabilities';
$string['healthcheck_rolecapabilitiessemco_summary'] = 'The SEMCO webservice role must hold all webservice capabilities of this plugin.';
$string['healthcheck_rolecapabilitiessemco_description'] = 'The SEMCO webservice user needs all webservice capabilities of this plugin to be able to enrol users, to edit enrolments and to fetch course completions. The plugin installer has assigned these capabilities to the SEMCO webservice role during the installation of this plugin and it has to stay this way.';
$string['healthcheck_rolecapabilitiesmoodle_title'] = 'SEMCO webservice role: Moodle core capabilities';
$string['healthcheck_rolecapabilitiesmoodle_summary'] = 'The SEMCO webservice role must hold several Moodle core capabilities.';
$string['healthcheck_rolecapabilitiesmoodle_description'] = 'Apart from the capabilities of this plugin, the SEMCO webservice user needs several Moodle core capabilities to be able to create and update user accounts, to view courses and to read grades. The plugin installer has assigned these capabilities to the SEMCO webservice role during the installation of this plugin and it has to stay this way.';
$string['healthcheck_rolecapabilityrest_title'] = 'SEMCO webservice role: REST capability';
$string['healthcheck_rolecapabilityrest_summary'] = 'The SEMCO webservice role must be allowed to use the REST protocol.';
$string['healthcheck_rolecapabilityrest_description'] = 'The SEMCO webservice user needs the capability webservice/rest:use to call the Moodle webservices with the REST protocol. If this plugin has been installed together with a fresh Moodle installation, this capability is not assigned by the plugin installer directly but by an ad-hoc task which runs with one of the next cron runs. In the end, it has to stay this way.';
$string['healthcheck_rolecapabilityrest_findingtaskqueued'] = 'The ad-hoc task which is going to assign the capability is still queued. It will be processed with one of the next cron runs.';
$string['healthcheck_rolecapabilityrest_findingtaskoverdue'] = 'The ad-hoc task which is supposed to assign the capability is queued, but it has not been processed for more than {$a}. Please verify that the Moodle cron is running.';
$string['healthcheck_rolecapabilityrest_findingtaskfailed'] = 'The ad-hoc task which is supposed to assign the capability has failed.';
$string['healthcheck_rolecapabilityrest_findingnotask'] = 'There is not any ad-hoc task queued which would assign the capability, thus the situation will not resolve itself.';
$string['healthcheck_rolecapabilitiessurplus_title'] = 'SEMCO webservice role: Surplus capabilities';
$string['healthcheck_rolecapabilitiessurplus_summary'] = 'The SEMCO webservice role should not hold any capability which the plugin installer has not placed in it.';
$string['healthcheck_rolecapabilitiessurplus_description'] = 'The plugin installer places a fixed set of capabilities in the SEMCO webservice role. This set is the minimum which the SEMCO webservice functions need to do their work and, at the same time, the maximum which the SEMCO integration is meant to be able to do in this Moodle instance. The role is held by the SEMCO webservice user only, which is not a human being but the account behind the webservice token which is configured in SEMCO. A capability which is allowed in the role beyond that set does therefore not serve any SEMCO webservice function, but it widens what somebody could do with that token if it ever fell into the wrong hands. Such a capability is either a leftover of a manual experiment or has been added on purpose to support a customization of this Moodle instance.';
$string['healthcheck_rolecapabilitiessurplus_findingsurplus'] = 'The SEMCO webservice role holds {$a} capability(s) which the plugin installer has not placed in it. Please be aware that the SEMCO webservice token can be used for these capabilities as well:';
$string['healthcheck_roleassignallowed_title'] = 'SEMCO webservice role: Allowed role assignments';
$string['healthcheck_roleassignallowed_summary'] = 'The SEMCO webservice role must be allowed to assign the configured enrolment role in courses.';
$string['healthcheck_roleassignallowed_description'] = 'When SEMCO enrols a user into a course, it assigns the role which is configured in the plugin settings. To be able to do so, the SEMCO webservice role must be allowed to assign this role and the role itself must be assignable in the course context. The plugin installer has configured this during the installation of this plugin. If you change the role setting on the plugin settings page afterwards, the plugin grants the permission for the newly chosen role as well. However, the plugin does not revoke the permission for the role which you have chosen before, and it cannot notice a change which was made outside of the plugin settings page.';
$string['healthcheck_roleassignallowed_findingnotallowed'] = 'The SEMCO webservice role is not allowed to assign the role "{$a}".';
$string['healthcheck_roleassignallowed_findingnocoursecontext'] = 'The role "{$a}" cannot be assigned in the course context, thus SEMCO cannot enrol anyone with that role.';
$string['healthcheck_roleassignallowed_findingsuperfluous'] = 'The SEMCO webservice role is allowed to assign these roles which it does not need: {$a}';

// Health check items: SEMCO webservice user.
$string['healthcheck_userexists_title'] = 'SEMCO webservice user';
$string['healthcheck_userexists_summary'] = 'A dedicated user account for the SEMCO webservice must exist.';
$string['healthcheck_userexists_description'] = 'The plugin installer has created a dedicated user account which SEMCO uses to authenticate against the Moodle webservices. Without this account, SEMCO cannot connect to Moodle.';
$string['healthcheck_userexists_findingmissing'] = 'A user account with the username "{$a}" does not exist.';
$string['healthcheck_userexists_followupmissing'] = 'The SEMCO webservice user has been recreated, but its role assignment, its authorisation for the SEMCO external service and its webservice token have not. Moodle has removed them along with the previous account. Please continue with the checks which report these aspects.';
$string['healthcheck_userauthmethod_title'] = 'SEMCO webservice user: Authentication method';
$string['healthcheck_userauthmethod_summary'] = 'The SEMCO webservice user must use the web services authentication method.';
$string['healthcheck_userauthmethod_description'] = 'The plugin installer has created the SEMCO webservice user account with the web services authentication method and it has to stay this way. If the authentication method was changed to the manual authentication method, the account would still be able to use the webservice as long as everything else is configured properly, but this is not what this technical account is meant to be. And with any other authentication method, the account might not be able to authenticate against the webservice anymore, depending on the authentication method and its configuration, and SEMCO might not be able to connect to Moodle anymore.';
$string['healthcheck_userauthmethod_findingmanualauth'] = 'The user account uses the authentication method "{$a->found}" instead of "{$a->expected}". A manual account is able to use the webservice as long as everything else is configured properly, so the SEMCO integration is most likely still working. However, this account is a technical account which is only used by SEMCO, thus it should use the "{$a->expected}" authentication method.';
$string['healthcheck_userauthmethod_findingwrongauth'] = 'The user account uses the authentication method "{$a->found}" instead of "{$a->expected}". Depending on this authentication method and its configuration, the account might not be able to authenticate against the webservice anymore.';
$string['healthcheck_useractive_title'] = 'SEMCO webservice user: Account state';
$string['healthcheck_useractive_summary'] = 'The SEMCO webservice user account must be confirmed and must not be suspended.';
$string['healthcheck_useractive_description'] = 'A suspended or unconfirmed user account cannot authenticate against the Moodle webservices. If the SEMCO webservice user account is in such a state, SEMCO cannot connect to Moodle.';
$string['healthcheck_useractive_findingsuspended'] = 'The user account is suspended.';
$string['healthcheck_useractive_findingunconfirmed'] = 'The user account is not confirmed.';
$string['healthcheck_userprofile_title'] = 'SEMCO webservice user: Profile';
$string['healthcheck_userprofile_summary'] = 'The SEMCO webservice user account should carry the profile data which the plugin installer has set.';
$string['healthcheck_userprofile_description'] = 'The plugin installer sets the email address, the first name and the last name of the SEMCO webservice user account. The webservice calls work without a valid email address, but a user account with a completely missing email address breaks several Moodle core code paths. The user account\'s real name does not affect the SEMCO integration at all, it just keeps the account recognizable as a technical account in the Moodle user list.';
$string['healthcheck_userprofile_findingnoemail'] = 'The user account does not have an email address.';
$string['healthcheck_userprofile_findingmissingfirstname'] = 'The user account does not have a first name.';
$string['healthcheck_userprofile_findingmissinglastname'] = 'The user account does not have a last name.';
$string['healthcheck_userprofile_findingdeviatingemail'] = 'The email address of the user account is "{$a->current}" instead of "{$a->expected}".';
$string['healthcheck_userprofile_findingdeviatingfirstname'] = 'The first name of the user account is "{$a->current}" instead of "{$a->expected}".';
$string['healthcheck_userprofile_findingdeviatinglastname'] = 'The last name of the user account is "{$a->current}" instead of "{$a->expected}".';
$string['healthcheck_userroleassignment_title'] = 'SEMCO webservice user: Role assignment';
$string['healthcheck_userroleassignment_summary'] = 'The SEMCO webservice user must hold the SEMCO webservice role in the system context.';
$string['healthcheck_userroleassignment_description'] = 'All capabilities which the SEMCO webservice user needs are bundled in the SEMCO webservice role. The user only holds these capabilities if this role is assigned to him in the system context. The plugin installer has assigned the role during the installation of this plugin and it has to stay this way.';
$string['healthcheck_userroleassignment_findingnotassigned'] = 'The SEMCO webservice user does not hold the SEMCO webservice role in the system context, thus it does not have any of the permissions which SEMCO needs.';
$string['healthcheck_userserviceauthorised_title'] = 'SEMCO webservice user: Service authorisation';
$string['healthcheck_userserviceauthorised_summary'] = 'The SEMCO webservice user must be an authorised user of the SEMCO external service.';
$string['healthcheck_userserviceauthorised_description'] = 'The SEMCO external service is restricted to authorised users by its definition in the plugin code. Only users who are on the list of authorised users are able to call the functions of this service. The plugin installer has added the SEMCO webservice user to this list during the installation of this plugin and it should stay that way.';
$string['healthcheck_userserviceauthorised_findingunrestricted'] = 'The SEMCO external service is not restricted to authorised users at the moment. As long as this is the case, Moodle does not evaluate the list of authorised users at all, thus this check cannot be assessed. Please have a look at the \'SEMCO external service: Configuration\' item first.';
$string['healthcheck_userserviceauthorised_findingmissing'] = 'The SEMCO webservice user is not an authorised user of the SEMCO external service.';
$string['healthcheck_userserviceauthorised_findingexpired'] = 'The SEMCO webservice user is an authorised user of the SEMCO external service, as the plugin installer set it. But there was an authorisation expiration date set to the SEMCO webservice user and this date has expired on {$a}. Thus SEMCO cannot call the webservice functions anymore.';
$string['healthcheck_userserviceauthorised_findingexpiring'] = 'The SEMCO webservice user is an authorised user of the SEMCO external service, as the plugin installer set it. But there was an authorisation expiration date set to the SEMCO webservice user and this date is valid until {$a} only. SEMCO will not be able to call the webservice functions anymore after that date.';
$string['healthcheck_userserviceiprestriction_title'] = 'SEMCO webservice user: IP restriction';
$string['healthcheck_userserviceiprestriction_summary'] = 'The authorisation of the SEMCO webservice user for the SEMCO external service should only be restricted to particular IP addresses if this restriction has been set on purpose and covers the IP addresses of SEMCO.';
$string['healthcheck_userserviceiprestriction_description'] = 'Moodle allows to restrict the authorisation of a user for an external service to particular IP addresses. The plugin installer has not set any such restriction, as the IP addresses of SEMCO are not known to this plugin. If a restriction is set nonetheless and does not cover the IP addresses of SEMCO, SEMCO cannot call the webservice functions at all. This plugin cannot verify whether a restriction covers SEMCO, thus it reports every restriction it finds.';
$string['healthcheck_userserviceiprestriction_findingrestricted'] = 'The authorisation of the SEMCO webservice user for the SEMCO external service is restricted to the IP address(es) {$a}. If this restriction is correct and intended, you can mute this check.';
$string['healthcheck_userserviceiprestriction_findingunrestricted'] = 'The SEMCO external service is not restricted to authorised users at the moment. As long as this is the case, this check cannot be assessed. Please have a look at the \'SEMCO external service: Configuration\' item first.';
$string['healthcheck_userserviceiprestriction_findingnoauthorisation'] = 'The SEMCO webservice user is not an authorised user of the SEMCO external service, thus there is nothing which could be restricted and this check cannot be assessed. Please have a look at the \'SEMCO webservice user: Service authorisation\' item first.';

// Health check items: SEMCO webservice token.
$string['healthcheck_usertoken_title'] = 'SEMCO webservice token';
$string['healthcheck_usertoken_summary'] = 'A usable webservice token must exist for the SEMCO webservice user.';
$string['healthcheck_usertoken_description'] = 'SEMCO authenticates against the Moodle webservices with a webservice token. The plugin installer has created a permanent token during the installation of this plugin.';
$string['healthcheck_usertoken_findingmissing'] = 'There is not any webservice token for the SEMCO webservice user and the SEMCO external service.';
$string['healthcheck_usertoken_followupmissing'] = 'A new webservice token has been created, but a new token does not connect SEMCO to Moodle on its own. Please look the token up on the plugin settings page and enter it in the Moodle connection settings of SEMCO.';
$string['healthcheck_usertoken_findingmultiple'] = 'There are {$a} webservice tokens for the SEMCO webservice user and the SEMCO external service. It is unclear which one SEMCO uses. The most recently created token is assessed here.';
$string['healthcheck_usertoken_findingnotpermanent'] = 'The webservice token is not a permanent token and thus might expire unexpectedly.';
$string['healthcheck_usertoken_findingexpired'] = 'The webservice token has expired on {$a}. This is the expiry date of the token itself, it is not the expiry date of the user\'s authorisation for the SEMCO external service.';
$string['healthcheck_usertoken_findingexpiring'] = 'The webservice token is valid until {$a} only. This is the expiry date of the token itself, it is not the expiry date of the user\'s authorisation for the SEMCO external service.';
$string['healthcheck_usertoken_findingnocreator'] = 'The webservice token does not have a creator and is therefore not shown on the Moodle webservice tokens page.';
$string['healthcheck_usertokeniprestriction_title'] = 'SEMCO webservice token: IP restriction';
$string['healthcheck_usertokeniprestriction_summary'] = 'The SEMCO webservice token should only be restricted to particular IP addresses if this restriction has been set on purpose and covers the IP addresses of SEMCO.';
$string['healthcheck_usertokeniprestriction_description'] = 'Moodle allows to restrict a webservice token to particular IP addresses. The plugin installer has not set any such restriction, as the IP addresses of SEMCO are not known to this plugin. If a restriction is set nonetheless and does not cover the IP addresses of SEMCO, SEMCO cannot connect to Moodle at all. This plugin cannot verify whether a restriction covers SEMCO, thus it reports every restriction it finds.';
$string['healthcheck_usertokeniprestriction_findingrestricted'] = 'The webservice token is restricted to the IP address(es) {$a}. If this restriction is intended, you can mute this check.';
$string['healthcheck_usertokeniprestriction_findingnotoken'] = 'There is not any webservice token for the SEMCO webservice user and the SEMCO external service, thus there is nothing which could be restricted and this check cannot be assessed. Please have a look at the \'SEMCO webservice token\' item first.';

// Health check items: SEMCO user profile fields.
$string['healthcheck_profilefieldcategory_title'] = 'SEMCO user profile fields: Category';
$string['healthcheck_profilefieldcategory_summary'] = 'The SEMCO user profile field category must exist.';
$string['healthcheck_profilefieldcategory_description'] = 'The plugin installer has created a user profile field category which groups all user profile fields which SEMCO writes into Moodle. And this profile field category should stay as it is.';
$string['healthcheck_profilefieldcategory_findingmissing'] = 'A user profile field category with the name "{$a}" does not exist.';
$string['healthcheck_profilefield_name_userid'] = 'User ID';
$string['healthcheck_profilefield_name_usercompany'] = 'Company';
$string['healthcheck_profilefield_name_userbirthday'] = 'Birthday';
$string['healthcheck_profilefield_name_userplaceofbirth'] = 'Place of birth';
$string['healthcheck_profilefield_name_branchtoken'] = 'Tenant shortname';
$string['healthcheck_profilefield_title'] = 'SEMCO user profile field: {$a->name}';
$string['healthcheck_profilefield_summary'] = 'The SEMCO user profile field "{$a->name}" ({$a->shortname}) must exist and must be configured as the plugin installer has created it.';
$string['healthcheck_profilefield_description'] = 'SEMCO writes additional user data into dedicated Moodle user profile fields. One of them is the SEMCO user profile field "{$a->name}" with the shortname {$a->shortname}. The plugin installer has created it as a locked, unique and invisible text field within the SEMCO user profile field category.';
$string['healthcheck_profilefield_findingmissing'] = 'A user profile field with the shortname "{$a}" does not exist. SEMCO is still able to create and to update users, but the data which it writes into this field gets lost silently.';
$string['healthcheck_profilefield_findingdatatype'] = 'The user profile field has the data type "{$a->found}" instead of "{$a->expected}".';
$string['healthcheck_profilefield_findingcategory'] = 'The user profile field is not placed in the user profile field category "{$a}".';
$string['healthcheck_profilefield_findingnotlocked'] = 'The user profile field is not locked, thus users are able to edit the data which SEMCO has written.';
$string['healthcheck_profilefield_findingnotunique'] = 'The user profile field does not force unique values, thus the same value can be used for more than one user.';
$string['healthcheck_profilefield_findingvisible'] = 'The user profile field is visible, thus the data which SEMCO has written is shown in the user profile.';
$string['healthcheck_profilefield_findingrequired'] = 'The user profile field is required, thus every user has to fill it even if he does not come from SEMCO.';
$string['healthcheck_profilefield_findingsignup'] = 'The user profile field is shown on the signup page.';
$string['healthcheck_profilefield_findingparam1'] = 'The user profile field has a display size of {$a->found} instead of {$a->expected}.';
$string['healthcheck_profilefield_findingparam2'] = 'The user profile field has a maximum length of {$a->found} instead of {$a->expected}.';

// Health check items: Recompletion plugin.
$string['healthcheck_recompletioninstalled_title'] = 'Companion plugin local_recompletion';
$string['healthcheck_recompletioninstalled_summary'] = 'The companion plugin local_recompletion should be installed to let SEMCO reset course completions.';
$string['healthcheck_recompletioninstalled_description'] = 'If a course is configured in SEMCO to fetch completion data from Moodle, SEMCO resets a user\'s course completion on every SEMCO enrolment into a course so that the course is clean before the user starts it. Moodle cannot do this on its own, SEMCO relies on the companion plugin local_recompletion for it. Without that plugin, the webservice function to reset a course completion fails with an error and an enrolment fails as well as soon as SEMCO requests a course completion reset along with it.';
$string['healthcheck_recompletioninstalled_findingmissing'] = 'The plugin local_recompletion is not installed or its version is too old.';
$string['healthcheck_recompletionondemand_title'] = 'Course recompletion: Type';
$string['healthcheck_recompletionondemand_summary'] = 'Courses with SEMCO enrolments should have their recompletion type set to "On demand".';
$string['healthcheck_recompletionondemand_description'] = 'SEMCO can only reset a user\'s course completion if the "Recompletion type" setting of local_recompletion is set to "On demand" in the particular course. The site-wide default of this setting should be set to "On demand" so that every course which is created in the future is prepared right away. In addition to that, the teachers should not overwrite this setting in their courses, as the site-wide default only prefills the course settings and does not have any effect on a course anymore afterwards.';
$string['healthcheck_recompletionondemand_findingsitedefault'] = 'The site-wide default of the "Recompletion type" setting is not set to "On demand".';
$string['healthcheck_recompletionondemand_findingcourses'] = '{$a->count} of {$a->total} course(s) with SEMCO enrolments do not have their recompletion type set to "On demand": {$a->courses} SEMCO cannot reset a course completion in these courses.';
$string['healthcheck_recompletionnotify_title'] = 'Course recompletion: Notification';
$string['healthcheck_recompletionnotify_summary'] = 'The local_recompletion notification about a course completion reset should be disabled.';
$string['healthcheck_recompletionnotify_description'] = 'SEMCO resets a user\'s course completion on every SEMCO enrolment into a course, even on the very first one. This is done on purpose as the user might have been enrolled into the course manually before and might have completed it then. A notification about such a reset would confuse the user, thus the recompletion notification of local_recompletion should be disabled. The site-wide default of this setting should be disabled so that every course which is created in the future is prepared right away. In addition to that, the teachers should not overwrite this setting in their courses, as the site-wide default only prefills the course settings and does not have any effect on a course anymore afterwards.';
$string['healthcheck_recompletionnotify_findingsitedefault'] = 'The site-wide default of the "Send recompletion message" setting is not set to "Disabled".';
$string['healthcheck_recompletionnotify_findingcourses'] = '{$a->count} of {$a->total} course(s) with SEMCO enrolments notify their users about a course completion reset: {$a->courses}';
$string['healthcheck_recompletionactivities_title'] = 'Course recompletion: Activity reset';
$string['healthcheck_recompletionactivities_summary'] = 'The activity types which local_recompletion resets should be configured site-wide and should not be weakened in the courses.';
$string['healthcheck_recompletionactivities_description'] = 'By default, local_recompletion does not reset any activity in a course unless the teacher activates the activity type\'s reset in his particular course. To ease the teacher\'s life and to avoid that SEMCO triggers a course completion reset but nothing is deleted from the course in the end, you should enable all activity types in the \'Plugins settings\' section of the local_recompletion settings which are relevant for the courses in your Moodle instance. In addition to that, the teachers should not reset fewer activity types in their courses than the site-wide settings ask for, as these settings only prefill the course settings and do not have any effect on a course anymore afterwards. A course which resets more activity types than the site-wide settings is fine, though.';
$string['healthcheck_recompletionactivities_findingnosettings'] = 'The installed local_recompletion version does not offer any site-wide activity type setting, thus this check cannot be assessed.';
$string['healthcheck_recompletionactivities_findingnone'] = 'There is not any activity type which is reset. A course completion reset does not delete anything from the course, which means that local_recompletion does not have any effect at all.';
$string['healthcheck_recompletionactivities_findingsome'] = '{$a->count} activity type(s) are not reset: {$a->activities} Please be aware that the data of these activity types stays in the course when SEMCO resets a course completion.';
$string['healthcheck_recompletionactivities_findingcourses'] = '{$a->count} of {$a->total} course(s) with SEMCO enrolments reset fewer activity types than the site-wide settings ask for: {$a->courses} Please be aware that the data of these activity types stays in these courses when SEMCO resets a course completion.';
$string['healthcheck_recompletionresetmycompletion_title'] = 'Course recompletion: Self-service reset';
$string['healthcheck_recompletionresetmycompletion_summary'] = 'SEMCO course participants should not be able to reset their course completion themselves.';
$string['healthcheck_recompletionresetmycompletion_description'] = 'By default, local_recompletion grants the local/recompletion:resetmycompletion capability to the participant role. That way, course participants could reset a course completion on their own. Within a SEMCO-Moodle setup, the course completion should be controlled by SEMCO only. In addition to that, the role should not be granted this capability with a permission override in a course or in the category above it either, as such an override applies within the course regardless of the role definition.';
$string['healthcheck_recompletionresetmycompletion_findingallowed'] = 'The role "{$a}" is allowed to reset its own course completion.';
$string['healthcheck_recompletionresetmycompletion_findingoverride'] = 'The role "{$a->role}" is allowed to reset a course completion in {$a->count} of {$a->total} course(s) which hold SEMCO enrolments: {$a->courses} This is caused by a permission override in the course or in the category above it, which grants the capability within the course even though the role definition does not.';

// Health check items: Recommended Moodle settings.
$string['healthcheck_allowaccountssameemail_title'] = 'Unique email addresses';
$string['healthcheck_allowaccountssameemail_summary'] = 'Moodle user accounts should be forced to have unique email addresses.';
$string['healthcheck_allowaccountssameemail_description'] = 'With the Moodle setting allowaccountssameemail set to No, you avoid that SEMCO creates a Moodle user if - for any reason - another Moodle user already exists for the same email address.';
$string['healthcheck_allowaccountssameemail_findingallowed'] = 'Moodle allows multiple user accounts with the same email address at the moment.';
$string['healthcheck_manualauthlockedfields_title'] = 'Locked user profile fields';
$string['healthcheck_manualauthlockedfields_summary'] = 'The user profile fields which SEMCO owns should be locked for manually authenticated users.';
$string['healthcheck_manualauthlockedfields_description'] = 'SEMCO acts as leading system for the users which it creates in Moodle and overwrites their first name, last name and email address if necessary. As long as these fields are not locked on the settings page of the manual authentication method, a Moodle user can change them himself and might be confused when the change is reverted by SEMCO sometime later. Locking these fields affects all users with manual authentication, not only the users which SEMCO has created.';
$string['healthcheck_manualauthlockedfields_findingunlocked'] = 'The user profile field "{$a->field}" is set to "{$a->setting}" instead of "{$a->expected}" on the settings page of the manual authentication method.';
$string['healthcheck_selfenrolment_title'] = 'Self enrolment';
$string['healthcheck_selfenrolment_summary'] = 'Courses should not offer self enrolment.';
$string['healthcheck_selfenrolment_description'] = 'You will not want that users who got enrolled into a course by SEMCO are able to enrol into other courses themselves without paying for these courses via SEMCO. This item assesses the courses which hold SEMCO enrolments at the moment and reports those which offer an active self enrolment instance. A user which SEMCO has enrolled into a course is an ordinary Moodle user everywhere else, thus a course is only reported if the role which every authenticated user holds is allowed to enrol itself in it. Additionally, a course whose self enrolment is guarded by an enrolment key is reported separately, as such a key is a hurdle for a user who does not know it. The key itself is not assessed, only its presence, as a user might know the key or might have received it from somebody else. Ideally, no course in the Moodle instance offers self enrolment at all.';
$string['healthcheck_selfenrolment_findingopencourses'] = '{$a->count} of {$a->total} course(s) with SEMCO enrolments offer a self enrolment which every authenticated user can use right away: {$a->courses}';
$string['healthcheck_selfenrolment_findingguardedcourses'] = '{$a->count} of {$a->total} course(s) with SEMCO enrolments offer a self enrolment which is guarded by an enrolment key: {$a->courses} An authenticated user who knows that key can use it right away.';
$string['healthcheck_enrolmentroleviewparticipants_title'] = 'Participant visibility of the enrolment role';
$string['healthcheck_enrolmentroleviewparticipants_summary'] = 'The role with which SEMCO enrols users should not be able to view the other course participants.';
$string['healthcheck_enrolmentroleviewparticipants_description'] = 'You should assume that the participants of a course which is sold via SEMCO are not all members of the same class or cohort and do not know each other. If they were able to see each other in the participants list, you might even have a data protection leak.';
$string['healthcheck_enrolmentroleviewparticipants_findingallowed'] = 'The role "{$a}" is allowed to view the course participants.';
$string['healthcheck_enrolmentroleviewparticipants_findingoverride'] = 'The role "{$a->role}" is allowed to view the course participants in {$a->count} of {$a->total} course(s) which hold SEMCO enrolments: {$a->courses} This is caused by a permission override in the course or in the category above it, which grants the capability within the course even though the role definition does not.';
$string['healthcheck_messaging_title'] = 'Moodle messaging system';
$string['healthcheck_messaging_summary'] = 'The Moodle messaging system should be disabled.';
$string['healthcheck_messaging_description'] = 'You should assume that the participants of a course which is sold via SEMCO are not all members of the same class or cohort and do not know each other. If they were able to message each other, you would open a communication channel between people who have nothing in common but the fact that they booked the same course.';
$string['healthcheck_messaging_findingenabled'] = 'The Moodle messaging system is enabled at the moment.';
$string['healthcheck_coursecompletedmessage_title'] = 'Moodle course completion notification';
$string['healthcheck_coursecompletedmessage_summary'] = 'The Moodle "Course completed" notification should be disabled for the whole site.';
$string['healthcheck_coursecompletedmessage_description'] = 'From SEMCO 7.9 on, SEMCO is able to send out information mails itself as soon as a course has been completed. The Moodle "Course completed" notification should therefore be disabled for the whole site to avoid that your users receive two mails for the same event. A notification which is disabled for the whole site is not sent at all and it is not offered in the notification preferences of the users either, so that they cannot enable it for themselves.';
$string['healthcheck_coursecompletedmessage_findingenabled'] = 'The Moodle \'Course completed\' notification is enabled for the whole site at the moment, thus Moodle sends it and your users are able to enable it in their notification preferences.';

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
$string['updater_2025100601_addcapability'] = 'The capability \'enrol/semco:checkuserexistence\' was added to the role \'SEMCO webservice\' during the plugin update.';

// Capabilities.
$string['semco:checkuserexistence'] = 'Check the existence of a Moodle user by a given field';
$string['semco:editenrolment'] = 'Edit an existing SEMCO user enrolment';
$string['semco:enrol'] = 'Enrol SEMCO users into a course';
$string['semco:getenrolments'] = 'Get the existing SEMCO user enrolments from a course';
$string['semco:getcoursecompletions'] = 'Get the course completions for given SEMCO user enrolments';
$string['semco:resetcoursecompletion'] = 'Reset the course completion for the given SEMCO user enrolment';
$string['semco:unenrol'] = 'Unenrol SEMCO users from a course';
$string['semco:usewebservice'] = 'Use the SEMCO enrolment webservices';
$string['semco:viewhealthcheck'] = 'View the SEMCO health check';
$string['semco:viewreport'] = 'View the SEMCO enrolment report';

// Tasks.
$string['task_cleanorphaned'] = 'Clean orphaned SEMCO enrolment instances.';

// Checks API.
$string['checkhealthcheck'] = 'SEMCO health check';
$string['checkhealthcheckok'] = 'All aspects of the SEMCO setup are in the desired state.';
$string['checkhealthcheckerror'] = '{$a} aspect(s) of the SEMCO setup need your attention. At least one of them is broken, thus the SEMCO integration does not work at the moment.';
$string['checkhealthchecknotice'] = '{$a} aspect(s) of the SEMCO setup deviate from the state which the plugin installer has created or from a recommendation for a SEMCO-Moodle integration. None of them affects the SEMCO integration, they are worth a look nonetheless.';
$string['checkhealthcheckwarning'] = '{$a} aspect(s) of the SEMCO setup need your attention. None of them is broken, thus the SEMCO integration still works at the moment.';
$string['checkhealthcheckdetails'] = '<p>Review the affected aspects on the <a href="{$a->url}">SEMCO health check</a> page. Please note: It covers all checks of the health check page, including the companion plugin local_recompletion and the recommended global Moodle settings. If you have decided against one of these recommendations on purpose, you can mute the particular check on the health check page.</p>';

// Privacy API.
$string['privacy:metadata:enrol_semco:SEMCO'] = 'SEMCO is a course management system which is connected to Moodle for organizing course enrolments.';
$string['privacy:metadata:enrol_semco:SEMCO:user_profile'] = 'User profile data like the username, firstname, lastname and email are shared between SEMCO and Moodle. The data flows from SEMCO to Moodle.';
$string['privacy:metadata:enrol_semco:SEMCO:course_enrolments'] = 'Course enrolment metadata like course memberships and enrolment dates are shared between SEMCO and Moodle. The data flows from SEMCO to Moodle.';
$string['privacy:metadata:enrol_semco:SEMCO:course_completions'] = 'Course completion data including completion dates, grades and passing states are shared between SEMCO and Moodle. The data flows from Moodle to SEMCO.';
