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
 * Enrolment method "SEMCO" - Health check list class
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\table;

use core\output\html_writer;
use enrol_semco\healthcheck\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Require plugin library.
require_once($CFG->dirroot . '/enrol/semco/locallib.php');

// Require table library.
require_once($CFG->dirroot . '/lib/tablelib.php');

/**
 * Class healthchecklist_table
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class healthchecklist_table extends \core_table\sql_table {
    /**
     * The health check category which this table shows.
     *
     * @var string
     */
    protected $category;

    /**
     * Override the constructor to construct a health check list table instead of a simple table.
     *
     * @param string $category The health check category which this table shows.
     */
    public function __construct(string $category) {
        // Remember the category.
        $this->category = $category;

        // Call the parent constructor.
        parent::__construct('healthchecks-' . $category);

        // Define the headers and columns.
        $headers = [];
        $columns = [];
        $headers[] = get_string('healthcheckstatusheader', 'enrol_semco');
        $headers[] = get_string('healthcheckcheckheader', 'enrol_semco');
        $headers[] = get_string('healthchecksummaryheader', 'enrol_semco');
        $headers[] = get_string('healthcheckactionsheader', 'enrol_semco');
        $columns[] = 'status';
        $columns[] = 'title';
        $columns[] = 'summary';
        $columns[] = 'actions';
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_header_column('title');

        // Having a sortable, collapsible or pageable table does not make much sense here.
        $this->sortable(false);
        $this->collapsible(false);
        $this->pageable(false);

        // Set the table's HTML attributes.
        $this->column_class('actions', 'text-nowrap');
        $this->set_attribute('id', 'healthchecks-' . $category);
        $this->set_attribute('class', 'mb-5');

        // Note about the column widths: The category tables are rendered one below the other, but the amount of content
        // differs from category to category. With the browser's automatic table layout, each table would end up with its
        // own column widths which makes the page look inhomogeneous. The columns are therefore sized in the plugin's
        // styles.css. This cannot be done with flexible_table::column_style() as flexible_table::setup() removes the
        // width property from every column which is not collapsed.

        // Render the category title as table caption.
        // We fetch the string from the supported categories to ensure that only valid categories are used
        // and to avoid that we have to compose the string key here.
        $supportedcategories = manager::get_supported_categories();
        if (isset($supportedcategories[$this->category])) {
            $this->set_caption($supportedcategories[$this->category], ['class' => 'h4']);
        }
    }

    /**
     * Status column.
     *
     * @param \stdClass $data The row data.
     * @return string
     */
    public function col_status($data) {
        // The status label is already prepared in query_db(), we just need to wrap it in a badge.
        return html_writer::span(format_string($data->statuslabel), 'badge ' . $data->statusbadgeclass);
    }

    /**
     * Actions column.
     *
     * @param \stdClass $data The row data.
     * @return string
     */
    public function col_actions($data) {
        global $OUTPUT;

        // Initialize the actions.
        $actions = [];

        // Add the details action which opens the health check item's details modal.
        $actions[] = [
            'url' => '#',
            'icon' => new \core\output\pix_icon(
                'info',
                get_string('healthcheckmoreinfo', 'enrol_semco'),
                'enrol_semco'
            ),
            'attributes' => [
                'class' => 'action-details py-0 pl-0 ml-0 mr-0',
                'data-action' => 'healthcheck-details',
                'data-title' => $data->title,
                'data-summary' => $data->summary,
                'data-description' => $data->description,
                'data-findings' => json_encode($data->findings),
                'data-statuslabel' => $data->statuslabel,
                'data-statusbadgeclass' => $data->statusbadgeclass,
                'data-statusdescription' => $data->statusdescription,
                'data-possiblesolution' => $data->possiblesolution,
                'data-id' => $data->id,
            ],
        ];

        // Add the autofix action if the health check item can be fixed automatically. The confirmation is done with the
        // Moodle core confirmation modal which is driven by the data attributes below, see lib/amd/src/utility.js. The
        // link itself keeps the URL of the autofix action: The core modal follows it as soon as the admin has confirmed,
        // and it remains usable if JavaScript is not available.
        if (!empty($data->autofixable)) {
            $actions[] = [
                'url' => new \core\url('/enrol/semco/healthcheck.php', [
                    'action' => 'autofix',
                    'id' => $data->id,
                    'sesskey' => sesskey(),
                ]),
                'icon' => new \core\output\pix_icon(
                    'autofix',
                    get_string('healthcheckautofix', 'enrol_semco'),
                    'enrol_semco'
                ),
                'attributes' => [
                    'class' => 'action-autofix py-0 ml-0 mr-0',
                    'title' => get_string('healthcheckautofix', 'enrol_semco'),
                    'aria-label' => get_string('healthcheckautofix', 'enrol_semco'),
                    'data-modal' => 'confirmation',
                    'data-modal-title-str' => json_encode(['healthcheckautofixconfirmtitle', 'enrol_semco']),
                    'data-modal-content' => $data->autofixconfirmation,
                    'data-modal-yes-button-str' => json_encode(['healthcheckautofixconfirmbutton', 'enrol_semco']),
                ],
            ];

            // Otherwise, if the health check item needs attention nonetheless, add the support action. It does nothing
            // but to explain the options which the admin has, in a Moodle core alert modal.
        } else if (!empty($data->needsattention)) {
            $actions[] = [
                'url' => '#',
                'icon' => new \core\output\pix_icon(
                    'support',
                    get_string('healthchecksupport', 'enrol_semco'),
                    'enrol_semco'
                ),
                'attributes' => [
                    'class' => 'action-support py-0 ml-0 mr-0',
                    'title' => get_string('healthchecksupport', 'enrol_semco'),
                    'aria-label' => get_string('healthchecksupport', 'enrol_semco'),
                    'data-modal' => 'alert',
                    'data-modal-title-str' => json_encode(['healthchecksupport', 'enrol_semco']),
                    'data-modal-content-str' => json_encode(['healthchecksupport_desc', 'enrol_semco']),
                ],
            ];
        }

        // Add the edit action if an action URL is set, which is not the case for an item without findings and for a
        // muted item, see query_db().
        if ($data->actionurl !== null) {
            $actions[] = [
                'url' => $data->actionurl,
                'icon' => new \core\output\pix_icon('i/settings', get_string('healthcheckopensetting', 'enrol_semco')),
                'attributes' => ['class' => 'action-edit py-0 ml-0 mr-0'],
            ];
        }

        // Add the mute / unmute action.
        $mutelabel = get_string($data->muted ? 'healthcheckunmute' : 'healthcheckmute', 'enrol_semco');
        $actions[] = [
            'url' => new \core\url('/enrol/semco/healthcheck.php', [
                'action' => $data->muted ? 'unmute' : 'mute',
                'id' => $data->id,
                'sesskey' => sesskey(),
            ]),
            'icon' => new \core\output\pix_icon($data->muted ? 'muted' : 'unmuted', $mutelabel, 'enrol_semco'),
            'attributes' => [
                'class' => ($data->muted ? 'action-unmute' : 'action-mute') . ' py-0 pr-0 ml-0 mr-0',
                'title' => $mutelabel,
                'aria-label' => $mutelabel,
            ],
        ];

        // Compose the action icons for all actions.
        $actionshtml = [];
        foreach ($actions as $action) {
            $action['attributes']['role'] = 'button';
            $actionshtml[] = $OUTPUT->action_icon($action['url'], $action['icon'], null, $action['attributes']);
        }

        // Return all actions.
        return html_writer::span(join('', $actionshtml), 'healthcheck-actions');
    }

    /**
     * Get the health check items for the table.
     *
     * @param int $pagesize The number of rows to fetch (which is ignored as this table is not pageable).
     * @param bool $useinitialsbar Whether to use the initials bar (which is ignored as this table has no initials).
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        // Initialize the raw data array.
        $this->rawdata = [];

        // Get the health check items of the configured category.
        $healthchecks = manager::get_healthchecks($this->category);

        // Iterate over the health check items and prepare the data for the table.
        foreach ($healthchecks as $healthcheck) {
            // Initialize a row of data for the table.
            $row = new \stdClass();

            // Get the row data.
            $row->id = $healthcheck->get_id();
            $row->statuslabel = manager::get_status_label($healthcheck);
            $row->statusbadgeclass = manager::get_status_badge_class(manager::get_effective_status($healthcheck));
            $row->statusdescription = manager::get_status_description($healthcheck);
            $row->title = $healthcheck->get_title();
            $row->summary = $healthcheck->get_summary();
            $row->description = $healthcheck->get_description();
            $row->muted = manager::is_healthcheck_muted($healthcheck->get_id());

            // A muted item keeps being evaluated, but the admin has decided not to be bothered by it. Thus, it neither
            // names its findings nor does it offer any way to fix them until it is unmuted again. The automatic fix and
            // the support action are gone as well, as a muted item does not need attention anymore.
            $row->findings = $row->muted ? [] : $healthcheck->get_findings();
            $row->actionurl = $row->muted ? null : $healthcheck->get_action_url();
            $row->needsattention = manager::healthcheck_needs_attention($healthcheck);
            $row->autofixable = $row->needsattention && $healthcheck->supports_autofix();
            $row->autofixconfirmation = $row->autofixable ? $this->render_autofix_confirmation($healthcheck) : '';
            $row->possiblesolution = manager::get_possible_solution($healthcheck);

            // Add the row to the table data.
            $this->rawdata[] = $row;
        }
    }

    /**
     * Render the body of the confirmation modal which is shown before a health check item is fixed automatically.
     *
     * @param \enrol_semco\healthcheck\healthcheck $healthcheck The health check item which is going to be fixed.
     * @return string
     */
    protected function render_autofix_confirmation(\enrol_semco\healthcheck\healthcheck $healthcheck): string {
        global $OUTPUT;

        // Get the findings which are going to be fixed.
        $findings = $healthcheck->get_findings();

        // The items which do not cover the plugin installation do not repair anything which this plugin has set up.
        // They implement a recommendation from the plugin's README, either for the companion plugin local_recompletion
        // or for the global Moodle settings, and the admin should know that.
        $note = null;
        $statuscontext = manager::get_status_context($healthcheck);
        if ($statuscontext !== 'installation') {
            $note = get_string('healthcheckautofixconfirm' . $statuscontext, 'enrol_semco');
        }

        // Render the modal body.
        return $OUTPUT->render_from_template('enrol_semco/healthcheckautofixconfirm', [
            // The title of the item is shown in the body, as the modal title is too short to hold it.
            'title' => $healthcheck->get_title(),
            'findings' => array_map(fn(string $finding): array => ['finding' => $finding], $findings),
            // A single finding is rendered as a paragraph, more than one finding as a list.
            'singlefinding' => (count($findings) === 1) ? reset($findings) : null,
            'hasmultiplefindings' => count($findings) > 1,
            'note' => $note,
            'isrisky' => $healthcheck->is_autofix_risky(),
        ]);
    }
}
