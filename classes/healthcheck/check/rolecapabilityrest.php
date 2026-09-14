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
 * Enrolment method "SEMCO" - Health check: SEMCO webservice role REST capability
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that the SEMCO webservice role is allowed to use the REST protocol.
 *
 * This capability has an own health check item as it is not necessarily set during the plugin installation.
 * If the plugin is installed during an initial Moodle installation, the capability does not exist yet at that point
 * in time and is set by an ad-hoc task afterwards.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rolecapabilityrest extends rolecapabilities {
    /** @var string Finding: The ad-hoc task which is going to assign the capability is still queued. */
    public const FINDING_TASKQUEUED = 'taskqueued';

    /** @var string Finding: The ad-hoc task which is going to assign the capability is queued, but overdue. */
    public const FINDING_TASKOVERDUE = 'taskoverdue';

    /** @var string Finding: The ad-hoc task which is going to assign the capability has failed. */
    public const FINDING_TASKFAILED = 'taskfailed';

    /** @var string Finding: There is not any ad-hoc task which would assign the capability. */
    public const FINDING_NOTASK = 'notask';

    /** @var int The period which we grant cron to process the queued ad-hoc task before we report an error. */
    protected const TASK_GRACE_PERIOD = 3 * MINSECS;

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'rolecapabilityrest';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_rolecapabilityrest_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_rolecapabilityrest_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_rolecapabilityrest_description', 'enrol_semco');
    }

    /**
     * Return the capabilities which the SEMCO webservice role must hold in the system context.
     *
     * @return string[]
     */
    public function get_capabilities(): array {
        return [
            'webservice/rest:use',
        ];
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // Let the parent class determine the status.
        $status = parent::determine_status();

        // If the capability is present, the ad-hoc task has done its job (or was never needed) and we are done.
        if ($status !== healthcheck::ERROR) {
            return $status;
        }

        // The capability is missing. Have a look at the ad-hoc task which is supposed to assign it.
        $classname = \enrol_semco\task\set_webservice_capability::class;
        $queued = \core\task\manager::get_adhoc_tasks($classname);
        $failed = \core\task\manager::get_adhoc_tasks($classname, true);

        // If the task exists but is failing, the situation is not going to resolve itself.
        if (count($failed) > 0) {
            $this->add_finding(
                self::FINDING_TASKFAILED,
                get_string('healthcheck_rolecapabilityrest_findingtaskfailed', 'enrol_semco')
            );
            return healthcheck::ERROR;

            // If the task is queued but overdue, cron does not seem to process it. Strictly speaking, the SEMCO
            // integration does not work as long as the capability is missing, regardless of the task. We only tolerate
            // this state for the short period which a healthy cron needs to pick the task up, afterwards we report the
            // broken installation which it is.
        } else if (count($queued) > 0 && $this->get_earliest_run_time($queued) < time() - self::TASK_GRACE_PERIOD) {
            $this->add_finding(self::FINDING_TASKOVERDUE, get_string(
                'healthcheck_rolecapabilityrest_findingtaskoverdue',
                'enrol_semco',
                format_time(self::TASK_GRACE_PERIOD)
            ));
            return healthcheck::ERROR;

            // If the task is still queued and healthy, we just raise the admin's awareness instead of reporting a
            // broken installation, as the next cron run is going to assign the capability.
        } else if (count($queued) > 0) {
            $this->add_finding(
                self::FINDING_TASKQUEUED,
                get_string('healthcheck_rolecapabilityrest_findingtaskqueued', 'enrol_semco')
            );
            return healthcheck::NOTICE;

            // Otherwise, the capability is missing and nothing is going to assign it anymore.
        } else {
            $this->add_finding(
                self::FINDING_NOTASK,
                get_string('healthcheck_rolecapabilityrest_findingnotask', 'enrol_semco')
            );
            return healthcheck::ERROR;
        }
    }

    /**
     * Return the earliest point in time at which one of the given ad-hoc tasks was supposed to run.
     *
     * @param \core\task\adhoc_task[] $tasks The queued ad-hoc tasks.
     * @return int The earliest next run time as unix timestamp.
     */
    protected function get_earliest_run_time(array $tasks): int {
        return (int) min(array_map(fn(\core\task\adhoc_task $task) => $task->get_next_run_time(), $tasks));
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * The findings about the ad-hoc task never come alone, they just explain the state of the missing capability which
     * the parent class reports. Thus, they are fixed as soon as the missing capability is fixed, and they do not need a
     * fix strategy of their own in apply_autofix().
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        // Get the definitions of the parent class and reuse the URL of the missing capability.
        $definitions = parent::get_finding_definitions();
        $definition = ['autofix' => true, 'risky' => false, 'url' => $definitions[self::FINDING_MISSING]['url']];

        return $definitions + [
            self::FINDING_TASKFAILED => $definition,
            self::FINDING_TASKOVERDUE => $definition,
            self::FINDING_TASKQUEUED => $definition,
            self::FINDING_NOTASK => $definition,
        ];
    }
}
