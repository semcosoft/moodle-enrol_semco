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
 * SEMCO enrolment method - CLI script to recreate the webservice token
 *
 * This CLI script regenerates the webservice token for the SEMCO webservice user.
 * It deletes the old token and creates a new one with optional parameters.
 *
 * @package    enrol_semco
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/enrol/semco/locallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

// Get CLI options.
[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'yes' => false,
        'until' => null,
        'ip' => null,
    ],
    [
        'h' => 'help',
        'y' => 'yes',
        'u' => 'until',
        'i' => 'ip',
    ]
);
if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// Print CLI script title.
cli_heading('SEMCO: Recreate webservice token');

// CLI help.
if ($options['help']) {
    $help = "This CLI script regenerates the webservice token for the SEMCO webservice user.
It deletes the old token and creates a new one with optional parameters.

Options:
  -h, --help      Print out this help
  -y, --yes       Skip confirmation request before recreating the token
  -u, --until     Date until the token is valid in format yyyy-mm-dd
                  (example: 2026-12-31)
  -i, --ip        IP address or subnet for which the token is valid
                  (example: 192.168.1.0/24 or 192.168.1.5)

Examples:
  \$ sudo -u www-data /usr/bin/php recreate_webservice_token.php
  \$ sudo -u www-data /usr/bin/php recreate_webservice_token.php --yes
  \$ sudo -u www-data /usr/bin/php recreate_webservice_token.php --yes --until=2026-12-31
  \$ sudo -u www-data /usr/bin/php recreate_webservice_token.php --yes --until=2026-12-31 --ip=192.168.1.0/24";
    cli_writeln($help);
    exit(0);
}

// Verify that Moodle is installed already.
if (empty($CFG->version)) {
    cli_error('Error: Database is not yet installed.');
}

// Verify that the script is not run during an upgrade.
if (moodle_needs_upgrading()) {
    cli_error('Error: Moodle upgrade pending, script execution suspended.');
}

// Get the admin user.
$admin = get_admin();
if (!$admin) {
    cli_error('Error: No admin account was found.');
}

// Execute the CLI script with admin permissions.
\core\session\manager::set_user($admin);

// Verify that the SEMCO enrolment method is installed.
$pluginmanager = \core_plugin_manager::instance();
if (
    !$pluginmanager->get_installed_plugins('enrol')
        || !array_key_exists('semco', $pluginmanager->get_installed_plugins('enrol'))
) {
    cli_error('Error: SEMCO enrolment method is not installed yet.');
}

// Parse and validate the --until parameter if provided.
$validuntil = 0;
if ($options['until'] !== null) {
    // Parse the date and validate the format (yyyy-mm-dd) and value at the same time.
    // Re-formatting the parsed date and comparing it with the input catches both wrong formats
    // and invalid values (e.g. month 13 or day 32).
    $untildate = DateTime::createFromFormat('Y-m-d', $options['until']);
    if ($untildate === false || $untildate->format('Y-m-d') !== $options['until']) {
        cli_error('Error: Invalid date for --until parameter. Expected format: yyyy-mm-dd (example: 2026-12-31)');
    }

    // Set the time to the end of the day.
    $untildate->setTime(23, 59, 59);

    // Get the Unix timestamp for the valid until date.
    $validuntil = $untildate->getTimestamp();

    // Validate that the date is in the future.
    if ($validuntil < time()) {
        cli_error('Error: The --until date must be in the future.');
    }

    // Print the token validity date.
    cli_writeln('Token validity: ' . date('Y-m-d H:i:s', $validuntil));
}

