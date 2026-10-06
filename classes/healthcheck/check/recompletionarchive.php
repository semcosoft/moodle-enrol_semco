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
 * Enrolment method "SEMCO" - Health check: local_recompletion data archiving
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that local_recompletion archives the data which it deletes when SEMCO resets a user's
 * course completion.
 *
 * The data which a course completion reset deletes is lost for good unless local_recompletion archives it. This
 * concerns the course and activity completion data on the one hand, which is archived if the 'Archive completion
 * data' setting of the course or the site-wide 'Force archive completion data' switch asks for it. And it concerns
 * the data of the activities themselves on the other hand, which is only archived if the 'Archive' option of the
 * particular activity type is enabled in the course. The latter is not covered by the site-wide switch.
 *
 * This item assesses the site-wide settings of local_recompletion as well as the courses which hold SEMCO enrolments,
 * as the site-wide settings only prefill the course settings and do not change the courses which exist already.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompletionarchive extends healthcheck {
    /** @var string Finding: The site-wide default does not archive the completion data. */
    public const FINDING_SITEDEFAULT = 'sitedefault';

    /** @var string Finding: The archiving of the completion data is not forced for all courses. */
    public const FINDING_FORCE = 'force';

    /** @var string Finding: The site-wide settings do not archive the data of some activity types which are reset. */
    public const FINDING_ACTIVITIES = 'activities';

    /** @var string Finding: Courses with SEMCO enrolments do not archive all data which a reset deletes. */
    public const FINDING_COURSES = 'courses';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'recompletionarchive';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_recompletionarchive_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_recompletionarchive_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_recompletionarchive_description', 'enrol_semco');
    }

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_RECOMPLETION;
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        global $CFG;

        // If local_recompletion is not installed, this check cannot be assessed.
        if (enrol_semco_check_local_recompletion() != true) {
            $this->add_finding(
                self::FINDING_NORECOMPLETION,
                get_string('healthcheck_findingnorecompletion', 'enrol_semco')
            );
            return healthcheck::NA;
        }

        // We need the local_recompletion library for the list of supported activity types and for the constant which
        // marks an activity type as being deleted.
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');

        // Start with an intact state.
        $status = healthcheck::OK;

        // Check the site-wide default which local_recompletion uses to prefill the course settings.
        // local_recompletion itself evaluates the setting with a truthiness check, see its check_recompletion task,
        // thus an unset setting is just as disabled as a value of 0.
        if (empty(get_config('local_recompletion', 'archivecompletiondata'))) {
            $this->add_finding(
                self::FINDING_SITEDEFAULT,
                get_string('healthcheck_recompletionarchive_findingsitedefault', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // Check the site-wide switch which forces the archiving of the completion data in all courses. Unlike the
        // setting above, this switch is not copied into the courses, it is evaluated directly when a course completion
        // is reset.
        $forced = !empty(get_config('local_recompletion', 'forcearchivecompletiondata'));
        if (!$forced) {
            $this->add_finding(
                self::FINDING_FORCE,
                get_string('healthcheck_recompletionarchive_findingforce', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // Check the site-wide archive settings of the activity types. An activity type only archives its data if it
        // is set to delete it in the first place, see the reset() functions of the local_recompletion plugins, thus
        // only the activity types which are deleted are assessed. The other activity types are covered by the
        // 'Activity reset' item.
        $notarchived = [];
        foreach ($this->get_archivable_activity_types() as $settingname => $component) {
            if (
                (int) get_config('local_recompletion', $settingname) === LOCAL_RECOMPLETION_DELETE
                && empty(get_config('local_recompletion', 'archive' . $settingname))
            ) {
                $notarchived[$settingname] = get_string('pluginname', $component);
            }
        }
        if (count($notarchived) > 0) {
            $this->add_finding(
                self::FINDING_ACTIVITIES,
                get_string('healthcheck_recompletionarchive_findingactivities', 'enrol_semco', [
                    'count' => count($notarchived),
                    'activities' => $this->format_list(array_values($notarchived)),
                ]),
                array_keys($notarchived)
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // The site-wide settings only prefill the course settings, they do not change the courses which exist
        // already. Thus, get the courses which hold SEMCO enrolments and which do not archive all data which a reset
        // deletes. The courses are named, as an admin who fixes them himself has to look into each of them. The ids
        // of the courses along with the settings which have to be enabled are handed over as context, so that the
        // automatic fix knows what to change.
        $weakenedcourses = $this->get_weakened_semco_courses($forced);
        if (count($weakenedcourses) > 0) {
            $this->add_finding(
                self::FINDING_COURSES,
                get_string('healthcheck_recompletionarchive_findingcourses', 'enrol_semco', [
                    'count' => count($weakenedcourses),
                    'total' => $this->count_semco_courses(true),
                    'courses' => $this->name_courses(array_keys($weakenedcourses)),
                ]),
                $weakenedcourses
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // Return the status.
        return $status;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * All fixes only enable an archive copy of data which is deleted anyway, thus they are harmless. This includes the
     * course settings, as an archive copy is not visible to the users of the course.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        $settingsurl = new \core\url('/admin/settings.php', ['section' => 'local_recompletion']);
        return [
            self::FINDING_SITEDEFAULT => ['autofix' => true, 'risky' => false, 'url' => $settingsurl],
            self::FINDING_FORCE => ['autofix' => true, 'risky' => false, 'url' => $settingsurl],
            self::FINDING_ACTIVITIES => ['autofix' => true, 'risky' => false, 'url' => $settingsurl],
            // The settings are configured per course, thus there is no single page to link to.
            self::FINDING_COURSES => ['autofix' => true, 'risky' => false, 'url' => null],
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
            // Enable the archiving of the completion data in the site-wide default.
            case self::FINDING_SITEDEFAULT:
                autofix::set_recompletion_site_config('archivecompletiondata', '1');
                break;

            // Force the archiving of the completion data for all courses.
            case self::FINDING_FORCE:
                autofix::set_recompletion_site_config('forcearchivecompletiondata', '1');
                break;

            // Enable the archiving of the affected activity types in the site-wide settings.
            case self::FINDING_ACTIVITIES:
                foreach (array_merge(...$contexts) as $settingname) {
                    autofix::set_recompletion_site_config('archive' . $settingname, '1');
                }
                break;

            // Enable the archive settings which the affected courses lack. Each course lacks its own set of settings,
            // thus the courses are fixed one by one.
            case self::FINDING_COURSES:
                foreach ($contexts as $context) {
                    foreach ($context as $courseid => $settingnames) {
                        foreach ($settingnames as $settingname) {
                            autofix::set_recompletion_course_config([$courseid], $settingname, '1');
                        }
                    }
                }
                break;
        }
    }

    /**
     * Get the activity types which local_recompletion can archive.
     *
     * Not every supported activity type offers an archive option, for example the assignment submissions are deleted
     * without an archive copy. And the archive option is only assessable site-wide if the activity type brings a
     * site-wide setting along.
     *
     * @return string[] The frankenstyle components of the activity types, indexed by the name of their
     *                  local_recompletion setting.
     */
    protected function get_archivable_activity_types(): array {
        $types = [];
        foreach (local_recompletion_get_supported_plugins() as $component) {
            // The settings of an activity type are named after the module without its 'mod_' prefix.
            $settingname = preg_replace('/^mod_/', '', $component);
            if (get_config('local_recompletion', 'archive' . $settingname) !== false) {
                $types[$settingname] = $component;
            }
        }
        return $types;
    }

    /**
     * Get the courses which hold SEMCO enrolments and which do not archive all data which a reset deletes.
     *
     * A course is picked if it does not archive the completion data while the site-wide switch does not force the
     * archiving, or if it deletes the data of an activity type without archiving it. A course which does not hold a
     * setting at all does not archive either, thus a missing setting counts just like a disabled setting.
     *
     * @param bool $forced Whether the site-wide switch forces the archiving of the completion data.
     * @return array[] The names of the settings which have to be enabled, indexed by the id of the affected course.
     */
    protected function get_weakened_semco_courses(bool $forced): array {
        global $DB;

        // Collect the names of the relevant course settings.
        $activitytypes = array_keys($this->get_archivable_activity_types());
        $settingnames = ['archivecompletiondata'];
        foreach ($activitytypes as $settingname) {
            $settingnames[] = $settingname;
            $settingnames[] = 'archive' . $settingname;
        }

        // Get the relevant course settings of all courses which hold SEMCO enrolments.
        [$insql, $inparams] = $DB->get_in_or_equal($settingnames, SQL_PARAMS_NAMED, 'name');
        // Courses which do not have completion tracking enabled in their course settings are out of scope, as there is no
        // course completion which SEMCO could reset there.
        $sql = 'SELECT c.id AS courseid, rc.name AS settingname, rc.value AS settingvalue
                FROM {enrol} e
                JOIN {course} c ON c.id = e.courseid
                LEFT JOIN {local_recompletion_config} rc ON rc.course = c.id AND rc.name ' . $insql . '
                WHERE e.enrol = :enrol AND c.enablecompletion = :enablecompletion';
        $params = array_merge($inparams, ['enrol' => 'semco', 'enablecompletion' => 1]);

        // Pick the courses apart.
        $coursesettings = [];
        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $record) {
            if (!array_key_exists($record->courseid, $coursesettings)) {
                $coursesettings[$record->courseid] = [];
            }
            if ($record->settingname !== null) {
                $coursesettings[$record->courseid][$record->settingname] = $record->settingvalue;
            }
        }
        $recordset->close();

        // Pick the courses which lack an archive setting.
        $weakened = [];
        foreach ($coursesettings as $courseid => $settings) {
            $missing = [];
            if (!$forced && empty($settings['archivecompletiondata'])) {
                $missing[] = 'archivecompletiondata';
            }
            foreach ($activitytypes as $settingname) {
                if (
                    (int) ($settings[$settingname] ?? LOCAL_RECOMPLETION_NOTHING) === LOCAL_RECOMPLETION_DELETE
                    && empty($settings['archive' . $settingname])
                ) {
                    $missing[] = 'archive' . $settingname;
                }
            }
            if (count($missing) > 0) {
                $weakened[(int) $courseid] = $missing;
            }
        }

        // Return the affected courses.
        return $weakened;
    }
}
