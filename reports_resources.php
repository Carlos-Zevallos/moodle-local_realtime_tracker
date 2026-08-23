<?php
/**
 * Institutional Resources Report.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_realtime_tracker_resources');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);
$year = optional_param('year', '', PARAM_INT);
$semester = optional_param('semester', '', PARAM_ALPHANUM);

$PAGE->set_url('/local/realtime_tracker/reports_resources.php', ['categoryid' => $categoryid, 'subcategoryid' => $subcategoryid, 'year' => $year, 'semester' => $semester]);
$PAGE->set_title(get_string('resourcesreport', 'local_realtime_tracker'));
$PAGE->set_heading(get_string('resourcesreport', 'local_realtime_tracker'));

// Build category filter options
$categories = $DB->get_records('course_categories', [], 'sortorder', 'id, name, parent');
$top_categories = [];
$subcategories = [];
foreach ($categories as $cat) {
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
$sql = "SELECT
            ".$DB->sql_concat('c.id', "'-'", 'u.id')." AS uniqueid,
            c.id AS courseid,
            cc.name AS escuela,
            c.fullname AS cursos,
            c.shortname,
            ".$DB->sql_concat('u.firstname', "' '", 'u.lastname')." AS docente,
            (SELECT COUNT(cm.id) FROM {course_modules} cm
             JOIN {modules} m ON cm.module = m.id
             WHERE cm.course = c.id AND m.name LIKE '%url%') AS url_count,
            (SELECT COUNT(cm.id) FROM {course_modules} cm
             JOIN {modules} m ON cm.module = m.id
             WHERE cm.course = c.id AND m.name LIKE '%label%') AS etiqueta_count,
            (SELECT COUNT(cm.id) FROM {course_modules} cm
             JOIN {modules} m ON cm.module = m.id
             WHERE cm.course = c.id AND (m.name LIKE '%assign%' OR m.name LIKE '%assignment%')) AS tarea_count,
            (SELECT COUNT(cm.id) FROM {course_modules} cm
             JOIN {modules} m ON cm.module = m.id
             JOIN {forum} f ON f.id = cm.instance
             WHERE cm.course = c.id AND m.name LIKE '%forum%' AND f.type != 'news') AS foros_count,
            (SELECT COUNT(cm.id) FROM {course_modules} cm
             JOIN {modules} m ON cm.module = m.id
             WHERE cm.course = c.id AND m.name LIKE '%resource%') AS archivos_count,
            (SELECT COUNT(cm.id) FROM {course_modules} cm
             JOIN {modules} m ON cm.module = m.id
             WHERE cm.course = c.id AND m.name LIKE '%msteams%') AS teams_count,
            (SELECT COUNT(cm.id) FROM {course_modules} cm
             JOIN {modules} m ON cm.module = m.id
             WHERE cm.course = c.id AND m.name LIKE '%folder%') AS folder_count,
            (SELECT COUNT(ra.userid) FROM {role_assignments} ra
             JOIN {context} subctx ON ra.contextid = subctx.id
             WHERE ra.roleid = 5 AND subctx.instanceid = c.id) AS alumnos_count,
            (SELECT COALESCE(SUM(create_seconds), 0) FROM {local_realtime_tracker_acts} WHERE courseid = c.id AND userid = u.id) AS total_creation,
            (SELECT COALESCE(total_active_seconds, 0) FROM {local_realtime_tracker_summ} WHERE courseid = c.id AND userid = u.id) AS total_connection,
            c.id, u.id AS userid
        FROM {course} c
        INNER JOIN {course_categories} cc ON c.category = cc.id
        JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
        JOIN {role_assignments} ra ON ra.contextid = ctx.id
        JOIN {role} r ON r.id = ra.roleid AND r.shortname NOT IN ('student', 'guest') AND r.id NOT IN (5, 6)
        JOIN {user} u ON u.id = ra.userid
        WHERE 1=1";

$params = [];
$filter_category = $subcategoryid > 0 ? $subcategoryid : $categoryid;
if ($filter_category > 0) {
    // If they picked a top category, we might ideally search it and all subcategories.
    // For simplicity, we just filter exactly by the selected ID.
    // Assuming courses are placed exactly in the subcategory.
    $sql .= " AND c.category = ?";
    $params[] = $filter_category;
}
$search = '';
if (!empty($year)) {
    $search .= $year;
}
if (!empty($semester)) {
    $search .= $semester;
}

if (!empty($search)) {
    $sql .= " AND " . $DB->sql_like('c.shortname', '?', false, false);
    $params[] = '%' . $search . '%';
}

// Ensure proper GROUP BY for cross-DB compatibility
$sql .= " GROUP BY c.id, u.id, cc.name, c.fullname, c.shortname, u.firstname, u.lastname
          ORDER BY c.fullname, u.lastname, u.firstname";

$records = $DB->get_records_sql($sql, $params);

$totals = [
    'url' => 0, 'etiqueta' => 0, 'tarea' => 0,
    'foros' => 0, 'archivos' => 0, 'teams' => 0, 'folder' => 0
];
$table_data = [];

if ($records) {
    foreach ($records as $r) {
        $totals['url'] += $r->url_count;
        $totals['etiqueta'] += $r->etiqueta_count;
        $totals['tarea'] += $r->tarea_count;
        $totals['foros'] += $r->foros_count;
        $totals['archivos'] += $r->archivos_count;
        $totals['teams'] += $r->teams_count;
        $totals['folder'] += $r->folder_count;
        
        $courseurl = new moodle_url('/course/view.php', ['id' => $r->courseid]);
        $link = \html_writer::link($courseurl, $r->shortname, ['target' => '_blank']);

        list($tracker_conn, $tracker_creat) = local_realtime_tracker_get_fallback_times($r->courseid, $r->userid, $r->total_connection, $r->total_creation);
        
        $c_hrs = floor($tracker_creat / 3600);
        $c_mins = floor(($tracker_creat % 3600) / 60);

        $a_hrs = floor($tracker_conn / 3600);
        $a_mins = floor(($tracker_conn % 3600) / 60);

        $format_zero = function($val) {
            return $val == 0 ? '<span class="text-danger font-weight-bold">0</span>' : $val;
        };

        $table_data[] = [
            'courseid' => $r->courseid,
            'userid' => $r->userid,
            'escuela' => $r->escuela,
            'cursos' => $r->cursos,
            'link_curso' => $link,
            'docente' => $r->docente,
            'url_count' => $format_zero($r->url_count),
            'etiqueta_count' => $format_zero($r->etiqueta_count),
            'tarea_count' => $format_zero($r->tarea_count),
            'foros_count' => $format_zero($r->foros_count),
            'archivos_count' => $format_zero($r->archivos_count),
            'teams_count' => $format_zero($r->teams_count),
            'folder_count' => $format_zero($r->folder_count),
            'recursos_compartidos' => $format_zero($r->url_count + $r->archivos_count + $r->folder_count),
            'alumnos_count' => $format_zero($r->alumnos_count),
            'tiempo_conexion' => sprintf('%dh %dm', $a_hrs, $a_mins),
            'tiempo_creacion' => sprintf('%dh %dm', $c_hrs, $c_mins)
        ];
    }
}

// Chart 1: Total resources distribution
$chart1 = new \core\chart_pie();
$series_data = [];
$labels = [];
foreach ($totals as $type => $count) {
    if ($count > 0) {
        $labels[] = ucfirst($type);
        $series_data[] = $count;
    }
}
if (empty($series_data)) {
    $series_data = [1];
    $labels = [get_string('nodata', 'local_realtime_tracker')];
}
$series1 = new \core\chart_series(get_string('resources', 'local_realtime_tracker'), $series_data);
$chart1->add_series($series1);
$chart1->set_labels($labels);

// Render
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('local_realtime_tracker');

$template_data = [
    'topcategories' => $top_categories,
    'categoryid' => $categoryid,
    'subcategoryid' => $subcategoryid,
    'subcategories_json' => json_encode($subcategories),
    'year' => $year,
    'semester' => $semester,
    'sem01' => ($semester === '01'),
    'sem02' => ($semester === '02'),
    'chart1' => $OUTPUT->render($chart1),
    'chart_data' => [
        ['label' => 'URL', 'value' => $totals['url']],
        ['label' => 'Etiqueta', 'value' => $totals['etiqueta']],
        ['label' => 'Tarea', 'value' => $totals['tarea']],
        ['label' => 'Foros', 'value' => $totals['foros']],
        ['label' => 'Archivos', 'value' => $totals['archivos']],
        ['label' => 'Teams', 'value' => $totals['teams']],
        ['label' => 'Folder', 'value' => $totals['folder']]
    ],
    'currenturl' => $PAGE->url->out(false),
    'results' => array_values($table_data)
];

echo $renderer->render_from_template('local_realtime_tracker/report_resources', $template_data);

echo $OUTPUT->footer();
