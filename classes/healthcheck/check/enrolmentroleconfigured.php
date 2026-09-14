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
 * Enrolment method "SEMCO" - Health check: SEMCO enrolment role
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Health check which verifies that a valid SEMCO enrolment role is configured in the plugin settings.
 *
 * This is the entry point for all items which assess the SEMCO enrolment role: They can only be assessed if this one
 * reports an intact state. Please note that the SEMCO enrolment role is not the SEMCO webservice role. It is the role
 * which SEMCO assigns to the users which it enrols into courses.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolmentroleconfigured extends healthcheck {
    /** @var string Finding: There is not any role configured as SEMCO enrolment role. */
    public const FINDING_EMPTY = 'empty';

    /** @var string Finding: The role which is configured as SEMCO enrolment role does not exist anymore. */
    public const FINDING_DELETED = 'deleted';

    /**
     * Return the health check item id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'enrolmentroleconfigured';
    }

    /**
     * Return the health check item title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_enrolmentroleconfigured_title', 'enrol_semco');
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_enrolmentroleconfigured_summary', 'enrol_semco');
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_enrolmentroleconfigured_description', 'enrol_semco');
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
        // If a valid enrolment role is configured, everything is fine.
        if ($this->get_configured_enrolment_role() !== null) {
            return healthcheck::OK;
        }

        // Otherwise, SEMCO cannot enrol anyone into courses, as the enrolment webservice refuses to work without a
        // role which it can assign. Name the reason: Either the setting is empty.
        $enrolmentroleid = get_config('enrol_semco', 'role');
        if (empty($enrolmentroleid)) {
            $this->add_finding(
                self::FINDING_EMPTY,
                get_string('healthcheck_enrolmentroleconfigured_findingempty', 'enrol_semco')
            );

            // Or it points to a role which does not exist anymore. Moodle does not touch the plugin setting when a role
            // is deleted.
        } else {
            $this->add_finding(
                self::FINDING_DELETED,
                get_string('healthcheck_enrolmentroleconfigured_findingdeleted', 'enrol_semco', $enrolmentroleid)
            );
        }
        return healthcheck::ERROR;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * An empty setting is filled with the plugin's default role automatically, which is the role which the plugin
     * installer and the settings page would pick as well. Nobody has chosen an empty setting on purpose, thus this is
     * harmless, but the admin is asked to verify the choice afterwards. This is only offered if there is a default role
     * at all, which is not the case on a site without any role of the 'student' archetype.
     *
     * A setting which points to a deleted role is not fixed automatically: The admin has picked that role on purpose
     * and picking another one is a decision which only he can make.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        $settingsurl = new \core\url('/admin/settings.php', ['section' => 'enrolsettingssemco']);
        if (!empty(enrol_semco_get_firststudentroleid())) {
            $emptydefinition = [
                'autofix' => true,
                'risky' => false,
                'url' => $settingsurl,
                'followup' => get_string('healthcheck_enrolmentroleconfigured_followupempty', 'enrol_semco'),
            ];
        } else {
            $emptydefinition = ['autofix' => false, 'risky' => false, 'url' => $settingsurl];
        }
        return [
            self::FINDING_EMPTY => $emptydefinition,
            self::FINDING_DELETED => ['autofix' => false, 'risky' => false, 'url' => $settingsurl],
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
            // Configure the plugin's default role, just as the settings page does, including its update callback which
            // allows the SEMCO webservice role to assign the enrolment role.
            case self::FINDING_EMPTY:
                autofix::configure_default_enrolment_role();
                break;
        }
    }
}
