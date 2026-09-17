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

$returnurl = new moodle_url('/local/realtime_tracker/reports_resources.php', [
    'categoryid' => $categoryid,
    'subcategoryid' => $subcategoryid,
    'year' => $year,
    'semester' => $semester,
]);

$records = \local_realtime_tracker\local\helper::get_resource_report_rows($categoryid, $subcategoryid, $year, $semester);
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
    
    <ol style="text-align: justify;">
        <li>Los docentes que en una columna estén en rojo, es porque no cumplen con el uso de esos recursos, siento estos los 3 más importantes a usar.</li>
        <li>Los indicadores en rojo señalan un uso por debajo del estándar institucional para los recursos correspondientes. Para la evaluación del criterio de archivos, la inclusión de carpetas es contabilizada como un equivalente válido.</li>
    </ol>
    
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
    $pdf->Rotate(90, 195, 250);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Text(195, 250, 'Documento electrónico firmado digitalmente en el marco de la Ley N° 27269, Ley de Firmas y Certificados Digitales, su Reglamento y modificatorias.');
    $pdf->StopTransform();
    
    // Footer box
    $pdf->SetY(-30);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->MultiCell(130, 4, "Esta es una copia auténtica imprimible de un documento electrónico archivado en la Universidad Nacional Hermilio Valdizán, aplicando lo dispuesto por el Art. 25 de D.S. 070-2013-PCM y la Tercera Disposición Complementaria Final del D.S. 026-2016-PCM. Su autenticidad e integridad pueden ser contrastadas a través de la siguiente dirección web:\nUrl: https://sisgedo.unheval.edu.pe:8181/verifica/inicio.do Clave: PCYWTQK", 1, 'L');

    // PAGE 2: Table Report
    $pdf->AddPage('L');
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ' ' . get_string('pdfteacherresources', 'local_realtime_tracker'), 0, 1, 'L', true);
    $pdf->Ln(5);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 7);
    $widths = [33, 50, 30, 9, 9, 15, 11, 9, 10, 23, 12, 18, 18];
    
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
        get_string('students', 'local_realtime_tracker'),
        get_string('connectiontime', 'local_realtime_tracker'),
        get_string('creationtime', 'local_realtime_tracker')
    ];
    
    foreach ($headers as $index => $header) {
        $ln = ($index === count($headers) - 1) ? 1 : 0;
        $pdf->Cell($widths[$index], 6, $header, 1, $ln, 'C', true);
    }

    $pdf->SetFont('helvetica', '', 7);
    foreach ($userrecords as $record) {
        $pdf->Cell(33, 6, $record->categoryname, 1, 0, 'C', false, '', 1);
        $pdf->Cell(50, 6, $record->coursename, 1, 0, 'C', false, '', 1);
        $pdf->Cell(30, 6, $record->teachername, 1, 0, 'C', false, '', 1);

        $values = [
            $record->url_count, $record->label_count, $record->assign_count, $record->forum_count,
            $record->file_count, $record->folder_count, $record->shared_count, $record->student_count,
        ];
        foreach ($values as $idx => $value) {
            if ($value == 0) {
                $pdf->SetTextColor(255, 0, 0);
            }
            $pdf->Cell($widths[$idx + 3], 6, $value, 1, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Cell($widths[11], 6, \local_realtime_tracker\local\helper::format_duration((int)$record->total_connection), 1, 0, 'C');
        $pdf->Cell($widths[12], 6, \local_realtime_tracker\local\helper::format_duration((int)$record->total_creation), 1, 1, 'C');
    }

    $tempdir = make_temp_directory('local_realtime_tracker_emails');
    // Ensure the attachment name has the .pdf extension
    $attachmentname = get_string('emailattachment', 'local_realtime_tracker', $recipient->username) . '.pdf';
    $filepath = $tempdir . '/' . $attachmentname;
    $pdf->Output($filepath, 'F');

    $body = get_string('emailbody', 'local_realtime_tracker');
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
