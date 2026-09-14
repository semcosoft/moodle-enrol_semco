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
 * Enrolment method "SEMCO" - Health check: Exclusiveness of the webservice capabilities
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the plugin's webservice capabilities are held by the SEMCO webservice role only.
 *
 * These capabilities are not granted to any role archetype by default and the plugin installer assigns them to the
 * SEMCO webservice role only. They must not be granted to a role which is used by humans in the Moodle GUI, see the
 * capability chapter of the plugin's README.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capabilitiesexclusive extends healthcheck {
    /** @var string Finding: The definition of another role grants a webservice capability of this plugin. */
    public const FINDING_ROLE = 'role';

    /** @var string Finding: A webservice capability of this plugin is granted to another role by permission overrides. */
    public const FINDING_OVERRIDE = 'override';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'capabilitiesexclusive';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_capabilitiesexclusive_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_capabilitiesexclusive_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_capabilitiesexclusive_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_PLUGIN;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        global $DB;

        // Get the SEMCO webservice role. It is the only role which is allowed to hold these capabilities.
        // If the role does not exist, we do not exclude any role from the query and report every grant which we find.
        $role = $this->get_semco_role();
        $semcoroleid = ($role !== null) ? $role->id : 0;

        // Get all grants of these capabilities to other roles, in any context.
        [$insql, $inparams] = $DB->get_in_or_equal((new rolecapabilitiessemco())->get_capabilities(), SQL_PARAMS_NAMED, 'cap');
        $sql = 'SELECT rc.id, rc.capability, rc.contextid, r.id AS roleid, r.name, r.shortname, r.archetype
                FROM {role_capabilities} rc
                JOIN {role} r ON r.id = rc.roleid
                WHERE rc.capability ' . $insql . '
                    AND rc.permission = :allow
                    AND rc.roleid <> :semcoroleid
                ORDER BY r.shortname, rc.capability';
        $params = $inparams + ['allow' => CAP_ALLOW, 'semcoroleid' => $semcoroleid];
        $grants = $DB->get_records_sql($sql, $params);

        // If there is not any grant to another role, everything is fine.
        if (count($grants) < 1) {
            return healthcheck::OK;
        }

        // Group the grants by role and capability. A grant in the system context comes from the role definition and
        // can exist only once per role and capability, every other grant is a context override and can exist as often
        // as there are contexts. Without this grouping, a capability which is overridden in many courses would produce
        // a wall of identical findings.
        $systemcontextid = \context_system::instance()->id;
        $rolenames = role_fix_names(get_all_roles(), \context_system::instance(), ROLENAME_ORIGINAL);
        $grouped = [];
        foreach ($grants as $grant) {
            // Compose the role name. We fall back to the shortname if the role has vanished in the meantime.
            // The localised role name is HTML-safe already, the shortname from the database is not.
            $rolename = isset($rolenames[$grant->roleid]) ? $rolenames[$grant->roleid]->localname : s($grant->shortname);

            // Remember the grant within its group.
            $key = $grant->roleid . '/' . $grant->capability;
            if (!array_key_exists($key, $grouped)) {
                $grouped[$key] = [
                    'roleid' => (int) $grant->roleid,
                    'capability' => $grant->capability,
                    'role' => $rolename,
                    'inrole' => false,
                    'overrides' => [],
                ];
            }
            if ((int) $grant->contextid === $systemcontextid) {
                $grouped[$key]['inrole'] = true;
            } else {
                $grouped[$key]['overrides'][] = (int) $grant->contextid;
            }
        }

        // Report one finding per role and capability. The role, the capability and the affected contexts are handed
        // over as context, so that the automatic fix knows what to revoke.
        foreach ($grouped as $group) {
            // The role definition itself grants the capability.
            if ($group['inrole'] === true) {
                $this->add_finding(
                    self::FINDING_ROLE,
                    get_string('healthcheck_capabilitiesexclusive_findingrole', 'enrol_semco', [
                        'capability' => $group['capability'],
                        'role' => $group['role'],
                    ]),
                    ['roleid' => $group['roleid'], 'capability' => $group['capability']]
                );
            }

            // And / or the capability is granted with permission overrides in other contexts.
            if (count($group['overrides']) > 0) {
                $this->add_finding(
                    self::FINDING_OVERRIDE,
                    get_string('healthcheck_capabilitiesexclusive_findingoverride', 'enrol_semco', [
                        'capability' => $group['capability'],
                        'role' => $group['role'],
                        'count' => count($group['overrides']),
                    ]),
                    [
                        'roleid' => $group['roleid'],
                        'capability' => $group['capability'],
                        'contextids' => $group['overrides'],
                    ]
                );
            }
        }

        // This is a security issue, thus we report a warning.
        return healthcheck::WARNING;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * Both findings are fixed automatically by revoking the grants, but this is not entirely harmless: The affected
     * roles are used by humans and we cannot know why somebody has granted the capabilities to them. Both findings
     * link to the capability overview report which allows to inspect one capability across all roles.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        $reporturl = new \core\url('/admin/tool/capability/index.php');
        return [
            self::FINDING_ROLE => ['autofix' => true, 'risky' => true, 'url' => $reporturl],
            self::FINDING_OVERRIDE => ['autofix' => true, 'risky' => true, 'url' => $reporturl],
        ];
    }

    /**
     * Apply the automatic fix for one finding of this health check item.
     *
     * @param string $findingid The id of the finding to fix.
     * @param array $contexts The contexts which have been handed over to add_finding() for this finding id.
     * @return void
     */
    protected function apply_autofix(string $findingid, array $contexts): void {
        switch ($findingid) {
            // Remove the capability from the role definition, which lives in the system context.
            case self::FINDING_ROLE:
                foreach ($contexts as $grant) {
                    autofix::revoke_capability($grant['capability'], $grant['roleid'], \context_system::instance()->id);
                }
                break;

            // Remove the permission overrides from the contexts in which they have been set.
            case self::FINDING_OVERRIDE:
                foreach ($contexts as $grant) {
                    foreach ($grant['contextids'] as $contextid) {
                        autofix::revoke_capability($grant['capability'], $grant['roleid'], $contextid);
                    }
                }
                break;
        }
    }
}