// Parse and validate the --ip parameter if provided.
$iprestriction = '';
if ($options['ip'] !== null) {
    // Get the IP restriction value.
    $iprestriction = $options['ip'];

    // Validate the IP address or subnet format.
    // Allow plain IP addresses (IPv4/IPv6) or CIDR notation.
    if (strpos($iprestriction, '/') !== false) {
        // CIDR notation: split into IP and prefix length.
        [$ipaddress, $cidr] = explode('/', $iprestriction, 2);

        // Validate the IP part with filter_var.
        if (!filter_var($ipaddress, FILTER_VALIDATE_IP)) {
            cli_error('Error: Invalid IP address in --ip parameter. '
                    . 'Expected format: x.x.x.x/prefix or x:x:x:x:x:x:x:x/prefix (example: 192.168.1.0/24)');
        }

        // Determine max prefix length based on IP version.
        $maxcidr = filter_var($ipaddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;
        $cidrvalue = (int) $cidr;
        if (!ctype_digit((string) $cidr) || $cidrvalue < 0 || $cidrvalue > $maxcidr) {
            cli_error('Error: Invalid CIDR prefix in --ip parameter. '
                    . 'Must be a number between 0 and ' . $maxcidr . '.');
        }
    } else {
        // Plain IP address (IPv4 or IPv6).
        if (!filter_var($iprestriction, FILTER_VALIDATE_IP)) {
            cli_error('Error: Invalid IP address for --ip parameter. '
                    . 'Expected a valid IPv4 or IPv6 address, optionally with CIDR notation '
                    . '(example: 192.168.1.5 or 192.168.1.0/24)');
        }
    }

    // Print the IP restriction.
    cli_writeln('IP restriction: ' . $iprestriction);
}

// Show warning about token recreation and required updates in SEMCO systems.
cli_writeln('');
cli_writeln('WARNING: This will recreate the webservice token for the SEMCO webservice user.');
cli_writeln('The old token will be deleted and a new one will be generated.');
cli_writeln('All SEMCO systems using the old token will need to be updated.');
cli_writeln('');

// Show security confirmation if --yes is not provided.
if (!$options['yes']) {
    $input = cli_input('Are you sure you want to continue? (y/n)', 'n', ['y', 'n']);
    if ($input !== 'y') {
        cli_writeln('Aborted.');
        exit(2);
    }
} else {
    cli_writeln('Confirmation skipped due to --yes parameter.');
}

// Print Processing title.
cli_writeln('');
cli_heading('Processing');

// Get the SEMCO webservice user.
global $DB;
$semcouser = $DB->get_record('user', ['username' => ENROL_SEMCO_ROLEANDUSERNAME]);
if (!$semcouser) {
    cli_error('Error: SEMCO webservice user not found.');
}

// Get the ID of the SEMCO webservice service.
$semcoserviceid = $DB->get_field('external_services', 'id', ['shortname' => ENROL_SEMCO_SERVICENAME]);
if (!$semcoserviceid) {
    cli_error('Error: SEMCO webservice service not found.');
}

// Delete the old token.
$webservicemanager = new webservice();
$webservicemanager->delete_user_ws_token(
    $DB->get_field(
        'external_tokens',
        'id',
        ['externalserviceid' => $semcoserviceid, 'userid' => $semcouser->id]
    )
);
cli_writeln('Success: Old webservice token has been deleted.');

// Generate a new webservice token for the user.
$systemcontext = context_system::instance();
$serviceobject = \core_external\util::get_service_by_id($semcoserviceid);
\core_external\util::generate_token(
    EXTERNAL_TOKEN_PERMANENT,
    $serviceobject,
    $semcouser->id,
    $systemcontext,
    $validuntil,
    $iprestriction
);

// Unfortunately, with the previous function, the token was created with a creator ID of 0 which will result in the
// fact that the token is not shown on /admin/webservice/tokens.php.
// To avoid this problem, we set the creatorid of the token to the SEMCO webservice user id now.
$generatedtoken = $DB->get_record(
    'external_tokens',
    ['externalserviceid' => $semcoserviceid, 'userid' => $semcouser->id],
    '*',
    MUST_EXIST
);
$generatedtoken->creatorid = $semcouser->id;
$DB->update_record('external_tokens', $generatedtoken);
cli_writeln('Success: New webservice token has been created.');

// Print the new token.
cli_writeln('');
cli_writeln('New token: ' . $generatedtoken->token);
cli_writeln('');

exit(0);
