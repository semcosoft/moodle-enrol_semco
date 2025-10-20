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
 * Enrolment method "SEMCO" - Privacy provider
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy Subsystem implementing null provider.
 *
 * @package    enrol_semco
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param  collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'SEMCO',
            [
                'user_profile' => 'privacy:metadata:enrol_semco:SEMCO:user_profile',
                'course_enrolments' => 'privacy:metadata:enrol_semco:SEMCO:course_enrolments',
                'course_completions' => 'privacy:metadata:enrol_semco:SEMCO:course_completions',
            ],
            'privacy:metadata:enrol_semco:SEMCO'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param  int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // This Privacy API function does not return real data (yet), it is just there to keep the API happy.
        // This is because the user data is mostly imported from SEMCO to Moodle and not the other way round.
        // Identifying contexts from which data has been exported from Moodle to SEMCO is not obvious.
        return new contextlist();
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        // This Privacy API function does not return real data (yet), it is just there to keep the API happy.
        // This is because the user data is mostly imported from SEMCO to Moodle and not the other way round.
        // Identifying users of whom data has been exported from Moodle to SEMCO is not obvious.
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // This Privacy API function does not return real data, it is just there to keep the API happy.
        // There is no personal user data in Moodle to be exported.
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // This Privacy API function does not return real data, it is just there to keep the API happy.
        // There is no personal user data to be deleted which would not be deleted already otherwise
        // if the user account is deleted.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // This Privacy API function does not return real data, it is just there to keep the API happy.
        // There is no personal user data to be deleted which would not be deleted already otherwise
        // if the user account is deleted.
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // This Privacy API function does not return real data, it is just there to keep the API happy.
        // There is no personal user data to be deleted which would not be deleted already otherwise
        // if the user account is deleted.
    }
}
