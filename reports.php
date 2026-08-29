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
 * Course reports for Mauri Tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'general', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$rolefilter = optional_param('role', 'student', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/realtime_tracker:viewreports', $context);

$PAGE->set_url(new moodle_url('/local/realtime_tracker/reports.php', [
    'courseid' => $courseid,
    'action' => $action,
    'userid' => $userid,
    'role' => $rolefilter,
]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('maurireports', 'local_realtime_tracker'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('report');

$renderer = $PAGE->get_renderer('local_realtime_tracker');

echo $OUTPUT->header();

if ($action === 'student' && $userid) {
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    echo $renderer->render(new \local_realtime_tracker\output\student_detail($course, $user));
} else if ($action === 'teacher' && $userid) {
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    echo $renderer->render(new \local_realtime_tracker\output\teacher_detail($course, $user));
} else {
    echo $renderer->render(new \local_realtime_tracker\output\dashboard($course, $rolefilter));
}

echo $OUTPUT->footer();
