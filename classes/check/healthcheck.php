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
 * Enrolment method "SEMCO" - Health check for the Moodle Checks API
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_semco\check;

use core\check\result;
use enrol_semco\healthcheck\healthcheck as healthcheckitem;
use enrol_semco\healthcheck\manager;

/**
 * Check if the SEMCO health check items need attention.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class healthcheck extends \core\check\check {
    /**
     * A link to a place to action this.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        // Compose and return a link to the health check page.
        return new \action_link(
            new \core\url('/enrol/semco/healthcheck.php'),
            get_string('healthcheckpagetitle', 'enrol_semco')
        );
    }

    /**
     * Return the check result.
     *
     * @return result
     */
    public function get_result(): result {
        // Get the health check items which need attention.
        // This covers all categories, including the recompletion and the recommendations category which do not check
        // the state of the plugin installation but the configuration of a companion plugin and global Moodle settings.
        // An admin who has decided against such a recommendation on purpose can mute the particular item on the health
        // check page, and a muted item does not need attention anymore.
        $needattention = manager::get_healthchecks_needing_attention();

        // If there are any health check items which need attention.
        if (count($needattention) > 0) {
            // Map the most severe health check status to a Moodle check result. A notice does not deserve a warning on
            // the Moodle system status page as it neither breaks nor endangers the SEMCO integration.
            switch (manager::get_most_severe_status()) {
                case healthcheckitem::ERROR:
                    $status = result::ERROR;
                    $summary = get_string('checkhealthcheckerror', 'enrol_semco', count($needattention));
                    break;
                case healthcheckitem::WARNING:
                    $status = result::WARNING;
                    $summary = get_string('checkhealthcheckwarning', 'enrol_semco', count($needattention));
                    break;
                default:
                    $status = result::INFO;
                    $summary = get_string('checkhealthchecknotice', 'enrol_semco', count($needattention));
                    break;
            }

            // And we compose the details with the affected health check items.
            $items = [];
            foreach ($needattention as $healthcheck) {
                $items[] = \core\output\html_writer::tag(
                    'li',
                    \core\output\html_writer::span(
                        format_string(manager::get_status_label($healthcheck)),
                        'badge ' . manager::get_status_badge_class($healthcheck->get_status())
                    ) . ' ' . format_string($healthcheck->get_title()) . ': ' . format_string($healthcheck->get_summary())
                );
            }
            $details = \core\output\html_writer::tag('ul', implode('', $items));

            // Otherwise.
        } else {
            // We are good.
            $status = result::OK;
            $summary = get_string('checkhealthcheckok', 'enrol_semco');
            $details = '';
        }

        // Add a link to the health check page to the details.
        $details .= get_string('checkhealthcheckdetails', 'enrol_semco', [
            'url' => (new \core\url('/enrol/semco/healthcheck.php'))->out(),
        ]);

        // Return the result.
        return new result($status, $summary, $details);
    }
}
