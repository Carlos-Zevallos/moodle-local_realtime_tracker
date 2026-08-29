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
 * Hooks for local_realtime_tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend course navigation to add the Mauri reports link.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context $context Course context.
 */
function local_realtime_tracker_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/realtime_tracker:viewreports', $context)) {
        return;
    }

    $url = new moodle_url('/local/realtime_tracker/reports.php', ['courseid' => $course->id]);
    $node = navigation_node::create(
        get_string('maurireports', 'local_realtime_tracker'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_realtime_tracker_reports',
        new pix_icon('i/report', '')
    );
    $navigation->add_node($node);
}

/**
 * Inject the JavaScript tracker into course pages.
 */
function local_realtime_tracker_before_footer() {
    global $PAGE, $COURSE, $USER;

    if (!isloggedin() || isguestuser() || empty($COURSE) || $COURSE->id === SITEID) {
        return;
    }

    $cmid = 0;
    if ($PAGE->cm) {
        $cmid = $PAGE->cm->id;
    }

    $actiontype = 'view';
    $currenturl = $PAGE->url ? $PAGE->url->out(false) : '';
    if (strpos($currenturl, 'modedit.php') !== false) {
        $actiontype = 'create';
    } else {
        $aikeywords = explode(',', (string)get_config('local_realtime_tracker', 'aikeywords'));
        foreach ($aikeywords as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '' && strpos($currenturl, $keyword) !== false) {
                $actiontype = 'ai';
                break;
            }
        }
    }

    $PAGE->requires->js_call_amd('local_realtime_tracker/tracker', 'init', [[
        'courseid' => $COURSE->id,
        'userid' => $USER->id,
        'cmid' => $cmid,
        'actiontype' => $actiontype,
        'sessionid' => sesskey() . '_' . time(),
        'interval' => 30000,
    ]]);
}
