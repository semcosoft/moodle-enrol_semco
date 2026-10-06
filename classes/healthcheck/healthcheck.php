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
 * Enrolment method "SEMCO" - Health check base class and status constants
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Require plugin library (as the health check items rely on the plugin's constants).
require_once($CFG->dirroot . '/enrol/semco/locallib.php');

/**
 * Base class for a SEMCO health check item.
 *
 * Most health check items verify one particular aspect which the plugin's installer (db/install.php) has set up and
 * report if this aspect is still in the desired state. The items of the CATEGORY_RECOMMENDATIONS category go one step
 * further: They verify the global Moodle settings which the plugin's README recommends for a SEMCO-Moodle integration.
 * These settings are not touched by the plugin installer, thus these items are recommendations and not requirements.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class healthcheck {
    /** @var string Status: The checked aspect is in the desired state. */
    public const OK = 'ok';

    /** @var string Status: The checked aspect deviates from the desired state, but this is a cosmetic issue only. */
    public const NOTICE = 'notice';

    /** @var string Status: The checked aspect is not in the desired state, but the SEMCO integration still works. */
    public const WARNING = 'warning';

    /** @var string Status: The checked aspect is broken and the SEMCO integration does not work at the moment. */
    public const ERROR = 'error';

    /** @var string Status: The checked aspect cannot be assessed as a prerequisite is missing. */
    public const NA = 'na';

    /**
     * @var string Status: The health check item has been muted by an admin. This status is never reported by an item
     *             itself, it is applied on top of the item's status, see manager::get_effective_status().
     */
    public const MUTED = 'muted';

    /** @var string Category: Webservice infrastructure. */
    public const CATEGORY_WEBSERVICE = 'webservice';

    /** @var string Category: SEMCO enrolment plugin. */
    public const CATEGORY_PLUGIN = 'plugin';

    /** @var string Category: SEMCO webservice role. */
    public const CATEGORY_ROLE = 'role';

    /** @var string Category: SEMCO webservice user. */
    public const CATEGORY_USER = 'user';

    /** @var string Category: SEMCO webservice token. */
    public const CATEGORY_TOKEN = 'token';

    /** @var string Category: SEMCO user profile fields. */
    public const CATEGORY_PROFILEFIELDS = 'profilefields';

    /** @var string Category: Recompletion plugin. */
    public const CATEGORY_RECOMPLETION = 'recompletion';

    /** @var string Category: Recommended Moodle settings. */
    public const CATEGORY_RECOMMENDATIONS = 'recommendations';

    /** @var string Shared finding: The SEMCO webservice role does not exist. */
    public const FINDING_NOROLE = 'norole';

    /** @var string Shared finding: The SEMCO webservice user does not exist. */
    public const FINDING_NOUSER = 'nouser';

    /** @var string Shared finding: The SEMCO external service does not exist. */
    public const FINDING_NOSERVICE = 'noservice';

    /** @var string Shared finding: The SEMCO webservice user or the SEMCO webservice role does not exist. */
    public const FINDING_NOUSERORROLE = 'nouserorrole';

    /** @var string Shared finding: The SEMCO webservice user or the SEMCO external service does not exist. */
    public const FINDING_NOUSERORSERVICE = 'nouserorservice';

    /** @var string Shared finding: There is not any valid SEMCO enrolment role configured. */
    public const FINDING_NOENROLMENTROLE = 'noenrolmentrole';

    /** @var string Shared finding: The companion plugin local_recompletion is not installed. */
    public const FINDING_NORECOMPLETION = 'norecompletion';

    /** @var int The maximum amount of courses which a finding names before it just counts the rest. */
    protected const MAX_NAMED_COURSES = 10;

    /** @var array Per-request cache for the database records which nearly all health check items need. */
    protected static array $recordcache = [];

    /**
     * @var array Per-request cache for the evaluation results of the health check items, keyed by health check item id.
     *            Each entry holds the 'status' and the 'findings' which the item has determined. The cache is static on
     *            purpose: The manager hands out fresh instances with every call, but determining a status can be
     *            expensive and must only be done once per request.
     */
    protected static array $resultcache = [];

    /**
     * @var array[] Findings which are collected while determine_status() is running. Each finding is an array with the
     *              keys 'id' (the finding id), 'text' (the human readable finding) and 'context' (the data which the
     *              automatic fix of the finding needs).
     */
    protected array $findings = [];

    /**
     * Return an unique health check item identifier.
     *
     * The identifier is also used to compose the language string keys of this item, see get_title(),
     * get_summary() and get_description().
     *
     * @return string
     */
    abstract public function get_id(): string;

    /**
     * Return the health check item title.
     *
     * @return string
     */
    abstract public function get_title(): string;

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    abstract public function get_summary(): string;

    /**
     * Return the health check item description.
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * Return the health check item category.
     *
     * @return string One of the CATEGORY_* constants.
     */
    abstract public function get_category(): string;

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * The array is keyed by the finding id. Each definition is an array with these elements:
     * - 'autofix' (bool): Whether the finding can be fixed automatically.
     * - 'risky' (bool): Whether the automatic fix is not entirely harmless, for example because it changes a setting
     *   which affects more than the SEMCO integration or which an admin may have set on purpose. It is only evaluated
     *   if 'autofix' is true.
     * - 'url' (\core\url|null): The URL where the admin can fix the finding himself.
     * - 'followup' (string, optional): What the admin has to do himself after the automatic fix has been applied, if
     *   the fix cannot do the whole job. It is only evaluated if 'autofix' is true and it is shown along with the
     *   success notification of the automatic fix, see get_autofix_followups().
     *
     * The order of the definitions matters: If more than one finding applies at the same time, the findings are fixed
     * from top to bottom and the URL of the topmost finding is offered to the admin, thus the finding which should be
     * fixed first has to be defined first.
     *
     * The shared findings which are reported along with the N/A status do not have to be defined here, see
     * get_shared_finding_definitions().
     *
     * @return array[]
     */
    abstract protected function get_finding_definitions(): array;

    /**
     * Return the definitions of the shared findings which explain why a health check item cannot be assessed.
     *
     * These findings are exclusively used together with the N/A status: An item reports one of them and returns the N/A
     * status right away, it never combines them with another status or with another finding. They are shared as several
     * items depend on the very same prerequisite, for example on the SEMCO webservice role.
     *
     * These findings cannot be fixed within the item which reports them, neither automatically nor manually, as the
     * missing prerequisite is reported and fixed by another item.
     *
     * Please note that this is not true the other way round: An item can also report the N/A status along with a finding
     * of its own if the reason is specific to this item. Such a finding is defined in get_finding_definitions() like
     * any other finding of the item.
     *
     * @return array[]
     */
    final protected function get_shared_finding_definitions(): array {
        $definition = ['autofix' => false, 'risky' => false, 'url' => null];
        return [
            self::FINDING_NOROLE => $definition,
            self::FINDING_NOUSER => $definition,
            self::FINDING_NOSERVICE => $definition,
            self::FINDING_NOUSERORROLE => $definition,
            self::FINDING_NOUSERORSERVICE => $definition,
            self::FINDING_NOENROLMENTROLE => $definition,
            self::FINDING_NORECOMPLETION => $definition,
        ];
    }

    /**
     * Return all finding definitions of this health check item, the item's own ones first.
     *
     * @return array[]
     */
    final protected function get_all_finding_definitions(): array {
        return $this->get_finding_definitions() + $this->get_shared_finding_definitions();
    }

    /**
     * Return the evaluation result of this health check item and remember it for the rest of the request.
     *
     * @return array An array with the keys 'status' and 'findings'.
     */
    final protected function get_result(): array {
        // If the item is evaluated already, return the result.
        $id = $this->get_id();
        if (array_key_exists($id, self::$resultcache)) {
            return self::$resultcache[$id];
        }

        // Let the health check item determine its status and collect its findings.
        $this->findings = [];
        $status = $this->determine_status();

        // A status which is not OK is useless without a finding which explains it, and the same applies to the N/A
        // status which has to name the missing prerequisite. We cannot enforce that with unit tests alone, as most
        // items report the same status on more than one code path, thus we check it here for every single evaluation.
        if ($status !== self::OK && count($this->findings) < 1) {
            debugging(
                'Health check "' . $id . '" reports the status "' . $status . '" without ' .
                    'explaining it with a finding.',
                DEBUG_DEVELOPER
            );
        }

        // Sort the findings in the order of their definitions, so that everything which is derived from them - the
        // findings list, the action URL and the automatic fix - follows the order in which they should be fixed.
        // The sort is stable, thus findings which share an id keep the order in which they have been added.
        $order = array_flip(array_keys($this->get_all_finding_definitions()));
        usort(
            $this->findings,
            fn(array $a, array $b): int => ($order[$a['id']] ?? PHP_INT_MAX) <=> ($order[$b['id']] ?? PHP_INT_MAX)
        );

        // Remember and return the result.
        self::$resultcache[$id] = ['status' => $status, 'findings' => $this->findings];
        return self::$resultcache[$id];
    }

    /**
     * Return the health check item status.
     *
     * This function is final on purpose: Determining a status can be expensive and is done more than once per request,
     * thus the result has to be cached, and no health check item shall be able to forget that. Items implement
     * determine_status() and leave the caching to this class.
     *
     * @return string One of the status constants of this class.
     */
    final public function get_status(): string {
        return $this->get_result()['status'];
    }

    /**
     * Determine the health check item status.
     *
     * Items do not have to care about caching the result, see get_status(). They collect their findings with
     * add_finding() while they determine the status.
     *
     * @return string One of the status constants of this class.
     */
    abstract protected function determine_status(): string;

    /**
     * Return the findings which the item has collected while it has determined its status.
     *
     * The findings are shown as an additional list in the item's details modal. They are meant to name the concrete
     * details of a problem which the item's static description cannot cover.
     *
     * @return string[]
     */
    final public function get_findings(): array {
        return array_column($this->get_result()['findings'], 'text');
    }

    /**
     * Return the ids of the findings which the item has collected, in the order in which they should be fixed.
     *
     * @return string[]
     */
    final public function get_finding_ids(): array {
        return array_values(array_unique(array_column($this->get_result()['findings'], 'id')));
    }

    /**
     * Return the action URL for this health check item.
     *
     * This is the URL where the admin can fix the topmost finding himself. Not every finding can be fixed manually, such
     * a finding does not define an URL and is skipped here, so that the admin is led to the topmost finding which he is
     * able to do something about. An item which does not have any finding does not have anything to fix and thus does
     * not offer an URL.
     *
     * @return \core\url|null
     */
    final public function get_action_url(): ?\core\url {
        // Get the finding definitions.
        $definitions = $this->get_all_finding_definitions();

        // Return the URL of the topmost finding which offers one.
        foreach ($this->get_finding_ids() as $findingid) {
            if (($definitions[$findingid]['url'] ?? null) !== null) {
                return $definitions[$findingid]['url'];
            }
        }

        // Fall back to null if there is not any finding which can be fixed manually.
        return null;
    }

    /**
     * Return whether this health check item can be fixed automatically.
     *
     * This does not analyse anything again, it just looks the findings up which have been collected already: The item
     * can be fixed automatically if it has at least one finding and if each of its findings can be fixed
     * automatically. An item which is fine or which cannot be assessed does not have anything to fix.
     *
     * @return bool
     */
    final public function supports_autofix(): bool {
        // An item which is fine or which cannot be assessed cannot be fixed.
        if (in_array($this->get_status(), [self::OK, self::NA])) {
            return false;
        }

        // Neither can an item without any finding.
        $findingids = $this->get_finding_ids();
        if (count($findingids) < 1) {
            return false;
        }

        // Each and every finding must be fixable automatically.
        $definitions = $this->get_all_finding_definitions();
        foreach ($findingids as $findingid) {
            if (empty($definitions[$findingid]['autofix'])) {
                return false;
            }
        }

        // The item can be fixed automatically.
        return true;
    }

    /**
     * Return whether the automatic fix of this health check item is not entirely harmless.
     *
     * This is the case as soon as at least one of the findings which would be fixed is defined as risky.
     *
     * @return bool
     */
    final public function is_autofix_risky(): bool {
        // If the item cannot be fixed automatically, there is no risk to take.
        if (!$this->supports_autofix()) {
            return false;
        }

        // Check if at least one finding is defined as risky.
        $definitions = $this->get_all_finding_definitions();
        foreach ($this->get_finding_ids() as $findingid) {
            if (!empty($definitions[$findingid]['risky'])) {
                return true;
            }
        }

        // The automatic fix is harmless.
        return false;
    }

    /**
     * Return what the admin has to do himself after the automatic fix of this health check item has been applied.
     *
     * This collects the 'followup' texts of the findings which the automatic fix would fix, in the order in which the
     * findings are defined. Most fixes do the whole job and the list is empty then.
     *
     * Please note that this has to be called before autofix() is called: The automatic fix invalidates the caches and
     * the findings which have just been fixed are not reported anymore afterwards.
     *
     * @return string[]
     */
    final public function get_autofix_followups(): array {
        // If the item cannot be fixed automatically, there is nothing to follow up.
        if (!$this->supports_autofix()) {
            return [];
        }

        // Collect the follow-up texts of the findings. The finding ids are sorted already, see get_result(). An item
        // may hand the same text to several of its findings, thus the texts are deduplicated.
        $definitions = $this->get_all_finding_definitions();
        $followups = [];
        foreach ($this->get_finding_ids() as $findingid) {
            if (!empty($definitions[$findingid]['followup'])) {
                $followups[] = $definitions[$findingid]['followup'];
            }
        }

        return array_values(array_unique($followups));
    }

    /**
     * Apply the automatic fix for this health check item and invalidate the caches afterwards.
     *
     * This function is final on purpose: It does not analyse anything again, it just hands the findings which have been
     * collected already over to apply_autofix(), one finding id after the other and in the order in which the findings
     * are defined. An automatic fix writes to the database, thus the caches have to be invalidated afterwards, and no
     * health check item shall be able to forget that.
     *
     * @return void
     */
    final public function autofix(): void {
        // If the item cannot be fixed automatically, there is nothing to do.
        if (!$this->supports_autofix()) {
            return;
        }

        // Group the contexts of the findings by finding id. The findings are sorted already, see get_result().
        $grouped = [];
        foreach ($this->get_result()['findings'] as $finding) {
            $grouped[$finding['id']][] = $finding['context'];
        }

        // Let the health check item fix one finding id after the other.
        foreach ($grouped as $findingid => $contexts) {
            // A finding which does not carry a context does not have anything to hand over.
            $contexts = array_values(array_filter($contexts, fn($context): bool => $context !== null));
            $this->apply_autofix($findingid, $contexts);
        }

        // Drop the database records and the evaluation results which have been determined before the fix was applied.
        self::reset_caches();
    }

    /**
     * Apply the automatic fix for one finding of this health check item.
     *
     * Items which define at least one finding as fixable implement this function. It is not supposed to analyse
     * anything, it just implements the fix strategy of each finding id. The items do not have to care about the
     * caches, see autofix().
     *
     * @param string $findingid The id of the finding to fix.
     * @param array $contexts The contexts which have been handed over to add_finding() for this finding id.
     * @return void
     */
    protected function apply_autofix(string $findingid, array $contexts): void {
        // No automatic fix available by default.
    }

    /**
     * Reset the shared record cache as well as the evaluation results of all health check items.
     *
     * This is called after an automatic fix has been applied and can be called from unit tests as well.
     *
     * @return void
     */
    public static function reset_caches(): void {
        self::$recordcache = [];
        self::$resultcache = [];
    }

    /**
     * Add a finding to the list of findings of this item.
     *
     * A finding is HTML, as it may contain a list of the things which it names, see format_list(). It is output as it
     * is, thus every dynamic value which goes into a finding has to be HTML-safe already: Values which come from the
     * database or from the user - names of roles, courses, users and the like - have to be escaped with s() or
     * format_string() first. Language strings and values which the plugin controls do not need that.
     *
     * @param string $findingid The id of the finding, which has to be defined in get_finding_definitions().
     * @param string $finding The human readable finding as HTML.
     * @param mixed $context The data which the automatic fix of this finding needs, if any. This saves the automatic
     *                       fix from analysing the problem once more.
     * @return void
     */
    protected function add_finding(string $findingid, string $finding, $context = null): void {
        // A finding which is not defined can neither be fixed nor linked, thus we complain about it.
        if (!array_key_exists($findingid, $this->get_all_finding_definitions())) {
            debugging(
                'Health check "' . $this->get_id() . '" reports the finding "' . $findingid . '" which it does not define.',
                DEBUG_DEVELOPER
            );
        }

        // Remember the finding.
        $this->findings[] = ['id' => $findingid, 'text' => $finding, 'context' => $context];
    }

    /**
     * Return the more severe of two statuses.
     *
     * Health check items which assess more than one aspect run their checks one after another and must not let a
     * later check downgrade the status which an earlier one has raised. They pass their current status and the status
     * of the aspect which they have just assessed to this function and keep the result.
     *
     * @param string $status The status which the item has determined so far.
     * @param string $candidate The status of the aspect which has just been assessed.
     * @return string The more severe of the two statuses.
     */
    protected function escalate(string $status, string $candidate): string {
        // Define the severity order of the statuses which a health check item can escalate between.
        // The N/A status is not part of this order as it is never combined with another status.
        $severity = [
            self::OK => 0,
            self::NOTICE => 1,
            self::WARNING => 2,
            self::ERROR => 3,
        ];

        // Return the more severe status of the two.
        return ($severity[$candidate] > $severity[$status]) ? $candidate : $status;
    }

    /**
     * Return the SEMCO webservice role record.
     *
     * @return \stdClass|null The role record or null if the role does not exist.
     */
    protected function get_semco_role(): ?\stdClass {
        global $DB;

        // If the record is not cached yet, fetch it.
        if (!array_key_exists('role', self::$recordcache)) {
            $record = $DB->get_record('role', ['shortname' => ENROL_SEMCO_ROLEANDUSERNAME]);
            self::$recordcache['role'] = ($record !== false) ? $record : null;
        }

        // Return the cached record.
        return self::$recordcache['role'];
    }

    /**
     * Return the SEMCO webservice user record.
     *
     * @return \stdClass|null The user record or null if the user does not exist.
     */
    protected function get_semco_user(): ?\stdClass {
        global $DB;

        // If the record is not cached yet, fetch it.
        if (!array_key_exists('user', self::$recordcache)) {
            $record = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME, 'deleted' => 0]);
            self::$recordcache['user'] = ($record !== false) ? $record : null;
        }

        // Return the cached record.
        return self::$recordcache['user'];
    }

    /**
     * Return the SEMCO external service record.
     *
     * @return \stdClass|null The external service record or null if the service does not exist.
     */
    protected function get_semco_service(): ?\stdClass {
        global $DB;

        // If the record is not cached yet, fetch it.
        if (!array_key_exists('service', self::$recordcache)) {
            $record = $DB->get_record('external_services', ['shortname' => ENROL_SEMCO_SERVICENAME]);
            self::$recordcache['service'] = ($record !== false) ? $record : null;
        }

        // Return the cached record.
        return self::$recordcache['service'];
    }

    /**
     * Count the courses which hold SEMCO enrolments.
     *
     * Health check items which assess the courses which SEMCO uses report how many of them are affected by a problem.
     * They use this function to name the total amount of courses next to it, as an amount alone does not tell an admin
     * whether a problem affects a corner case or the whole integration.
     *
     * Please note that SEMCO adds and removes its enrolment instances on the fly, see the plugin's README. A course
     * which SEMCO sells but in which nobody is enrolled at the moment does not hold any enrolment instance and is
     * therefore not counted here.
     *
     * The items of the recompletion category restrict themselves to the courses which have completion tracking enabled
     * in their course settings, as there is no course completion which SEMCO could reset in the other courses. They
     * ask for the matching total so that the amount of affected courses is compared with the same set of courses.
     *
     * @param bool $completiononly Whether to count only the courses which have completion tracking enabled.
     * @return int The amount of courses which hold SEMCO enrolments.
     */
    protected function count_semco_courses(bool $completiononly = false): int {
        global $DB;

        // The two totals are cached separately.
        $cachekey = $completiononly ? 'semcocoursecountcompletion' : 'semcocoursecount';

        // If the count is not cached yet, fetch it.
        if (!array_key_exists($cachekey, self::$recordcache)) {
            $sql = 'SELECT COUNT(DISTINCT e.courseid)
                    FROM {enrol} e
                    JOIN {course} c ON c.id = e.courseid
                    WHERE e.enrol = :enrol';
            $params = ['enrol' => 'semco'];
            if ($completiononly) {
                $sql .= ' AND c.enablecompletion = :enablecompletion';
                $params['enablecompletion'] = 1;
            }
            self::$recordcache[$cachekey] = (int) $DB->count_records_sql($sql, $params);
        }

        // Return the cached count.
        return self::$recordcache[$cachekey];
    }

    /**
     * Get the courses which hold SEMCO enrolments and in which a role effectively holds a capability.
     *
     * Health check items which assess a capability of the SEMCO enrolment role read the role definition in the system
     * context. A permission override can grant the very same capability again without showing up there, thus the items
     * use this function to look into the courses which SEMCO actually uses.
     *
     * @param string $capability The capability to look for.
     * @param int $roleid The id of the role to look for.
     * @param bool $completiononly Whether to look only into the courses which have completion tracking enabled, see
     *                             count_semco_courses().
     * @return int[] The ids of the affected courses.
     */
    protected function get_semco_courses_with_capability(
        string $capability,
        int $roleid,
        bool $completiononly = false
    ): array {
        $roles = $this->get_roles_with_capability_in_semco_courses($capability, $completiononly);
        return array_key_exists($roleid, $roles) ? $roles[$roleid] : [];
    }

    /**
     * Get the roles which effectively hold a capability in the courses which hold SEMCO enrolments.
     *
     * Health check items which want to know whether any role holds a capability read the role definitions in the system
     * context. A permission override can grant the very same capability again without showing up there, thus the items
     * use this function to look into the courses which SEMCO actually uses.
     *
     * The permission is not resolved by this function itself, it is left to the Moodle core function
     * get_roles_with_cap_in_context(). That way, the override of a course, the override of the category above it and
     * the role definition itself are weighted exactly like Moodle weights them when it evaluates the capability at
     * runtime, including the special role of a 'Prohibit' permission.
     *
     * @param string $capability The capability to look for.
     * @param bool $completiononly Whether to look only into the courses which have completion tracking enabled, see
     *                             count_semco_courses().
     * @return int[][] The ids of the affected courses, keyed by the id of the role which holds the capability there.
     */
    protected function get_roles_with_capability_in_semco_courses(string $capability, bool $completiononly = false): array {
        global $DB;

        // Get the course contexts of the courses which hold SEMCO enrolments. The context path is all that
        // get_roles_with_cap_in_context() needs, thus there is no need to instantiate the context objects.
        $sql = 'SELECT DISTINCT ctx.id, ctx.instanceid, ctx.path
                FROM {enrol} e
                JOIN {course} c ON c.id = e.courseid
                JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextlevel
                WHERE e.enrol = :enrol';
        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'enrol' => 'semco',
        ];
        if ($completiononly) {
            $sql .= ' AND c.enablecompletion = :enablecompletion';
            $params['enablecompletion'] = 1;
        }
        $contexts = $DB->get_records_sql($sql, $params);

        // Pick the roles which hold the capability in each course in the end.
        $roles = [];
        foreach ($contexts as $context) {
            [$needed, $forbidden] = get_roles_with_cap_in_context($context, $capability);
            foreach (array_keys($needed) as $roleid) {
                if (!isset($forbidden[$roleid])) {
                    $roles[(int) $roleid][] = (int) $context->instanceid;
                }
            }
        }

        // Return the ids of the affected courses per role.
        return $roles;
    }

    /**
     * Format the given items as a HTML list for a finding.
     *
     * Findings are HTML, see add_finding(), and a finding which names several things - courses, roles, users - names
     * them as a list rather than in the running text, so that the admin can read them at a glance. If a maximum is
     * given, the list is cut off after that many items to keep the finding readable and the remaining items are just
     * counted in the last item of the list.
     *
     * @param string[] $items The items to list. They have to be HTML-safe already, see add_finding().
     * @param int $max The maximum amount of items to name, or 0 to name them all.
     * @return string The HTML list.
     */
    protected function format_list(array $items, int $max = 0): string {
        // Cut the list off and count the rest.
        if ($max > 0 && count($items) > $max) {
            $more = count($items) - $max;
            $items = array_slice($items, 0, $max);
            $items[] = get_string('healthcheck_listmore', 'enrol_semco', $more);
        }

        // Render the list. The bottom margin is removed as the list either ends the finding or is followed by the rest
        // of the finding's sentence.
        return \core\output\html_writer::alist($items, ['class' => 'mb-0']);
    }

    /**
     * Name the given courses in a HTML list for a finding.
     *
     * Health check items which report a problem in particular courses use this function to tell the admin which
     * courses are affected, as an amount alone does not tell him where to go. The list is cut off after
     * MAX_NAMED_COURSES courses to keep the finding readable, the remaining courses are just counted.
     *
     * @param int[] $courseids The ids of the courses to name.
     * @return string The HTML list of courses, each one named by its full name and its short name.
     */
    protected function name_courses(array $courseids): string {
        global $DB;

        // If there is nothing to name, there is nothing to do.
        if (count($courseids) < 1) {
            return '';
        }

        // Get the courses, sorted by their full name.
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $courses = $DB->get_records_select(
            'course',
            'id ' . $insql,
            $inparams,
            'fullname, shortname, id',
            'id, fullname, shortname'
        );

        // Name the courses. format_string() returns HTML-safe text.
        $names = [];
        foreach ($courses as $course) {
            $names[] = format_string($course->fullname) . ' (' . format_string($course->shortname) . ')';
        }

        return $this->format_list($names, self::MAX_NAMED_COURSES);
    }

    /**
     * Return the role which is configured as SEMCO enrolment role in the plugin settings.
     *
     * This is not the SEMCO webservice role which get_semco_role() returns. It is the role which SEMCO assigns to the
     * users which it enrols into courses.
     *
     * @return \stdClass|null The role record or null if the setting is empty or points to a role which does not exist.
     */
    protected function get_configured_enrolment_role(): ?\stdClass {
        global $DB;

        // If the record is not cached yet, fetch it.
        if (!array_key_exists('enrolmentrole', self::$recordcache)) {
            // Get the configured enrolment role.
            $enrolmentroleid = get_config('enrol_semco', 'role');

            // If the setting is empty, there is no role to return.
            if (empty($enrolmentroleid)) {
                self::$recordcache['enrolmentrole'] = null;
            } else {
                $record = $DB->get_record('role', ['id' => $enrolmentroleid]);
                self::$recordcache['enrolmentrole'] = ($record !== false) ? $record : null;
            }
        }

        // Return the cached record.
        return self::$recordcache['enrolmentrole'];
    }

    /**
     * Return the SEMCO user profile field category record.
     *
     * @return \stdClass|null The user profile field category record or null if the category does not exist.
     */
    protected function get_semco_profilefield_category(): ?\stdClass {
        global $DB;

        // If the record is not cached yet, fetch it.
        if (!array_key_exists('profilefieldcategory', self::$recordcache)) {
            $record = $DB->get_record('user_info_category', ['name' => ENROL_SEMCO_USERFIELDCATEGORY]);
            self::$recordcache['profilefieldcategory'] = ($record !== false) ? $record : null;
        }

        // Return the cached record.
        return self::$recordcache['profilefieldcategory'];
    }
}
