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
 * Institutional resources report.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_realtime_tracker_resources');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);
$year = optional_param('year', '', PARAM_INT);
$semester = optional_param('semester', '', PARAM_ALPHANUM);

$PAGE->set_url('/local/realtime_tracker/reports_resources.php', [
    'categoryid' => $categoryid,
    'subcategoryid' => $subcategoryid,
    'year' => $year,
    'semester' => $semester,
]);
$PAGE->set_title(get_string('resourcesreport', 'local_realtime_tracker'));
$PAGE->set_heading(get_string('resourcesreport', 'local_realtime_tracker'));

$renderer = $PAGE->get_renderer('local_realtime_tracker');
$renderable = new \local_realtime_tracker\output\report_resources($categoryid, $subcategoryid, $year, $semester);
list(, $subcategories) = \local_realtime_tracker\local\helper::get_category_options($categoryid, $subcategoryid);

$PAGE->requires->js_call_amd('local_realtime_tracker/reports', 'initFilters', [
    $subcategories,
    $subcategoryid,
]);
$PAGE->requires->js_call_amd('local_realtime_tracker/reports', 'initExport', [
    $categoryid,
    $subcategoryid,
    (string)$year,
    $semester,
]);

echo $OUTPUT->header();
echo $renderer->render($renderable);
echo $OUTPUT->footer();
