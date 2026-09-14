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
 * Enrolment method "SEMCO" - JS code for the health check details modal.
 *
 * @module     enrol_semco/healthcheckdetailsmodal
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalCancel from 'core/modal_cancel';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString} from 'core/str';

const SELECTORS = {
    DETAILS: '[data-action="healthcheck-details"]',
};

/**
 * Entrypoint of the js.
 *
 * @method init
 */
export const init = () => {
    registerListenerEvents();
};

/**
 * Register the health check related event listeners.
 *
 * @method registerListenerEvents
 */
const registerListenerEvents = () => {
    document.addEventListener('click', (e) => {
        const details = e.target.closest(SELECTORS.DETAILS);
        if (details) {
            e.preventDefault();
            buildModal(details).catch(Notification.exception);
        }
    });
};

/**
 * Parse the findings which are attached to the details action as JSON encoded array.
 *
 * @method parseFindings
 * @param {String} findings The JSON encoded findings.
 * @return {Array} The findings, prepared for the mustache template.
 */
const parseFindings = (findings) => {
    // If there aren't any findings, return an empty array.
    if (!findings) {
        return [];
    }

    // Otherwise, try to decode the findings.
    try {
        const parsed = JSON.parse(findings);
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed.map((finding) => ({finding: finding}));
    } catch (e) {
        return [];
    }
};

/**
 * Build the modal with the provided data.
 *
 * @method buildModal
 * @param {object} element
 */
const buildModal = async(element) => {
    // Prepare the findings for the modal.
    const findings = parseFindings(element.getAttribute('data-findings'));

    // Prepare the data for the modal.
    const data = {
        title: element.getAttribute('data-title'),
        summary: element.getAttribute('data-summary'),
        description: element.getAttribute('data-description'),
        statuslabel: element.getAttribute('data-statuslabel'),
        statusbadgeclass: element.getAttribute('data-statusbadgeclass'),
        statusdescription: element.getAttribute('data-statusdescription'),
        possiblesolution: element.getAttribute('data-possiblesolution'),
        findings: findings,
        // A single finding is rendered as a paragraph, more than one finding as a list.
        singlefinding: findings.length === 1 ? findings[0].finding : null,
        hasmultiplefindings: findings.length > 1,
    };

    await ModalCancel.create({
        title: data.title,
        body: Templates.render('enrol_semco/healthcheckdetailsmodal', data),
        large: true,
        buttons: {
            'cancel': getString('closebuttontitle', 'moodle'),
        },
        show: true,
    });
};
