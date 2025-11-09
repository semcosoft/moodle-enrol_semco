moodle-enrol_semco
==================

[![Moodle Plugin CI](https://github.com/semcosoft/moodle-enrol_semco/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=MOODLE_501_STABLE)](https://github.com/semcosoft/moodle-enrol_semco/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3AMOODLE_501_STABLE)

Moodle enrolment plugin which allows the SEMCO seminar management system to enrol and manage users in Moodle courses


Requirements
------------

This plugin requires Moodle 5.1+


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
To realize this course completion reset and to avoid to re-invent the wheel, this plugin has a soft dependency to local_recompletion (see https://moodle.org/plugins/local_recompletion) by Dan Marsden.

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

There, you find four sections:

### 1. Connection information

In this section, you will find the Moodle base URL and the webservice token which was automatically created during the plugin installation. Please use this data to configure the Moodle connection in SEMCO.

### 2. Enrolment report

In this section, you will find the link to a site report where you can see all enrolments which have been made by SEMCO.
For managers, this report is also linked in the 'Reports' section within the site administration.

### 3. Enrolment process

In this section, you control with which role SEMCO enrols users into courses. The configured role is mandatory for all users who are enrolled from SEMCO and cannot be overridden with the SEMCO enrolment webservice endpoint.

### 4. Course completion

In this section, you can verify that the local_recompletion plugin is installed and SEMCO would be able to reset the completion of a user if he is enrolled into a particular course once more.


Connecting to SEMCO
-------------------

This documentation explains how to install this plugin in Moodle until it is ready to be connected by SEMCO.

The other side of this connection is documented by SEMCO on https://www.semcosoft.com/de/helpreader/moodle-online-shop-mit-semco-moodle-integration (german).


Capabilities
------------

This plugin also introduces these additional capabilities:

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

### enrol/semco:resetcoursecompletion

This capability controls the ability to reset the course completion for a given SEMCO user enrolment.

### Please note

By default, these capabilities are not allowed to any role archetype as they should just be used by a webservice.
They will be automatically assigned to the 'SEMCO webservice' role during the plugin installation.

### enrol/semco:viewreport

This capability controls the ability to view the enrolment report of all SEMCO user enrolments.
In contrast to the previous capabilities, this capability is allowed for the manager role by default.


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


Important global Moodle settings
--------------------------------

During the design of the SEMCO-Moodle integration, some assumptions about the usage scenarios were made which have consequences on global Moodle settings.
Your SEMCO-Moodle integration does not necessarily need to fully match these usage scenarios, but you should think about them before the go-live of your integration:

* The email addresses of Moodle users should be unique (i.e. the Moodle setting allowaccountssameemail is set to No). This is because you will want to avoid that SEMCO creates a Moodle user if - for any reason - another Moodle user already exists for the same email address. However, SEMCO is able to deal with multiple tenants where multiple user accounts have the same email address. If your usage scenario requires it and as soon as your SEMCO consultant recommends it, you can set the allowaccountssameemail setting to Yes.
* As mentioned above already, SEMCO acts as leading system for the users which it creates in Moodle and will overwrite the first name, last name or email address fields if necessary. However, Moodle users can still update these profile fields themselves in their Moodle profile by default and might be confused if a change which they made is "magically" reverted sometime later. To avoid this, you can lock these three profile fields on /admin/settings.php?section=authsettingmanual. However, please gauge the pros and cons yourself as locking these fields will affect all existing users with manual authentication and not only SEMCO users.
* A course which is sold via SEMCO - or ideally all courses in the Moodle instance - should not have self-enrolment enabled. Alternatively, you should configure the 'Authenticated user' role in Moodle in a way that users cannot enrol into courses themselves. This is because you will not want that users who got enrolled into course A by SEMCO are able to enrol into course B themselves (without paying for the course via SEMCO). And you might not want that users who came from SEMCO snoop around in other Moodle courses which are not connected to SEMCO.
* The role with which SEMCO enrols users into courses (and which can be set in the plugin configuration) should not have the moodle/course:viewparticipants capabilities set. This is because you should assume that these course participants are not all members of the same class / cohort and do not know each other. If they would see each other participants in the course, you might even have a data protection leak.
* For the same reason, you should also disable the Moodle messaging system to avoid that users get in touch with each other on the Moodle instance.
* The system message 'Course completed' should be disabled (on /admin/message.php) as a default. This is because, from SEMCO 7.9 on, SEMCO is able to send out information mails itself as soon as a course has been completed.


Useful settings for local_recompletion
--------------------------------------

If you decide to use the companion plugin local_recompletion to allow SEMCO to reset course completions during subsequent user enrolments, please verify these settings of local_recompletion before the go-live of your integration:

* SEMCO can only reset a user's course completion if the "Recompletion type" setting in the particular course is set to "On demand". It's up to the individual teachers to go to the course recompletion settings in their courses and save the settings before SEMCO can reset a user's course completion in a course.
* By design, SEMCO will reset a user's course completion even on the user's first SEMCO enrolment into the course. This might seem unnecessary, but as it is not impossible that the user might have been manually enrolled before into that course (and might have completed it then), SEMCO resets the course completion just to be sure that the course is clean before each and every SEMCO enrolment. Against this background, the standard behaviour of local_recompletion to send out a notification message to the user when the course is reset will confuse the user. To avoid such confusion, you should disable the "Send recompletion message" setting on /admin/settings.php?section=local_recompletion.
* By default, local_recompletion is configured in a way that it does not reset any activity in a course unless the teacher activates the activity type's reset in his particular course. To ease the teacher's life and to avoid that SEMCO triggers a course completion reset but nothing is deleted from the course in the end, you should enable all items in the "Plugins settings" section on /admin/settings.php?section=local_recompletion which are relevant for the courses in your Moodle instance.
* By default, local_recompletion grants the local/recompletion:resetmycompletion capability to the participant role. That way, course participants could reset a course's completion on their own. Within a SEMCO-Moodle setup, this should be avoided. Please retract the local/recompletion:resetmycompletion capability from at least the participants role after installing the plugin.


Backup & Restore
----------------

This enrolment plugin does not support backup & restore of courses.
This is done by purpose as each particular course enrolment is mapped to a particular SEMCO booking ID which is a unique 1:1 mapping. If we would backup & restore course enrolments to duplicated / restored / imported courses, this constraint could not be guaranteed.


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is published and regularly updated in the Moodle plugins repository:
http://moodle.org/plugins/view/enrol_semco

The latest stable version can be found on Github:
https://github.com/semcosoft/moodle-enrol_semco


Bug and problem reports
-----------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/semcosoft/moodle-enrol_semco/issues


Community feature proposals
---------------------------

The functionality of this plugin is primarily implemented for the needs of our clients and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/semcosoft/moodle-enrol_semco/issues

Please create pull requests on Github:
https://github.com/semcosoft/moodle-enrol_semco/pulls


Moodle release support
----------------------

This plugin is maintained for all officially supported Moodle core versions, particularly the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to each supported release. New features and improvements are backported to the each supported release as well, if possible.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

If you are running a legacy version of Moodle, but want or need to run the latest version of this plugin, you can get the latest version of the plugin, remove the line starting with $plugin->requires from version.php and use this latest plugin version then on your legacy Moodle. However, please note that you will run this setup completely at your own risk. We can't support this approach in any way and there is an undeniable risk for erratic behavior.


Translating this plugin
-----------------------

This Moodle plugin is shipped with an english language pack only. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by what they will become part of Moodle's official language pack.

As the plugin creator, we manage the translation into german for our own local needs on AMOS. Please contribute your translation into all other languages in AMOS where they will be reviewed by the official language pack maintainers for Moodle.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

SEMCO Software Engineering GmbH


Copyright
---------

SEMCO Software Engineering GmbH
