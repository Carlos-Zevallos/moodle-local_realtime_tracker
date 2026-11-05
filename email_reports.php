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
 * Email resource reports to teachers.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/pdflib.php');

admin_externalpage_setup('local_realtime_tracker_resources');
require_sesskey();

global $SITE;

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);
$year = optional_param('year', '', PARAM_INT);
$semester = optional_param('semester', '', PARAM_ALPHANUM);
$zerorec = optional_param('zerorec', 0, PARAM_INT);

$returnurl = new moodle_url('/local/realtime_tracker/reports_resources.php', [
    'categoryid' => $categoryid,
    'subcategoryid' => $subcategoryid,
    'year' => $year,
    'semester' => $semester,
]);

$records = \local_realtime_tracker\local\helper::get_resource_report_rows($categoryid, $subcategoryid, $year, $semester);

if ($zerorec) {
    $teacher_totals = [];
    foreach ($records as $record) {
        if (!isset($teacher_totals[$record->userid])) {
            $teacher_totals[$record->userid] = 0;
        }
        $teacher_totals[$record->userid] += $record->shared_count;
    }
    
    $filtered = [];
    foreach ($records as $record) {
        if ($teacher_totals[$record->userid] == 0) {
            $filtered[] = $record;
        }
    }
    $records = $filtered;
}

if (!$records) {
    redirect($returnurl, get_string('emailempty', 'local_realtime_tracker'), null, \core\output\notification::NOTIFY_WARNING);
}

$usersdata = [];
$userids = [];
foreach ($records as $record) {
    $userid = $record->userid;
    if (!isset($usersdata[$userid])) {
        $usersdata[$userid] = [];
        $userids[] = $userid;
    }
    $usersdata[$userid][] = $record;
}

$users = $DB->get_records_list('user', 'id', $userids);
$sender = core_user::get_noreply_user();
$count = 0;
$totalattempted = count($usersdata);

