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
 * Enrolment method "SEMCO" - Health check: SEMCO user profile field (base class)
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\healthcheck\check;

use enrol_semco\healthcheck\autofix;
use enrol_semco\healthcheck\healthcheck;

/**
 * Base class for the health checks which verify one of the SEMCO user profile fields.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class profilefield extends healthcheck {
    /** @var string Finding: The user profile field does not exist. */
    public const FINDING_MISSING = 'missing';

    /** @var string Finding: The user profile field has an unexpected data type. */
    public const FINDING_DATATYPE = 'datatype';

    /** @var string Finding: The user profile field is visible. */
    public const FINDING_VISIBLE = 'visible';

    /** @var string Finding: The user profile field is not locked. */
    public const FINDING_NOTLOCKED = 'notlocked';

    /** @var string Finding: The user profile field is required. */
    public const FINDING_REQUIRED = 'required';

    /** @var string Finding: The user profile field is shown on the signup page. */
    public const FINDING_SIGNUP = 'signup';

    /** @var string Finding: The user profile field does not force unique values although it should. */
    public const FINDING_NOTUNIQUE = 'notunique';

    /** @var string Finding: The user profile field forces unique values although it should not. */
    public const FINDING_UNIQUE = 'unique';

    /** @var string Finding: The user profile field has an unexpected maximum length. */
    public const FINDING_PARAM2 = 'param2';

    /** @var string Finding: The user profile field has an unexpected display size. */
    public const FINDING_PARAM1 = 'param1';

    /** @var string Finding: The user profile field is not placed in the SEMCO user profile field category. */
    public const FINDING_CATEGORY = 'category';

    /** @var string The expected data type of the SEMCO user profile fields. */
    protected const EXPECTED_DATATYPE = 'text';

    /**
     * Return the shortname of the user profile field which this health check item verifies.
     *
     * @return string
     */
    abstract protected function get_shortname(): string;

    /**
     * Return the human readable name of the user profile field which this health check item verifies.
     *
     * @return string
     */
    abstract protected function get_name(): string;

    /**
     * Return the expected display size (param1) of the user profile field.
     *
     * @return int
     */
    abstract protected function get_expected_param_size(): int;

    /**
     * Return the expected maximum length (param2) of the user profile field.
     *
     * @return int
     */
    abstract protected function get_expected_param_length(): int;

    /**
     * Return whether the user profile field is expected to force unique values.
     *
     * @return bool
     */
    abstract protected function is_expected_unique(): bool;

    /**
     * Return the health check item category.
     *
     * @return string
     */
    public function get_category(): string {
        return healthcheck::CATEGORY_PROFILEFIELDS;
    }

    /**
     * Return the health check item title.
     *
     * All SEMCO user profile field health check items share their language strings and just inject their name and
     * shortname.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('healthcheck_profilefield_title', 'enrol_semco', [
            'name' => $this->get_name(),
            'shortname' => $this->get_shortname(),
        ]);
    }

    /**
     * Return the health check item summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string('healthcheck_profilefield_summary', 'enrol_semco', [
            'name' => $this->get_name(),
            'shortname' => $this->get_shortname(),
        ]);
    }

    /**
     * Return the health check item description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('healthcheck_profilefield_description', 'enrol_semco', [
            'name' => $this->get_name(),
            'shortname' => $this->get_shortname(),
            'unique' => $this->is_expected_unique() ?
                ' ' . get_string('healthcheck_profilefield_description_unique', 'enrol_semco') : '',
        ]);
    }

    /**
     * Determine the health check item status.
     *
     * @return string
     */
    protected function determine_status(): string {
        // Get the user profile field.
        $field = $this->get_profile_field();

        // If the field does not exist, SEMCO cannot store this piece of data in Moodle. SEMCO fills these fields within
        // the very same webservice call which creates or updates the user account, but this call does not fail: Moodle
        // core hands the custom fields over to profile_save_data() which only iterates over the fields which exist and
        // silently drops everything else, see core_user_create_users and core_user_update_users in
        // user/externallib.php. SEMCO does not read the fields back either. Thus, the data gets lost silently while
        // the SEMCO integration keeps working, which is a warning and not an error.
        if ($field === null) {
            $this->add_finding(
                self::FINDING_MISSING,
                get_string('healthcheck_profilefield_findingmissing', 'enrol_semco', $this->get_shortname())
            );
            return healthcheck::WARNING;
        }

        // Start with an intact state.
        $status = healthcheck::OK;

        // Check the field's data type.
        if ($field->datatype !== self::EXPECTED_DATATYPE) {
            $this->add_finding(
                self::FINDING_DATATYPE,
                get_string('healthcheck_profilefield_findingdatatype', 'enrol_semco', [
                    'expected' => self::EXPECTED_DATATYPE,
                    'found' => s($field->datatype),
                ])
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // Check that the field is placed in the SEMCO user profile field category. A field in another category still
        // works exactly the same, it is just not grouped as intended, thus this is a cosmetic issue only.
        $category = $this->get_semco_profilefield_category();
        if ($category !== null && (int) $field->categoryid !== (int) $category->id) {
            $this->add_finding(
                self::FINDING_CATEGORY,
                get_string('healthcheck_profilefield_findingcategory', 'enrol_semco', ENROL_SEMCO_USERFIELDCATEGORY)
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // Check that the field is locked and invisible. A field which deviates here can expose the data which SEMCO has
        // written to the users or can let them change it.
        if (empty($field->locked)) {
            $this->add_finding(
                self::FINDING_NOTLOCKED,
                get_string('healthcheck_profilefield_findingnotlocked', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }
        if (!empty($field->visible)) {
            $this->add_finding(self::FINDING_VISIBLE, get_string('healthcheck_profilefield_findingvisible', 'enrol_semco'));
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // Check the field's uniqueness. The SEMCO user ID field must force unique values as SEMCO relies on it to
        // identify a user unambiguously. The other fields must not: Several users legitimately share the same value,
        // and Moodle core validates the uniqueness as soon as somebody saves the profile form of such a user (the
        // webservices which SEMCO uses do not validate it, thus SEMCO itself keeps working).
        if ($this->is_expected_unique() && empty($field->forceunique)) {
            $this->add_finding(
                self::FINDING_NOTUNIQUE,
                get_string('healthcheck_profilefield_findingnotunique', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }
        if (!$this->is_expected_unique() && !empty($field->forceunique)) {
            $this->add_finding(
                self::FINDING_UNIQUE,
                get_string('healthcheck_profilefield_findingunique', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // Check that the field is neither required nor shown on the signup page.
        if (!empty($field->required)) {
            $this->add_finding(
                self::FINDING_REQUIRED,
                get_string('healthcheck_profilefield_findingrequired', 'enrol_semco')
            );
            $status = $this->escalate($status, healthcheck::WARNING);
        }
        if (!empty($field->signup)) {
            $this->add_finding(self::FINDING_SIGNUP, get_string('healthcheck_profilefield_findingsignup', 'enrol_semco'));
            $status = $this->escalate($status, healthcheck::WARNING);
        }

        // Check the field's display size. It only controls the width of the input element in the profile form, thus a
        // deviation is a cosmetic issue only.
        if ((int) $field->param1 !== $this->get_expected_param_size()) {
            $this->add_finding(
                self::FINDING_PARAM1,
                get_string('healthcheck_profilefield_findingparam1', 'enrol_semco', [
                    'expected' => $this->get_expected_param_size(),
                    'found' => (int) $field->param1,
                ])
            );
            $status = $this->escalate($status, healthcheck::NOTICE);
        }

        // Check the field's maximum length. A field which allows more characters than the plugin installer has set
        // holds everything which SEMCO writes, thus this is a cosmetic issue only. A field which allows less
        // characters is too short for SEMCO's data: The webservice does not enforce the maximum length, but the
        // profile form does as soon as somebody edits the profile of such a user.
        if ((int) $field->param2 !== $this->get_expected_param_length()) {
            $this->add_finding(
                self::FINDING_PARAM2,
                get_string('healthcheck_profilefield_findingparam2', 'enrol_semco', [
                    'expected' => $this->get_expected_param_length(),
                    'found' => (int) $field->param2,
                ])
            );
            $tooshort = (int) $field->param2 < $this->get_expected_param_length();
            $status = $this->escalate($status, $tooshort ? healthcheck::WARNING : healthcheck::NOTICE);
        }

        // Return the status.
        return $status;
    }

    /**
     * Return the user profile field record which this health check item verifies.
     *
     * @return \stdClass|null The user profile field record or null if the field does not exist.
     */
    protected function get_profile_field(): ?\stdClass {
        global $DB;

        // Get the user profile field.
        $record = $DB->get_record('user_info_field', ['shortname' => $this->get_shortname()]);

        // Return the user profile field.
        return ($record !== false) ? $record : null;
    }

    /**
     * Return the definitions of the findings which this health check item can report.
     *
     * A field which is gone is recreated automatically, which is harmless: It is exactly what the plugin installer does,
     * and the data which the field has held is lost anyway. A field which sits in the wrong category is moved. A field
     * which forces unique values although it should not is relieved of that constraint, which is harmless as well as
     * it does not touch the data. Any other deviation is not fixed automatically, as the field holds the data which
     * SEMCO has written and may have been adjusted on purpose. The findings which can expose or damage this data are
     * defined first, the cosmetic ones last.
     *
     * @return array[]
     */
    protected function get_finding_definitions(): array {
        $definition = ['autofix' => false, 'risky' => false, 'url' => new \core\url('/user/profile/index.php')];
        return [
            self::FINDING_MISSING => ['autofix' => true] + $definition,
            self::FINDING_DATATYPE => $definition,
            self::FINDING_VISIBLE => $definition,
            self::FINDING_NOTLOCKED => $definition,
            self::FINDING_REQUIRED => $definition,
            self::FINDING_SIGNUP => $definition,
            self::FINDING_NOTUNIQUE => $definition,
            // Dropping the uniqueness constraint is harmless, the data of the field is not affected by that.
            self::FINDING_UNIQUE => ['autofix' => true] + $definition,
            self::FINDING_PARAM2 => $definition,
            self::FINDING_PARAM1 => $definition,
            // Moving the field into the SEMCO user profile field category is harmless, the data of the field is not
            // affected by that.
            self::FINDING_CATEGORY => ['autofix' => true] + $definition,
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
            // Create the user profile field, just as the plugin installer does. The field needs the SEMCO user profile
            // field category to live in, thus the category is created along with it if it is gone as well.
            case self::FINDING_MISSING:
                $category = $this->get_semco_profilefield_category() ?? autofix::create_semco_profilefield_category();
                autofix::create_semco_profilefield(
                    $this->get_shortname(),
                    $this->get_installer_fullname(),
                    $category->id,
                    $this->get_expected_param_size(),
                    $this->get_expected_param_length(),
                    $this->is_expected_unique()
                );
                break;

            // Stop the field from forcing unique values, just as the plugin updater does.
            case self::FINDING_UNIQUE:
                autofix::unset_profilefield_forceunique($this->get_profile_field());
                break;

            // Move the field to the end of the SEMCO user profile field category, which exists as the finding could
            // not have been reported otherwise.
            case self::FINDING_CATEGORY:
                autofix::move_profilefield_to_category(
                    $this->get_profile_field(),
                    $this->get_semco_profilefield_category()->id
                );
                break;
        }
    }

    /**
     * Return the full name which the plugin installer gives to the user profile field of this health check item.
     *
     * @return string
     */
    protected function get_installer_fullname(): string {
        // The plugin installer numbers the language strings of the fields in the order in which it creates them.
        $stringkeys = [
            ENROL_SEMCO_USERFIELD1NAME => 'installer_userfield1fullname',
            ENROL_SEMCO_USERFIELD2NAME => 'installer_userfield2fullname',
            ENROL_SEMCO_USERFIELD3NAME => 'installer_userfield3fullname',
            ENROL_SEMCO_USERFIELD4NAME => 'installer_userfield4fullname',
            ENROL_SEMCO_USERFIELD5NAME => 'installer_userfield5fullname',
        ];
        return get_string($stringkeys[$this->get_shortname()], 'enrol_semco');
    }
}
