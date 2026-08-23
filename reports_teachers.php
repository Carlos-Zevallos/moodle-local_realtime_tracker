<?php
/**
 * Institutional Teacher Analytics Dashboard.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_realtime_tracker_reports');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);

$PAGE->set_url('/local/realtime_tracker/reports_teachers.php', ['categoryid' => $categoryid, 'subcategoryid' => $subcategoryid]);
$PAGE->set_title(get_string('institutionalreports', 'local_realtime_tracker'));
$PAGE->set_heading(get_string('institutionalreports', 'local_realtime_tracker'));

// Build category filter options
$categories = $DB->get_records('course_categories', [], 'sortorder', 'id, name, parent');
$top_categories = [];
$subcategories = [];
foreach ($categories as $cat) {
    if ($cat->id == 1) continue; // Exclude Miscelánea
    if ($cat->parent == 0) {
        $top_categories[] = [
            'id' => $cat->id,
            'name' => $cat->name,
            'selected' => ($cat->id == $categoryid)
        ];
    } else {
        $subcategories[] = [
            'id' => $cat->id,
            'name' => $cat->name,
            'parent' => $cat->parent,
            'selected' => ($cat->id == $subcategoryid)
        ];
    }
}

// Fetch Data
$sql = "SELECT c.id AS courseid, c.category, a.userid, a.cmid, a.active_seconds, a.create_seconds, a.ai_seconds
        FROM {local_realtime_tracker_acts} a
        JOIN {course} c ON c.id = a.courseid";

$params = [];
$filter_category = $subcategoryid > 0 ? $subcategoryid : $categoryid;
if ($filter_category > 0) {
    $sql .= " WHERE c.category = ?";
    $params[] = $filter_category;
} else {
    // Exclude category 1 (Miscelánea / Miscellaneous) by default when no specific filter is selected
    $sql .= " WHERE c.category != 1";
}

$acts = $DB->get_records_sql($sql, $params);

// Aggregate metrics
$total_creation = 0;
$total_ai = 0;
$resource_creation = []; // Group by module type

foreach ($acts as $act) {
    // Exclude site administrators
    if (is_siteadmin($act->userid)) {
        continue;
    }

    // Only count teachers
    $context = context_course::instance($act->courseid);
    if (!has_capability('moodle/course:update', $context, $act->userid)) {
        continue;
    }

    $total_creation += $act->create_seconds;
    $total_ai += $act->ai_seconds;

    if ($act->create_seconds > 0) {
        $cm = $DB->get_record('course_modules', ['id' => $act->cmid]);
        if ($cm) {
            $mod = $DB->get_record('modules', ['id' => $cm->module]);
            if (!isset($resource_creation[$mod->name])) {
                $resource_creation[$mod->name] = 0;
            }
            $resource_creation[$mod->name] += $act->create_seconds;
        }
    }
}

// Fetch Site-Wide Datacurso Consumption
if (class_exists('\aiprovider_datacurso\local\service\consumption_service')) {
    try {
        $dc_response = \aiprovider_datacurso\local\service\consumption_service::get_all_consumption();
        if (!empty($dc_response['consumption'])) {
            foreach ($dc_response['consumption'] as $dc_act) {
                // If filtering by user is needed, we'd check $dc_act['userid']
                // For simplicity on the total dashboard, we add all teachers' tokens
                if (isset($dc_act['cant_tokens'])) {
                    $total_ai += ($dc_act['cant_tokens'] * 60); // 1 token = 1 minute
                }
            }
        }
    } catch (\Exception $e) {
        // Silently fail
    }
}

// Chart 1: Time vs Activity Type
$chart1 = new \core\chart_bar();
$labels1 = [];
$data1 = [];
foreach ($resource_creation as $type => $seconds) {
    $labels1[] = ucfirst($type);
    $data1[] = round($seconds / 60); // minutes
}
$series1 = new \core\chart_series('Minutos de Creación', $data1);
$chart1->add_series($series1);
$chart1->set_labels($labels1);
$chart1->set_title('Tiempo invertido en creación por recurso');

// Chart 2: AI vs Manual Creation
$chart2 = new \core\chart_pie();
$series2 = new \core\chart_series('Tiempo (Minutos)', [round($total_creation/60), round($total_ai/60)]);
$chart2->add_series($series2);
$chart2->set_labels(['Creación Manual', 'Uso de IA']);
$chart2->set_title('Creación Manual vs IA');

// Render
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('local_realtime_tracker');

$template_data = [
    'topcategories' => $top_categories,
    'subcategoryid' => $subcategoryid,
    'subcategories_json' => json_encode($subcategories),
    'chart1' => $OUTPUT->render($chart1),
    'chart2' => $OUTPUT->render($chart2),
    'total_creation_hours' => round($total_creation / 3600, 1),
    'total_ai_hours' => round($total_ai / 3600, 1),
    'currenturl' => $PAGE->url->out(false)
];

echo $renderer->render_from_template('local_realtime_tracker/teacher_dashboard', $template_data);

echo $OUTPUT->footer();
