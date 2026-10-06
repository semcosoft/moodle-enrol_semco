moodle-enrol_semco
==================

[![Moodle Plugin CI](https://github.com/semcosoft/moodle-enrol_semco/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=main)](https://github.com/semcosoft/moodle-enrol_semco/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain)

Moodle enrolment plugin which allows the SEMCO seminar management system to enrol and manage users in Moodle courses


Requirements
------------

This plugin requires Moodle 5.2+


Motivation for this plugin
--------------------------

Moodle is great for managing and running e-learning courses, however is lacks some features and matureness when it comes to selling and organizing course memberships.\
On the other hand, SEMCO is great in selling and organizing course memberships, but it lacks features to provide e-learning content for blended learning and self-learning scenarios.\
This plugin bridges this gap and allows organizations to sell and manage their Moodle course memberships in SEMCO.


Installation
------------

Install the plugin like any other plugin to folder
/enrol/semco

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Soft dependencies
-----------------

The SEMCO enrolment plugin is able to reset a user's course completion if he gets enrolled into a particular course by SEMCO once more.
To realize this course completion reset and to avoid to re-invent the wheel, this plugin has a soft dependency to local_recompletion (see https://github.com/danmarsden/moodle-local_recompletion) by Dan Marsden.

Please install local_recompletion with at least version 2024071103 alongside this plugin if you plan to use subsequent user enrolments into the same course and need to reset course completion.
If you do not need plan to reset course completion, you do not need to install local_recompletion.


Usage & Settings
----------------

During the installation, several steps to enable the webservice communication from SEMCO to Moodle are done automatically to save you time and headaches:

* The webservice subsystem is enabled if it is not enabled yet.\
  You can verify this on /admin/settings.php?section=externalservices.
* The webservice REST protocol is enabled if it is not enabled yet.\
  You can verify this on /admin/settings.php?section=webserviceprotocols.
* The 'Webservice' authentication method is enabled automatically.\
  You can verify this on /admin/settings.php?section=manageauths.
* A 'SEMCO webservice' system role is created automatically.\
  You can verify this on /admin/roles/manage.php.
* The following capabilities are automatically added as allowed to the 'SEMCO webservice' role.\
  You can verify them on /admin/roles/manage.php:
  * enrol/semco:usewebservice
  * enrol/semco:enrol
  * enrol/semco:unenrol
  * enrol/semco:editenrolment
  * enrol/semco:getenrolments
  * moodle/role:assign
  * moodle/course:useremail
  * moodle/course:view
  * moodle/user:create
  * moodle/user:delete
  * moodle/user:update
  * moodle/user:viewdetails
  * moodle/user:viewhiddendetails
  * webservice/rest:use
* The 'SEMCO webservice' is automatically allowed to assign the 'student' role.\
  You can verify this on /admin/roles/allow.php?mode=assign.
* A 'SEMCO webservice' user is created automatically.\
  You can verify this on /admin/user.php.
* The 'SEMCO webservice' user is added automatically to the 'SEMCO webservice' system role.\
  You can verify this on /admin/roles/assign.php?contextid=1
* A webservice token is created automatically for the 'SEMCO webservice' user.\
  You can verify this on /admin/webservice/tokens.php.
  It is correct that you will not see the token there, you will just see _that_ a token exists.
* A 'SEMCO' user profile field category is created automatically and the following user profile fields are added to this category.
  You can verify this on /user/profile/index.php.
  * SEMCO User ID
  * SEMCO User company
  * SEMCO User birthday
  * SEMCO User place of birth
* The enrol_semco plugin is activated automatically.\
  You can verify this on /admin/settings.php?section=manageenrols.

Each step is monitored with a clear success message in the installation wizard (in the web GUI as well as in the CLI). Watch out for any error messages during the installation of the plugin. If you see any error messages, please try to uninstall the plugin and re-install it again. If the error messages continue to be posted, please step through the list above and check if you can spot any asset which could block the automatic installation.

After installing the plugin and after the automatic configuration, it is ready to be used with SEMCO.

To configure the plugin and its behaviour, please visit:
Site administration -> Plugins -> Enrolments -> SEMCO

There, you find five sections:

### 1. Connection information

In this section, you will find the Moodle base URL and the webservice token which was automatically created during the plugin installation. Please use this data to configure the Moodle connection in SEMCO.\
If the 'SEMCO webservice' user has more than one token for the SEMCO external service, the oldest token is shown here as this is the token which has most probably been entered in SEMCO initially. The health check reports the fact that there is more than one token.

### 2. Health check

In this section, you will find the link to a site report where you can check if the Moodle setup which this plugin needs is still intact. If at least one aspect of this setup needs your attention, this section will tell you as well.
Please see the 'Health check' chapter below for more details.

### 3. Enrolment report

In this section, you will find the link to a site report where you can see all enrolments which have been made by SEMCO.
For managers, this report is also linked in the 'Reports' section within the site administration.

Additionally, you can adapt the report table with these two settings:

* With the 'Initial sorting column' setting, you control by which column the report is sorted when it is opened.
* With the 'Optional report columns' setting, you control which of the optional columns are shown in the report. The columns which are offered in the 'Initial sorting column' setting are always shown and are therefore not offered here.
  Please note that the 'SEMCO User birthday' and 'SEMCO User place of birth' columns are not enabled by default. These two columns show a particularly sensitive piece of personal data, and the enrolment report is a site wide report which can be downloaded as a file as well. Please enable them only if you really need them there.

### 4. Enrolment process

In this section, you control with which role SEMCO enrols users into courses. The configured role is mandatory for all users who are enrolled from SEMCO and cannot be overridden with the SEMCO enrolment webservice endpoint.

### 5. Course completion

In this section, you can verify that the local_recompletion plugin is installed and SEMCO would be able to reset the completion of a user if he is enrolled into a particular course once more.


Connecting to SEMCO
-------------------

This documentation explains how to install this plugin in Moodle until it is ready to be connected by SEMCO.

The other side of this connection is documented by SEMCO on https://www.semcosoft.com/de/helpreader/moodle-online-shop-mit-semco-moodle-integration (german).


Capabilities
------------

This plugin also introduces these additional capabilities.

All of them except the last two, enrol/semco:viewreport and enrol/semco:viewhealthcheck, are webservice capabilities which are only there to let SEMCO do its job. Please see the important note below the list before you grant any of them to a role.

### enrol/semco:usewebservice

This capability controls the ability to control Moodle enrolments via the SEMCO enrolment webservice.

### enrol/semco:enrol

This capability controls the ability to enrol a SEMCO user into a course.

### enrol/semco:unenrol

This capability controls the ability to unenrol a SEMCO user from a course.

### enrol/semco:editenrolment

This capability controls the ability to edit an existing SEMCO user enrolment in a course.

### enrol/semco:getenrolments

This capability controls the ability to get the existing SEMCO user enrolments in a course.

### enrol/semco:getcoursecompletions

This capability controls the ability to get the course completions for given SEMCO user enrolments.

### enrol/semco:resetcoursecompletion

This capability controls the ability to reset the course completion for a given SEMCO user enrolment.

### enrol/semco:checkuserexistence

This capability controls the ability to check the existence of a Moodle user by a given field.

### enrol/semco:viewreport

This capability controls the ability to view the enrolment report of all SEMCO user enrolments.

In contrast to the webservice capabilities above, this capability is allowed for the manager role by default.

### enrol/semco:viewhealthcheck

This capability controls the ability to view the SEMCO health check.

In contrast to enrol/semco:viewreport, this capability is not allowed for any role archetype by default, not even for the manager role. This is a deliberate decision: The health check does not only show the state of the plugin installation, it also allows to fix several aspects of it automatically and these automatic fixes change the site configuration. Please grant this capability only to roles which you would also trust with these configuration changes.

### Important note about the webservice capabilities

This note applies to every capability in the list above except enrol/semco:viewreport and enrol/semco:viewhealthcheck.

By default, these capabilities are not allowed to any role archetype as they should just be used by a webservice.
They will be automatically assigned to the 'SEMCO webservice' role during the plugin installation.

These capabilities should not be granted to any role which is used by humans in the Moodle GUI. Furthermore, there is no user interface in Moodle which makes use of them as all of those capabilities only become effective in combination with a webservice token for the 'SEMCO' service.


Scheduled Tasks
---------------

This plugin also introduces these additional scheduled tasks:

### \enrol_semco\task\cleanup_orphaned_enrolment_instances

This task is there to clean orphaned SEMCO enrolment instances.
By default, the task is enabled and runs once per hour.


How this plugin works
---------------------

### General

This plugin is implemented as enrolment plugin as this is its main purpose: Enrolling users into Moodle courses. To achieve this goal, this plugin offers multiple webservice functions which are called by SEMCO.\
However, it is important to know that this plugin is part of the full SEMCO-Moodle integration. The business logic of this integration is implemented in SEMCO itself. SEMCO will not only communicate with this plugin but also with Moodle core webservice functions, especially to create users and to fill their user profile fields. To allow this communication, this plugin sets several capabilities from Moodle core during its installation (see above).

### Course enrolments

Course enrolments which are created by SEMCO with this enrolment method are special in several ways. As Moodle administrator, you should know these facts:

* There is one instance of this enrolment method _per course participant_ instead of one common 'SEMCO' enrolment instance for the whole course. This decision was made to allow SEMCO to store the (user-specific SEMCO booking ID within Moodle and to show this information in the course participant list).
* These user-specific enrolment instances are added and removed on-the-fly everytime when SEMCO is adding a user to a course or removing a user from a course. Adding the SEMCO enrolment method to a course manually is neither necessary nor possible.
* These user-specific enrolment instances do not have any enrolment instance settings. You simply do not need to configure them.
* These user-specific enrolment instances are protected. You simply cannot remove them from a course.
* Likewise, the user enrolments are protected as well. You simply cannot manually unenrol a user which was enrolled by SEMCO.
* Furthermore, the role assignments of these enrolments are protected as well. You can assign additional roles to enrolled SEMCO users, but you cannot remove the role which was assigned by SEMCO.

### Data mappings

Within the SEMCO-Moodle integration user-specific data is passed from SEMCO to Moodle. As Moodle administrator, you should know these facts:

* The user accounts which are created by SEMCO are created as manual user accounts. There is no 'SEMCO' auth method for Moodle.
* The usernames / login names of these users follow a common scheme. They all start with 'kn-', followed by a six digit number, followed by a dash, followed by another digit. An example would be: kn-010020-1. This name scheme might differ in future SEMCO releases or in customer-specific SEMCO installations.
* This plugin created a user profile field called 'SEMCO User ID' during its installation. This user profile field holds the user ID of the user from within SEMCO. This profile field is filled when SEMCO creates a Moodle user. The 'SEMCO User ID' is normally the same as the Moodle username, just without the last digit.
* As mentioned above, the SEMCO booking ID of a particular course booking is stored into the name of the enrolment instance with which the user is enrolled into a course. SEMCO booking IDs are unique which means that, if you look at a particular enrolment instance in a course, you can trace this enrolment back to the booking in SEMCO with the help of the given SEMCO booking ID.
* The base user profile fields (first name, last name, email address) and the 'SEMCO User ID' profile field of all users which are created by SEMCO are kept up to date by SEMCO. If these fields are changed in SEMCO for any reason, they are updated in Moodle as well.

### Warnings

As Moodle administrator, you have the power to tamper with the user which are created by SEMCO and to break the integration for these users. This risk could not be eliminated programmatically during the implementation of this plugin.

Thus, please do not fiddle with this data, please:

1. Do not change the user name of SEMCO users. You will break their ability to login to Moodle and might prevent that SEMCO will find this user again in future webservice calls.
2. Do not change the 'SEMCO User ID' profile field of SEMCO users for the same reason.
3. Do not change the first name, last name or email address of SEMCO users. Change these fields directly in SEMCO. SEMCO will overwrite these fields during its next full synchronisation with Moodle anyway.
4. Do not change the settings of the 'SEMCO User ID' profile field, especially do not rename it, unlock it, make it required or change the visibility to anything else than 'Not visible'. You might break the expected / proper usage of this profile field or uncover the profile field data to other users who do not need to see it.
5. Do not fill the 'SEMCO User ID' profile field of manually created Moodle users and do not try to "link" existing Moodle users to SEMCO by filling their 'SEMCO User ID' profile field. Let SEMCO handle its users itself. SEMCO will not know about these users anyway.
6. Do not manually enrol SEMCO users who got enrolled into course A by SEMCO into other courses which are controlled by SEMCO as well. Let SEMCO manage its enrolments itself. SEMCO will not know about these manual enrolments anyway.
7. Last but not least, do not delete Moodle users who were created by SEMCO. You can always trigger the suspension of such a Moodle user account from within SEMCO, but you must not delete Moodle users manually.


Important global Moodle settings
--------------------------------

During the design of the SEMCO-Moodle integration, some assumptions about the usage scenarios were made which have consequences on global Moodle settings.
Your SEMCO-Moodle integration does not necessarily need to fully match these usage scenarios, but you should think about them before the go-live of your integration:

* The email addresses of Moodle users should be unique (i.e. the Moodle setting allowaccountssameemail is set to No). This is because you will want to avoid that SEMCO creates a Moodle user if - for any reason - another Moodle user already exists for the same email address. However, SEMCO is able to deal with multiple tenants where multiple user accounts have the same email address. If your usage scenario requires it and as soon as your SEMCO consultant recommends it, you can set the allowaccountssameemail setting to Yes.
* As mentioned above already, SEMCO acts as leading system for the users which it creates in Moodle and will overwrite the first name, last name or email address fields if necessary. However, Moodle users can still update these profile fields themselves in their Moodle profile by default and might be confused if a change which they made is "magically" reverted sometime later. To avoid this, you can lock these three profile fields on /admin/settings.php?section=authsettingmanual. However, please gauge the pros and cons yourself as locking these fields will affect all existing users with manual authentication and not only SEMCO users.
* A course which is sold via SEMCO - or ideally all courses in the Moodle instance - should not have self-enrolment enabled. Alternatively, you should configure the 'Authenticated user' role in Moodle in a way that users cannot enrol into courses themselves. This is because you will not want that users who got enrolled into course A by SEMCO are able to enrol into course B themselves (without paying for the course via SEMCO). And you might not want that users who came from SEMCO snoop around in other Moodle courses which are not connected to SEMCO.
* The role with which SEMCO enrols users into courses (and which can be set in the plugin configuration) should not have the moodle/course:viewparticipants capabilities set. This is because you should assume that these course participants are not all members of the same class / cohort and do not know each other. If they would see each other participants in the course, you might even have a data protection leak.
* For the same reason, you should also disable the Moodle messaging system to avoid that users get in touch with each other on the Moodle instance.
* The system message 'Course completed' should be disabled for the whole site with the 'Enabled' toggle on /admin/message.php. This is because, from SEMCO 7.9 on, SEMCO is able to send out information mails itself as soon as a course has been completed. Disabling the notification for the whole site is more than just changing its default: Moodle does not send it at all then and your users cannot enable it in their notification preferences either.

You do not have to walk through these settings on your own: The plugin's health check (see the 'Health check' chapter below) verifies each of them in its 'Recommended Moodle settings' category and, where this is possible, offers to apply the recommendation for you with a single click. Some findings still have to be fixed manually, for example the self-enrolment of particular courses, as these enrolment instances are in the hands of the teachers.


Recommended settings for local_recompletion
-------------------------------------------

If you decide to use the companion plugin local_recompletion to allow SEMCO to reset course completions during subsequent user enrolments, please verify these settings of local_recompletion before the go-live of your integration:

* SEMCO can only reset a user's course completion if the "Recompletion type" setting in the particular course is set to "On demand". To avoid that each and every teacher has to go to the course recompletion settings in his course and save the settings before SEMCO can reset a user's course completion, you should set the site-wide default of the "Recompletion type" setting to "On demand" on /admin/settings.php?section=local_recompletion.
* By design, SEMCO will reset a user's course completion even on the user's first SEMCO enrolment into the course. This might seem unnecessary, but as it is not impossible that the user might have been manually enrolled before into that course (and might have completed it then), SEMCO resets the course completion just to be sure that the course is clean before each and every SEMCO enrolment. Against this background, the standard behaviour of local_recompletion to send out a notification message to the user when the course is reset will confuse the user. To avoid such confusion, you should disable the "Send recompletion message" setting on /admin/settings.php?section=local_recompletion.
* By default, local_recompletion is configured in a way that it does not reset any activity in a course unless the teacher activates the activity type's reset in his particular course. To ease the teacher's life and to avoid that SEMCO resets a course completion but the user's data within the activities stays in the course, you should enable all items in the "Plugins settings" section on /admin/settings.php?section=local_recompletion which are relevant for the courses in your Moodle instance.
* When SEMCO resets a user's course completion, local_recompletion should delete the user's grades from the gradebook along with the activity data. Otherwise, the gradebook keeps showing the grades of attempts which do not exist anymore. Thus, you should keep the "Delete all grades for the user" setting enabled on /admin/settings.php?section=local_recompletion. As this setting is only a default for new courses, the teachers should not disable it in their courses either.
* The data which a course completion reset deletes is lost for good unless local_recompletion archives it. This concerns the course and activity completion data on the one hand: You should keep the "Archive completion data" setting enabled on /admin/settings.php?section=local_recompletion and, as this setting is only a default for new courses, you should enable the "Force archive completion data" setting on top, which enforces the archiving of the completion data in every course. On the other hand, this concerns the data of the activities themselves, for example the quiz attempts, which is not covered by that switch: You should keep the "Archive" option of every activity type which you have set to delete its data enabled in the "Plugins settings" section, and the teachers should not disable it in their courses either.
* local_recompletion can restrict a course completion reset to users who are enrolled with particular enrolment methods with its "Enrol method" setting on /admin/settings.php?section=local_recompletion. If this restriction is set but does not include the SEMCO enrolment method, local_recompletion refuses to reset the course completion of a SEMCO user, the webservice function enrol_semco_reset_course_completion reports the reset as unsuccessful and the course completion stays in place. Thus, you should either leave this setting empty or include the SEMCO enrolment method.
* By default, local_recompletion grants the local/recompletion:resetmycompletion capability to the participant role. That way, course participants could reset a course's completion on their own. Within a SEMCO-Moodle setup, this should be avoided. Please retract the local/recompletion:resetmycompletion capability from at least the participants role after installing the plugin.
* By default, local_recompletion grants the local/recompletion:manage capability to the teacher and the manager roles. That way, teachers and managers can change the recompletion settings within their courses and weaken the site-wide rules from the recommendations above, for example switch the recompletion type away from "On demand" or exclude activity types from the reset. Within a SEMCO-Moodle setup, the course recompletion settings should follow the site-wide defaults only, which local_recompletion copies into every course when it is created. Please retract the local/recompletion:manage capability from all roles after installing the plugin. Site administrators are not affected by this as they hold every capability anyway, they can still adjust the settings of a particular course if this is really needed.

Again, you do not have to walk through these settings on your own: The plugin's health check (see the 'Health check' chapter below) verifies each of them in its 'Recompletion plugin' category and, where this is possible, offers to apply the recommendation for you with a single click. Some findings still have to be fixed manually, for example the reset of the particular activity types, as picking the reset strategy of an activity type is a didactical decision which the health check cannot make for you.


Health check
------------

As described in the 'Installation' chapter above, this plugin sets up a whole bunch of Moodle assets during its installation. All of these assets are needed for the plugin to work properly, but Moodle does not protect them from being changed or removed afterwards. An administrator might disable the webservice subsystem, might suspend the SEMCO webservice user or might revoke a capability from the SEMCO webservice role - and the SEMCO connection would silently stop working.

The health check verifies every single aspect of this setup and reports if it is still in the desired state. It is available as a site report on /enrol/semco/healthcheck.php and it is linked in the 'Reports' section within the site administration as well as in the plugin settings.

The health check items are grouped into eight categories. The first six categories - the webservice infrastructure, the SEMCO enrolment plugin, the SEMCO webservice role, the SEMCO webservice user, the SEMCO webservice token and the SEMCO user profile fields - cover the state of the plugin installation.

The seventh category, 'Recompletion plugin', covers the companion plugin local_recompletion which SEMCO needs to reset course completions. It checks whether the plugin is installed at all and, if it is, whether it is configured as described in the 'Recommended settings for local_recompletion' chapter above. If the plugin is not installed, the other items of this category cannot be assessed.

The eighth category, 'Recommended Moodle settings', goes one step further: It checks the global Moodle settings which are described in the 'Important global Moodle settings' chapter above. These settings are not touched by the plugin installer and your integration does not necessarily need to follow them. They are recommendations and not requirements, thus these items are meant to make you think about them rather than to make you change them right away.

Each item reports one of these six statuses:

* OK: The checked aspect is in the desired state. No action is required.
* Notice: The checked aspect either deviates from the state which the plugin installer has created or it does not follow a recommendation. The SEMCO integration works as expected. There is one exception: If the plugin was installed together with a fresh Moodle installation, the REST capability of the SEMCO webservice role is assigned by an ad-hoc task with the first cron run. While this task is waiting, the item reports a notice even though SEMCO cannot connect yet. If the task has not been processed within 3 minutes, the item reports an error instead.
* Warning: The checked aspect needs your attention. The SEMCO integration still works, but the aspect is either not in the desired state or it has a consequence which you should not accept unknowingly.
* Error: The checked aspect is broken and the SEMCO integration does not work at the moment. These items need your immediate attention, for example when the Moodle webservice subsystem is switched off, when the SEMCO webservice user is suspended or when its webservice token is gone.
* N/A: The checked aspect could not be assessed as one of its prerequisites is missing.
* Muted: You have muted the item. Regardless of its real status, it does not bother you anymore until you unmute it again (see below).

The distinction between Error and Warning is what makes the health check actionable: An error means that SEMCO cannot talk to Moodle or cannot do its job right now, so every minute counts. A warning means that you should have a look at the aspect, but your integration keeps running in the meantime. The items of the 'Recompletion plugin' and 'Recommended Moodle settings' categories never report an error as they do not cover the SEMCO integration itself.

For each item, you can open a details view which explains the background of the item and, if the item found a problem, names the concrete finding.

The 'Actions' column offers up to four icons per item:

* The info icon opens the details view which is described above.
* The wand icon fixes the item automatically. Before anything is changed, a confirmation dialogue names the findings which are going to be fixed. An item can only be fixed automatically if each and every one of its findings can be fixed. This is offered wherever the desired state is known and can be restored safely. Even an asset which is gone completely is created again, just as the plugin installer has created it. If such a fix cannot do the whole job, for example because a new webservice token still has to be entered in SEMCO, the success message tells you what is left to do.\
  Some automatic fixes are not entirely harmless, because they remove something which somebody may have added on purpose or because they change something which affects more than the SEMCO integration. In these cases, the confirmation dialogue shows a warning in red. If you are in doubt, fix the finding yourself or contact the [SEMCO support](https://support.semcosoft.com).\
  Some findings cannot be fixed automatically at all, especially findings which require decisions which only you can make and everything which has been set outside of the reach of the site administration.\
  The items of the 'Recompletion plugin' and 'Recommended Moodle settings' categories can be fixed automatically as well where this is possible. Please note that such a finding is not a fault of the SEMCO plugin, the automatic fix rather implements the recommendation from this README for you. The confirmation dialogue points this out as well.
* The phone icon is shown instead of the wand icon if an item needs attention but cannot be fixed automatically. It explains which options you have, including contacting the [SEMCO support](https://support.semcosoft.com).
* The cog icon leads you to the Moodle page where you can fix the item's most important finding yourself. It is only shown if the item has a finding which can be fixed manually at all, as some findings can only be fixed automatically or not at all.
* The bell icon mutes the item. A muted item keeps being checked, but it is shown with the status 'Muted', it is sorted to the bottom of its category, it does not name its findings and does not offer the wand, the phone and the cog icon anymore and it does not raise any alert anymore - neither on the plugin settings page nor on the Moodle System status page. This is meant for the recommendations which you have decided against on purpose, but every item can be muted. A muted item can be unmuted with the very same icon at any time.

CLI tools
---------

This plugin provides the following CLI tools:

### cli/recreate_webservice_token.php

This script can be used to recreate and to harden the web service token for the SEMCO web service user without going through Moodle’s token management process in the GUI.

Using this script is recommended in the following cases:

* If you need to change or renew the web service token. This may be particularly necessary if you have cloned your Moodle instance and want to use a different SEMCO webservice token in the clone.
* If you want to harden the web service token. The token is initially created during plugin installation without restrictions. And the CLI script allows you to set IP or date restrictions on the token without hassle.

The script refuses to work if the 'SEMCO webservice' user has more than one webservice token for the SEMCO external service, as it cannot tell which token SEMCO uses. In this case, please delete all but one token on /admin/webservice/tokens.php first and run the script again.


Checks API
----------

This plugin also introduces these additional checks to the System status page:

### \enrol_semco\check\healthcheck

This check mirrors the most severe status which the plugin's health check (see above) reports: If at least one aspect has the status Error, i.e. if the SEMCO integration does not work anymore, the check reports an error. If the most severe aspect has the status Warning, the check reports a warning. If there are deviations but all of them are notices, the check reports an info result, as a notice neither breaks nor endangers the integration. Aspects with the status OK, N/A or Muted do not trigger it.

The check covers the items of all categories, including the 'Recompletion plugin' and 'Recommended Moodle settings' categories. If you have decided against such a recommendation - or against using local_recompletion at all - on purpose, please mute the particular item on the health check page. A muted item does not trigger the check anymore, so you will not end up with a permanently failing check on the System status page.
