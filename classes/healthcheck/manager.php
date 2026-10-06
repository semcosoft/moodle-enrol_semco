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
 * Enrolment method "SEMCO" - Health check manager
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck;

/**
 * Manager to collect and evaluate the health check items.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Return the health check item instances.
     *
     * @param string|null $category Optional category constant to filter the health check items by.
     * @return healthcheck[] Array of health check item instances.
     */
    public static function get_healthchecks(?string $category = null): array {
        // Get the health check item instances.
        $healthchecks = self::get_healthcheck_instances();

        // Skip health check items from other categories if a filter is set.
        if ($category !== null) {
            $healthchecks = array_filter(
                $healthchecks,
                fn(healthcheck $healthcheck): bool => $healthcheck->get_category() === $category
            );
        }

        // Sort the health check items by status priority (defined by get_supported_statuses()) first, so that the items
        // which need attention are shown on top of their category and the muted ones at its bottom. Items which share a
        // status keep the order of get_healthcheck_classes() which is grouped by category.
        // Please note that the sorting evaluates every single item. Consumers which do not need the sorted list use
        // get_healthcheck_instances() directly to avoid that.
        $statusorder = array_flip(array_keys(self::get_supported_statuses()));
        $classorder = array_flip(array_keys($healthchecks));
        uasort($healthchecks, function (healthcheck $a, healthcheck $b) use ($statusorder, $classorder): int {
            $statuscmp = ($statusorder[self::get_effective_status($a)] ?? PHP_INT_MAX)
                <=> ($statusorder[self::get_effective_status($b)] ?? PHP_INT_MAX);
            if ($statuscmp !== 0) {
                return $statuscmp;
            }

            return $classorder[$a->get_id()] <=> $classorder[$b->get_id()];
        });

        // Return the result.
        return $healthchecks;
    }

    /**
     * Return the health check item instances in the order of get_healthcheck_classes(), without evaluating any of them.
     *
     * Instantiating a health check item is cheap, evaluating it is not: Determining a status can take several database
     * queries, and some items look into every course which SEMCO uses. This function therefore does not touch the
     * status of the items, so that a consumer which needs only one item or which can stop at the first item needing
     * attention does not pay for the evaluation of all the others.
     *
     * @return healthcheck[] Array of health check item instances keyed by health check item id.
     */
    protected static function get_healthcheck_instances(): array {
        // Initialize empty result array.
        $healthchecks = [];

        // Iterate over all health check classes.
        foreach (self::get_healthcheck_classes() as $classname) {
            // Instantiate the health check item.
            $instance = self::instantiate_healthcheck($classname);
            if ($instance === null) {
                continue;
            }

            // Check for duplicate health check item ids.
            $id = $instance->get_id();
            if (isset($healthchecks[$id])) {
                debugging('Duplicate health check id "' . $id . '" for class "' . $classname . '".', DEBUG_DEVELOPER);
                continue;
            }

            // Add to result keyed by health check item id.
            $healthchecks[$id] = $instance;
        }

        // Return the result.
        return $healthchecks;
    }

    /**
     * Return a health check item instance by id.
     *
     * This does not evaluate any health check item, not even the returned one. The mute and unmute actions of the health
     * check page rely on that, as they do not need a status at all.
     *
     * @param string $healthcheckid The id of the health check item.
     * @return healthcheck|null
     */
    public static function get_healthcheck_by_id(string $healthcheckid): ?healthcheck {
        // Return the health check item with the matching id or fall back to null if it does not exist.
        return self::get_healthcheck_instances()[$healthcheckid] ?? null;
    }

    /**
     * Return the supported status values with their labels and descriptions in order.
     *
     * Each entry is an array with the key 'label'. The descriptions are not part of this list as they depend on the
     * context in which a health check item has to be read, see get_status_description().
     *
     * @return array[] Array of status data keyed by status value.
     */
    public static function get_supported_statuses(): array {
        return [
            healthcheck::ERROR => [
                'label' => get_string('healthcheckstatus_error', 'enrol_semco'),
            ],
            healthcheck::WARNING => [
                'label' => get_string('healthcheckstatus_warning', 'enrol_semco'),
            ],
            healthcheck::NOTICE => [
                'label' => get_string('healthcheckstatus_notice', 'enrol_semco'),
            ],
            healthcheck::OK => [
                'label' => get_string('healthcheckstatus_ok', 'enrol_semco'),
            ],
            healthcheck::NA => [
                'label' => get_string('healthcheckstatus_na', 'enrol_semco'),
            ],
            healthcheck::MUTED => [
                'label' => get_string('healthcheckstatus_muted', 'enrol_semco'),
            ],
        ];
    }

    /**
     * Return the supported category values and labels in order.
     *
     * @return string[] Array of category labels keyed by category value.
     */
    public static function get_supported_categories(): array {
        return [
            healthcheck::CATEGORY_WEBSERVICE => get_string('healthcheckcategory_webservice', 'enrol_semco'),
            healthcheck::CATEGORY_PLUGIN => get_string('healthcheckcategory_plugin', 'enrol_semco'),
            healthcheck::CATEGORY_ROLE => get_string('healthcheckcategory_role', 'enrol_semco'),
            healthcheck::CATEGORY_USER => get_string('healthcheckcategory_user', 'enrol_semco'),
            healthcheck::CATEGORY_TOKEN => get_string('healthcheckcategory_token', 'enrol_semco'),
            healthcheck::CATEGORY_PROFILEFIELDS => get_string('healthcheckcategory_profilefields', 'enrol_semco'),
            healthcheck::CATEGORY_RECOMPLETION => get_string('healthcheckcategory_recompletion', 'enrol_semco'),
            healthcheck::CATEGORY_RECOMMENDATIONS => get_string('healthcheckcategory_recommendations', 'enrol_semco'),
        ];
    }

    /**
     * Check if at least one health check item needs attention.
     *
     * A health check item needs attention if its effective status is neither OK, N/A nor MUTED.
     *
     * @return bool
     */
    public static function has_healthchecks_needing_attention(): bool {
        // Iterate over the unsorted health check items and stop at the first one which needs attention. The plugin
        // settings page calls this on every single page load, thus it must not evaluate more items than necessary.
        foreach (self::get_healthcheck_instances() as $healthcheck) {
            if (self::healthcheck_needs_attention($healthcheck)) {
                return true;
            }
        }

        // No health check item needs attention.
        return false;
    }

    /**
     * Return the health check items which need attention.
     *
     * This covers the items of all categories. The items which do not check the state of the plugin installation - the
     * recompletion and the recommendations category - check settings which an admin may have decided against on
     * purpose. Such an admin mutes the particular item to get rid of the alarm which it raises outside of the health
     * check page, see set_healthcheck_muted().
     *
     * @return healthcheck[] Array of health check item instances keyed by health check item id.
     */
    public static function get_healthchecks_needing_attention(): array {
        // Filter the health check items which need attention.
        return array_filter(
            self::get_healthchecks(),
            fn(healthcheck $healthcheck): bool => self::healthcheck_needs_attention($healthcheck)
        );
    }

    /**
     * Check if at least one health check item reports a broken SEMCO integration.
     *
     * @return bool
     */
    public static function has_healthchecks_with_error(): bool {
        // A muted item does not need attention, thus it is not taken into account here either.
        return self::get_most_severe_status() === healthcheck::ERROR;
    }

    /**
     * Return the most severe status among the health check items which need attention.
     *
     * This is what a consumer outside of the health check page needs to decide how loud it has to be. The Moodle
     * Checks API integration uses it to map the health check result to a Moodle check result.
     *
     * @return string|null One of the status constants or null if no health check item needs attention.
     */
    public static function get_most_severe_status(): ?string {
        // Get the severity order of the statuses. The supported statuses are listed from severe to harmless.
        $statusorder = array_flip(array_keys(self::get_supported_statuses()));

        // Iterate over the health check items which need attention and remember the most severe status.
        $moststatus = null;
        foreach (self::get_healthchecks_needing_attention() as $healthcheck) {
            $status = $healthcheck->get_status();
            if ($moststatus === null || ($statusorder[$status] ?? PHP_INT_MAX) < $statusorder[$moststatus]) {
                $moststatus = $status;
            }
        }

        // Return the most severe status.
        return $moststatus;
    }

    /**
     * Check if a health check item needs attention.
     *
     * A health check item needs attention if its effective status is neither OK, N/A nor MUTED.
     *
     * @param healthcheck $healthcheck The health check item to check.
     * @return bool
     */
    public static function healthcheck_needs_attention(healthcheck $healthcheck): bool {
        return !in_array(self::get_effective_status($healthcheck), [healthcheck::OK, healthcheck::NA, healthcheck::MUTED]);
    }

    /**
     * Return the health check item status with the muted state applied.
     *
     * @param healthcheck $healthcheck The health check item to get the effective status for.
     * @return string
     */
    public static function get_effective_status(healthcheck $healthcheck): string {
        // If the health check item is muted, return the muted status regardless of the actual status.
        if (self::is_healthcheck_muted($healthcheck->get_id())) {
            return healthcheck::MUTED;
        }

        // Otherwise, return the actual status.
        return $healthcheck->get_status();
    }

    /**
     * Check if a health check item is muted.
     *
     * @param string $healthcheckid The id of the health check item to check the muted state for.
     * @return bool
     */
    public static function is_healthcheck_muted(string $healthcheckid): bool {
        // This will automatically return false if the config is not set, which is the default state for all items.
        return !empty(get_config('enrol_semco', self::get_healthcheck_muted_config_key($healthcheckid)));
    }

    /**
     * Persist the muted state for a health check item.
     *
     * @param string $healthcheckid The id of the health check item to set the muted state for.
     * @param bool $muted Whether the health check item should be muted or unmuted.
     * @return bool
     */
    public static function set_healthcheck_muted(string $healthcheckid, bool $muted): bool {
        // Determine the config key to persist the muted state.
        $key = self::get_healthcheck_muted_config_key($healthcheckid);

        // If the item should be muted, set the config.
        if ($muted == true) {
            return set_config($key, 1, 'enrol_semco');

            // Otherwise, remove the config.
        } else {
            return unset_config($key, 'enrol_semco');
        }
    }

    /**
     * Return the config key which is used to persist a muted health check item.
     *
     * @param string $healthcheckid The id of the health check item to get the config key for.
     * @return string
     */
    protected static function get_healthcheck_muted_config_key(string $healthcheckid): string {
        return 'healthcheck-muted-' . $healthcheckid;
    }

    /**
     * Return the badge class for a health check item status.
     *
     * @param string $status The health check item status to get the badge class for.
     * @return string
     */
    public static function get_status_badge_class(string $status): string {
        switch ($status) {
            case healthcheck::OK:
                return 'text-bg-success';

            case healthcheck::ERROR:
                return 'text-bg-danger';

            case healthcheck::NOTICE:
                return 'text-bg-info';

            case healthcheck::WARNING:
                return 'text-bg-warning';

            case healthcheck::NA:
                return 'text-bg-secondary';

            case healthcheck::MUTED:
                return 'text-bg-secondary';

            default:
                return 'text-bg-secondary';
        }
    }

    /**
     * Return the validated status label for a health check item.
     *
     * @param healthcheck $healthcheck The health check item to get the status label for.
     * @return string
     */
    public static function get_status_label(healthcheck $healthcheck): string {
        // Get the supported statuses and the health check item status.
        $supported = self::get_supported_statuses();
        $result = self::get_effective_status($healthcheck);

        // If the status is supported, return the corresponding label.
        if (array_key_exists($result, $supported)) {
            return $supported[$result]['label'];
        }

        // If the status is not supported, log a debug message.
        debugging(
            'Health check "' . $healthcheck->get_id() . '" returned unsupported status "' . $result . '".',
            DEBUG_DEVELOPER
        );

        // Return the fallback label for an unsupported status.
        return $supported[healthcheck::NA]['label'];
    }

    /**
     * Return the context in which a health check item has to be read.
     *
     * A deviation means something different depending on the category: The items which cover the plugin installation
     * deviate from the state which the plugin installer has created, the items of the recompletion category deviate
     * from a setting which has to be made after the installation of the companion plugin, and the items of the
     * recommendations category do not follow a recommendation for a SEMCO-Moodle integration.
     *
     * @param healthcheck $healthcheck The health check item.
     * @return string One of 'installation', 'recompletion' and 'recommendation'.
     */
    public static function get_status_context(healthcheck $healthcheck): string {
        // Derive the context from the category which the health check item belongs to.
        switch ($healthcheck->get_category()) {
            case healthcheck::CATEGORY_RECOMMENDATIONS:
                return 'recommendation';
            case healthcheck::CATEGORY_RECOMPLETION:
                return 'recompletion';
            default:
                return 'installation';
        }
    }

    /**
     * Return the validated status description for a health check item.
     *
     * The statuses which need attention carry a description per context, as a deviation from the state which the
     * plugin installer has created means something different than a global Moodle setting which does not follow a
     * recommendation, see get_status_context().
     *
     * @param healthcheck $healthcheck The health check item to get the status description for.
     * @return string
     */
    public static function get_status_description(healthcheck $healthcheck): string {
        // Get the health check item status.
        $result = self::get_effective_status($healthcheck);

        // An error means that the SEMCO integration is broken, which only an item of the installation categories can
        // report: The recompletion and the recommendations items never exceed a warning, see
        // test_recompletion_and_recommendations_never_report_an_error(). Thus, there is only one error description
        // and no need for a per-context variant.
        if ($result === healthcheck::ERROR) {
            return get_string('healthcheckstatus_error_description_installation', 'enrol_semco');
        }

        // The other statuses which need attention are described per context, as a deviation from the state which the
        // plugin installer has created means something different than a global Moodle setting which does not follow a
        // recommendation.
        if (in_array($result, [healthcheck::WARNING, healthcheck::NOTICE])) {
            return get_string(
                'healthcheckstatus_' . $result . '_description_' . self::get_status_context($healthcheck),
                'enrol_semco'
            );
        }

        // The statuses which do not need attention are described context-free.
        if (in_array($result, [healthcheck::OK, healthcheck::NA, healthcheck::MUTED])) {
            return get_string('healthcheckstatus_' . $result . '_description', 'enrol_semco');
        }

        // If the status is not supported at all, log a debug message.
        debugging(
            'Health check "' . $healthcheck->get_id() . '" returned unsupported status "' . $result . '".',
            DEBUG_DEVELOPER
        );

        // And fall back to the description of the N/A status.
        return get_string('healthcheckstatus_' . healthcheck::NA . '_description', 'enrol_semco');
    }

    /**
     * Return a "Possible solutions" hint text for a health check item which needs attention.
     *
     * Returns an empty string when the health check item does not need attention or when neither an action URL
     * nor autofix support is available.
     *
     * @param healthcheck $healthcheck The health check item to get the solution text for.
     * @return string
     */
    public static function get_possible_solution(healthcheck $healthcheck): string {
        // Only show a solution hint when the health check item needs attention.
        if (!self::healthcheck_needs_attention($healthcheck)) {
            return '';
        }

        // Determine which solution options are available for this health check item.
        $autofixable = $healthcheck->supports_autofix();
        $hasactionurl = $healthcheck->get_action_url() !== null;

        // Return the appropriate hint text based on the available solution options.
        if ($autofixable && $hasactionurl) {
            return get_string('healthchecksolution_both', 'enrol_semco');
        } else if ($autofixable) {
            return get_string('healthchecksolution_autofixonly', 'enrol_semco');
        } else if ($hasactionurl) {
            return get_string('healthchecksolution_actionurlonly', 'enrol_semco');
        }

        // Neither an automatic fix nor an action URL is available, the SEMCO support is the only way to go.
        return get_string('healthchecksolution_supportonly', 'enrol_semco');
    }

    /**
     * Return all health check class names.
     *
     * @return string[]
     */
    public static function get_healthcheck_classes(): array {
        return [
            // Webservice infrastructure.
            check\webservicesenabled::class,
            check\restprotocol::class,
            check\authmethod::class,
            check\externalserviceexists::class,
            check\externalserviceconfig::class,
            check\externalservicefunctions::class,
            // SEMCO enrolment plugin.
            check\enrolpluginenabled::class,
            check\enrolmentroleconfigured::class,
            check\capabilitiesregistered::class,
            check\capabilitiesexclusive::class,
            // SEMCO webservice role.
            check\roleexists::class,
            check\rolecontextlevel::class,
            check\rolecapabilitiessemco::class,
            check\rolecapabilitiesmoodle::class,
            check\rolecapabilityrest::class,
            check\rolecapabilitiessurplus::class,
            check\roleassignallowed::class,
            // SEMCO webservice user.
            check\userexists::class,
            check\userauthmethod::class,
            check\useractive::class,
            check\userprofile::class,
            check\userroleassignment::class,
            check\userserviceauthorised::class,
            check\userserviceiprestriction::class,
            // SEMCO webservice token.
            check\usertoken::class,
            check\usertokeniprestriction::class,
            // SEMCO user profile fields.
            check\profilefieldcategory::class,
            check\profilefield_userid::class,
            check\profilefield_usercompany::class,
            check\profilefield_userbirthday::class,
            check\profilefield_userplaceofbirth::class,
            check\profilefield_branchtoken::class,
            // Recommended Moodle settings.
            check\allowaccountssameemail::class,
            check\manualauthlockedfields::class,
            check\selfenrolment::class,
            check\enrolmentroleviewparticipants::class,
            check\messaging::class,
            check\coursecompletedmessage::class,
            // Recompletion plugin.
            check\recompletioninstalled::class,
            check\recompletionondemand::class,
            check\recompletionnotify::class,
            check\recompletionactivities::class,
            check\recompletiongrades::class,
            check\recompletionarchive::class,
            check\recompletionrestrictenrol::class,
            check\recompletionresetmycompletion::class,
            check\recompletionmanage::class,
        ];
    }

    /**
     * Instantiate a health check item by class name.
     *
     * @param string $classname The class name of the health check item.
     * @return healthcheck|null
     */
    protected static function instantiate_healthcheck(string $classname): ?healthcheck {
        // Check if the class exists.
        if (!class_exists($classname)) {
            debugging('Health check class "' . $classname . '" could not be loaded.', DEBUG_DEVELOPER);
            return null;
        }

        // Instantiate the class and check if it extends the health check base class.
        $instance = new $classname();
        if (!($instance instanceof healthcheck)) {
            debugging('Health check class "' . $classname . '" must extend healthcheck.', DEBUG_DEVELOPER);
            return null;
        }

        // Return the instance.
        return $instance;
    }
}
