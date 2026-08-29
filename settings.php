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
 * Settings for local_realtime_tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_realtime_tracker', get_string('settings', 'local_realtime_tracker'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_realtime_tracker/cif',
        get_string('cif', 'local_realtime_tracker'),
        get_string('cif_desc', 'local_realtime_tracker'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_realtime_tracker/centername',
        get_string('centername', 'local_realtime_tracker'),
        get_string('centername_desc', 'local_realtime_tracker'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_realtime_tracker/modalidad',
        get_string('modalidad', 'local_realtime_tracker'),
        get_string('modalidad_desc', 'local_realtime_tracker'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_realtime_tracker/aikeywords',
        get_string('aikeywords', 'local_realtime_tracker'),
        get_string('aikeywords_desc', 'local_realtime_tracker'),
        'chatgpt,openai,ai_block'
    ));

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
