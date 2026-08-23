<?php
/**
 * Settings for the local realtime tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_realtime_tracker', get_string('settings', 'local_realtime_tracker'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_realtime_tracker/cif',
        get_string('cif', 'local_realtime_tracker'),
        get_string('cif_desc', 'local_realtime_tracker'),
        '12345678Z'
    ));

    $settings->add(new admin_setting_configtext(
        'local_realtime_tracker/centername',
        get_string('centername', 'local_realtime_tracker'),
        get_string('centername_desc', 'local_realtime_tracker'),
        'Centro Opositas'
    ));

    $settings->add(new admin_setting_configtext(
        'local_realtime_tracker/modalidad',
        get_string('modalidad', 'local_realtime_tracker'),
        get_string('modalidad_desc', 'local_realtime_tracker'),
        'Teleformación'
    ));

    $settings->add(new admin_setting_configtext(
        'local_realtime_tracker/aikeywords',
        get_string('aikeywords', 'local_realtime_tracker'),
        get_string('aikeywords_desc', 'local_realtime_tracker'),
        'chatgpt,openai,ai_block'
    ));

    // Add Institutional Report link to Site Administration > Reports
    $ADMIN->add('reports', new admin_externalpage(
        'local_realtime_tracker_reports',
        get_string('institutionalreports', 'local_realtime_tracker'),
        new moodle_url('/local/realtime_tracker/reports_teachers.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'local_realtime_tracker_resources',
        get_string('resourcesreport', 'local_realtime_tracker'),
        new moodle_url('/local/realtime_tracker/reports_resources.php'),
        'moodle/site:config'
    ));
}