foreach ($usersdata as $userid => $userrecords) {
    if (!isset($users[$userid])) {
        continue;
    }
    $recipient = $users[$userid];
    $site = get_site();
    $subject = get_string('emailsubject', 'local_realtime_tracker', format_string($site->fullname));

    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // PAGE 1: Formal Letter
    $pdf->AddPage('P', 'A4');
    $pdf->SetTextColor(0, 0, 0);
    $fullname = mb_strtoupper(fullname($recipient));
    
    // The exact HTML string for the formal letter
    $letterhtml = <<<EOD
<div style="font-family: helvetica; font-size: 11pt; line-height: 1.5;">
    <p>Señor:<br>
    <b>{$fullname}</b><br>
    <u>Presente.-</u></p>
    <br>
    <table cellpadding="2">
        <tr>
            <td width="20%">Asunto</td>
            <td width="5%">:</td>
            <td width="75%">REPORTE DE USO DEL AULA VIRTUAL SEMESTRE 2026-2</td>
        </tr>
        <tr>
            <td>Referencia</td>
            <td>:</td>
            <td></td>
        </tr>
    </table>
    <br>
    <p>De mi consideración:</p>
    
    <p style="text-align: justify;">Por el presente me dirijo a usted para saludarlo cordialmente y a la vez indicar que, de acuerdo con lo solicitado, se le envía el reporte de uso del aula virtual en el cual se indica lo siguiente:</p>
    
    <p style="text-align: justify;">Cabe destacar que este informe evalúa principalmente tres criterios: Archivos, Tareas y Foros. Para su consideración, detallamos las siguientes pautas de evaluación:</p>
    
    <ul style="text-align: justify;">
        <li>En el criterio de "Archivos", la inclusión de carpetas y enlaces (URLs) se contabiliza como un equivalente válido.</li>
        <li>La utilización de áreas de texto y medios es de carácter opcional.</li>
        <li>El rubro de "Recursos compartidos" refleja la sumatoria total de enlaces (URLs), archivos y carpetas empleados.</li>
    </ul>
    
    <p style="text-align: justify;">El reporte se saca de acuerdo con el <i>REGLAMENTO DE ESTUDIOS 2025</i>, en el <i>Art. 250 inciso A, D, E</i>, la supervisión está a cargo de los <i>DIRECTORES DE DEPARTAMENTO ACADÉMICO</i> según <i>RESOLUCIÓN CONSEJO UNIVERSITARIO N° 1318-2023-UNHEVAL, ANEXO N°6 EVALUACIÓN DEL DESEMPEÑO DOCENTE</i>, esto en base a la <i>Dimensión 7 EVALUACIÓN DE APRENDIZAJE</i>.</p>
    
    <p style="text-align: justify;">Sin otro particular, es propicia la ocasión para renovarle las muestras de mi mayor consideración y estima especial.</p>
    
    <p>Atentamente,</p>
    <br><br><br>
    <p style="text-align: center;">Documento firmado digitalmente<br>
    <b>CARLOS LUDWIN ZEVALLOS GONZALES</b><br>
    ESPECIALISTA LMS</p>
</div>
EOD;

    $pdf->writeHTMLCell(0, 0, '', '', $letterhtml, 0, 1, 0, true, '', true);
    
    // Add side text with rotation
    $pdf->StartTransform();
    $pdf->Rotate(90, 202, 250);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Text(202, 250, 'Documento electrónico firmado digitalmente en el marco de la Ley N° 27269, Ley de Firmas y Certificados Digitales, su Reglamento y modificatorias.');
    $pdf->StopTransform();
    
    // Footer box
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->MultiCell(130, 4, "Esta es una copia auténtica imprimible de un documento electrónico archivado en la Universidad Nacional Hermilio Valdizán, aplicando lo dispuesto por el Art. 25 de D.S. 070-2013-PCM y la Tercera Disposición Complementaria Final del D.S. 026-2016-PCM. Su autenticidad e integridad pueden ser contrastadas a través de la siguiente dirección web:\nUrl: https://sisgedo.unheval.edu.pe:8181/verifica/inicio.do Clave: PCYWTQK", 1, 'L');

    // PAGE 2: Table Report
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage('L');
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ' ' . get_string('pdfteacherresources', 'local_realtime_tracker'), 0, 1, 'L', true);
    $pdf->Ln(5);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 7);
    $widths = [35, 53, 40, 10, 28, 16, 15, 18, 18, 26, 18];
    
    $headers = [
        get_string('school', 'local_realtime_tracker'),
        get_string('courses', 'local_realtime_tracker'),
        get_string('teacher', 'local_realtime_tracker'),
        get_string('url', 'local_realtime_tracker'),
        get_string('label', 'local_realtime_tracker'),
        get_string('assignment', 'local_realtime_tracker'),
        get_string('forums', 'local_realtime_tracker'),
        get_string('files', 'local_realtime_tracker'),
        get_string('folder', 'local_realtime_tracker'),
        get_string('sharedresources', 'local_realtime_tracker'),
        get_string('students', 'local_realtime_tracker')
    ];
    
    foreach ($headers as $index => $header) {
        $ln = ($index === count($headers) - 1) ? 1 : 0;
        $pdf->Cell($widths[$index], 6, $header, 1, $ln, 'C', true, '', 1);
    }

    $pdf->SetFont('helvetica', '', 7);
    foreach ($userrecords as $record) {
        $has_base_reqs = ($record->assign_count >= 2 && $record->forum_count >= 2);
        $is_excellent = $has_base_reqs && ($record->shared_count >= 10);
        $is_congrats = $has_base_reqs && ($record->shared_count >= 6 && $record->shared_count < 10);
        
        $h = ($is_excellent || $is_congrats) ? 9 : 6;
        
        $teacherhtml = $record->teachername;
        if ($is_excellent) {
            $teacherhtml .= '<br><span style="color:#047857; font-size:8pt;"><span style="font-family:zapfdingbats;">H</span> Excelente</span>';
        } else if ($is_congrats) {
            $teacherhtml .= '<br><span style="color:#d97706; font-size:8pt;"><span style="font-family:zapfdingbats;">I</span> ¡Felicitaciones!</span>';
        }

        $pdf->Cell($widths[0], $h, $record->categoryname, 1, 0, 'C', false, '', 1, false, 'T', 'M');
        $pdf->Cell($widths[1], $h, $record->coursename, 1, 0, 'C', false, '', 1, false, 'T', 'M');
        $pdf->MultiCell($widths[2], $h, $teacherhtml, 1, 'C', false, 0, '', '', true, 0, true, true, $h, 'M');

        $values = [
            'url' => $record->url_count,
            'label' => $record->label_count,
            'assign' => $record->assign_count,
            'forum' => $record->forum_count,
            'file' => $record->file_count,
            'folder' => $record->folder_count,
            'shared' => $record->shared_count,
            'student' => $record->student_count,
        ];
        
        $idx = 0;
        foreach ($values as $key => $value) {
            $is_red = false;
            if ($key === 'assign' && $value <= 1) {
                $is_red = true;
            } else if ($key === 'forum' && $value <= 1) {
                $is_red = true;
            } else if ($key === 'file' && ($record->file_count + $record->folder_count) == 0) {
                $is_red = true;
            } else if ($key === 'folder' && ($record->file_count + $record->folder_count) == 0) {
                $is_red = true;
            }
            
            if ($is_red) {
                $pdf->SetTextColor(255, 0, 0);
            }
            $is_last = ($idx === count($values) - 1);
            $pdf->Cell($widths[$idx + 3], $h, $value, 1, $is_last ? 1 : 0, 'C', false, '', 1, false, 'T', 'M');
            $pdf->SetTextColor(0, 0, 0);
            $idx++;
        }
    }

    $tempdir = make_temp_directory('local_realtime_tracker_emails');
    // Ensure the attachment name has the .pdf extension
    $attachmentname = get_string('emailattachment', 'local_realtime_tracker', $recipient->username) . '.pdf';
    $filepath = $tempdir . '/' . $attachmentname;
    $pdf->Output($filepath, 'F');

    $body = get_string('emailbody', 'local_realtime_tracker');
    global $OUTPUT;
    $emaildata = new \stdClass();
    $emaildata->firmaurl = $OUTPUT->image_url('firma', 'local_realtime_tracker')->out(false);
    $body = get_string('emailbody', 'local_realtime_tracker', $emaildata);
    if (email_to_user($recipient, $sender, $subject, $body, nl2br($body), $filepath, $attachmentname)) {
        $count++;
    }
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}

if ($count == 0 && $totalattempted > 0) {
    redirect($returnurl, get_string('emailnone', 'local_realtime_tracker'), null,
        \core\output\notification::NOTIFY_ERROR);
}

redirect($returnurl, get_string('emailsent', 'local_realtime_tracker', [
    'sent' => $count,
    'total' => $totalattempted,
]), null, \core\output\notification::NOTIFY_SUCCESS);
