<?php
/**
 * Email reports to teachers.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_realtime_tracker_resources');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);
$year = optional_param('year', '', PARAM_INT);
$semester = optional_param('semester', '', PARAM_ALPHANUM);

$returnurl = new moodle_url('/local/realtime_tracker/reports_resources.php', [
    'categoryid' => $categoryid,
    'subcategoryid' => $subcategoryid,
    'year' => $year,
    'semester' => $semester
]);

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
             WHERE cm.course = c.id AND m.name LIKE '%lti%') AS cisco_count,
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

$sql .= " GROUP BY c.id, u.id, cc.name, c.fullname, c.shortname, u.firstname, u.lastname
          ORDER BY u.id, c.fullname";

$records = $DB->get_records_sql($sql, $params);

if (!$records) {
    redirect($returnurl, 'No hay datos para enviar.', null, \core\output\notification::NOTIFY_WARNING);
}

// Group records by user
$users_data = [];
$user_ids = [];
foreach ($records as $r) {
    $userid = $r->userid;
    if (!isset($users_data[$userid])) {
        $users_data[$userid] = [];
        $user_ids[] = $userid;
    }
    $users_data[$userid][] = $r;
}

$users = $DB->get_records_list('user', 'id', $user_ids);
$sender = core_user::get_noreply_user();
$count = 0;
$total_attempted = count($users_data);

$format_cell = function($val) {
    return $val == 0 ? '<td align="center" style="color:red; border:1px solid #ccc; padding:5px;">0</td>' : '<td align="center" style="border:1px solid #ccc; padding:5px;">' . $val . '</td>';
};

foreach ($users_data as $userid => $user_records) {
    if (!isset($users[$userid])) {
        continue;
    }
    $subject = "Reporte de Uso del Aula Virtual - " . format_string($SITE->fullname);
    
    // Create PDF attachment
    require_once($CFG->libdir . '/pdflib.php');
    $pdf = new \pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L');
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ' Reporte de Recursos del Docente ', 0, 1, 'L', true);
    $pdf->Ln(5);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(46, 6, 'Escuela', 1, 0, 'C', true);
    $pdf->Cell(55, 6, 'Curso', 1, 0, 'C', true);
    $pdf->Cell(32, 6, 'Docente', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'URL', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'Etiq.', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'Tarea', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'Foros', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'Arch.', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'Teams', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'Fold.', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'Comp.', 1, 0, 'C', true);
    $pdf->Cell(10, 6, 'Alum.', 1, 0, 'C', true);
    $pdf->Cell(16, 6, 'T. Conx', 1, 0, 'C', true);
    $pdf->Cell(16, 6, 'T. Cre.', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 7);
    foreach ($user_records as $r) {
        $c_hrs = floor($r->total_creation / 3600);
        $c_mins = floor(($r->total_creation % 3600) / 60);
        $a_hrs = floor($r->total_connection / 3600);
        $a_mins = floor(($r->total_connection % 3600) / 60);
        
        $pdf->Cell(46, 6, $r->escuela, 1, 0, 'C', false, '', 1);
        $pdf->Cell(55, 6, $r->cursos, 1, 0, 'C', false, '', 1);
        $pdf->Cell(32, 6, $r->docente, 1, 0, 'C', false, '', 1);
        
        $print_cell = function($val, $w) use ($pdf) {
            if ($val == 0) {
                $pdf->SetTextColor(255, 0, 0);
            }
            $pdf->Cell($w, 6, $val, 1, 0, 'C');
            if ($val == 0) {
                $pdf->SetTextColor(0, 0, 0);
            }
        };

        $recursos_compartidos = $r->url_count + $r->archivos_count + $r->folder_count;

        $print_cell($r->url_count, 10);
        $print_cell($r->etiqueta_count, 10);
        $print_cell($r->tarea_count, 10);
        $print_cell($r->foros_count, 10);
        $print_cell($r->archivos_count, 10);
        $print_cell($r->teams_count, 10);
        $print_cell($r->folder_count, 10);
        $print_cell($recursos_compartidos, 10);
        $print_cell($r->alumnos_count, 10);
        $pdf->Cell(16, 6, sprintf('%dh %dm', $a_hrs, $a_mins), 1, 0, 'C');
        $pdf->Cell(16, 6, sprintf('%dh %dm', $c_hrs, $c_mins), 1, 1, 'C');
    }
    
    $tempdir = make_temp_directory('local_realtime_tracker_emails');
    $filename = 'Reporte_Uso_Aula_Virtual_' . $recipient->username . '.pdf';
    $filepath = $tempdir . '/' . $filename;
    
    $pdf->Output($filepath, 'F');
    
    // Email body
    $body_text = "Por el presente me dirijo a usted para saludarlo cordialmente y a la vez indicar que, de acuerdo con lo solicitado, se le envía el reporte de uso del aula virtual en el cual se indica lo siguiente:

1. Los docentes que en una columna estén en rojo, es porque no cumplen con el uso de esos recursos, siento estos los 3 más importantes a usar.
2. Se está considerando a los docentes que usan CARPETA en consideración de ARCHIVOS, en este sentido solo es cumplir uno de los dos (2) recursos.

El reporte se saca de acuerdo con el REGLAMENTO DE ESTUDIOS 2025, en el Art. 250 inciso A, D, E, la supervisión está a cargo de los DIRECTORES DE DEPARTAMENTO ACADÉMICO según RESOLUCIÓN CONSEJO UNIVERSITARIO N° 1318-2023-UNHEVAL, ANEXO N°6 EVALUACIÓN DEL DESEMPEÑO DOCENTE, esto en base a la Dimensión 7 EVALUACIÓN DE APRENDIZAJE.

Sin otro particular, es propicia la ocasión para renovarle las muestras de mi mayor consideración y estima especial.

Atentamente,

Documento firmado digitalmente
CARLOS LUDWIN ZEVALLOS GONZALES
ESPECIALISTA LMS";

    $html = nl2br($body_text);
    $text = $body_text;
    
    // Send email with attachment
    if (email_to_user($recipient, $sender, $subject, $text, $html, $filepath, $filename)) {
        $count++;
    }
    
    // Cleanup
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}

if ($count == 0 && $total_attempted > 0) {
    redirect($returnurl, "No se pudo enviar ningún correo. Por favor, verifica la configuración SMTP de tu servidor Moodle.", null, \core\output\notification::NOTIFY_ERROR);
} else {
    redirect($returnurl, "Se han enviado $count correos exitosamente de un total de $total_attempted.", null, \core\output\notification::NOTIFY_SUCCESS);
}
