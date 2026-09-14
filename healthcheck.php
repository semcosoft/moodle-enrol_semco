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
 * Enrolment method "SEMCO" - Health check
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_semco\table\healthchecklist_table;
use enrol_semco\healthcheck\manager;

// Include config.php.
require(__DIR__ . '/../../config.php');

// Globals.
global $CFG, $DB, $PAGE, $OUTPUT;

// Get parameters.
$action = optional_param('action', null, PARAM_ALPHA);

// Get system context.
$context = context_system::instance();

// Access checks.
require_login();
require_capability('enrol/semco:viewhealthcheck', $context);

// Prepare page (to make sure that all necessary information is already set even if we just handle the actions as a start).
$title = get_string('healthcheckpagetitle', 'enrol_semco');
$PAGE->set_context($context);
$PAGE->set_url('/enrol/semco/healthcheck.php');
$PAGE->set_title($title);
$PAGE->set_pagelayout('report');
$PAGE->set_cacheable(false);

// Process actions.
if ($action !== null && confirm_sesskey()) {
    // Every action is based on a health check item, thus the health check item ID param has to exist.
    $id = required_param('id', PARAM_ALPHANUMEXT);

    // Get the health check item.
    $healthcheck = manager::get_healthcheck_by_id($id);

    // Handle the mute action.
    if ($action === 'mute' && $healthcheck !== null) {
        manager::set_healthcheck_muted($healthcheck->get_id(), true);
        \core\notification::success(get_string('healthcheckmutesuccess', 'enrol_semco'));

        // Handle the unmute action.
    } else if ($action === 'unmute' && $healthcheck !== null) {
        manager::set_healthcheck_muted($healthcheck->get_id(), false);
        \core\notification::success(get_string('healthcheckunmutesuccess', 'enrol_semco'));

        // Handle the autofix action.
    } else if ($action === 'autofix') {
        // If the health check item exists and supports an automatic fix.
        if ($healthcheck !== null && $healthcheck->supports_autofix()) {
            // Look up what the admin has to do himself afterwards. This has to be done before the fix is applied, as the
            // findings are gone afterwards.
            $followups = $healthcheck->get_autofix_followups();

            // The automatic fix might be done with more than one DB statement which should have a monolithic effect,
            // so we use a transaction.
            $transaction = $DB->start_delegated_transaction();

            // Apply the automatic fix.
            $healthcheck->autofix();

            // Allow to write the changes to the database.
            $transaction->allow_commit();

            // Compose the notification about that fact, including what is left to do for the admin. The success message
            // becomes a paragraph of its own then, so that there is a small gap before the follow-up. A single follow-up
            // is added as a plain line, several follow-ups are added as a list.
            $message = get_string('healthcheckautofixsuccess', 'enrol_semco');
            if (count($followups) === 1) {
                $message = html_writer::tag('p', $message);
                $message .= get_string('healthcheckautofixfollowup', 'enrol_semco');
                $message .= html_writer::empty_tag('br') . reset($followups);
            } else if (count($followups) > 1) {
                $message = html_writer::tag('p', $message);
                $message .= get_string('healthcheckautofixfollowup', 'enrol_semco');
                $message .= html_writer::alist($followups, ['class' => 'mb-0']);
            }

            // And show it.
            \core\notification::success($message);

            // Otherwise.
        } else {
            // Show an error notification.
            \core\notification::error(get_string('healthcheckautofixerror', 'enrol_semco'));
        }
    }

    // Redirect to the same page.
    redirect($PAGE->url);
}

// Add the JS module for the health check details modal.
$PAGE->requires->js_call_amd('enrol_semco/healthcheckdetailsmodal', 'init');

// Start page output.
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Show the health check intro.
$intro = new \core\output\notification(
    get_string('healthcheckpage_desc', 'enrol_semco'),
    \core\output\notification::NOTIFY_INFO,
    false
);
$intro->set_extra_classes(['alert-light']);
echo $OUTPUT->render($intro);

// Show the tables grouped by category.
$categories = manager::get_supported_categories();
foreach ($categories as $categorykey => $categorylabel) {
    // Build the health check table for this category.
    $table = new healthchecklist_table($categorykey);
    $table->define_baseurl($PAGE->url);

    // Show the health check table for this category.
    $table->out(0, true);
}

// Finish page output.
echo $OUTPUT->footer();
