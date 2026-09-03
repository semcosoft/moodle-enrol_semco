Upgrading this plugin
=====================

This is an internal documentation for plugin developers with some notes what has to be considered when updating this plugin to a new Moodle major version.

General
-------

* Generally, this is a quite simple plugin with just one purpose.
* It does not rely on any fluctuating library functions and should remain quite stable between Moodle major versions.
* Thus, the upgrading effort is low.


Upstream changes
----------------

* This plugin does not inherit or copy any code from upstream sources.
* However, the filter menu of the enrolment report rebuilds the look of the Moodle core report builder filter menu by hand. Its stylesheet carries the measures from theme/boost/scss/moodle/reportbuilder.scss, which cannot be reused as they are scoped to the report builder's own wrapper element. Please compare the two menus after a Moodle major upgrade, see the visual checks below.


Automated tests
---------------

* The plugin has a good coverage with PHPUnit tests which test all of the plugin's webservices.
* The plugin has a good coverage with Behat tests which test all of the plugin's user stories.


Manual tests
------------

* Manual tests are carried out by SEMCO staff.


Visual checks
-------------

* The filter menu of the enrolment report on /enrol/semco/enrolreport.php needs a look after each Moodle major upgrade. The plugin's stylesheet builds it on top of markup and styles which Moodle core does not guarantee to keep stable, namely:
  * the width which the theme gives to a Bootstrap dropdown menu,
  * the col-md-3 / col-md-9 grid which a moodleform element is rendered into,
  * the data-groupname attribute which addresses the form's action buttons, and
  * the CSS :has() selector which drops the separator line below the last filter.
  Any of these can change without the plugin noticing, as none of them makes a test fail. Please open the menu and check that the filters are stacked below their labels, that the list scrolls while the 'Apply' and 'Reset all' buttons stay in the menu footer, and that the menu sits next to the initials bar of the report table on a wide screen.
* Apart from that, there aren't any additional visual checks in the Moodle GUI needed to upgrade this plugin.
