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
 * Institutional teacher analytics dashboard.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_realtime_tracker_reports');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);

$PAGE->set_url('/local/realtime_tracker/reports_teachers.php', [
    'categoryid' => $categoryid,
    'subcategoryid' => $subcategoryid,
]);
$PAGE->set_title(get_string('institutionalreports', 'local_realtime_tracker'));
$PAGE->set_heading(get_string('institutionalreports', 'local_realtime_tracker'));

$renderer = $PAGE->get_renderer('local_realtime_tracker');
$renderable = new \local_realtime_tracker\output\teacher_dashboard($categoryid, $subcategoryid);
list(, $subcategories) = \local_realtime_tracker\local\helper::get_category_options($categoryid, $subcategoryid);

$PAGE->requires->js_call_amd('local_realtime_tracker/reports', 'initFilters', [
    $subcategories,
    $subcategoryid,
]);

echo $OUTPUT->header();
echo $renderer->render($renderable);
echo $OUTPUT->footer();
