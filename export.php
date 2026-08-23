<?php
/**
 * 1-Click PDF Expediente Export for a Student.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/pdflib.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$format = optional_param('format', 'pdf', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/realtime_tracker:viewreports', $context);

$cif = get_config('local_realtime_tracker', 'cif');
$center = get_config('local_realtime_tracker', 'centername');

$filename_base = 'Expediente_' . $user->username . '_' . date('Ymd');

if ($format === 'pdf') {
    $pdf = new \pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ' Informe General Mauri: ' . $course->fullname, 0, 1, 'L', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(45, 6, 'CENTRO DE FORMACIÓN:', 0, 0);
    $pdf->Cell(45, 6, $center, 0, 0);
    $pdf->Cell(45, 6, 'Nº CIF:', 0, 0);
    $pdf->Cell(45, 6, $cif, 0, 1);
    $pdf->Ln(5);
    
    $pdf->SetFillColor(230, 240, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, ' Estudiante: ' . fullname($user) . ' | ' . $user->email, 0, 1, 'L', true);
    $pdf->Ln(2);
    
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, 6, 'Actividad', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'Tipo', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'Entradas', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Tiempo', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Estado', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 9);
    foreach ($acts as $act) {
        $cm = $DB->get_record('course_modules', ['id' => $act->cmid]);
        if ($cm) {
            $module = $DB->get_record('modules', ['id' => $cm->module]);
            $instance = $DB->get_record($module->name, ['id' => $cm->instance]);
            $hrs = floor($act->active_seconds / 3600);
            $mins = floor(($act->active_seconds % 3600) / 60);
            $time_str = sprintf('%dh %dm', $hrs, $mins);
            
            $pdf->Cell(60, 6, substr($instance->name, 0, 30), 1);
            $pdf->Cell(30, 6, $module->name, 1);
            $pdf->Cell(20, 6, $act->entries, 1, 0, 'C');
            $pdf->Cell(40, 6, $time_str, 1, 0, 'C');
            $pdf->Cell(40, 6, 'Completada', 1, 1, 'C');
        }
    }
    
    $pdf->Output($filename_base . '.pdf', 'D');

} elseif ($format === 'doc') {
    header("Content-Type: application/vnd.ms-word; charset=UTF-8");
    header("Content-Disposition: attachment;Filename=" . $filename_base . ".doc");
    
    echo "<html><body>";
    echo "<h1>Informe General Mauri: " . htmlspecialchars($course->fullname) . "</h1>";
    echo "<p><b>CENTRO DE FORMACIÓN:</b> " . htmlspecialchars($center) . "</p>";
    echo "<p><b>Nº CIF:</b> " . htmlspecialchars($cif) . "</p>";
    echo "<h3>Estudiante: " . fullname($user) . " | " . $user->email . "</h3>";
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr style='background-color:#ccc;'><th>Actividad</th><th>Tipo</th><th>Entradas</th><th>Tiempo</th><th>Estado</th></tr>";
    
    foreach ($acts as $act) {
        $cm = $DB->get_record('course_modules', ['id' => $act->cmid]);
        if ($cm) {
            $module = $DB->get_record('modules', ['id' => $cm->module]);
            $instance = $DB->get_record($module->name, ['id' => $cm->instance]);
            $hrs = floor($act->active_seconds / 3600);
            $mins = floor(($act->active_seconds % 3600) / 60);
            $time_str = sprintf('%dh %dm', $hrs, $mins);
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($instance->name) . "</td>";
            echo "<td>" . htmlspecialchars($module->name) . "</td>";
            echo "<td align='center'>" . $act->entries . "</td>";
            echo "<td align='center'>" . $time_str . "</td>";
            echo "<td align='center'>Completada</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    echo "</body></html>";

} elseif ($format === 'xml') {
    header('Content-Type: text/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename_base . '.xml"');
    
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><informe/>');
    $xml->addChild('curso', htmlspecialchars($course->fullname));
    $xml->addChild('centro', htmlspecialchars($center));
    $xml->addChild('cif', htmlspecialchars($cif));
    
    $estudiante = $xml->addChild('estudiante');
    $estudiante->addChild('nombre', fullname($user));
    $estudiante->addChild('email', $user->email);
    
    $actividades = $xml->addChild('actividades');
    
    foreach ($acts as $act) {
        $cm = $DB->get_record('course_modules', ['id' => $act->cmid]);
        if ($cm) {
            $module = $DB->get_record('modules', ['id' => $cm->module]);
            $instance = $DB->get_record($module->name, ['id' => $cm->instance]);
            $hrs = floor($act->active_seconds / 3600);
            $mins = floor(($act->active_seconds % 3600) / 60);
            $time_str = sprintf('%dh %dm', $hrs, $mins);
            
            $actNode = $actividades->addChild('actividad');
            $actNode->addChild('nombre', htmlspecialchars($instance->name));
            $actNode->addChild('tipo', htmlspecialchars($module->name));
            $actNode->addChild('entradas', $act->entries);
            $actNode->addChild('tiempo', $time_str);
            $actNode->addChild('estado', 'Completada');
        }
    }
    
    echo $xml->asXML();
}
die;
