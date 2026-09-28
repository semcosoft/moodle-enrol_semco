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

namespace enrol_semco;

use enrol_semco\healthcheck\healthcheck;
use enrol_semco\healthcheck\manager;

/**
 * Enrolment method "SEMCO" - PHPUnit tests for the plugin's health check.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The healthcheck_test class.
 *
 * The plugin's installation script db/install.php also runs when the PHPUnit test database is initialized. Therefore,
 * every health check item must report an intact installation at the beginning of each test. Each test then breaks
 * exactly one aspect of the installation, verifies that the corresponding health check item notices the breakage and,
 * if the item supports an automatic fix, verifies that the automatic fix repairs the aspect again.
 *
 * @covers \enrol_semco\healthcheck\healthcheck
 * @covers \enrol_semco\healthcheck\manager
 * @covers \enrol_semco\healthcheck\autofix
 * @covers \enrol_semco\healthcheck\check\allowaccountssameemail
 * @covers \enrol_semco\healthcheck\check\authmethod
 * @covers \enrol_semco\healthcheck\check\capabilitiesexclusive
 * @covers \enrol_semco\healthcheck\check\capabilitiesregistered
 * @covers \enrol_semco\healthcheck\check\coursecompletedmessage
 * @covers \enrol_semco\healthcheck\check\enrolmentroleconfigured
 * @covers \enrol_semco\healthcheck\check\enrolmentroleviewparticipants
 * @covers \enrol_semco\healthcheck\check\enrolpluginenabled
 * @covers \enrol_semco\healthcheck\check\externalserviceconfig
 * @covers \enrol_semco\healthcheck\check\externalserviceexists
 * @covers \enrol_semco\healthcheck\check\externalservicefunctions
 * @covers \enrol_semco\healthcheck\check\manualauthlockedfields
 * @covers \enrol_semco\healthcheck\check\messaging
 * @covers \enrol_semco\healthcheck\check\profilefield
 * @covers \enrol_semco\healthcheck\check\profilefield_branchtoken
 * @covers \enrol_semco\healthcheck\check\profilefield_userbirthday
 * @covers \enrol_semco\healthcheck\check\profilefield_usercompany
 * @covers \enrol_semco\healthcheck\check\profilefield_userid
 * @covers \enrol_semco\healthcheck\check\profilefield_userplaceofbirth
 * @covers \enrol_semco\healthcheck\check\profilefieldcategory
 * @covers \enrol_semco\healthcheck\check\recompletionactivities
 * @covers \enrol_semco\healthcheck\check\recompletioninstalled
 * @covers \enrol_semco\healthcheck\check\recompletionnotify
 * @covers \enrol_semco\healthcheck\check\recompletionondemand
 * @covers \enrol_semco\healthcheck\check\recompletionresetmycompletion
 * @covers \enrol_semco\healthcheck\check\recompletionmanage
 * @covers \enrol_semco\healthcheck\check\restprotocol
 * @covers \enrol_semco\healthcheck\check\roleassignallowed
 * @covers \enrol_semco\healthcheck\check\rolecapabilities
 * @covers \enrol_semco\healthcheck\check\rolecapabilitiesmoodle
 * @covers \enrol_semco\healthcheck\check\rolecapabilitiessemco
 * @covers \enrol_semco\healthcheck\check\rolecapabilitiessurplus
 * @covers \enrol_semco\healthcheck\check\rolecapabilityrest
 * @covers \enrol_semco\healthcheck\check\rolecontextlevel
 * @covers \enrol_semco\healthcheck\check\roleexists
 * @covers \enrol_semco\healthcheck\check\selfenrolment
 * @covers \enrol_semco\healthcheck\check\useractive
 * @covers \enrol_semco\healthcheck\check\userauthmethod
 * @covers \enrol_semco\healthcheck\check\userexists
 * @covers \enrol_semco\healthcheck\check\userprofile
 * @covers \enrol_semco\healthcheck\check\userroleassignment
 * @covers \enrol_semco\healthcheck\check\userserviceauthorised
 * @covers \enrol_semco\healthcheck\check\userserviceiprestriction
 * @covers \enrol_semco\healthcheck\check\usertoken
 * @covers \enrol_semco\healthcheck\check\usertokeniprestriction
 * @covers \enrol_semco\healthcheck\check\webservicesenabled
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class healthcheck_test extends \advanced_testcase {
    /** @var \stdClass The SEMCO webservice role. */
    private \stdClass $semcorole;

    /** @var \stdClass The SEMCO webservice user. */
    private \stdClass $semcouser;

    /** @var \stdClass The SEMCO external service. */
    private \stdClass $semcoservice;

    /** @var \context_system The system context. */
    private \context_system $systemcontext;

    /**
     * Setup testcase.
     */
    public function setUp(): void {
        global $CFG, $DB;

        // Require plugin library.
        require_once($CFG->dirroot . '/enrol/semco/locallib.php');

        // Call the parent setup.
        parent::setUp();

        // Reset after the test.
        $this->resetAfterTest(true);

        // Get the system context.
        $this->systemcontext = \context_system::instance();

        // Get the objects which the plugin installer has created.
        $this->semcorole = $DB->get_record('role', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME], '*', MUST_EXIST);
        $this->semcouser = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME], '*', MUST_EXIST);
        $this->semcoservice = $DB->get_record(
            'external_services',
            ['shortname' => ENROL_SEMCO_SERVICENAME],
            '*',
            MUST_EXIST
        );

        // Make sure that the plugin installation is intact even if this Moodle instance was installed from scratch.
        // In that case, the REST capability is not assigned by the plugin installer but by an ad-hoc task which has
        // not run yet in the test environment.
        $this->repair_rest_capability();

        // Reset the health check caches so that each test starts with a clean state.
        healthcheck::reset_caches();
    }

    /**
     * Helper function to make sure that the REST capability is assigned to the SEMCO webservice role.
     *
     * @return void
     */
    private function repair_rest_capability(): void {
        global $DB;

        // Assign the capability.
        assign_capability('webservice/rest:use', CAP_ALLOW, $this->semcorole->id, $this->systemcontext->id, true);

        // And remove the ad-hoc task which would have assigned it.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\' . \enrol_semco\task\set_webservice_capability::class,
        ]);
    }

    /**
     * Helper function to mute all health check items which need attention at the moment.
     *
     * A stock Moodle instance does not follow all recommendations, thus the tests which assess the manager as a whole
     * have to silence these items first. This is restricted to the items which do not cover the plugin installation on
     * purpose: An item of the installation categories which needs attention means that the test environment is broken,
     * and muting it would hide that from the tests which build upon this helper.
     *
     * @return void
     */
    private function mute_all_items_needing_attention(): void {
        healthcheck::reset_caches();
        foreach (manager::get_healthchecks_needing_attention() as $healthcheck) {
            $this->assertNotSame(
                'installation',
                manager::get_status_context($healthcheck),
                'Health check "' . $healthcheck->get_id() . '" reports a broken plugin installation in the test ' .
                    'environment, it must not be muted.'
            );
            manager::set_healthcheck_muted($healthcheck->get_id(), true);
        }
    }

    /**
     * Helper function to get a fresh health check item instance with a cleared cache.
     *
     * @param string $classname The class name of the health check item.
     * @return healthcheck
     */
    private function get_check(string $classname): healthcheck {
        // Reset the caches as the test has just modified the database.
        healthcheck::reset_caches();

        // Return a fresh instance.
        return new $classname();
    }

    /**
     * Test that all health check items report an intact installation right after the plugin installation.
     *
     * The items of the recompletion and the recommendations category are left out here. They do not check the plugin
     * installation but the configuration of a companion plugin and global Moodle settings which a stock Moodle instance
     * does not necessarily follow.
     */
    public function test_fresh_installation_is_healthy(): void {
        // Iterate over all health check items.
        foreach (manager::get_healthchecks() as $healthcheck) {
            // Skip the categories which do not check the plugin installation.
            if (manager::get_status_context($healthcheck) !== 'installation') {
                continue;
            }

            // Each of them must report an intact installation.
            $this->assertEquals(
                healthcheck::OK,
                $healthcheck->get_status(),
                'Health check "' . $healthcheck->get_id() . '" does not report an intact installation.'
            );

            // An item which is fine neither offers an automatic fix nor a place to fix anything manually.
            $this->assertFalse($healthcheck->supports_autofix());
            $this->assertNull($healthcheck->get_action_url());
            $this->assertCount(0, $healthcheck->get_finding_ids());
        }
    }

    /**
     * Test that the items of all categories are taken into account when the manager looks for a need for attention.
     */
    public function test_all_categories_need_attention(): void {
        // Make sure that none of the items needs attention as a start.
        $this->mute_all_items_needing_attention();
        $this->assertFalse(manager::has_healthchecks_needing_attention());
        $this->assertNull(manager::get_most_severe_status());

        // Break one of the recommendations.
        set_config('messaging', 1);
        manager::set_healthcheck_muted('messaging', false);
        healthcheck::reset_caches();

        // The manager must see a need for attention, but it must not see a broken integration.
        $this->assertTrue(manager::has_healthchecks_needing_attention());
        $this->assertArrayHasKey('messaging', manager::get_healthchecks_needing_attention());
        $this->assertEquals(healthcheck::WARNING, manager::get_most_severe_status());
        $this->assertFalse(manager::has_healthchecks_with_error());
    }

    /**
     * Test that a health check item can be muted and unmuted.
     */
    public function test_healthcheck_items_can_be_muted(): void {
        // Make sure that none of the items needs attention as a start.
        $this->mute_all_items_needing_attention();

        // Break an aspect which the SEMCO integration cannot work without.
        set_config('enablewebservices', 0);
        $check = $this->get_check(\enrol_semco\healthcheck\check\webservicesenabled::class);
        $this->assertFalse(manager::is_healthcheck_muted($check->get_id()));
        $this->assertEquals(healthcheck::ERROR, manager::get_effective_status($check));
        $this->assertTrue(manager::healthcheck_needs_attention($check));
        $this->assertTrue(manager::has_healthchecks_with_error());

        // Mute the item.
        manager::set_healthcheck_muted($check->get_id(), true);
        $this->assertTrue(manager::is_healthcheck_muted($check->get_id()));

        // The item still knows its real status, but the manager reports it as muted and does not see a need for
        // attention anymore.
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertEquals(healthcheck::MUTED, manager::get_effective_status($check));
        $this->assertEquals(get_string('healthcheckstatus_muted', 'enrol_semco'), manager::get_status_label($check));
        $this->assertEquals(
            get_string('healthcheckstatus_muted_description', 'enrol_semco'),
            manager::get_status_description($check)
        );
        $this->assertFalse(manager::healthcheck_needs_attention($check));
        $this->assertFalse(manager::has_healthchecks_needing_attention());
        $this->assertFalse(manager::has_healthchecks_with_error());
        $this->assertSame('', manager::get_possible_solution($check));

        // A muted item is sorted to the bottom of its category.
        $healthchecks = array_values(manager::get_healthchecks(healthcheck::CATEGORY_WEBSERVICE));
        $this->assertEquals('webservicesenabled', end($healthchecks)->get_id());

        // Unmute the item again.
        manager::set_healthcheck_muted($check->get_id(), false);
        $this->assertFalse(manager::is_healthcheck_muted($check->get_id()));
        $this->assertEquals(healthcheck::ERROR, manager::get_effective_status($check));
        $this->assertTrue(manager::has_healthchecks_with_error());
    }

    /**
     * Helper function to count the health check items which have been evaluated since the last cache reset.
     *
     * The base class keeps the evaluation results in a protected static cache, thus the test has to look into it with
     * reflection.
     *
     * @return int
     */
    private function count_evaluated_items(): int {
        $property = new \ReflectionProperty(healthcheck::class, 'resultcache');
        return count($property->getValue());
    }

    /**
     * Test that looking up a single health check item does not evaluate any health check item.
     *
     * The mute and unmute actions of the health check page look the item up by its id and do not need a status at all,
     * thus they must not pay for the evaluation of the whole health check.
     */
    public function test_healthcheck_lookup_by_id_does_not_evaluate_anything(): void {
        healthcheck::reset_caches();
        $check = manager::get_healthcheck_by_id('messaging');
        $this->assertInstanceOf(\enrol_semco\healthcheck\check\messaging::class, $check);
        $this->assertSame(0, $this->count_evaluated_items());
    }

    /**
     * Test that the manager stops looking for a need for attention as soon as it has found one.
     *
     * The plugin settings page asks the manager on every page load, thus it must not evaluate more items than necessary.
     */
    public function test_need_for_attention_is_detected_without_evaluating_everything(): void {
        // Break the very first health check item.
        set_config('enablewebservices', 0);
        healthcheck::reset_caches();

        // The manager must see the need for attention after having evaluated this one item only.
        $this->assertTrue(manager::has_healthchecks_needing_attention());
        $this->assertSame(1, $this->count_evaluated_items());

        // If nothing needs attention, the manager has to evaluate every item to be sure. The muting helper evaluates
        // the items itself, thus the caches are reset once more before the manager is asked.
        set_config('enablewebservices', 1);
        $this->mute_all_items_needing_attention();
        healthcheck::reset_caches();
        $this->assertFalse(manager::has_healthchecks_needing_attention());

        // Every item except the muted ones must have been evaluated then. A muted item does not need attention
        // regardless of its status, thus the manager does not have to evaluate it at all.
        $unmuted = 0;
        foreach (manager::get_healthcheck_classes() as $classname) {
            if (!manager::is_healthcheck_muted((new $classname())->get_id())) {
                $unmuted++;
            }
        }
        $this->assertGreaterThan(0, $unmuted);
        $this->assertSame($unmuted, $this->count_evaluated_items());
    }

    /**
     * Test that the evaluation result of a health check item is determined only once per request.
     */
    public function test_healthcheck_result_is_cached_statically(): void {
        $classname = \enrol_semco\healthcheck\check\webservicesenabled::class;

        // Evaluate the item.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Break the aspect. Even a fresh instance must return the result which has been determined already, as the
        // manager hands out fresh instances with every call.
        set_config('enablewebservices', 0);
        $this->assertEquals(healthcheck::OK, (new $classname())->get_status());
        $this->assertCount(0, (new $classname())->get_finding_ids());

        // After the caches have been reset, the item is evaluated again.
        healthcheck::reset_caches();
        $check = new $classname();
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertEquals([$classname::FINDING_DISABLED], $check->get_finding_ids());

        // An automatic fix resets the caches on its own.
        $check->autofix();
        $this->assertEquals(healthcheck::OK, (new $classname())->get_status());
    }

    /**
     * Test that the automatic fix and the action URL of a health check item are derived from its findings.
     */
    public function test_autofix_and_action_url_are_derived_from_the_findings(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\externalserviceconfig::class;

        // Disable the SEMCO external service and lift its restriction to authorised users. Both findings can be fixed
        // automatically and harmlessly.
        $DB->set_field('external_services', 'enabled', 0, ['id' => $this->semcoservice->id]);
        $DB->set_field('external_services', 'restrictedusers', 0, ['id' => $this->semcoservice->id]);
        $check = $this->get_check($classname);
        $this->assertEquals([$classname::FINDING_DISABLED, $classname::FINDING_UNRESTRICTED], $check->get_finding_ids());
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());

        // The action URL is the one of the topmost finding.
        $this->assertStringContainsString('section=externalservices', $check->get_action_url()->out(false));

        // Authorise another user for the service on top of that. This finding can be fixed as well, but not entirely
        // harmlessly, and as soon as one finding is risky, the automatic fix as a whole is risky.
        $DB->insert_record('external_services_users', (object) [
            'externalserviceid' => $this->semcoservice->id,
            'userid' => $this->getDataGenerator()->create_user()->id,
            'timecreated' => time(),
        ]);
        $check = $this->get_check($classname);
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());

        // The findings are reported in the order in which they are defined, regardless of the order in which the item
        // has found them, and the manager offers both ways to fix them.
        $this->assertEquals(
            [$classname::FINDING_DISABLED, $classname::FINDING_UNRESTRICTED, $classname::FINDING_OTHERUSERS],
            $check->get_finding_ids()
        );
        $this->assertEquals(get_string('healthchecksolution_both', 'enrol_semco'), manager::get_possible_solution($check));

        // The automatic fix resolves all findings at once.
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // As soon as one finding cannot be fixed automatically, the item as a whole cannot be fixed either. The
        // missing creator of the SEMCO webservice token can be fixed, a second token cannot.
        $tokenclassname = \enrol_semco\healthcheck\check\usertoken::class;
        $token = $DB->get_record('external_tokens', ['userid' => $this->semcouser->id], '*', MUST_EXIST);
        $DB->set_field('external_tokens', 'creatorid', 0, ['id' => $token->id]);
        $this->assertTrue($this->get_check($tokenclassname)->supports_autofix());
        $secondtoken = clone $token;
        unset($secondtoken->id);
        $secondtoken->token = md5('semcohealthchecktesttoken');
        $secondtoken->privatetoken = null;
        $secondtoken->timecreated = $token->timecreated - 1;
        $DB->insert_record('external_tokens', $secondtoken);
        $tokencheck = $this->get_check($tokenclassname);
        $this->assertEquals(
            [$tokenclassname::FINDING_MULTIPLE, $tokenclassname::FINDING_NOCREATOR],
            $tokencheck->get_finding_ids()
        );
        $this->assertFalse($tokencheck->supports_autofix());
        $this->assertFalse($tokencheck->is_autofix_risky());
        $this->assertEquals(
            get_string('healthchecksolution_actionurlonly', 'enrol_semco'),
            manager::get_possible_solution($tokencheck)
        );

        // Calling the automatic fix nonetheless must not change anything.
        $tokencheck->autofix();
        $this->assertEquals(0, $DB->get_field('external_tokens', 'creatorid', ['id' => $token->id]));

        // An item which cannot be assessed does not offer anything.
        $DB->delete_records('external_services', ['id' => $this->semcoservice->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertEquals([healthcheck::FINDING_NOSERVICE], $check->get_finding_ids());
        $this->assertFalse($check->supports_autofix());
        $this->assertNull($check->get_action_url());
    }

    /**
     * Test that the action URL skips the findings which cannot be fixed manually.
     */
    public function test_action_url_skips_findings_without_url(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\externalserviceconfig::class;

        // Lift the restriction of the SEMCO external service to authorised users. Moodle does not let an admin change
        // this on the service settings page, thus the finding does not offer an URL. The automatic fix is all there is.
        $DB->set_field('external_services', 'restrictedusers', 0, ['id' => $this->semcoservice->id]);
        $check = $this->get_check($classname);
        $this->assertEquals([$classname::FINDING_UNRESTRICTED], $check->get_finding_ids());
        $this->assertNull($check->get_action_url());
        $this->assertTrue($check->supports_autofix());
        $this->assertEquals(
            get_string('healthchecksolution_autofixonly', 'enrol_semco'),
            manager::get_possible_solution($check)
        );

        // Authorise another user for the service on top of that. This finding can be fixed manually, thus its URL is
        // offered even though it is not the topmost finding.
        $DB->insert_record('external_services_users', (object) [
            'externalserviceid' => $this->semcoservice->id,
            'userid' => $this->getDataGenerator()->create_user()->id,
            'timecreated' => time(),
        ]);
        $check = $this->get_check($classname);
        $this->assertEquals(
            [$classname::FINDING_UNRESTRICTED, $classname::FINDING_OTHERUSERS],
            $check->get_finding_ids()
        );
        $this->assertStringContainsString('service_users.php', $check->get_action_url()->out(false));
    }

    /**
     * Test that every finding which a health check item can fix automatically comes with a fix strategy.
     *
     * A finding which is defined as fixable but which is not handled in apply_autofix() would make the automatic fix
     * report a success without doing anything. We cannot trigger every finding of every item here, thus we read the
     * source code of the items and look for the case label of each fixable finding.
     */
    public function test_fixable_findings_have_a_fix_strategy(): void {
        foreach (manager::get_healthchecks() as $healthcheck) {
            // Get the finding definitions of the item.
            $reflection = new \ReflectionClass($healthcheck);
            $method = $reflection->getMethod('get_finding_definitions');
            $definitions = $method->invoke($healthcheck);

            // Collect the source code of the item and of its parent classes.
            $source = '';
            for ($class = $reflection; $class !== false; $class = $class->getParentClass()) {
                $source .= file_get_contents($class->getFileName());
            }

            // Iterate over the definitions.
            foreach ($definitions as $findingid => $definition) {
                // Each definition must be complete, the follow-up is optional.
                $this->assertContains(
                    array_keys($definition),
                    [['autofix', 'risky', 'url'], ['autofix', 'risky', 'url', 'followup']]
                );
                $this->assertIsBool($definition['autofix']);
                $this->assertIsBool($definition['risky']);
                if (array_key_exists('followup', $definition)) {
                    $this->assertIsString($definition['followup']);
                    $this->assertNotEmpty($definition['followup']);
                }

                // A finding which cannot be fixed automatically cannot be risky either, and there is nothing to follow
                // up after a fix which does not exist.
                if ($definition['autofix'] === false) {
                    $this->assertFalse($definition['risky']);
                    $this->assertArrayNotHasKey('followup', $definition);
                    continue;
                }

                // The findings about the REST capability ad-hoc task are fixed along with the missing capability.
                $taskfindings = ['taskfailed', 'taskoverdue', 'taskqueued', 'notask'];
                if ($healthcheck->get_id() === 'rolecapabilityrest' && in_array($findingid, $taskfindings)) {
                    continue;
                }

                // The finding must be handled by a case label.
                $constant = array_search($findingid, $reflection->getConstants(), true);
                $this->assertNotFalse($constant);
                $this->assertStringContainsString(
                    'case self::' . $constant . ':',
                    $source,
                    'Health check "' . $healthcheck->get_id() . '" does not fix its finding "' . $findingid . '".'
                );
            }
        }
    }

    /**
     * Test that all health check items are well-formed.
     */
    public function test_healthcheck_items_are_wellformed(): void {
        // Get the supported statuses and categories.
        $supportedstatuses = array_keys(manager::get_supported_statuses());
        $supportedcategories = array_keys(manager::get_supported_categories());

        // Initialize the list of seen ids.
        $seenids = [];

        // Iterate over all health check items.
        foreach (manager::get_healthchecks() as $healthcheck) {
            // The item id must be unique.
            $id = $healthcheck->get_id();
            $this->assertNotContains($id, $seenids, 'Health check id "' . $id . '" is not unique.');
            $seenids[] = $id;

            // The item must return a supported status and category.
            $this->assertContains($healthcheck->get_status(), $supportedstatuses);
            $this->assertContains($healthcheck->get_category(), $supportedcategories);

            // The item must return a title, a summary and a description.
            $this->assertNotEmpty($healthcheck->get_title());
            $this->assertNotEmpty($healthcheck->get_summary());
            $this->assertNotEmpty($healthcheck->get_description());

            // The item's language strings must exist.
            $this->assertStringNotContainsString('[[', $healthcheck->get_title());
            $this->assertStringNotContainsString('[[', $healthcheck->get_summary());
            $this->assertStringNotContainsString('[[', $healthcheck->get_description());

            // The item must be retrievable by its id.
            $this->assertSame($id, manager::get_healthcheck_by_id($id)->get_id());
        }

        // All hardcoded health check classes must have been instantiated.
        $this->assertCount(count(manager::get_healthcheck_classes()), $seenids);

        // An unknown id must not return an item.
        $this->assertNull(manager::get_healthcheck_by_id('thishealthcheckdoesnotexist'));
    }

    /**
     * Test that the health check items can be filtered by category.
     */
    public function test_healthcheck_items_can_be_filtered_by_category(): void {
        // Initialize the counter of all health check items which have been seen in a category.
        $countedbycategory = 0;

        // Iterate over all categories.
        foreach (array_keys(manager::get_supported_categories()) as $category) {
            // Get the health check items of this category.
            $healthchecks = manager::get_healthchecks($category);

            // Each category must hold at least one item.
            $this->assertNotEmpty($healthchecks);

            // And each returned item must belong to this category.
            foreach ($healthchecks as $healthcheck) {
                $this->assertEquals($category, $healthcheck->get_category());
            }

            // Count the items of this category.
            $countedbycategory += count($healthchecks);
        }

        // The sum of all categories must be the complete list of health check items.
        $this->assertEquals(count(manager::get_healthchecks()), $countedbycategory);
    }

    /**
     * Test that the health check items are sorted by status severity.
     */
    public function test_healthcheck_items_are_sorted_by_status(): void {
        // Break one aspect of the webservice infrastructure category.
        set_config('enablewebservices', 0);
        healthcheck::reset_caches();

        // Get the health check items of this category.
        $healthchecks = array_values(manager::get_healthchecks(healthcheck::CATEGORY_WEBSERVICE));

        // The broken item must be listed first.
        $this->assertEquals('webservicesenabled', $healthchecks[0]->get_id());
        $this->assertEquals(healthcheck::ERROR, $healthchecks[0]->get_status());
    }

    /**
     * Test that health check items which share a status are sorted in the order of the plugin installation.
     *
     * Within a status group, the items must not be sorted alphabetically but in the order in which db/install.php sets
     * the checked aspects up. This way, an administrator who works through a category reads the items in the same
     * order in which the aspects depend on each other.
     */
    public function test_healthcheck_items_are_sorted_by_installation_order(): void {
        // Get the health check items of the webservice infrastructure category of an intact installation.
        $healthchecks = array_values(manager::get_healthchecks(healthcheck::CATEGORY_WEBSERVICE));

        // As all of them share the OK status, they must appear in the order of the plugin installation.
        $this->assertEquals(
            [
                'webservicesenabled',
                'restprotocol',
                'authmethod',
                'externalserviceexists',
                'externalserviceconfig',
                'externalservicefunctions',
            ],
            array_map(fn(healthcheck $healthcheck) => $healthcheck->get_id(), $healthchecks)
        );
    }

    /**
     * Test the health check item for the webservice subsystem.
     */
    public function test_webservicesenabled(): void {
        $classname = \enrol_semco\healthcheck\check\webservicesenabled::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Disable the webservice subsystem.
        set_config('enablewebservices', 0);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
    }

    /**
     * Test the health check item for the webservice REST protocol.
     */
    public function test_restprotocol(): void {
        $classname = \enrol_semco\healthcheck\check\restprotocol::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Disable the REST protocol.
        \core\plugininfo\webservice::enable_plugin('rest', 0);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
    }

    /**
     * Test the health check item for the authentication method.
     */
    public function test_authmethod(): void {
        $classname = \enrol_semco\healthcheck\check\authmethod::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Disable the authentication method.
        \core\plugininfo\auth::enable_plugin(ENROL_SEMCO_AUTH, false);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
    }

    /**
     * Test the health check item for the enrolment method.
     */
    public function test_enrolpluginenabled(): void {
        $classname = \enrol_semco\healthcheck\check\enrolpluginenabled::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Disable the enrolment method.
        \core\plugininfo\enrol::enable_plugin('semco', false);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
    }

    /**
     * Test the health check item for the external service.
     */
    public function test_externalserviceconfig(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\externalserviceconfig::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Disable the external service and remove the user restriction.
        $DB->set_field('external_services', 'enabled', 0, ['id' => $this->semcoservice->id]);
        $DB->set_field('external_services', 'restrictedusers', 0, ['id' => $this->semcoservice->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // And fix it automatically. The fix must log its changes just as the core administration page does.
        $this->assertTrue($check->supports_autofix());
        $sink = $this->redirectEvents();
        $check->autofix();
        $events = $sink->get_events();
        $sink->close();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertInstanceOf(\core\event\webservice_service_updated::class, $event);
            $this->assertEquals($this->semcoservice->id, $event->objectid);
        }

        // Without the external service, this check cannot be assessed.
        $DB->delete_records('external_services', ['id' => $this->semcoservice->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // And there is nothing to fix automatically then.
        $this->assertFalse($check->supports_autofix());
    }

    /**
     * Test the health check item for the existence of the SEMCO external service.
     */
    public function test_externalserviceexists(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\externalserviceexists::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Remove the external service completely.
        $DB->delete_records('external_services', ['id' => $this->semcoservice->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The admin cannot recreate the service himself, thus the item does not offer an URL.
        $this->assertNull($check->get_action_url());

        // The automatic fix lets Moodle create the service again, just as during the installation of the plugin.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // The new service must be configured as the plugin declares it and must offer all functions.
        $serviceclassnames = [
            \enrol_semco\healthcheck\check\externalserviceconfig::class,
            \enrol_semco\healthcheck\check\externalservicefunctions::class,
        ];
        foreach ($serviceclassnames as $serviceclassname) {
            $this->assertEquals(healthcheck::OK, $this->get_check($serviceclassname)->get_status());
        }

        // The authorisation and the token belonged to the service which is gone. They are reported by their own items,
        // and both can be fixed automatically as well.
        $dependentclassnames = [
            \enrol_semco\healthcheck\check\userserviceauthorised::class,
            \enrol_semco\healthcheck\check\usertoken::class,
        ];
        foreach ($dependentclassnames as $dependentclassname) {
            $dependentcheck = $this->get_check($dependentclassname);
            $this->assertEquals(healthcheck::ERROR, $dependentcheck->get_status());
            $this->assertTrue($dependentcheck->supports_autofix());
            $dependentcheck->autofix();
            $this->assertEquals(healthcheck::OK, $dependentcheck->get_status());
        }
    }

    /**
     * Test that the health check item for the external service also covers the list of authorised users.
     */
    public function test_externalserviceconfig_authorised_users(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\externalserviceconfig::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Authorise another user for the SEMCO external service.
        $otheruser = $this->getDataGenerator()->create_user();
        $DB->insert_record('external_services_users', (object) [
            'externalserviceid' => $this->semcoservice->id,
            'userid' => $otheruser->id,
            'timecreated' => time(),
        ]);

        // The service is still usable, thus this is a warning and not an error.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());

        // The finding must name the amount of other users and the users themselves, as the automatic fix is going to
        // revoke their authorisation.
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString('1 user(s)', $check->get_findings()[0]);
        $this->assertStringContainsString(fullname($otheruser), $check->get_findings()[0]);
        $this->assertStringContainsString($otheruser->username, $check->get_findings()[0]);

        // The automatic fix revokes the authorisation of the other user. It is not entirely harmless, as an admin may
        // have authorised that user on purpose.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $sink = $this->redirectEvents();
        $check->autofix();
        $events = $sink->get_events();
        $sink->close();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertFalse($DB->record_exists('external_services_users', ['userid' => $otheruser->id]));

        // The fix must log the revocation just as the core administration page does.
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\webservice_service_user_removed::class, $events[0]);
        $this->assertEquals($this->semcoservice->id, $events[0]->objectid);
        $this->assertEquals($otheruser->id, $events[0]->relateduserid);

        // It must not touch the authorisation of the SEMCO webservice user.
        $this->assertTrue($DB->record_exists('external_services_users', [
            'externalserviceid' => $this->semcoservice->id,
            'userid' => $this->semcouser->id,
        ]));

        // And it must not touch the authorisations for other external services either.
        $otherserviceid = $DB->insert_record('external_services', (object) [
            'name' => 'Other service',
            'shortname' => 'semcohealthchecktestservice',
            'enabled' => 1,
            'restrictedusers' => 1,
            'timecreated' => time(),
        ]);
        foreach ([$this->semcoservice->id, $otherserviceid] as $serviceid) {
            $DB->insert_record('external_services_users', (object) [
                'externalserviceid' => $serviceid,
                'userid' => $otheruser->id,
                'timecreated' => time(),
            ]);
        }
        $this->get_check($classname)->autofix();
        $this->assertFalse($DB->record_exists('external_services_users', [
            'externalserviceid' => $this->semcoservice->id,
            'userid' => $otheruser->id,
        ]));
        $this->assertTrue($DB->record_exists('external_services_users', [
            'externalserviceid' => $otherserviceid,
            'userid' => $otheruser->id,
        ]));

        // A long list of other users is cut off, but the automatic fix still covers all of them.
        for ($i = 0; $i < 12; $i++) {
            $DB->insert_record('external_services_users', (object) [
                'externalserviceid' => $this->semcoservice->id,
                'userid' => $this->getDataGenerator()->create_user()->id,
                'timecreated' => time(),
            ]);
        }
        $check = $this->get_check($classname);
        $this->assertStringContainsString('12 user(s)', $check->get_findings()[0]);
        $this->assertStringContainsString('and 2 more', $check->get_findings()[0]);
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(1, $DB->count_records('external_services_users', [
            'externalserviceid' => $this->semcoservice->id,
        ]));
    }

    /**
     * Test the health check item for the existence of the SEMCO webservice role.
     */
    public function test_roleexists(): void {
        $classname = \enrol_semco\healthcheck\check\roleexists::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Delete the SEMCO webservice role.
        delete_role($this->semcorole->id);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // An admin who wants to create the role himself is led to the form which creates a new role.
        $this->assertStringContainsString('/admin/roles/define.php?action=add', $check->get_action_url()->out(false));

        // The automatic fix creates the role again, just as the plugin installer does, which is harmless.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // Everything which makes up the role is reported by the items which cover these aspects, and each of them can be
        // fixed automatically as well.
        $dependentclassnames = [
            \enrol_semco\healthcheck\check\rolecontextlevel::class => healthcheck::WARNING,
            \enrol_semco\healthcheck\check\rolecapabilitiessemco::class => healthcheck::ERROR,
            \enrol_semco\healthcheck\check\rolecapabilitiesmoodle::class => healthcheck::ERROR,
            \enrol_semco\healthcheck\check\rolecapabilityrest::class => healthcheck::ERROR,
            \enrol_semco\healthcheck\check\roleassignallowed::class => healthcheck::ERROR,
            \enrol_semco\healthcheck\check\userroleassignment::class => healthcheck::ERROR,
        ];
        foreach ($dependentclassnames as $dependentclassname => $expectedstatus) {
            $dependentcheck = $this->get_check($dependentclassname);
            $this->assertEquals($expectedstatus, $dependentcheck->get_status());
            $this->assertTrue($dependentcheck->supports_autofix());
            $dependentcheck->autofix();
            $this->assertEquals(healthcheck::OK, $dependentcheck->get_status());
        }

        // Afterwards, the whole plugin installation is intact again.
        healthcheck::reset_caches();
        foreach (manager::get_healthchecks() as $healthcheck) {
            if (manager::get_status_context($healthcheck) === 'installation') {
                $this->assertEquals(healthcheck::OK, $healthcheck->get_status(), $healthcheck->get_id());
            }
        }
    }

    /**
     * Test the health check item for the context level of the SEMCO webservice role.
     */
    public function test_rolecontextlevel(): void {
        $classname = \enrol_semco\healthcheck\check\rolecontextlevel::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Allow an additional context level next to the system context.
        set_role_contextlevels($this->semcorole->id, [CONTEXT_SYSTEM, CONTEXT_COURSE]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // Replace the role's context levels with the course context, which adds the missing system context.
        set_role_contextlevels($this->semcorole->id, [CONTEXT_COURSE]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // The automatic fix must have restored the system context as the one and only context level.
        $this->assertEquals([CONTEXT_SYSTEM], array_values(array_map('intval', get_role_contextlevels($this->semcorole->id))));

        // If the role does not exist, this check cannot be assessed.
        delete_role($this->semcorole->id);
        $this->assertEquals(healthcheck::NA, $this->get_check($classname)->get_status());
    }

    /**
     * Test the health check item for the plugin capabilities of the SEMCO webservice role.
     */
    public function test_rolecapabilitiessemco(): void {
        $classname = \enrol_semco\healthcheck\check\rolecapabilitiessemco::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Revoke two of the plugin capabilities.
        unassign_capability('enrol/semco:enrol', $this->semcorole->id, $this->systemcontext->id);
        assign_capability('enrol/semco:unenrol', CAP_PREVENT, $this->semcorole->id, $this->systemcontext->id, true);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // If the role does not exist, this check cannot be assessed.
        delete_role($this->semcorole->id);
        $this->assertEquals(healthcheck::NA, $this->get_check($classname)->get_status());
    }

    /**
     * Test the health check item for the Moodle core capabilities of the SEMCO webservice role.
     */
    public function test_rolecapabilitiesmoodle(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\rolecapabilitiesmoodle::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Revoke one of the Moodle core capabilities.
        unassign_capability('moodle/user:create', $this->semcorole->id, $this->systemcontext->id);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // Without the SEMCO webservice role, this check cannot be assessed.
        $DB->delete_records('role', ['id' => $this->semcorole->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
    }

    /**
     * Test the health check item for the REST capability of the SEMCO webservice role.
     */
    public function test_rolecapabilityrest(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\rolecapabilityrest::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Revoke the REST capability.
        unassign_capability('webservice/rest:use', $this->semcorole->id, $this->systemcontext->id);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // Without the SEMCO webservice role, this check cannot be assessed.
        $DB->delete_records('role', ['id' => $this->semcorole->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
    }

    /**
     * Test that the health check item for the REST capability respects the queued ad-hoc task.
     */
    public function test_rolecapabilityrest_with_queued_task(): void {
        $classname = \enrol_semco\healthcheck\check\rolecapabilityrest::class;

        // Revoke the REST capability, but queue the ad-hoc task which is going to assign it.
        unassign_capability('webservice/rest:use', $this->semcorole->id, $this->systemcontext->id);
        \core\task\manager::queue_adhoc_task(new \enrol_semco\task\set_webservice_capability());

        // This is just a notice as the situation is going to resolve itself.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(2, $check->get_findings());
    }

    /**
     * Test that the health check item for the REST capability does not trust a queued ad-hoc task forever.
     */
    public function test_rolecapabilityrest_with_overdue_task(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\rolecapabilityrest::class;

        // Revoke the REST capability, but queue the ad-hoc task which is going to assign it.
        unassign_capability('webservice/rest:use', $this->semcorole->id, $this->systemcontext->id);
        \core\task\manager::queue_adhoc_task(new \enrol_semco\task\set_webservice_capability());

        // The item grants cron a grace period of 3 minutes. A task which is waiting for 2 minutes is still within the
        // period which a healthy cron needs.
        $taskconditions = ['classname' => '\\' . \enrol_semco\task\set_webservice_capability::class];
        $DB->set_field('task_adhoc', 'nextruntime', time() - 2 * MINSECS, $taskconditions);
        $this->assertEquals(healthcheck::NOTICE, $this->get_check($classname)->get_status());

        // A task which is overdue for 4 minutes is not going to be processed, most likely because cron does not run.
        // The SEMCO integration does not work as long as the capability is missing, thus this is an error.
        $DB->set_field('task_adhoc', 'nextruntime', time() - 4 * MINSECS, $taskconditions);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(2, $check->get_findings());
        $this->assertStringNotContainsString('[[', $check->get_findings()[1]);

        // The finding must name the grace period.
        $this->assertStringContainsString('3 min', $check->get_findings()[1]);

        // The automatic fix does not depend on the task.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
    }

    /**
     * Test the health check item for the surplus capabilities of the SEMCO webservice role.
     */
    public function test_rolecapabilitiessurplus(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\rolecapabilitiessurplus::class;

        // The installation is intact.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertFalse($check->supports_autofix());

        // A capability which is not allowed but only prevented does not make the role more powerful.
        assign_capability('moodle/site:config', CAP_PREVENT, $this->semcorole->id, $this->systemcontext->id, true);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Allow two capabilities which the plugin installer has not placed in the role.
        assign_capability('moodle/site:config', CAP_ALLOW, $this->semcorole->id, $this->systemcontext->id, true);
        assign_capability('moodle/course:create', CAP_ALLOW, $this->semcorole->id, $this->systemcontext->id, true);

        // The findings must name the amount of the surplus capabilities plus every single one of them.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(3, $check->get_findings());
        $this->assertContains('moodle/course:create', $check->get_findings());
        $this->assertContains('moodle/site:config', $check->get_findings());

        // The automatic fix removes the surplus capabilities. It is not entirely harmless, as somebody may have added
        // them on purpose.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertFalse($DB->record_exists('role_capabilities', [
            'roleid' => $this->semcorole->id,
            'capability' => 'moodle/course:create',
        ]));

        // It must neither touch the capabilities which the plugin installer has placed in the role nor the capability
        // which is prevented only.
        $this->assertEquals(
            healthcheck::OK,
            $this->get_check(\enrol_semco\healthcheck\check\rolecapabilitiesmoodle::class)->get_status()
        );
        $this->assertEquals(
            healthcheck::OK,
            $this->get_check(\enrol_semco\healthcheck\check\rolecapabilitiessemco::class)->get_status()
        );

        // Without the SEMCO webservice role, this check cannot be assessed.
        $DB->delete_records('role', ['id' => $this->semcorole->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
    }

    /**
     * Test the health check item for the allowed role assignments.
     */
    public function test_roleassignallowed(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\roleassignallowed::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Remove the allowed role assignment.
        $DB->delete_records('role_allow_assign', ['roleid' => $this->semcorole->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // A permission to assign another role is not needed by SEMCO and is reported as a notice.
        $otherroleid = $this->getDataGenerator()->create_role(['shortname' => 'semcohealthchecktestrole']);
        core_role_set_assign_allowed($this->semcorole->id, $otherroleid);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The finding must name the role and its language string must exist.
        $this->assertStringNotContainsString('[[', $check->get_findings()[0]);
        $this->assertStringContainsString(
            $DB->get_field('role', 'name', ['id' => $otherroleid]),
            $check->get_findings()[0]
        );

        // The automatic fix revokes it again.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertFalse($DB->record_exists('role_allow_assign', [
            'roleid' => $this->semcorole->id,
            'allowassign' => $otherroleid,
        ]));

        // But it must keep the permission for the configured enrolment role.
        $this->assertTrue($DB->record_exists('role_allow_assign', [
            'roleid' => $this->semcorole->id,
            'allowassign' => get_config('enrol_semco', 'role'),
        ]));

        // If the enrolment role cannot be assigned in the course context, SEMCO cannot enrol anyone with that role.
        $enrolmentroleid = (int) get_config('enrol_semco', 'role');
        $contextlevels = array_map('intval', get_role_contextlevels($enrolmentroleid));
        set_role_contextlevels($enrolmentroleid, array_diff($contextlevels, [CONTEXT_COURSE]));
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringNotContainsString('[[', $check->get_findings()[0]);

        // The automatic fix makes the role assignable in the course context again and keeps the other context levels.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEqualsCanonicalizing(
            $contextlevels,
            array_map('intval', get_role_contextlevels($enrolmentroleid))
        );

        // If there is not any enrolment role configured, this check cannot be assessed. The finding must point to the
        // item which reports the missing enrolment role itself.
        set_config('role', '', 'enrol_semco');
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString(
            get_string('healthcheck_enrolmentroleconfigured_title', 'enrol_semco'),
            $check->get_findings()[0]
        );
        $this->assertFalse($check->supports_autofix());
    }

    /**
     * Test the health check item for the configured SEMCO enrolment role.
     */
    public function test_enrolmentroleconfigured(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\enrolmentroleconfigured::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Configure a role which exists, but which is going to be deleted.
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'semcohealthchecktestrole']);
        set_config('role', $roleid, 'enrol_semco');
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Delete the role. Moodle does not touch the plugin setting, thus it points to a role which is gone and SEMCO
        // cannot enrol anyone anymore.
        delete_role($roleid);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringNotContainsString('[[', $check->get_findings()[0]);
        $this->assertStringContainsString((string) $roleid, $check->get_findings()[0]);

        // This cannot be fixed automatically as the admin has picked that role on purpose and picking another one is
        // a decision which only he can make.
        $this->assertFalse($check->supports_autofix());

        // An empty setting is a broken installation as well.
        set_config('role', '', 'enrol_semco');
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringNotContainsString('[[', $check->get_findings()[0]);

        // The items which assess the enrolment role cannot be assessed in that case and must not report an error of
        // their own, so that the admin is pointed to one item only.
        $this->assertEquals(
            healthcheck::NA,
            $this->get_check(\enrol_semco\healthcheck\check\roleassignallowed::class)->get_status()
        );
        $this->assertEquals(
            healthcheck::NA,
            $this->get_check(\enrol_semco\healthcheck\check\enrolmentroleviewparticipants::class)->get_status()
        );

        // The automatic fix configures the plugin's default role, which is harmless, and asks the admin to verify the
        // choice afterwards. Along with the setting, the SEMCO webservice role is allowed to assign the default role,
        // just as the settings page does. That allowance is removed first to see that the fix restores it.
        $defaultroleid = enrol_semco_get_firststudentroleid();
        $this->assertNotEmpty($defaultroleid);
        $DB->delete_records('role_allow_assign', ['roleid' => $this->semcorole->id, 'allowassign' => $defaultroleid]);
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $followups = $check->get_autofix_followups();
        $this->assertCount(1, $followups);
        $this->assertStringContainsString('verify', $followups[0]);
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals($defaultroleid, get_config('enrol_semco', 'role'));
        $this->assertTrue(
            $DB->record_exists('role_allow_assign', ['roleid' => $this->semcorole->id, 'allowassign' => $defaultroleid])
        );
        $this->assertEquals(
            healthcheck::OK,
            $this->get_check(\enrol_semco\healthcheck\check\roleassignallowed::class)->get_status()
        );
    }

    /**
     * Test the health check item for the existence of the SEMCO webservice user.
     */
    public function test_userexists(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\userexists::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Delete the SEMCO webservice user.
        delete_user($this->semcouser);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // An admin who wants to create the user himself is led to the form which creates a new user.
        $this->assertStringContainsString('/user/editadvanced.php?id=-1', $check->get_action_url()->out(false));

        // The automatic fix creates the user again, just as the plugin installer does, which is harmless.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // The new user is a different account than the one which has been deleted.
        $newuser = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME, 'deleted' => 0], '*', MUST_EXIST);
        $this->assertNotEquals($this->semcouser->id, $newuser->id);

        // The items which verify the user itself must be fine with the account which the automatic fix has created.
        $userclassnames = [
            \enrol_semco\healthcheck\check\userauthmethod::class,
            \enrol_semco\healthcheck\check\useractive::class,
            \enrol_semco\healthcheck\check\userprofile::class,
        ];
        foreach ($userclassnames as $userclassname) {
            $this->assertEquals(healthcheck::OK, $this->get_check($userclassname)->get_status());
        }

        // Moodle has removed everything which depended on the deleted user, and the automatic fix does not restore it.
        // This is reported by the items which cover these aspects, all of them can be fixed automatically as well.
        $dependentclassnames = [
            \enrol_semco\healthcheck\check\userroleassignment::class => true,
            \enrol_semco\healthcheck\check\userserviceauthorised::class => true,
            \enrol_semco\healthcheck\check\usertoken::class => true,
        ];
        foreach ($dependentclassnames as $dependentclassname => $fixable) {
            $dependentcheck = $this->get_check($dependentclassname);
            $this->assertEquals(healthcheck::ERROR, $dependentcheck->get_status());
            $this->assertEquals($fixable, $dependentcheck->supports_autofix());
            if ($fixable) {
                $dependentcheck->autofix();
                $this->assertEquals(healthcheck::OK, $dependentcheck->get_status());
            }
        }
    }

    /**
     * Test the health check item for the authentication method of the SEMCO webservice user.
     */
    public function test_userauthmethod(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\userauthmethod::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // With the manual authentication method, the account is still able to use the webservice, so this is only a
        // warning and not a broken installation.
        $DB->set_field('user', 'auth', 'manual', ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // Any other authentication method is reported as a broken installation, regardless of whether the account is
        // still able to authenticate against the webservice with it.
        $DB->set_field('user', 'auth', 'nologin', ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(ENROL_SEMCO_AUTH, $DB->get_field('user', 'auth', ['id' => $this->semcouser->id]));

        // A password hash on the account is not a broken installation, but it would open the username / password
        // authentication of the webservices to this technical account which nobody needs.
        $DB->set_field('user', 'password', hash_internal_user_password('dummy'), ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // Together with the manual authentication method, both findings are reported.
        $DB->set_field('user', 'auth', 'manual', ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // And fix it automatically. The webservice tokens of the user must survive the fix.
        $tokencount = $DB->count_records('external_tokens', ['userid' => $this->semcouser->id]);
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(ENROL_SEMCO_AUTH, $DB->get_field('user', 'auth', ['id' => $this->semcouser->id]));
        $this->assertEquals(AUTH_PASSWORD_NOT_CACHED, $DB->get_field('user', 'password', ['id' => $this->semcouser->id]));
        $this->assertEquals($tokencount, $DB->count_records('external_tokens', ['userid' => $this->semcouser->id]));

        // Without the SEMCO webservice user, this check cannot be assessed.
        $DB->set_field('user', 'deleted', 1, ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
    }

    /**
     * Test the health check item for the account state of the SEMCO webservice user.
     */
    public function test_useractive(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\useractive::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Suspend and unconfirm the SEMCO webservice user.
        $DB->set_field('user', 'suspended', 1, ['id' => $this->semcouser->id]);
        $DB->set_field('user', 'confirmed', 0, ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $this->semcouser->id]));
        $this->assertEquals(1, $DB->get_field('user', 'confirmed', ['id' => $this->semcouser->id]));

        // Without the SEMCO webservice user, this check cannot be assessed.
        $DB->set_field('user', 'deleted', 1, ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
    }

    /**
     * Test the health check item for the role assignment of the SEMCO webservice user.
     */
    public function test_userroleassignment(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\userroleassignment::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Unassign the SEMCO webservice role from the SEMCO webservice user.
        role_unassign($this->semcorole->id, $this->semcouser->id, $this->systemcontext->id);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // Without the SEMCO webservice user, this check cannot be assessed.
        $DB->set_field('user', 'deleted', 1, ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
    }

    /**
     * Test that the health check item for the service authorisation steps back if the service is not restricted.
     */
    public function test_userserviceauthorised_without_restricted_users(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\userserviceauthorised::class;

        // Remove the authorisation of the SEMCO webservice user, which normally breaks the integration.
        $DB->delete_records('external_services_users', [
            'externalserviceid' => $this->semcoservice->id,
            'userid' => $this->semcouser->id,
        ]);
        $this->assertEquals(healthcheck::ERROR, $this->get_check($classname)->get_status());

        // But as soon as the service is not restricted to authorised users anymore, Moodle does not evaluate the list
        // at all, thus this item must not claim a broken integration.
        $DB->set_field('external_services', 'restrictedusers', 0, ['id' => $this->semcoservice->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringNotContainsString('[[', $check->get_findings()[0]);

        // And it must not offer an automatic fix which would not change anything.
        $this->assertFalse($check->supports_autofix());
    }

    /**
     * Test the health check item for the service authorisation of the SEMCO webservice user.
     */
    public function test_userserviceauthorised(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\userserviceauthorised::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Restrict the authorisation to a date in the future. The plugin installer does not set such a date, thus
        // this is a state which the integration does not want, even though it keeps working until that date.
        $DB->set_field(
            'external_services_users',
            'validuntil',
            time() + DAYSECS,
            ['externalserviceid' => $this->semcoservice->id, 'userid' => $this->semcouser->id]
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertFalse($check->supports_autofix());

        // Restrict the authorisation to a date in the past.
        $DB->set_field(
            'external_services_users',
            'validuntil',
            time() - DAYSECS,
            ['externalserviceid' => $this->semcoservice->id, 'userid' => $this->semcouser->id]
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertFalse($check->supports_autofix());

        // Remove the authorisation completely.
        $DB->delete_records('external_services_users', [
            'externalserviceid' => $this->semcoservice->id,
            'userid' => $this->semcouser->id,
        ]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
    }

    /**
     * Test the health check item for the SEMCO webservice token.
     */
    public function test_usertoken(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\usertoken::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Get the token which the plugin installer has created.
        $token = $DB->get_record(
            'external_tokens',
            ['externalserviceid' => $this->semcoservice->id, 'userid' => $this->semcouser->id],
            '*',
            MUST_EXIST
        );

        // Remove the token's creator.
        $DB->set_field('external_tokens', 'creatorid', 0, ['id' => $token->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The automatic fix sets the SEMCO webservice user as creator, just as the plugin installer does.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals($this->semcouser->id, $DB->get_field('external_tokens', 'creatorid', ['id' => $token->id]));

        // A missing creator cannot be fixed automatically as soon as it comes along with a finding which cannot.
        $DB->set_field('external_tokens', 'creatorid', 0, ['id' => $token->id]);
        $DB->set_field('external_tokens', 'validuntil', time() + DAYSECS, ['id' => $token->id]);
        $this->assertFalse($this->get_check($classname)->supports_autofix());
        $DB->set_field('external_tokens', 'creatorid', $token->creatorid, ['id' => $token->id]);
        $DB->set_field('external_tokens', 'validuntil', 0, ['id' => $token->id]);

        // Turn the token into an embedded token which Moodle may drop unexpectedly. The token still works at the
        // moment, thus this is a notice which cannot be fixed automatically, only on the tokens page.
        $DB->set_field('external_tokens', 'tokentype', EXTERNAL_TOKEN_EMBEDDED, ['id' => $token->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertEquals([$classname::FINDING_NOTPERMANENT], $check->get_finding_ids());
        $this->assertFalse($check->supports_autofix());
        $this->assertStringContainsString('tokens.php', $check->get_action_url()->out(false));
        $DB->set_field('external_tokens', 'tokentype', EXTERNAL_TOKEN_PERMANENT, ['id' => $token->id]);

        // Restrict the token to a date in the near future. SEMCO is going to lose its access soon, thus this is a
        // warning and not just a notice.
        $DB->set_field('external_tokens', 'validuntil', time() + DAYSECS, ['id' => $token->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // Restrict the token to a date in the far future. An expiry date is a legitimate way to harden the token, thus
        // this is not reported at all.
        $DB->set_field('external_tokens', 'validuntil', time() + YEARSECS, ['id' => $token->id]);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Restrict the token to a date in the past.
        $DB->set_field('external_tokens', 'validuntil', time() - DAYSECS, ['id' => $token->id]);
        $this->assertEquals(healthcheck::ERROR, $this->get_check($classname)->get_status());
        $DB->set_field('external_tokens', 'validuntil', 0, ['id' => $token->id]);

        // Remove the token completely.
        $DB->delete_records('external_tokens', ['id' => $token->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The automatic fix generates a new token, just as the plugin installer does, which is harmless. But it is only
        // half of the job, thus the follow-up must tell the admin that the new token has to be entered in SEMCO.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $followups = $check->get_autofix_followups();
        $this->assertCount(1, $followups);
        $this->assertStringContainsString('enter it in the Moodle connection settings of SEMCO', $followups[0]);
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // A check which is fine does not have anything to follow up.
        $this->assertSame([], $check->get_autofix_followups());

        // The new token must be a different, permanent and unrestricted token which has the SEMCO webservice user as
        // creator.
        $newtoken = $DB->get_record(
            'external_tokens',
            ['externalserviceid' => $this->semcoservice->id, 'userid' => $this->semcouser->id],
            '*',
            MUST_EXIST
        );
        $this->assertNotEquals($token->token, $newtoken->token);
        $this->assertEquals(EXTERNAL_TOKEN_PERMANENT, $newtoken->tokentype);
        $this->assertEmpty($newtoken->validuntil);
        $this->assertEmpty($newtoken->iprestriction);
        $this->assertEquals($this->semcouser->id, $newtoken->creatorid);

        // Without the SEMCO webservice user, this check cannot be assessed.
        $DB->set_field('user', 'deleted', 1, ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
    }

    /**
     * Test the health check item for the IP restriction of the SEMCO webservice token.
     */
    public function test_usertokeniprestriction(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\usertokeniprestriction::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Get the token and the service authorisation which the plugin installer has created.
        $token = $DB->get_record(
            'external_tokens',
            ['externalserviceid' => $this->semcoservice->id, 'userid' => $this->semcouser->id],
            '*',
            MUST_EXIST
        );
        $authorisation = $DB->get_record(
            'external_services_users',
            ['externalserviceid' => $this->semcoservice->id, 'userid' => $this->semcouser->id],
            '*',
            MUST_EXIST
        );

        // Restrict the token to an IP address. The plugin cannot know whether SEMCO is covered, thus this is a warning
        // and not an error. The finding must name the restriction and must tell the admin that he can mute the check.
        $DB->set_field('external_tokens', 'iprestriction', '192.0.2.10', ['id' => $token->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertEquals([$classname::FINDING_RESTRICTED], $check->get_finding_ids());
        $this->assertStringContainsString('192.0.2.10', $check->get_findings()[0]);
        $this->assertStringContainsString('mute this check', $check->get_findings()[0]);
        $this->assertStringContainsString('tokens.php', $check->get_action_url()->out(false));

        // The automatic fix removes the restriction, but this is not entirely harmless as the admin may have set it on
        // purpose.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEmpty($DB->get_field('external_tokens', 'iprestriction', ['id' => $token->id]));

        // The restriction comes from the admin and thus has to be escaped in the finding.
        $DB->set_field('external_tokens', 'iprestriction', '192.0.2.0/24, <b>x</b>', ['id' => $token->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertStringContainsString('192.0.2.0/24, &lt;b&gt;x&lt;/b&gt;', $check->get_findings()[0]);
        $this->assertStringNotContainsString('<b>', $check->get_findings()[0]);

        // The restriction of the service authorisation is none of this item's business, it is covered by an item of its
        // own. Neither is the restriction of another token which SEMCO does not use.
        $DB->set_field('external_tokens', 'iprestriction', null, ['id' => $token->id]);
        $DB->set_field('external_services_users', 'iprestriction', '192.0.2.10', ['id' => $authorisation->id]);
        $othertoken = clone $token;
        unset($othertoken->id);
        $othertoken->token = md5('semcohealthchecktestothertoken');
        $othertoken->privatetoken = null;
        $othertoken->iprestriction = '198.51.100.1';
        $othertoken->timecreated = $token->timecreated - DAYSECS;
        $othertokenid = $DB->insert_record('external_tokens', $othertoken);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());
        $DB->set_field('external_services_users', 'iprestriction', null, ['id' => $authorisation->id]);
        $DB->delete_records('external_tokens', ['id' => $othertokenid]);

        // Without a token, there is nothing which could be restricted and the check cannot be assessed. The finding
        // must point the admin to the item which reports the missing token.
        $DB->delete_records('external_tokens', ['id' => $token->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertEquals([$classname::FINDING_NOTOKEN], $check->get_finding_ids());
        $this->assertFalse($check->supports_autofix());
        $this->assertNull($check->get_action_url());

        // Without the SEMCO webservice user, this check cannot be assessed either.
        $DB->set_field('user', 'deleted', 1, ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertEquals([healthcheck::FINDING_NOUSERORSERVICE], $check->get_finding_ids());
    }

    /**
     * Test the health check item for the IP restriction of the SEMCO webservice user's service authorisation.
     */
    public function test_userserviceiprestriction(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\userserviceiprestriction::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Get the service authorisation and the token which the plugin installer has created.
        $authorisation = $DB->get_record(
            'external_services_users',
            ['externalserviceid' => $this->semcoservice->id, 'userid' => $this->semcouser->id],
            '*',
            MUST_EXIST
        );
        $token = $DB->get_record(
            'external_tokens',
            ['externalserviceid' => $this->semcoservice->id, 'userid' => $this->semcouser->id],
            '*',
            MUST_EXIST
        );

        // Restrict the authorisation to an IP address. The plugin cannot know whether SEMCO is covered, thus this is a
        // warning and not an error. The finding must name the restriction, escaped as it comes from the admin, and must
        // tell the admin that he can mute the check.
        $DB->set_field('external_services_users', 'iprestriction', '192.0.2.0/24, <b>x</b>', ['id' => $authorisation->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertEquals([$classname::FINDING_RESTRICTED], $check->get_finding_ids());
        $this->assertStringContainsString('192.0.2.0/24, &lt;b&gt;x&lt;/b&gt;', $check->get_findings()[0]);
        $this->assertStringNotContainsString('<b>', $check->get_findings()[0]);
        $this->assertStringContainsString('mute this check', $check->get_findings()[0]);
        $this->assertStringContainsString('service_users.php', $check->get_action_url()->out(false));

        // The automatic fix removes the restriction, but this is not entirely harmless as the admin may have set it on
        // purpose. The restriction of another authorised user is none of this item's business and must be left alone.
        $otheruser = $this->getDataGenerator()->create_user();
        $otherauthorisationid = $DB->insert_record('external_services_users', (object) [
            'externalserviceid' => $this->semcoservice->id,
            'userid' => $otheruser->id,
            'iprestriction' => '198.51.100.1',
            'timecreated' => time(),
        ]);
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEmpty($DB->get_field('external_services_users', 'iprestriction', ['id' => $authorisation->id]));
        $this->assertSame(
            '198.51.100.1',
            $DB->get_field('external_services_users', 'iprestriction', ['id' => $otherauthorisationid])
        );
        $DB->delete_records('external_services_users', ['id' => $otherauthorisationid]);

        // The restriction of the token is none of this item's business either, it is covered by an item of its own.
        $DB->set_field('external_tokens', 'iprestriction', '192.0.2.10', ['id' => $token->id]);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());
        $DB->set_field('external_tokens', 'iprestriction', null, ['id' => $token->id]);

        // As long as the service is not restricted to authorised users, Moodle does not evaluate the authorisation at
        // all, thus the check cannot be assessed. The finding must point the admin to the item which reports the
        // unrestricted service.
        $DB->set_field('external_services_users', 'iprestriction', '192.0.2.10', ['id' => $authorisation->id]);
        $DB->set_field('external_services', 'restrictedusers', 0, ['id' => $this->semcoservice->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertEquals([$classname::FINDING_UNRESTRICTED], $check->get_finding_ids());
        $this->assertFalse($check->supports_autofix());
        $this->assertNull($check->get_action_url());
        $DB->set_field('external_services', 'restrictedusers', 1, ['id' => $this->semcoservice->id]);

        // Without an authorisation, there is nothing which could be restricted and the check cannot be assessed. The
        // finding must point the admin to the item which reports the missing authorisation.
        $DB->delete_records('external_services_users', ['id' => $authorisation->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertEquals([$classname::FINDING_NOAUTHORISATION], $check->get_finding_ids());
        $this->assertFalse($check->supports_autofix());
        $this->assertNull($check->get_action_url());

        // Without the SEMCO webservice user, this check cannot be assessed either.
        $DB->set_field('user', 'deleted', 1, ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertEquals([healthcheck::FINDING_NOUSERORSERVICE], $check->get_finding_ids());
    }

    /**
     * Test the health check item for the registered capabilities.
     */
    public function test_capabilitiesregistered(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\capabilitiesregistered::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Unregister one of the plugin's capabilities, just as if the plugin files had been updated without running
        // the Moodle upgrade afterwards.
        $DB->delete_records('capabilities', ['name' => 'enrol/semco:viewhealthcheck']);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertTrue($DB->record_exists('capabilities', ['name' => 'enrol/semco:viewhealthcheck']));
    }

    /**
     * Test the health check item for the exclusiveness of the webservice capabilities.
     */
    public function test_capabilitiesexclusive(): void {
        $classname = \enrol_semco\healthcheck\check\capabilitiesexclusive::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Grant one of the webservice capabilities to another role in the system context.
        $studentroleid = $this->getDataGenerator()->create_role(['shortname' => 'semcohealthcheckteststudent']);
        assign_capability('enrol/semco:enrol', CAP_ALLOW, $studentroleid, $this->systemcontext->id, true);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // This can be fixed automatically, but it is not entirely harmless as somebody may have granted the
        // capability to the role on purpose.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());

        // A permission override in a course context must be reported as well.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        assign_capability('enrol/semco:unenrol', CAP_ALLOW, $studentroleid, $coursecontext->id, true);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // A second override of the same capability and role must not produce a second finding. It is counted within
        // the existing one instead, so that a capability which is overridden in many courses does not flood the modal.
        $secondcourse = $this->getDataGenerator()->create_course();
        assign_capability(
            'enrol/semco:unenrol',
            CAP_ALLOW,
            $studentroleid,
            \context_course::instance($secondcourse->id)->id,
            true
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // And the remaining finding must name the amount of affected contexts.
        $this->assertStringContainsString('2', $check->get_findings()[1]);

        // A grant to the SEMCO webservice role itself is not reported, and a grant of an unrelated capability to the
        // other role is out of scope. Both must survive the automatic fix.
        assign_capability('moodle/course:view', CAP_ALLOW, $studentroleid, $coursecontext->id, true);
        $this->assertCount(2, $this->get_check($classname)->get_findings());

        // The automatic fix revokes the grant from the role definition as well as every override, and nothing else.
        $check = $this->get_check($classname);
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertFalse($this->grant_exists('enrol/semco:enrol', $studentroleid, $this->systemcontext->id));
        $this->assertFalse($this->grant_exists('enrol/semco:unenrol', $studentroleid, $coursecontext->id));
        $this->assertFalse(
            $this->grant_exists('enrol/semco:unenrol', $studentroleid, \context_course::instance($secondcourse->id)->id)
        );
        $this->assertTrue($this->grant_exists('moodle/course:view', $studentroleid, $coursecontext->id));
        $this->assertTrue($this->grant_exists('enrol/semco:enrol', $this->semcorole->id, $this->systemcontext->id));
    }

    /**
     * Check whether a capability is granted to a role in a context.
     *
     * @param string $capability The capability.
     * @param int $roleid The role id.
     * @param int $contextid The context id.
     * @return bool
     */
    private function grant_exists(string $capability, int $roleid, int $contextid): bool {
        global $DB;

        return $DB->record_exists('role_capabilities', [
            'capability' => $capability,
            'roleid' => $roleid,
            'contextid' => $contextid,
            'permission' => CAP_ALLOW,
        ]);
    }

    /**
     * Test the health check item for the external service functions.
     */
    public function test_externalservicefunctions(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\externalservicefunctions::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Remove one of the plugin's own functions from the service.
        $DB->delete_records('external_services_functions', [
            'externalserviceid' => $this->semcoservice->id,
            'functionname' => 'enrol_semco_enrol_user',
        ]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // Remember how many functions the service offered before the automatic fix.
        $functioncountbefore = $DB->count_records(
            'external_services_functions',
            ['externalserviceid' => $this->semcoservice->id]
        );

        // And fix it automatically.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // The automatic fix must have added the missing function back to the service without losing the other ones.
        $this->assertEquals(
            $functioncountbefore + 1,
            $DB->count_records('external_services_functions', ['externalserviceid' => $this->semcoservice->id])
        );
        $this->assertTrue($DB->record_exists('external_services_functions', [
            'externalserviceid' => $this->semcoservice->id,
            'functionname' => 'enrol_semco_enrol_user',
        ]));

        // Unregister one of the plugin's own functions in Moodle, just as if the plugin files had been updated without
        // running the Moodle upgrade afterwards. The service still lists the function, thus this is the only finding.
        // There is no page where an admin could register the function himself, thus no URL is offered.
        $DB->delete_records('external_functions', ['name' => 'enrol_semco_enrol_user', 'component' => 'enrol_semco']);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertEquals([$classname::FINDING_NOTREGISTERED], $check->get_finding_ids());
        $this->assertStringContainsString('enrol_semco_enrol_user', $check->get_findings()[0]);
        $this->assertNull($check->get_action_url());

        // The automatic fix registers the function again, just as the plugin installer does.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertTrue($DB->record_exists('external_functions', [
            'name' => 'enrol_semco_enrol_user',
            'component' => 'enrol_semco',
        ]));

        // If the plugin does not declare any function at all, there is nothing to check. This state cannot be brought
        // about with a real db/services.php, thus the item is subclassed to simulate an empty definition file.
        healthcheck::reset_caches();
        $check = new class extends \enrol_semco\healthcheck\check\externalservicefunctions {
            /**
             * Simulate a definition file without any function or service.
             *
             * @return array
             */
            protected function get_service_definitions(): array {
                return [[], []];
            }
        };
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertEquals([$classname::FINDING_NODEFINITIONS], $check->get_finding_ids());
        $this->assertFalse($check->supports_autofix());
        $this->assertNull($check->get_action_url());

        // If the external service does not exist, this check cannot be assessed.
        $DB->delete_records('external_services', ['id' => $this->semcoservice->id]);
        $this->assertEquals(healthcheck::NA, $this->get_check($classname)->get_status());
    }

    /**
     * Test the health check item for the SEMCO user profile field category.
     */
    public function test_profilefieldcategory(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\profilefieldcategory::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Rename the SEMCO user profile field category.
        $DB->set_field(
            'user_info_category',
            'name',
            'Something else',
            ['name' => ENROL_SEMCO_USERFIELDCATEGORY]
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The automatic fix creates the category again, just as the plugin installer does, which is harmless.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // The SEMCO user profile fields still sit in the category which has been renamed. This is reported by the items
        // of the fields as a cosmetic issue, and their automatic fix moves the fields into the new category.
        $fieldcheck = $this->get_check(\enrol_semco\healthcheck\check\profilefield_userid::class);
        $this->assertEquals(healthcheck::NOTICE, $fieldcheck->get_status());
        $this->assertTrue($fieldcheck->supports_autofix());
        $this->assertFalse($fieldcheck->is_autofix_risky());
        $fieldcheck->autofix();
        $this->assertEquals(healthcheck::OK, $fieldcheck->get_status());
        $this->assertEquals(
            $DB->get_field('user_info_category', 'id', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]),
            $DB->get_field('user_info_field', 'categoryid', ['shortname' => ENROL_SEMCO_USERFIELD1NAME])
        );
    }

    /**
     * Data provider for test_profilefield.
     *
     * @return array
     */
    public static function profilefield_provider(): array {
        return [
            'SEMCO user ID' => [
                'classname' => \enrol_semco\healthcheck\check\profilefield_userid::class,
                'shortname' => 'semco_userid',
                'unique' => true,
            ],
            'SEMCO user company' => [
                'classname' => \enrol_semco\healthcheck\check\profilefield_usercompany::class,
                'shortname' => 'semco_usercompany',
                'unique' => false,
            ],
            'SEMCO user birthday' => [
                'classname' => \enrol_semco\healthcheck\check\profilefield_userbirthday::class,
                'shortname' => 'semco_userbirthday',
                'unique' => false,
            ],
            'SEMCO user place of birth' => [
                'classname' => \enrol_semco\healthcheck\check\profilefield_userplaceofbirth::class,
                'shortname' => 'semco_userplaceofbirth',
                'unique' => false,
            ],
            'SEMCO tenant shortname' => [
                'classname' => \enrol_semco\healthcheck\check\profilefield_branchtoken::class,
                'shortname' => 'semco_branchtoken',
                'unique' => false,
            ],
        ];
    }

    /**
     * Test the health check items for the SEMCO user profile fields.
     *
     * @param string $classname The class name of the health check item which is under test.
     * @param string $shortname The shortname of the user profile field which the health check item verifies.
     * @param bool $unique Whether the user profile field is expected to force unique values.
     * @dataProvider profilefield_provider
     */
    public function test_profilefield($classname, $shortname, $unique): void {
        global $DB;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // The item's summary must name the field by its shortname, so that an administrator is able to find it.
        $this->assertStringContainsString($shortname, $this->get_check($classname)->get_summary());

        // Get the user profile field which the plugin installer has created. Only the SEMCO user ID field forces unique
        // values, the other fields do not as several users legitimately share the same value.
        $field = $DB->get_record('user_info_field', ['shortname' => $shortname], '*', MUST_EXIST);
        $this->assertEquals($unique, (bool) $field->forceunique);

        // Make the field visible and unlock it.
        $DB->set_field('user_info_field', 'visible', 1, ['id' => $field->id]);
        $DB->set_field('user_info_field', 'locked', 0, ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());
        $this->assertFalse($check->supports_autofix());
        $DB->set_field('user_info_field', 'visible', $field->visible, ['id' => $field->id]);
        $DB->set_field('user_info_field', 'locked', $field->locked, ['id' => $field->id]);

        // Change the field's data type. A field of another type may not accept the data which SEMCO writes, thus this is
        // a warning. The finding must name the expected and the found data type.
        $DB->set_field('user_info_field', 'datatype', 'textarea', ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertEquals([$classname::FINDING_DATATYPE], $check->get_finding_ids());
        $this->assertStringContainsString('textarea', $check->get_findings()[0]);
        $this->assertFalse($check->supports_autofix());
        $DB->set_field('user_info_field', 'datatype', $field->datatype, ['id' => $field->id]);

        // Flip the field's uniqueness.
        $DB->set_field('user_info_field', 'forceunique', $unique ? 0 : 1, ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        if ($unique) {
            // Dropping the uniqueness of the SEMCO user ID field is a warning as SEMCO relies on the field to identify
            // a user unambiguously. It is not fixed automatically as the field may hold duplicate values by now.
            $this->assertEquals([$classname::FINDING_NOTUNIQUE], $check->get_finding_ids());
            $this->assertFalse($check->supports_autofix());
            $DB->set_field('user_info_field', 'forceunique', $field->forceunique, ['id' => $field->id]);
        } else {
            // Enforcing the uniqueness of any other field is a warning as Moodle refuses to save the profile form of a
            // user as soon as another user has the same value. Dropping the constraint again is harmless, thus it is
            // fixed automatically.
            $this->assertEquals([$classname::FINDING_UNIQUE], $check->get_finding_ids());
            $this->assertTrue($check->supports_autofix());
            $this->assertFalse($check->is_autofix_risky());
            $check->autofix();
            $this->assertEquals(healthcheck::OK, $check->get_status());
            $this->assertEquals(0, $DB->get_field('user_info_field', 'forceunique', ['id' => $field->id]));
        }

        // Shrink the field's maximum length. SEMCO's data does not fit into the field anymore, thus this is a warning.
        $DB->set_field('user_info_field', 'param2', 1, ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // Enlarge the field's maximum length. SEMCO's data still fits, thus this is a cosmetic issue only.
        $DB->set_field('user_info_field', 'param2', $field->param2 + 1, ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $DB->set_field('user_info_field', 'param2', $field->param2, ['id' => $field->id]);

        // Change the field's display size, which is a cosmetic issue only.
        $DB->set_field('user_info_field', 'param1', $field->param1 + 1, ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $DB->set_field('user_info_field', 'param1', $field->param1, ['id' => $field->id]);

        // Move the field into another category, which is a cosmetic issue only.
        $othercategoryid = $DB->insert_record('user_info_category', ['name' => 'Other category', 'sortorder' => 99]);
        $DB->set_field('user_info_field', 'categoryid', $othercategoryid, ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // A cosmetic issue must not downgrade a warning which another aspect of the field has raised.
        $DB->set_field('user_info_field', 'visible', 1, ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());
        $DB->set_field('user_info_field', 'visible', $field->visible, ['id' => $field->id]);
        $DB->set_field('user_info_field', 'categoryid', $field->categoryid, ['id' => $field->id]);

        // Delete the field completely. Moodle core silently drops the data which SEMCO writes into a field which does
        // not exist, but the webservice call itself succeeds, thus this is a warning and not an error.
        $DB->delete_records('user_info_field', ['id' => $field->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The automatic fix creates the field again, just as the plugin installer does, which is harmless. The field
        // must be configured exactly as the item expects it, thus the item is fine afterwards.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $newfield = $DB->get_record('user_info_field', ['shortname' => $shortname], '*', MUST_EXIST);
        $this->assertEquals($field->name, $newfield->name);
        $this->assertEquals($field->categoryid, $newfield->categoryid);
        $this->assertEquals($unique, (bool) $newfield->forceunique);

        // If the category is gone along with the field, the automatic fix creates both.
        $DB->delete_records('user_info_field', ['id' => $newfield->id]);
        $DB->delete_records('user_info_category', ['id' => $field->categoryid]);
        $check = $this->get_check($classname);
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(
            healthcheck::OK,
            $this->get_check(\enrol_semco\healthcheck\check\profilefieldcategory::class)->get_status()
        );
    }

    /**
     * Test that the health check item for the REST capability reports a failed ad-hoc task as broken installation.
     */
    public function test_rolecapabilityrest_with_failed_task(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\rolecapabilityrest::class;

        // Revoke the REST capability and queue the ad-hoc task which is supposed to assign it.
        unassign_capability('webservice/rest:use', $this->semcorole->id, $this->systemcontext->id);
        \core\task\manager::queue_adhoc_task(new \enrol_semco\task\set_webservice_capability());

        // Let the task fail.
        $DB->set_field(
            'task_adhoc',
            'faildelay',
            60,
            ['classname' => '\\' . \enrol_semco\task\set_webservice_capability::class]
        );

        // The situation is not going to resolve itself, thus this is an error and not a notice anymore.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::ERROR, $check->get_status());
        $this->assertCount(2, $check->get_findings());
    }

    /**
     * Test the health check item for the profile data of the SEMCO webservice user.
     */
    public function test_userprofile(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\userprofile::class;

        // The profile data which the plugin installer has set is fine.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A missing name part is just a notice as it does not break anything.
        $DB->set_field('user', 'firstname', '', ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // A name part which deviates from the installer default is reported as well.
        $DB->set_field('user', 'lastname', 'Something else', ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // And the finding must name the current as well as the expected value.
        $this->assertStringContainsString('Something else', $check->get_findings()[1]);
        $this->assertStringContainsString(
            get_string('installer_userlastname', 'enrol_semco'),
            $check->get_findings()[1]
        );

        // An email address which deviates from the one which the plugin installer has set is a notice as well.
        $DB->set_field('user', 'email', 'someone@example.com', ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(3, $check->get_findings());
        $this->assertStringNotContainsString('[[', $check->get_findings()[0]);
        $this->assertStringContainsString('someone@example.com', $check->get_findings()[0]);

        // A missing email address is a warning as it breaks several Moodle core code paths. It must not be
        // downgraded to a notice by the name findings which are collected afterwards.
        $DB->set_field('user', 'email', '', ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(3, $check->get_findings());

        // All of them are fixed automatically by restoring the profile data which the plugin installer has set.
        $this->assertTrue($check->supports_autofix());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(
            get_string('installer_userfirstname', 'enrol_semco'),
            $DB->get_field('user', 'firstname', ['id' => $this->semcouser->id])
        );
        $this->assertEquals(
            get_string('installer_userlastname', 'enrol_semco'),
            $DB->get_field('user', 'lastname', ['id' => $this->semcouser->id])
        );
        $this->assertNotEmpty($DB->get_field('user', 'email', ['id' => $this->semcouser->id]));

        // Without the SEMCO webservice user, this check cannot be assessed.
        $DB->set_field('user', 'deleted', 1, ['id' => $this->semcouser->id]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertCount(1, $check->get_findings());
    }

    /**
     * Test that the health check items for the user profile fields also cover the required and signup settings.
     */
    public function test_profilefield_required_and_signup(): void {
        global $DB;

        $classname = \enrol_semco\healthcheck\check\profilefield_userid::class;

        // The installation is intact.
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Make the field required and show it on the signup page.
        $DB->set_field('user_info_field', 'required', 1, ['shortname' => 'semco_userid']);
        $DB->set_field('user_info_field', 'signup', 1, ['shortname' => 'semco_userid']);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());
    }

    /**
     * Test the health check item for unique email addresses.
     */
    public function test_allowaccountssameemail(): void {
        $classname = \enrol_semco\healthcheck\check\allowaccountssameemail::class;

        // With unique email addresses, everything is fine.
        set_config('allowaccountssameemail', 0);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Otherwise, the item raises the admin's awareness.
        set_config('allowaccountssameemail', 1);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());

        // The automatic fix enforces unique email addresses. It is not entirely harmless, as a SEMCO setup with
        // multiple tenants needs this setting to stay as it is.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEmpty(get_config('core', 'allowaccountssameemail'));
    }

    /**
     * Test the health check item for the Moodle messaging system.
     */
    public function test_messaging(): void {
        $classname = \enrol_semco\healthcheck\check\messaging::class;

        // With a disabled messaging system, everything is fine.
        set_config('messaging', 0);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Otherwise, the item raises the admin's awareness.
        set_config('messaging', 1);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());

        // The automatic fix disables the messaging system. It is not entirely harmless, as it takes a feature away
        // from all users of the Moodle instance.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEmpty(get_config('core', 'messaging'));
    }

    /**
     * Test the health check item for the Moodle course completion notification.
     */
    public function test_coursecompletedmessage(): void {
        $classname = \enrol_semco\healthcheck\check\coursecompletedmessage::class;

        // With a disabled notification, everything is fine.
        set_config('moodle_coursecompleted_disable', 1, 'message');
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Otherwise, the item raises the admin's awareness.
        unset_config('moodle_coursecompleted_disable', 'message');
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());

        // The automatic fix disables the notification, which is harmless.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(1, get_config('message', 'moodle_coursecompleted_disable'));
    }

    /**
     * Test the health check item for the locked user profile fields.
     */
    public function test_manualauthlockedfields(): void {
        $classname = \enrol_semco\healthcheck\check\manualauthlockedfields::class;

        // A stock Moodle does not lock any of the three profile fields which SEMCO owns.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(3, $check->get_findings());

        // Locking two of them leaves one finding.
        set_config('field_lock_firstname', 'locked', 'auth_manual');
        set_config('field_lock_lastname', 'locked', 'auth_manual');
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // Locking a field only if it is empty is not enough as SEMCO always fills these fields.
        set_config('field_lock_email', 'unlockedifempty', 'auth_manual');
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The automatic fix locks the remaining field and leaves the other ones alone. It is not entirely harmless, as
        // it affects all users with manual authentication and not only the users which SEMCO has created.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals('locked', get_config('auth_manual', 'field_lock_email'));
        $this->assertEquals('locked', get_config('auth_manual', 'field_lock_firstname'));

        // The automatic fix locks all three fields at once as well.
        foreach (['firstname', 'lastname', 'email'] as $field) {
            unset_config('field_lock_' . $field, 'auth_manual');
        }
        $check = $this->get_check($classname);
        $this->assertCount(3, $check->get_findings());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
    }

    /**
     * Test the health check item for self enrolment.
     */
    public function test_selfenrolment(): void {
        global $CFG, $DB;

        $classname = \enrol_semco\healthcheck\check\selfenrolment::class;

        // Without any course which offers self enrolment, everything is fine.
        \core\plugininfo\enrol::enable_plugin('self', true);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A course with an active self enrolment instance but without a SEMCO enrolment is out of scope.
        $othercourse = $this->getDataGenerator()->create_course();
        $selfplugin = enrol_get_plugin('self');
        $instancefields = ['status' => ENROL_INSTANCE_ENABLED];
        $selfplugin->add_instance($othercourse, $instancefields);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Add a course which holds a SEMCO enrolment and an active self enrolment instance at the same time.
        $course = $this->getDataGenerator()->create_course();
        enrol_get_plugin('semco')->add_instance($course);
        $instanceid = $selfplugin->add_instance($course, $instancefields);

        // Every authenticated user can get into this course without paying for it via SEMCO, thus this is a warning.
        // The finding names the course, so that the admin knows where to go.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString($course->fullname . ' (' . $course->shortname . ')', $check->get_findings()[0]);
        $this->assertStringNotContainsString($othercourse->fullname, $check->get_findings()[0]);
        $this->assertFalse($check->supports_autofix());

        // An instance which is guarded by an enrolment key is reported with its own finding. The key is a hurdle, thus
        // this is just a notice.
        $DB->set_field('enrol', 'password', 'secret', ['id' => $instanceid]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString('enrolment key', $check->get_findings()[0]);
        $this->assertStringContainsString($course->fullname, $check->get_findings()[0]);
        $DB->set_field('enrol', 'password', '', ['id' => $instanceid]);

        // The courses are named as a HTML list. The list is cut off after ten courses to keep the finding readable,
        // the rest is just counted in the last item of the list. The courses are sorted by their full name, thus
        // 'Self enrolment course 1' to '... 8' (with '... 10' and '... 11' in between) are named, while '... 9' and
        // the 'Test course' from above are the two which are just counted.
        $morecourses = [];
        for ($i = 1; $i <= 11; $i++) {
            $morecourse = $this->getDataGenerator()->create_course(['fullname' => 'Self enrolment course ' . $i]);
            enrol_get_plugin('semco')->add_instance($morecourse);
            $selfplugin->add_instance($morecourse, $instancefields);
            $morecourses[] = $morecourse;
        }
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $finding = $check->get_findings()[0];
        $this->assertStringContainsString('12 of 12 course(s)', $finding);
        $this->assertStringContainsString('Self enrolment course 1 (', $finding);
        $this->assertStringContainsString('Self enrolment course 8 (', $finding);
        $this->assertStringNotContainsString('Self enrolment course 9 (', $finding);
        $this->assertStringNotContainsString($course->fullname, $finding);
        $this->assertMatchesRegularExpression('#<li>and 2 more</li>\s*</ul>$#', $finding);
        $this->assertEquals(11, substr_count($finding, '<li>'));
        foreach ($morecourses as $morecourse) {
            $instance = $DB->get_record('enrol', ['courseid' => $morecourse->id, 'enrol' => 'semco'], '*', MUST_EXIST);
            enrol_get_plugin('semco')->delete_instance($instance);
        }

        // If the role of the authenticated users is not allowed to enrol itself, nobody can use the instance.
        assign_capability(
            'enrol/self:enrolself',
            CAP_PREVENT,
            $CFG->defaultuserroleid,
            \context_course::instance($course->id)->id,
            true
        );
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());
        unassign_capability('enrol/self:enrolself', $CFG->defaultuserroleid, \context_course::instance($course->id)->id);

        // If the self enrolment method is disabled altogether, nobody can enrol himself and everything is fine again.
        \core\plugininfo\enrol::enable_plugin('self', false);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());
    }

    /**
     * Test the health check item for the participant visibility of the enrolment role.
     */
    public function test_enrolmentroleviewparticipants(): void {
        $classname = \enrol_semco\healthcheck\check\enrolmentroleviewparticipants::class;

        // A stock Moodle grants this capability to the student role which is the default SEMCO enrolment role.
        $enrolmentroleid = get_config('enrol_semco', 'role');
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_ALLOW,
            $enrolmentroleid,
            $this->systemcontext->id,
            true
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The automatic fix removes the capability from the role definition. It is not entirely harmless, as the role
        // is not owned by this plugin and is most likely used in other courses as well.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // After preventing the capability, everything is fine as well.
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_PREVENT,
            $enrolmentroleid,
            $this->systemcontext->id,
            true
        );
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A permission override in a course which holds a SEMCO enrolment is reported as well, even though the role
        // definition itself does not grant the capability anymore.
        $course = $this->getDataGenerator()->create_course();
        enrol_get_plugin('semco')->add_instance($course);
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_ALLOW,
            $enrolmentroleid,
            \context_course::instance($course->id)->id,
            true
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString($course->fullname . ' (' . $course->shortname . ')', $check->get_findings()[0]);

        // A course without a SEMCO enrolment is out of scope.
        $othercourse = $this->getDataGenerator()->create_course();
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_ALLOW,
            $enrolmentroleid,
            \context_course::instance($othercourse->id)->id,
            true
        );
        $check = $this->get_check($classname);
        $this->assertCount(1, $check->get_findings());
        $this->assertStringNotContainsString($othercourse->fullname, $check->get_findings()[0]);

        // A permission override in the course itself beats the override of the category above it, thus a course which
        // revokes the capability again is not reported anymore.
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_PREVENT,
            $enrolmentroleid,
            \context_course::instance($course->id)->id,
            true
        );
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A permission override in the category above the course is reported as well, as it applies within the
        // course just like an override in the course itself.
        $category = $this->getDataGenerator()->create_category();
        $categorycourse = $this->getDataGenerator()->create_course(['category' => $category->id]);
        enrol_get_plugin('semco')->add_instance($categorycourse);
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_ALLOW,
            $enrolmentroleid,
            \context_coursecat::instance($category->id)->id,
            true
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // If there is not any enrolment role configured, this check cannot be assessed.
        set_config('role', '', 'enrol_semco');
        $this->assertEquals(healthcheck::NA, $this->get_check($classname)->get_status());
    }

    /**
     * Test that the detection of the companion plugin can be asked for the real state of the installation.
     *
     * The health check items which use the code or the database tables of local_recompletion must not rely on the
     * simulated state which automated tests can establish, as requiring a file of a plugin which is only simulated to
     * be there would end in a fatal error.
     */
    public function test_local_recompletion_detection_only_trusts_a_simulated_absence_by_default(): void {
        global $CFG;

        // Determine the real state of this installation once, without any override being set.
        unset($CFG->localrecompletionnotinstalled);
        unset($CFG->localrecompletionforceinstalled);
        $realstate = enrol_semco_check_local_recompletion();

        // A simulated absence is honoured by every caller, as believing that the plugin is gone can never make a
        // caller touch something which is not there.
        $CFG->localrecompletionnotinstalled = true;
        $this->assertFalse(enrol_semco_check_local_recompletion());
        $this->assertFalse(enrol_semco_check_local_recompletion(true));

        // A simulated presence is a different story: Only a caller which reports the state gets it, every other
        // caller gets the real state and is thus protected against using code which is not there.
        unset($CFG->localrecompletionnotinstalled);
        $CFG->localrecompletionforceinstalled = true;
        $this->assertEquals($realstate, enrol_semco_check_local_recompletion());
        $this->assertTrue(enrol_semco_check_local_recompletion(true));
    }

    /**
     * Test that the health check items which rely on local_recompletion degrade gracefully.
     */
    public function test_recompletion_items_without_local_recompletion(): void {
        global $CFG;

        // Simulate that local_recompletion is not installed. This simulation is honoured no matter whether the plugin
        // is really there, thus this test covers the N/A branches of these items on every installation.
        $CFG->localrecompletionnotinstalled = true;

        // All of them must report that they cannot be assessed.
        foreach (
            [
                \enrol_semco\healthcheck\check\recompletionondemand::class,
                \enrol_semco\healthcheck\check\recompletionnotify::class,
                \enrol_semco\healthcheck\check\recompletionactivities::class,
                \enrol_semco\healthcheck\check\recompletionresetmycompletion::class,
                \enrol_semco\healthcheck\check\recompletionmanage::class,
            ] as $classname
        ) {
            $check = $this->get_check($classname);
            $this->assertEquals(healthcheck::NA, $check->get_status());
            $this->assertCount(1, $check->get_findings());
            $this->assertFalse($check->supports_autofix());
        }
    }

    /**
     * Test that the health check items which rely on local_recompletion do not fail if the plugin is only claimed to
     * be installed.
     *
     * The plugin's helper function enrol_semco_check_local_recompletion() can be told to report local_recompletion as
     * installed even if it is not, which the plugin's own acceptance tests make use of. A health check item must never
     * be the reason for a fatal error, thus the items which use the code of the companion plugin ask the helper
     * function for the real state of the installation and ignore such a simulation.
     */
    public function test_recompletion_items_with_simulated_local_recompletion(): void {
        global $CFG;

        // Skip this test if local_recompletion is installed as there is nothing to simulate then.
        if (enrol_semco_check_local_recompletion() == true) {
            $this->markTestSkipped('local_recompletion is installed, the simulation cannot be tested.');
        }

        // Simulate that local_recompletion is installed even though it is not.
        $CFG->localrecompletionforceinstalled = true;

        // All of them must report that they cannot be assessed instead of running into a fatal error.
        foreach (
            [
                \enrol_semco\healthcheck\check\recompletionondemand::class,
                \enrol_semco\healthcheck\check\recompletionnotify::class,
                \enrol_semco\healthcheck\check\recompletionactivities::class,
                \enrol_semco\healthcheck\check\recompletionresetmycompletion::class,
                \enrol_semco\healthcheck\check\recompletionmanage::class,
            ] as $classname
        ) {
            $this->assertEquals(healthcheck::NA, $this->get_check($classname)->get_status());
        }

        // And the manager must be able to evaluate all health check items without a fatal error.
        $this->assertNotEmpty(manager::get_healthchecks());
    }

    /**
     * Test the health check item for the companion plugin local_recompletion.
     */
    public function test_recompletioninstalled(): void {
        global $CFG;

        $classname = \enrol_semco\healthcheck\check\recompletioninstalled::class;

        // If local_recompletion is installed, everything is fine.
        if (enrol_semco_check_local_recompletion() == true) {
            $check = $this->get_check($classname);
            $this->assertEquals(healthcheck::OK, $check->get_status());
            $this->assertCount(0, $check->get_findings());
            $this->assertFalse($check->supports_autofix());
        }

        // Simulate that local_recompletion is not installed. This item is one of the two callers which accept the
        // simulated state, as it does nothing but report the state of the companion plugin.
        $CFG->localrecompletionnotinstalled = true;

        // The item must report a warning and not an error as the SEMCO integration keeps working without the plugin.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertFalse($check->supports_autofix());
    }

    /**
     * Test the health check item for the course recompletion type.
     *
     * This test needs local_recompletion to be installed. The behaviour without that plugin is covered by
     * test_recompletion_items_without_local_recompletion().
     */
    public function test_recompletionondemand(): void {
        global $DB;

        // Skip this test if local_recompletion is not installed as the item cannot be assessed then.
        if (enrol_semco_check_local_recompletion() != true) {
            $this->markTestSkipped('local_recompletion is not installed, the item cannot be assessed.');
        }

        $classname = \enrol_semco\healthcheck\check\recompletionondemand::class;

        // The site-wide default of a stock local_recompletion is not set to 'On demand', which is a warning on its
        // own, even without any course which holds a SEMCO enrolment.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringNotContainsString('[[', $check->get_findings()[0]);

        // The automatic fix sets the site-wide default. This is harmless, as the default only prefills the settings of
        // the courses which get configured from now on. Without any affected course, everything is fine afterwards.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(
            \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND,
            get_config('local_recompletion', 'recompletiontype')
        );

        // A course which is created while the site-wide default is in place inherits it, thus a fresh course with a
        // SEMCO enrolment is prepared right away. This is what the site-wide default is good for.
        $course = $this->getDataGenerator()->create_course();
        enrol_get_plugin('semco')->add_instance($course);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A course which is not set to the 'On demand' recompletion type is reported as a warning, as every SEMCO
        // webservice call which wants to reset a course completion in it fails.
        $DB->set_field(
            'local_recompletion_config',
            'value',
            \local_recompletion_recompletion_form::RECOMPLETION_TYPE_DISABLED,
            ['course' => $course->id, 'name' => 'recompletiontype']
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());

        // The finding must name the amount of affected courses out of the total amount of courses which hold SEMCO
        // enrolments as well as the courses themselves.
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString('1 of 1', $check->get_findings()[0]);
        $this->assertStringContainsString($course->fullname . ' (' . $course->shortname . ')', $check->get_findings()[0]);

        // The automatic fix sets the course to the 'On demand' recompletion type. It is not entirely harmless, as the
        // course has been configured by its teacher.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(
            \local_recompletion_recompletion_form::RECOMPLETION_TYPE_ONDEMAND,
            $DB->get_field('local_recompletion_config', 'value', ['course' => $course->id, 'name' => 'recompletiontype'])
        );

        // The automatic fix also handles a course which does not hold the setting at all, and it leaves the courses
        // which do not hold a SEMCO enrolment alone.
        $othercourse = $this->getDataGenerator()->create_course();
        $DB->delete_records('local_recompletion_config', ['course' => $course->id, 'name' => 'recompletiontype']);
        $DB->delete_records('local_recompletion_config', ['course' => $othercourse->id, 'name' => 'recompletiontype']);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertTrue($DB->record_exists('local_recompletion_config', [
            'course' => $course->id,
            'name' => 'recompletiontype',
        ]));
        $this->assertFalse($DB->record_exists('local_recompletion_config', [
            'course' => $othercourse->id,
            'name' => 'recompletiontype',
        ]));
    }

    /**
     * Test the health check item for the course recompletion notification.
     *
     * This test needs local_recompletion to be installed. The behaviour without that plugin is covered by
     * test_recompletion_items_without_local_recompletion().
     */
    public function test_recompletionnotify(): void {
        global $DB;

        // Skip this test if local_recompletion is not installed as the item cannot be assessed then.
        if (enrol_semco_check_local_recompletion() != true) {
            $this->markTestSkipped('local_recompletion is not installed, the item cannot be assessed.');
        }

        $classname = \enrol_semco\healthcheck\check\recompletionnotify::class;

        // A stock local_recompletion does not notify anyone, thus everything is fine.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertFalse($check->supports_autofix());

        // A site-wide default which notifies the users is reported.
        set_config('recompletionnotify', 'completed', 'local_recompletion');
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The automatic fix disables the notification in the site-wide default, which is harmless.
        $this->assertTrue($check->supports_autofix());
        $this->assertFalse($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertSame('', get_config('local_recompletion', 'recompletionnotify'));

        // A course which holds a SEMCO enrolment and which notifies its users on its own is reported as well, even
        // though the site-wide default does not notify anyone.
        $course = $this->getDataGenerator()->create_course();
        enrol_get_plugin('semco')->add_instance($course);
        $DB->insert_record('local_recompletion_config', (object) [
            'course' => $course->id,
            'name' => 'recompletionnotify',
            'value' => 'completed',
        ]);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());

        // The finding must name the amount of affected courses as well as the courses themselves.
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString('1 of 1', $check->get_findings()[0]);
        $this->assertStringContainsString($course->fullname . ' (' . $course->shortname . ')', $check->get_findings()[0]);

        // A course without a SEMCO enrolment is out of scope.
        $othercourse = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_recompletion_config', (object) [
            'course' => $othercourse->id,
            'name' => 'recompletionnotify',
            'value' => 'completed',
        ]);
        $check = $this->get_check($classname);
        $this->assertCount(1, $check->get_findings());

        // A course which holds a SEMCO enrolment and which stores a value of 0 is not reported either: This is what
        // local_recompletion's course settings page writes for a setting which the form did not submit, and
        // local_recompletion treats it as 'disabled' just like the empty value.
        $zerocourse = $this->getDataGenerator()->create_course();
        enrol_get_plugin('semco')->add_instance($zerocourse);
        $DB->insert_record('local_recompletion_config', (object) [
            'course' => $zerocourse->id,
            'name' => 'recompletionnotify',
            'value' => '0',
        ]);
        $check = $this->get_check($classname);
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString('1 of 2', $check->get_findings()[0]);
        $this->assertStringNotContainsString($zerocourse->fullname, $check->get_findings()[0]);

        // The automatic fix disables the notification in the course which holds a SEMCO enrolment and leaves the other
        // course alone. It is not entirely harmless, as the course has been configured by its teacher.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertSame('', $DB->get_field('local_recompletion_config', 'value', [
            'course' => $course->id,
            'name' => 'recompletionnotify',
        ]));
        $this->assertSame('completed', $DB->get_field('local_recompletion_config', 'value', [
            'course' => $othercourse->id,
            'name' => 'recompletionnotify',
        ]));
    }

    /**
     * Test the health check item for the course recompletion activity reset.
     *
     * This test needs local_recompletion to be installed. The behaviour without that plugin is covered by
     * test_recompletion_items_without_local_recompletion().
     */
    public function test_recompletionactivities(): void {
        global $CFG, $DB;

        // Skip this test if local_recompletion is not installed as the item cannot be assessed then.
        if (enrol_semco_check_local_recompletion() != true) {
            $this->markTestSkipped('local_recompletion is not installed, the item cannot be assessed.');
        }

        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        $classname = \enrol_semco\healthcheck\check\recompletionactivities::class;

        // Collect the activity types which offer a site-wide setting.
        $settingnames = [];
        foreach (local_recompletion_get_supported_plugins() as $component) {
            $settingname = preg_replace('/^mod_/', '', $component);
            if (get_config('local_recompletion', $settingname) !== false) {
                $settingnames[] = $settingname;
            }
        }
        $this->assertNotEmpty($settingnames, 'local_recompletion does not offer any site-wide activity type setting.');

        // A stock local_recompletion does not reset any activity type at all, which renders it ineffective.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertFalse($check->supports_autofix());

        // As soon as one activity type is reset, the remaining ones are only a notice.
        set_config($settingnames[0], LOCAL_RECOMPLETION_DELETE, 'local_recompletion');
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());

        // The finding must name the amount of the affected activity types, which is the amount of assessable types
        // minus the one which has just been set to be reset, and list every single one of them.
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString((count($settingnames) - 1) . ' activity type(s)', $check->get_findings()[0]);
        $this->assertEquals(count($settingnames) - 1, substr_count($check->get_findings()[0], '<li>'));

        // With every activity type being reset, everything is fine.
        foreach ($settingnames as $settingname) {
            set_config($settingname, LOCAL_RECOMPLETION_DELETE, 'local_recompletion');
        }
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A course with SEMCO enrolments which does not hold any recompletion setting does not reset any activity
        // type, which is less than the site-wide settings ask for.
        $course = $this->getDataGenerator()->create_course();
        enrol_get_plugin('semco')->add_instance($course);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString($course->fullname . ' (' . $course->shortname . ')', $check->get_findings()[0]);

        // As soon as the course resets every activity type as well, everything is fine again.
        foreach ($settingnames as $settingname) {
            $DB->insert_record('local_recompletion_config', (object) [
                'course' => $course->id,
                'name' => $settingname,
                'value' => LOCAL_RECOMPLETION_DELETE,
            ]);
        }
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A course which resets more activity types than the site-wide settings ask for is not reported. Only the
        // site-wide finding about the one activity type which is not reset remains.
        set_config($settingnames[0], LOCAL_RECOMPLETION_NOTHING, 'local_recompletion');
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString('1 activity type(s)', $check->get_findings()[0]);
        $this->assertEquals(1, substr_count($check->get_findings()[0], '<li>'));

        // Without any site-wide activity type setting, there is nothing to compare the courses against and the check
        // cannot be assessed. Moodle writes the default values of these settings when local_recompletion is installed,
        // thus this does not happen on a proper installation, but the item has to cope with it nonetheless.
        foreach ($settingnames as $settingname) {
            unset_config($settingname, 'local_recompletion');
        }
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NA, $check->get_status());
        $this->assertEquals([$classname::FINDING_NOSETTINGS], $check->get_finding_ids());
        $this->assertFalse($check->supports_autofix());
        $this->assertNull($check->get_action_url());
    }

    /**
     * Test the health check item for the self-service course completion reset.
     *
     * This test needs local_recompletion to be installed. The behaviour without that plugin is covered by
     * test_recompletion_items_without_local_recompletion().
     */
    public function test_recompletionresetmycompletion(): void {
        // Skip this test if local_recompletion is not installed as the item cannot be assessed then.
        if (enrol_semco_check_local_recompletion() != true) {
            $this->markTestSkipped('local_recompletion is not installed, the item cannot be assessed.');
        }

        $classname = \enrol_semco\healthcheck\check\recompletionresetmycompletion::class;

        // A stock local_recompletion grants this capability to the student role which is the default SEMCO
        // enrolment role.
        $enrolmentroleid = get_config('enrol_semco', 'role');
        assign_capability(
            'local/recompletion:resetmycompletion',
            CAP_ALLOW,
            $enrolmentroleid,
            $this->systemcontext->id,
            true
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());

        // The automatic fix removes the capability from the role definition. It is not entirely harmless, as the role
        // is not owned by this plugin and is most likely used in other courses as well.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());

        // After preventing the capability, everything is fine as well.
        assign_capability(
            'local/recompletion:resetmycompletion',
            CAP_PREVENT,
            $enrolmentroleid,
            $this->systemcontext->id,
            true
        );
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A permission override in a course which holds a SEMCO enrolment is reported as well, even though the role
        // definition itself does not grant the capability anymore.
        $course = $this->getDataGenerator()->create_course();
        enrol_get_plugin('semco')->add_instance($course);
        assign_capability(
            'local/recompletion:resetmycompletion',
            CAP_ALLOW,
            $enrolmentroleid,
            \context_course::instance($course->id)->id,
            true
        );
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString($course->fullname . ' (' . $course->shortname . ')', $check->get_findings()[0]);

        // If there is not any enrolment role configured, this check cannot be assessed.
        set_config('role', '', 'enrol_semco');
        $this->assertEquals(healthcheck::NA, $this->get_check($classname)->get_status());
    }

    /**
     * Test the health check item for the access to the course recompletion settings.
     *
     * This test needs local_recompletion to be installed. The behaviour without that plugin is covered by
     * test_recompletion_items_without_local_recompletion().
     */
    public function test_recompletionmanage(): void {
        global $DB;

        // Skip this test if local_recompletion is not installed as the item cannot be assessed then.
        if (enrol_semco_check_local_recompletion() != true) {
            $this->markTestSkipped('local_recompletion is not installed, the item cannot be assessed.');
        }

        $classname = \enrol_semco\healthcheck\check\recompletionmanage::class;
        $capability = 'local/recompletion:manage';
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        // A stock local_recompletion grants this capability to the teacher and the manager roles. The teacher role
        // is what makes the item report a warning.
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // The automatic fix removes the capability from all role definitions. It is not entirely harmless, as the roles
        // are not owned by this plugin and are most likely used in other courses as well.
        $this->assertTrue($check->supports_autofix());
        $this->assertTrue($check->is_autofix_risky());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $check->get_status());
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A role with the teacher archetype which holds the capability is reported as a warning.
        assign_capability($capability, CAP_ALLOW, $teacherroleid, $this->systemcontext->id, true);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // Any other role which holds the capability is reported as a notice only.
        assign_capability($capability, CAP_ALLOW, $managerroleid, $this->systemcontext->id, true);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // This also applies to a custom role without an archetype.
        $customroleid = create_role('Custom role', 'customrole', '', '');
        assign_capability($capability, CAP_ALLOW, $customroleid, $this->systemcontext->id, true);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString('Custom role', $check->get_findings()[0]);
        $check->autofix();
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // After preventing the capability, everything is fine as well.
        assign_capability($capability, CAP_PREVENT, $teacherroleid, $this->systemcontext->id, true);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());

        // A permission override in a course which holds a SEMCO enrolment is reported as well, even though the role
        // definition itself does not grant the capability. The severity follows the archetype here as well, and such an
        // override is not removed automatically.
        $course = $this->getDataGenerator()->create_course();
        enrol_get_plugin('semco')->add_instance($course);
        assign_capability($capability, CAP_ALLOW, $studentroleid, \context_course::instance($course->id)->id, true);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::NOTICE, $check->get_status());
        $this->assertCount(1, $check->get_findings());
        $this->assertStringContainsString($course->fullname . ' (' . $course->shortname . ')', $check->get_findings()[0]);
        $this->assertFalse($check->supports_autofix());
        assign_capability($capability, CAP_ALLOW, $teacherroleid, \context_course::instance($course->id)->id, true);
        $check = $this->get_check($classname);
        $this->assertEquals(healthcheck::WARNING, $check->get_status());
        $this->assertCount(2, $check->get_findings());

        // A course without SEMCO enrolments is not looked at.
        $othercourse = $this->getDataGenerator()->create_course();
        unassign_capability($capability, $studentroleid, \context_course::instance($course->id)->id);
        unassign_capability($capability, $teacherroleid, \context_course::instance($course->id)->id);
        assign_capability($capability, CAP_ALLOW, $teacherroleid, \context_course::instance($othercourse->id)->id, true);
        $this->assertEquals(healthcheck::OK, $this->get_check($classname)->get_status());
    }

    /**
     * Test that the error status is separated from the warning status.
     *
     * The error status is meant for aspects which stop the SEMCO integration from working, the warning status for
     * aspects which are not in the desired state but still let the integration work.
     */
    public function test_error_status_is_separated_from_warning_status(): void {
        global $DB;

        // Make sure that none of the recommendations which a stock Moodle does not follow interferes.
        $this->mute_all_items_needing_attention();

        // Break an aspect which does not stop the SEMCO integration from working.
        $DB->set_field('external_services', 'restrictedusers', 0, ['id' => $this->semcoservice->id]);
        healthcheck::reset_caches();

        // The affected item must report a warning and the manager must not see a broken integration.
        $this->assertEquals(
            healthcheck::WARNING,
            $this->get_check(\enrol_semco\healthcheck\check\externalserviceconfig::class)->get_status()
        );
        $this->assertTrue(manager::has_healthchecks_needing_attention());
        $this->assertFalse(manager::has_healthchecks_with_error());

        // Now break an aspect which the SEMCO integration cannot work without.
        set_config('enablewebservices', 0);
        healthcheck::reset_caches();

        // The affected item must report an error and the manager must see a broken integration.
        $this->assertEquals(
            healthcheck::ERROR,
            $this->get_check(\enrol_semco\healthcheck\check\webservicesenabled::class)->get_status()
        );
        $this->assertTrue(manager::has_healthchecks_with_error());

        // And the error must be sorted before the warning.
        $healthchecks = array_values(manager::get_healthchecks(healthcheck::CATEGORY_WEBSERVICE));
        $this->assertEquals(healthcheck::ERROR, $healthchecks[0]->get_status());
        $this->assertEquals('webservicesenabled', $healthchecks[0]->get_id());
    }

    /**
     * Test that the recommendations never report a broken SEMCO integration.
     *
     * The items of this category check global Moodle settings which the plugin installer does not touch. They may well
     * ask for the admin's attention, but they must never claim that the integration is down.
     */
    public function test_recompletion_and_recommendations_never_report_an_error(): void {
        // Iterate over the items which do not cover the plugin installation.
        $categories = [healthcheck::CATEGORY_RECOMPLETION, healthcheck::CATEGORY_RECOMMENDATIONS];
        foreach ($categories as $category) {
            foreach (manager::get_healthchecks($category) as $healthcheck) {
                // None of them may report an error, neither in the stock state which is assessed here nor anywhere in
                // its code: The error status is reserved for a broken plugin installation, and the manager relies on
                // that when it describes the error status, see manager::get_status_description().
                $this->assertNotEquals(
                    healthcheck::ERROR,
                    $healthcheck->get_status(),
                    'Health check "' . $healthcheck->get_id() . '" reports an error, but it does not cover the ' .
                        'plugin installation.'
                );
                $source = file_get_contents((new \ReflectionClass($healthcheck))->getFileName());
                $this->assertStringNotContainsString(
                    'healthcheck::ERROR',
                    $source,
                    'Health check "' . $healthcheck->get_id() . '" can report an error, but it does not cover the ' .
                        'plugin installation.'
                );
            }
        }
    }
}
