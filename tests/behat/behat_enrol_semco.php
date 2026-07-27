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
 * Enrolment method "SEMCO" - Behat step definitions and page resolvers
 *
 * @package    enrol_semco
 * @category   test
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

/**
 * Enrolment method "SEMCO" - Behat step definitions and page resolvers class.
 *
 * @package    enrol_semco
 * @category   test
 * @copyright  2025 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_enrol_semco extends behat_base {
    /**
     * @var string|null The SEMCO webservice token which was remembered within the scenario.
     */
    protected ?string $rememberedwebservicetoken = null;

    /**
     * @var string|null The output of the SEMCO CLI script which was run within the scenario.
     */
    protected ?string $cliscriptoutput = null;

    /**
     * @var string|null The error message of the course completion reset which was tried within the scenario.
     *                  It stays null as long as no reset was tried and is an empty string if the reset was successful.
     */
    protected ?string $resetcompletionerror = null;

    /**
     * @var bool Whether the scenario has simulated the presence or absence of local_recompletion.
     */
    protected bool $localrecompletionsimulated = false;

    /**
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | None generic  |                                 |
     * | Report        | The SEMCO enrolment report page |
     * | Settings      | The SEMCO plugin settings page  |
     *
     * @param string $page name of the page, with the component name removed e.g. 'Report'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'report':
                return new moodle_url('/enrol/semco/enrolreport.php');
            case 'settings':
                return new moodle_url('/admin/settings.php', ['section' => 'enrolsettingssemco']);
            default:
                throw new Exception('Unrecognised enrol_semco page "' . $page . '."');
        }
    }

    /**
     * Check that the SEMCO enrolment report refuses the access of the currently logged in user.
     *
     * The report page is guarded with require_capability(), so a user without the 'enrol/semco:viewreport' capability does
     * not only miss the links which lead to the report, the page itself refuses to show anything and Moodle renders its
     * fatal error page instead.
     *
     * This cannot be covered with the standard navigation and assertion steps: Behat inspects the page after every single
     * step and fails the scenario as soon as it finds Moodle's fatal error box, no matter if the error was expected or not.
     * This step therefore drives the browser session directly, remembers what the report page has shown and leaves the
     * error page again before it evaluates the outcome.
     *
     * @Then the SEMCO enrolment report should refuse the access
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function the_semco_enrolment_report_should_refuse_the_access(): void {
        $session = $this->getSession();

        // Visit the report page and remember what it has shown.
        $session->visit($this->locate_path($this->resolve_page_url('report')->out_as_local_url(false)));
        $reportpagetext = $session->getPage()->getText();

        // Leave the error page again before Behat's own exception check gets to see it.
        $session->visit($this->locate_path('/'));

        // The report must have refused the access with the 'no permissions' error which names the report capability.
        // The capability is part of the assertion on purpose: Without it, the step would also pass if the page failed
        // for any other reason.
        $expectederror = get_string('nopermissions', 'error', get_capability_string('enrol/semco:viewreport'));
        if (strpos($reportpagetext, $expectederror) === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The SEMCO enrolment report did not refuse the access with the message "' . $expectederror . '".',
                $session
            );
        }
    }

    /**
     * Purge the configuration cache within the Behat process.
     *
     * The plugin settings are saved within the webserver process while the SEMCO webservices are run from within the Behat
     * process (through the plugin's data generator and through the steps below). Whenever the Behat process has read the
     * plugin's configuration before the setting was changed - which already happens when a course is created, as course
     * creation asks each enabled enrolment plugin for its configuration - it keeps this configuration in the statically
     * accelerated 'core/config' cache. Saving the setting in the webserver process only drops the entry from the shared cache
     * store, it cannot clear the copy which the Behat process holds in memory. This step drops that copy so that the
     * webservices really run with the settings which have just been saved on the settings page.
     *
     * @Given the SEMCO plugin configuration cache is purged
     */
    public function the_semco_plugin_configuration_cache_is_purged(): void {
        \core_cache\helper::purge_by_definition('core', 'config');
    }

    /**
     * Bring the SEMCO webservice role into the state which it has right after an initial installation of Moodle.
     *
     * During an initial installation of Moodle, the plugin is not able to assign the 'webservice/rest:use' capability to the
     * SEMCO webservice role as the webservice subsystem is installed after this plugin and the capability does not exist yet
     * at this point in time. Instead, the plugin queues an ad-hoc task which assigns the capability as soon as the Moodle cron
     * is running for the first time.
     *
     * This state cannot be relied on in a Behat scenario: It is only present if the Behat site was set up with an initial
     * installation of Moodle which already contained this plugin. If the plugin was added to an existing Moodle installation
     * instead, the capability was assigned directly by the installation routine and no ad-hoc task exists at all. This step
     * therefore establishes the state explicitly so that the ad-hoc task can be tested in any Behat site.
     *
     * @Given the SEMCO webservice role is in the state of an initial Moodle installation
     */
    public function the_semco_webservice_role_is_in_the_state_of_an_initial_moodle_installation(): void {
        global $CFG, $DB;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Get the system context.
        $systemcontext = \context_system::instance();

        // Get the SEMCO webservice role which was created during the plugin installation.
        $semcoroleid = $DB->get_field('role', 'id', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME], MUST_EXIST);

        // Remove the capability which the plugin was not able to assign during an initial installation.
        unassign_capability('webservice/rest:use', $semcoroleid, $systemcontext->id);

        // And queue the ad-hoc task, just as the plugin's installation routine does during an initial installation.
        // We let the task manager check for an existing task as the Behat site might have been set up with an initial
        // installation and might have the task in the queue already.
        $adhoctask = new \enrol_semco\task\set_webservice_capability();
        \core\task\manager::queue_adhoc_task($adhoctask, true);
    }

    /**
     * Build the XPath which selects all nodes which belong to a particular section of an admin settings page.
     *
     * The SEMCO plugin settings page is a standard admin settings page where each section is introduced by an
     * <h3 class="main"> heading and the section's settings are rendered as following sibling nodes within the same fieldset
     * (until the next <h3 class="main"> heading appears). As the sections are not wrapped in a dedicated container element,
     * they cannot be addressed with a named selector (which Behat resolves to a single container node). Instead, this helper
     * returns the XPath which selects exactly the sibling nodes which sit _after_ the given section heading and _before_ the
     * next section heading (i.e. those nodes whose nearest preceding section heading is the given one).
     *
     * @param string $heading The visible text of the section heading.
     * @return string The XPath expression which selects the section's nodes.
     */
    protected function settings_section_nodes_xpath(string $heading): string {
        $main = "contains(concat(' ', normalize-space(@class), ' '), ' main ')";
        return $this->settings_section_heading_xpath($heading) . "/following-sibling::*" .
                "[normalize-space(preceding-sibling::h3[{$main}][1])=" . behat_context_helper::escape($heading) . "]";
    }

    /**
     * Build the XPath which selects the heading of a particular section of an admin settings page.
     *
     * @param string $heading The visible text of the section heading.
     * @return string The XPath expression which selects the section's heading.
     */
    protected function settings_section_heading_xpath(string $heading): string {
        $main = "contains(concat(' ', normalize-space(@class), ' '), ' main ')";
        return "//h3[{$main}][normalize-space(.)=" . behat_context_helper::escape($heading) . "]";
    }

    /**
     * Check that the given section exists on the settings page at all.
     *
     * The two steps below address a settings section by the markup which Moodle core renders for an admin settings heading
     * (see the core_admin/setting_heading template). Should that markup ever change, the section's nodes would not be found
     * anymore. The positive step would then fail with a misleading message which claims that the searched element is missing,
     * and - much worse - the negative step would silently pass for every element, as an element which cannot be found is
     * exactly what it expects. This helper turns both cases into an explicit failure which names the real cause.
     *
     * @param string $heading The visible text of the section heading.
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    protected function ensure_settings_section_exists(string $heading): void {
        // Look for the section heading. A reduced timeout is enough here as this check is only run after the search for the
        // element itself has already waited for the page.
        try {
            $this->find(
                'xpath',
                $this->settings_section_heading_xpath($heading),
                false,
                false,
                behat_base::get_reduced_timeout()
            );
        } catch (\Behat\Mink\Exception\ElementNotFoundException $e) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The "' . $heading . '" settings section was not found on this page at all. Either the section is really ' .
                        'missing or Moodle core does not render an admin settings heading as an <h3 class="main"> anymore.',
                $this->getSession()
            );
        }
    }

    /**
     * Check that the given element is shown within the given section of the settings page.
     *
     * This step accepts any Moodle selector type (like the core "should exist in the" steps do), so a section can be
     * checked for plain text with the "text" selector, for a link with the "link" selector and so on.
     *
     * The three capture groups of the step pattern are the element locator, the selector type and the section heading.
     *
     * @Then /^I should see (?:an? )?"((?:[^"]|\\")*)" "([^"]*)" in the "([^"]*)" settings section$/
     * @param string $element The locator of the given selector type.
     * @param string $selectortype The selector type.
     * @param string $heading The visible text of the section heading.
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function i_should_see_element_in_the_settings_section(
        string $element,
        string $selectortype,
        string $heading
    ): void {
        try {
            $this->find('xpath', $this->settings_section_element_xpath($element, $selectortype, $heading));
        } catch (\Behat\Mink\Exception\ElementNotFoundException $e) {
            // Make sure that the section itself is there before we blame the element for being missing.
            $this->ensure_settings_section_exists($heading);

            throw new \Behat\Mink\Exception\ExpectationException(
                'A "' . $element . '" "' . $selectortype . '" was not found in the "' . $heading . '" settings section.',
                $this->getSession()
            );
        }
    }

    /**
     * Check that the given element is not shown within the given section of the settings page.
     *
     * This is the counterpart of the step above and accepts any Moodle selector type as well.
     *
     * @Then /^I should not see (?:an? )?"((?:[^"]|\\")*)" "([^"]*)" in the "([^"]*)" settings section$/
     * @param string $element The locator of the given selector type.
     * @param string $selectortype The selector type.
     * @param string $heading The visible text of the section heading.
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function i_should_not_see_element_in_the_settings_section(
        string $element,
        string $selectortype,
        string $heading
    ): void {
        // Look for the element. A reduced timeout is used as this step expects the element to be missing, i.e. the search is
        // going to time out in the good case. In a session without JavaScript the timeout does not have any effect anyway
        // (behat_base::spin() does not wait at all there), but it saves the full waiting time as soon as this step is used in
        // an @javascript scenario. This is the same approach which the core "should not exist" steps take.
        try {
            $this->find(
                'xpath',
                $this->settings_section_element_xpath($element, $selectortype, $heading),
                false,
                false,
                behat_base::get_reduced_timeout()
            );
        } catch (\Behat\Mink\Exception\ElementNotFoundException $e) {
            // The element was not found, which is exactly what this step expects. However, an element is never found in a
            // section which does not exist either, so we have to make sure that we are really looking at a present section.
            $this->ensure_settings_section_exists($heading);

            return;
        }

        throw new \Behat\Mink\Exception\ExpectationException(
            'A "' . $element . '" "' . $selectortype . '" was found in the "' . $heading .
                    '" settings section, but it was not expected to be there.',
            $this->getSession()
        );
    }

    /**
     * Build the XPath which finds the given element within the given section of the settings page.
     *
     * @param string $element The locator of the given selector type.
     * @param string $selectortype The selector type.
     * @param string $heading The visible text of the section heading.
     * @return string The XPath.
     */
    protected function settings_section_element_xpath(string $element, string $selectortype, string $heading): string {
        // Let Behat build the XPath which it would use to find the element anywhere on the page.
        [$selector, $locator] = $this->transform_selector($selectortype, $element);
        $elementxpath = $this->getSession()->getSelectorsHandler()->selectorToXpath($selector, $locator);

        // Restrict this XPath to the section's nodes. We use Mink's XPath manipulator for this as the element's XPath
        // can consist of multiple union parts (for example, the "button" selector matches <input>, <button> and
        // [role=button] elements) which all have to be restricted individually.
        $manipulator = new \Behat\Mink\Selector\Xpath\Manipulator();
        return $manipulator->prepend($elementxpath, '(' . $this->settings_section_nodes_xpath($heading) . ')');
    }

    /**
     * Check that the SEMCO Moodle base URL (i.e. $CFG->wwwroot) is shown within the given section of the settings page.
     *
     * This step compares the shown value against the actual Moodle configuration.
     *
     * @Then I should see the SEMCO Moodle base URL in the :heading settings section
     * @param string $heading The visible text of the section heading.
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function i_should_see_the_semco_moodle_base_url_in_the_settings_section(string $heading): void {
        global $CFG;

        // Compare against the value which the plugin is expected to show.
        $this->i_should_see_element_in_the_settings_section($CFG->wwwroot, 'text', $heading);
    }

    /**
     * Check that the SEMCO webservice token from the database is shown within the given section of the settings page.
     *
     * This step compares the shown value against the token which is actually stored in the database.
     *
     * @Then I should see the SEMCO webservice token in the :heading settings section
     * @param string $heading The visible text of the section heading.
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function i_should_see_the_semco_webservice_token_in_the_settings_section(string $heading): void {
        global $CFG;

        // Require plugin library to get the plugin's helper functions.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Get the webservice token from the database.
        $webservicetoken = enrol_semco_get_webservice_token();

        // Throw an exception if there is no token in the database (which would indicate a broken plugin installation).
        if (empty($webservicetoken)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'There is no SEMCO webservice token in the database to check against.',
                $this->getSession()
            );
        }

        // Compare the shown value against the token from the database.
        $this->i_should_see_element_in_the_settings_section($webservicetoken, 'text', $heading);
    }

    /**
     * Remember the current SEMCO webservice token so that it can be compared to a later state.
     *
     * @Given I remember the SEMCO webservice token
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function i_remember_the_semco_webservice_token(): void {
        global $CFG;

        // Require plugin library to get the plugin's helper functions.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Get the webservice token from the database.
        $webservicetoken = enrol_semco_get_webservice_token();

        // Throw an exception if there is no token in the database (which would indicate a broken plugin installation).
        if (empty($webservicetoken)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'There is no SEMCO webservice token in the database which could be remembered.',
                $this->getSession()
            );
        }

        // Remember the token.
        $this->rememberedwebservicetoken = $webservicetoken;
    }

    /**
     * Check that the SEMCO webservice token in the database differs from the remembered one.
     *
     * @Then the SEMCO webservice token has changed
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function the_semco_webservice_token_has_changed(): void {
        global $CFG;

        // Require plugin library to get the plugin's helper functions.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Throw an exception if no token was remembered before (which would indicate a broken scenario).
        if ($this->rememberedwebservicetoken === null) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'No SEMCO webservice token was remembered before, please add the corresponding step to the scenario.',
                $this->getSession()
            );
        }

        // Get the webservice token from the database.
        $webservicetoken = enrol_semco_get_webservice_token();

        // Throw an exception if there is no token in the database anymore.
        if (empty($webservicetoken)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'There is no SEMCO webservice token in the database anymore.',
                $this->getSession()
            );
        }

        // Compare the token against the remembered one.
        if ($webservicetoken === $this->rememberedwebservicetoken) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The SEMCO webservice token is still the same as before.',
                $this->getSession()
            );
        }
    }

    /**
     * Run the plugin's CLI script which recreates the SEMCO webservice token.
     *
     * The CLI script is run as a real subprocess as this is the only way to cover the script as such (its logic is not
     * wrapped in a function which could be called directly). The BEHAT_CLI environment variable tells the Moodle bootstrap
     * that this CLI script has to run on the Behat test site instead of the production site. As the subprocess goes through
     * the same bootstrap, it also reports the database tables which it has modified to the Behat process, so that the
     * database reset after the scenario works as usual.
     * The --yes option is always passed as the script would wait for an interactive confirmation otherwise.
     *
     * @When /^I run the SEMCO webservice token CLI script(?: with the options "(?P<options_string>[^"]*)")?$/
     * @param string $options Additional options to pass to the CLI script.
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function i_run_the_semco_webservice_token_cli_script(string $options = ''): void {
        global $CFG;

        // Build the command.
        $command = 'BEHAT_CLI=1 ' . escapeshellarg(PHP_BINARY) . ' ' .
                escapeshellarg($CFG->dirroot . '/enrol/semco/cli/recreate_webservice_token.php') . ' --yes ' .
                $options . ' 2>&1';

        // Run the CLI script.
        $output = [];
        $exitcode = 0;
        exec($command, $output, $exitcode);

        // Remember the output for later assertions.
        $this->cliscriptoutput = implode("\n", $output);

        // Throw an exception if the CLI script did not finish successfully.
        if ($exitcode !== 0) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The SEMCO webservice token CLI script failed with exit code ' . $exitcode . ".\n\n" .
                        $this->cliscriptoutput,
                $this->getSession()
            );
        }
    }

    /**
     * Check that the output of the CLI script which was run before contains the given text.
     *
     * @Then the SEMCO CLI script output should contain :text
     * @param string $text The text which is expected in the CLI script output.
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function the_semco_cli_script_output_should_contain(string $text): void {
        // Throw an exception if no CLI script was run before (which would indicate a broken scenario).
        if ($this->cliscriptoutput === null) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'No SEMCO CLI script was run before, please add the corresponding step to the scenario.',
                $this->getSession()
            );
        }

        // Compare the output against the expected text.
        if (strpos($this->cliscriptoutput, $text) === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The SEMCO CLI script output does not contain "' . $text . '".' . "\n\n" . $this->cliscriptoutput,
                $this->getSession()
            );
        }
    }

    /**
     * Get the plugin's test data generator.
     *
     * The steps below share two helpers with the plugin's PHPUnit tests, namely run_as_semco_webservice_user() which runs a
     * webservice call as the SEMCO webservice user (which is needed to pass the webservice's capability checks) and
     * build_core_user_webservice_record() which maps user data to the structure of the core user webservices. Both live in
     * the plugin's data generator, which is available in a Behat run as well, so that the steps and the PHPUnit tests
     * really go through the same code.
     *
     * @return \enrol_semco_generator The plugin's test data generator.
     */
    protected function get_semco_data_generator(): \enrol_semco_generator {
        return \behat_util::get_data_generator()->get_plugin_generator('enrol_semco');
    }

    /**
     * Get the Moodle user enrolment ID (i.e. the user_enrolments.id) of the SEMCO enrolment with the given booking ID.
     *
     * As this plugin creates an individual enrolment instance per SEMCO booking (with the booking ID stored in the
     * instance's customchar1 field), the enrolment can be addressed unambiguously by its booking ID.
     *
     * @param string $bookingid The SEMCO booking ID.
     * @return int The Moodle user enrolment ID.
     */
    protected function get_semco_user_enrolment_id(string $bookingid): int {
        global $DB;

        // Get the enrolment instance which carries the given booking ID.
        $instance = $DB->get_record('enrol', ['enrol' => 'semco', 'customchar1' => $bookingid], '*', MUST_EXIST);

        // Get the user enrolment which belongs to this instance (there is exactly one per SEMCO booking).
        $userenrolment = $DB->get_record('user_enrolments', ['enrolid' => $instance->id], '*', MUST_EXIST);

        return (int) $userenrolment->id;
    }

    /**
     * Unenrol one or more SEMCO enrolments (which is what SEMCO does through the unenrolment webservice).
     *
     * The data table identifies each enrolment by its booking ID in the "semcobookingid" column.
     *
     * @Given /^the following SEMCO enrolments are unenrolled:$/
     * @param \Behat\Gherkin\Node\TableNode $data The enrolments to unenrol.
     */
    public function the_following_semco_enrolments_are_unenrolled(\Behat\Gherkin\Node\TableNode $data): void {
        foreach ($data->getHash() as $row) {
            // The booking ID is required to address the enrolment.
            if (empty($row['semcobookingid'])) {
                throw new \coding_exception('The "semcobookingid" column is required when unenrolling a SEMCO enrolment.');
            }
            $enrolid = $this->get_semco_user_enrolment_id($row['semcobookingid']);

            $this->get_semco_data_generator()->run_as_semco_webservice_user(function () use ($enrolid) {
                return \enrol_semco\external::unenrol_user($enrolid);
            });
        }
    }

    /**
     * Edit one or more SEMCO enrolments (which is what SEMCO does through the edit webservice).
     *
     * The data table identifies each enrolment by its booking ID in the "semcobookingid" column and takes any of the
     * optional "timestart", "timeend" and "suspend" columns. An empty cell (or an omitted column) means that the respective
     * field is left unchanged, while an explicit "0" in a date column removes the respective date (as the "##...##" date
     * syntax may be used, the date columns usually hold a rendered timestamp).
     *
     * @Given /^the following SEMCO enrolments are edited:$/
     * @param \Behat\Gherkin\Node\TableNode $data The enrolment changes.
     */
    public function the_following_semco_enrolments_are_edited(\Behat\Gherkin\Node\TableNode $data): void {
        foreach ($data->getHash() as $row) {
            // The booking ID is required to address the enrolment.
            if (empty($row['semcobookingid'])) {
                throw new \coding_exception('The "semcobookingid" column is required when editing a SEMCO enrolment.');
            }
            $enrolid = $this->get_semco_user_enrolment_id($row['semcobookingid']);

            // Pick the optional fields. An empty cell (or an omitted column) is passed as null, which tells the webservice
            // to leave the respective field unchanged.
            $timestart = (array_key_exists('timestart', $row) && $row['timestart'] !== '') ? (int) $row['timestart'] : null;
            $timeend = (array_key_exists('timeend', $row) && $row['timeend'] !== '') ? (int) $row['timeend'] : null;
            $suspend = (array_key_exists('suspend', $row) && $row['suspend'] !== '') ? (bool) (int) $row['suspend'] : null;

            $generator = $this->get_semco_data_generator();
            $generator->run_as_semco_webservice_user(function () use ($enrolid, $timestart, $timeend, $suspend) {
                return \enrol_semco\external::edit_enrolment($enrolid, null, $timestart, $timeend, $suspend);
            });
        }
    }

    /**
     * Update one or more users as SEMCO does, i.e. through the core user webservice (core_user_update_users).
     *
     * The data table identifies each user by its (unchanged) username in the "username" column and takes any standard user
     * column or SEMCO profile field column which should be updated. The columns are turned into the structure which the
     * core user webservice expects by the plugin's data generator, i.e. by the very same code which builds the records for
     * the user creation.
     *
     * @Given /^the following users are updated by SEMCO:$/
     * @param \Behat\Gherkin\Node\TableNode $data The user changes.
     */
    public function the_following_users_are_updated_by_semco(\Behat\Gherkin\Node\TableNode $data): void {
        global $CFG, $DB;

        // Require the core user webservice library.
        require_once($CFG->dirroot . '/user/externallib.php');

        // Build the webservice user records, adding the user ID which the update webservice requires.
        $users = [];
        foreach ($data->getHash() as $row) {
            if (empty($row['username'])) {
                throw new \coding_exception('The "username" column is required when updating a user.');
            }
            $record = $this->get_semco_data_generator()->build_core_user_webservice_record($row);
            $record['id'] = (int) $DB->get_field('user', 'id', ['username' => $row['username'], 'deleted' => 0], MUST_EXIST);
            $users[] = $record;
        }

        // Update the users through the core user webservice, running as the SEMCO webservice user.
        $this->get_semco_data_generator()->run_as_semco_webservice_user(function () use ($users) {
            return \core_user_external::update_users($users);
        });
    }

    /**
     * Delete one or more users as SEMCO does, i.e. through the core user webservice (core_user_delete_users).
     *
     * The data table identifies each user by its username in the "username" column.
     *
     * @Given /^the following users are deleted by SEMCO:$/
     * @param \Behat\Gherkin\Node\TableNode $data The users to delete.
     */
    public function the_following_users_are_deleted_by_semco(\Behat\Gherkin\Node\TableNode $data): void {
        global $CFG, $DB;

        // Require the core user webservice library.
        require_once($CFG->dirroot . '/user/externallib.php');

        // Collect the user IDs to delete.
        $userids = [];
        foreach ($data->getHash() as $row) {
            if (empty($row['username'])) {
                throw new \coding_exception('The "username" column is required when deleting a user.');
            }
            $userids[] = (int) $DB->get_field('user', 'id', ['username' => $row['username'], 'deleted' => 0], MUST_EXIST);
        }

        // Delete the users through the core user webservice, running as the SEMCO webservice user.
        $this->get_semco_data_generator()->run_as_semco_webservice_user(function () use ($userids) {
            return \core_user_external::delete_users($userids);
        });
    }

    /**
     * Simulate that the companion plugin local_recompletion is installed or not installed.
     *
     * This plugin has only a soft dependency to local_recompletion and behaves differently depending on the fact if the
     * companion plugin is there or not. As a Behat run cannot install or uninstall a plugin on the fly, this step overrides
     * what enrol_semco_check_local_recompletion() reports. That way, a scenario can cover both kinds of installations,
     * regardless of the fact if local_recompletion is really present in the Behat installation or not.
     *
     * Please note that this step only controls the plugin's detection of the companion plugin. It is meant for scenarios
     * which verify how this plugin presents itself, it does not make the course completion reset work in an installation
     * where local_recompletion is really missing.
     *
     * @Given /^local_recompletion is simulated to be "(?P<state_string>installed|not installed)"$/
     * @param string $state Either 'installed' or 'not installed'.
     */
    public function local_recompletion_is_simulated_to_be(string $state): void {
        // Set the switches which the plugin evaluates. Both of them are always set to keep the two switches consistent,
        // no matter which state a previous step within the same scenario has established.
        switch ($state) {
            case 'installed':
                set_config('localrecompletionnotinstalled', 0);
                set_config('localrecompletionforceinstalled', 1);
                break;
            case 'not installed':
                set_config('localrecompletionnotinstalled', 1);
                set_config('localrecompletionforceinstalled', 0);
                break;
            default:
                throw new \coding_exception('The state "' . $state . '" is unknown, ' .
                        'it has to be either "installed" or "not installed".');
        }

        // Remember that this scenario has to be cleaned up afterwards.
        $this->localrecompletionsimulated = true;
    }

    /**
     * Remove the local_recompletion simulation after a scenario which has used it.
     *
     * The simulation switches are stored in the site configuration, i.e. they outlive the scenario which has set them.
     * Behat resets the database between the scenarios, but that reset writes to the database directly and does not
     * invalidate the configuration cache, so the switches would keep their effect for the rest of the Behat run and would
     * make all following scenarios (and even the following feature files) believe in the simulated state. Removing the
     * switches with unset_config() drops them from the database and from the cache and thus really ends the simulation.
     *
     * @AfterScenario @enrol_semco
     */
    public function remove_the_local_recompletion_simulation(): void {
        // There is nothing to clean up if the scenario has not simulated anything.
        if ($this->localrecompletionsimulated != true) {
            return;
        }

        // Remove the simulation switches.
        unset_config('localrecompletionnotinstalled');
        unset_config('localrecompletionforceinstalled');

        $this->localrecompletionsimulated = false;
    }

    /**
     * Set the course recompletion settings of one or more courses.
     *
     * In real life, these settings are configured by the teacher on the course recompletion settings page of the companion
     * plugin local_recompletion. That page just stores each setting as a name / value pair in the local_recompletion_config
     * table, which is exactly what this step does as well.
     *
     * The data table identifies each course by its shortname in the "course" column. Any other column is stored as a
     * recompletion setting of that course. The values of the "recompletiontype" column are given as the recompletion type
     * identifiers which local_recompletion uses, i.e. "disabled", "period", "ondemand" or "schedule".
     *
     * @Given /^the following SEMCO course recompletion settings are set:$/
     * @param \Behat\Gherkin\Node\TableNode $data The recompletion settings.
     */
    public function the_following_semco_course_recompletion_settings_are_set(\Behat\Gherkin\Node\TableNode $data): void {
        global $DB;

        foreach ($data->getHash() as $row) {
            // The course is required to address the settings.
            if (empty($row['course'])) {
                throw new \coding_exception('The "course" column is required when setting course recompletion settings.');
            }
            $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $row['course']], MUST_EXIST);

            // Drop the course column and add the defaults for those settings which local_recompletion reads without
            // checking whether they exist. Its own settings page always writes the complete set of settings, so a course
            // which was configured through the user interface never misses them. As a data table usually only lists the
            // settings which matter for the scenario, we have to add them here to mimic a realistically configured course.
            unset($row['course']);
            $row += ['archivecompletiondata' => 0, 'deletegradedata' => 0];

            // Store each setting of this course.
            foreach ($row as $name => $value) {
                // Translate the recompletion type identifier into the value which local_recompletion stores.
                if ($name == 'recompletiontype') {
                    $value = $this->get_semco_data_generator()->resolve_recompletiontype($value);
                }

                $DB->insert_record('local_recompletion_config', ['course' => $courseid, 'name' => $name, 'value' => $value]);
            }
        }
    }

    /**
     * Reset the course completion of one or more SEMCO enrolments (which is what SEMCO does through the reset webservice).
     *
     * The data table identifies each enrolment by its booking ID in the "semcobookingid" column. The step fails if the
     * webservice rejects the reset, so it is meant for scenarios which expect the reset to work.
     *
     * @Given /^the following SEMCO course completions are reset:$/
     * @param \Behat\Gherkin\Node\TableNode $data The enrolments to reset.
     */
    public function the_following_semco_course_completions_are_reset(\Behat\Gherkin\Node\TableNode $data): void {
        foreach ($data->getHash() as $row) {
            // The booking ID is required to address the enrolment.
            if (empty($row['semcobookingid'])) {
                throw new \coding_exception('The "semcobookingid" column is required when resetting a course completion.');
            }
            $enrolid = $this->get_semco_user_enrolment_id($row['semcobookingid']);

            $this->get_semco_data_generator()->run_as_semco_webservice_user(function () use ($enrolid) {
                return \enrol_semco\external::reset_course_completion($enrolid);
            });
        }
    }

    /**
     * Try to reset the course completion of a SEMCO enrolment and remember the outcome.
     *
     * Compared to the step above, this step swallows the exception which the webservice throws if it refuses to reset the
     * course completion. This allows a scenario to verify the error message which SEMCO would receive with the step below.
     *
     * @When /^SEMCO tries to reset the course completion of the SEMCO enrolment "(?P<bookingid_string>(?:[^"]|\\")*)"$/
     * @param string $bookingid The SEMCO booking ID of the enrolment.
     */
    public function semco_tries_to_reset_the_course_completion_of_the_semco_enrolment(string $bookingid): void {
        global $DB;

        $enrolid = $this->get_semco_user_enrolment_id($bookingid);

        // Run the reset and remember the error message if it was rejected.
        try {
            $this->get_semco_data_generator()->run_as_semco_webservice_user(function () use ($enrolid) {
                return \enrol_semco\external::reset_course_completion($enrolid);
            });
            $this->resetcompletionerror = '';
        } catch (\Exception $e) {
            // Roll back the database transaction which the webservice function has opened before it threw the exception.
            // For a real webservice request, Moodle's exception handler does this on its own, but as we catch the exception
            // here, the handler is never reached. Without this rollback, the transaction would stay open for the rest of
            // the scenario and would make all following requests run into the database locks which it holds.
            $DB->force_transaction_rollback();

            $this->resetcompletionerror = $e->getMessage();
        }
    }

    /**
     * Check the error message of a course completion reset which was rejected by the webservice.
     *
     * The expected text is compared as a substring as the error messages of the plugin contain a link to the course
     * recompletion settings page whose URL depends on the course ID.
     *
     * @Then /^the SEMCO course completion reset should have failed with a message containing "(?P<text_string>(?:[^"]|\\")*)"$/
     * @param string $text The text which the error message is expected to contain.
     */
    public function the_semco_course_completion_reset_should_have_failed_with_a_message_containing(string $text): void {
        // Fail if no reset was tried before.
        if ($this->resetcompletionerror === null) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'No SEMCO course completion reset was tried before, please add the corresponding step to the scenario.',
                $this->getSession()
            );
        }

        // Fail if the reset was not rejected at all.
        if ($this->resetcompletionerror === '') {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The SEMCO course completion reset was not rejected, but it was expected to fail.',
                $this->getSession()
            );
        }

        // Compare the error message against the expected text.
        if (strpos($this->resetcompletionerror, $text) === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The error message of the SEMCO course completion reset does not contain "' . $text . '".' . "\n\n" .
                        $this->resetcompletionerror,
                $this->getSession()
            );
        }
    }
}
