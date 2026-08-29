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
 * Institutional resources report export.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/excellib.php');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);
$year = optional_param('year', '', PARAM_INT);
$semester = optional_param('semester', '', PARAM_ALPHANUM);
$format = optional_param('format', 'pdf', PARAM_ALPHA);

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
require_sesskey();

$records = \local_realtime_tracker\local\helper::get_resource_report_rows($categoryid, $subcategoryid, $year, $semester);
$filename = get_string('resourcesfilename', 'local_realtime_tracker', date('Ymd'));

$totals = [
    'url' => 0,
    'label' => 0,
    'assign' => 0,
    'forum' => 0,
    'file' => 0,
    'teams' => 0,
    'folder' => 0,
];
foreach ($records as $record) {
    $totals['url'] += $record->url_count;
    $totals['label'] += $record->label_count;
    $totals['assign'] += $record->assign_count;
    $totals['forum'] += $record->forum_count;
    $totals['file'] += $record->file_count;
    $totals['teams'] += $record->teams_count;
    $totals['folder'] += $record->folder_count;
}

$headers = [
    get_string('school', 'local_realtime_tracker'),
    get_string('courses', 'local_realtime_tracker'),
    get_string('teacher', 'local_realtime_tracker'),
    get_string('url', 'local_realtime_tracker'),
    get_string('label', 'local_realtime_tracker'),
    get_string('assignment', 'local_realtime_tracker'),
    get_string('forums', 'local_realtime_tracker'),
    get_string('files', 'local_realtime_tracker'),
    get_string('teams', 'local_realtime_tracker'),
    get_string('folder', 'local_realtime_tracker'),
    get_string('students', 'local_realtime_tracker'),
    get_string('connectiontime', 'local_realtime_tracker'),
    get_string('creationtime', 'local_realtime_tracker'),
];

if ($format === 'pdf') {
    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L');
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ' ' . get_string('pdfresourcesreport', 'local_realtime_tracker'), 0, 1, 'L', true);
    $pdf->Ln(5);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 10, get_string('resourcetotals', 'local_realtime_tracker'), 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $totallabels = [
        'url' => get_string('url', 'local_realtime_tracker'),
        'label' => get_string('label', 'local_realtime_tracker'),
        'assign' => get_string('assignment', 'local_realtime_tracker'),
        'forum' => get_string('forums', 'local_realtime_tracker'),
        'file' => get_string('files', 'local_realtime_tracker'),
        'teams' => get_string('teams', 'local_realtime_tracker'),
        'folder' => get_string('folder', 'local_realtime_tracker'),
    ];
    foreach ($totallabels as $key => $label) {
        $pdf->Cell(40, 6, $label, 1, 0, 'L');
        $pdf->Cell(20, 6, $totals[$key], 1, 1, 'C');
    }
    $pdf->Ln(5);

    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 8);
    $widths = [46, 61, 35, 12, 12, 12, 12, 12, 12, 12, 12, 17, 17];
    foreach ($headers as $index => $header) {
        $ln = ($index === count($headers) - 1) ? 1 : 0;
        $pdf->Cell($widths[$index], 6, $header, 1, $ln, 'C', true);
    }

    $pdf->SetFont('helvetica', '', 7);
    foreach ($records as $record) {
        $pdf->Cell(46, 6, $record->categoryname, 1, 0, 'C', false, '', 1);
        $pdf->Cell(61, 6, $record->coursename, 1, 0, 'C', false, '', 1);
        $pdf->Cell(35, 6, $record->teachername, 1, 0, 'C', false, '', 1);
        $values = [
            $record->url_count, $record->label_count, $record->assign_count, $record->forum_count,
            $record->file_count, $record->teams_count, $record->folder_count, $record->student_count,
        ];
        foreach ($values as $value) {
            if ($value == 0) {
                $pdf->SetTextColor(255, 0, 0);
            }
            $pdf->Cell(12, 6, $value, 1, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Cell(17, 6, \local_realtime_tracker\local\helper::format_duration((int)$record->total_connection), 1, 0, 'C');
        $pdf->Cell(17, 6, \local_realtime_tracker\local\helper::format_duration((int)$record->total_creation), 1, 1, 'C');
    }
    $pdf->Output($filename . '.pdf', 'D');
} else if ($format === 'doc') {
    header('Content-Type: application/vnd.ms-word; charset=UTF-8');
    header('Content-Disposition: attachment;Filename=' . $filename . '.doc');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h1>' . s(get_string('pdfresourcesreport', 'local_realtime_tracker')) . '</h1>';
    echo '<h3>' . s(get_string('resourcetotals', 'local_realtime_tracker')) . '</h3>';
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo '<tr><td>' . s(get_string('url', 'local_realtime_tracker')) . '</td><td>' . $totals['url'] . '</td></tr>';
    echo '<tr><td>' . s(get_string('label', 'local_realtime_tracker')) . '</td><td>' . $totals['label'] . '</td></tr>';
    echo '<tr><td>' . s(get_string('assignment', 'local_realtime_tracker')) . '</td><td>' . $totals['assign'] . '</td></tr>';
    echo '<tr><td>' . s(get_string('forums', 'local_realtime_tracker')) . '</td><td>' . $totals['forum'] . '</td></tr>';
    echo '<tr><td>' . s(get_string('files', 'local_realtime_tracker')) . '</td><td>' . $totals['file'] . '</td></tr>';
    echo '<tr><td>' . s(get_string('teams', 'local_realtime_tracker')) . '</td><td>' . $totals['teams'] . '</td></tr>';
    echo '<tr><td>' . s(get_string('folder', 'local_realtime_tracker')) . '</td><td>' . $totals['folder'] . '</td></tr>';
    echo '</table>';
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . s($header) . '</th>';
    }
    echo '</tr>';
    foreach ($records as $record) {
        echo '<tr>';
        echo '<td>' . s($record->categoryname) . '</td>';
        echo '<td>' . s($record->coursename) . '</td>';
        echo '<td>' . s($record->teachername) . '</td>';
        echo '<td>' . $record->url_count . '</td>';
        echo '<td>' . $record->label_count . '</td>';
        echo '<td>' . $record->assign_count . '</td>';
        echo '<td>' . $record->forum_count . '</td>';
        echo '<td>' . $record->file_count . '</td>';
        echo '<td>' . $record->teams_count . '</td>';
        echo '<td>' . $record->folder_count . '</td>';
        echo '<td>' . $record->student_count . '</td>';
        echo '<td>' . s(\local_realtime_tracker\local\helper::format_duration((int)$record->total_connection)) . '</td>';
        echo '<td>' . s(\local_realtime_tracker\local\helper::format_duration((int)$record->total_creation)) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
} else if ($format === 'xlsx') {
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send($filename);
    $sheet = $workbook->add_worksheet(get_string('results', 'local_realtime_tracker'));
    foreach ($headers as $col => $header) {
        $sheet->write_string(0, $col, $header);
    }
    $rownum = 1;
    foreach ($records as $record) {
        $sheet->write_string($rownum, 0, $record->categoryname);
        $sheet->write_string($rownum, 1, $record->coursename);
        $sheet->write_string($rownum, 2, $record->teachername);
        $sheet->write_number($rownum, 3, $record->url_count);
        $sheet->write_number($rownum, 4, $record->label_count);
        $sheet->write_number($rownum, 5, $record->assign_count);
        $sheet->write_number($rownum, 6, $record->forum_count);
        $sheet->write_number($rownum, 7, $record->file_count);
        $sheet->write_number($rownum, 8, $record->teams_count);
        $sheet->write_number($rownum, 9, $record->folder_count);
        $sheet->write_number($rownum, 10, $record->student_count);
        $sheet->write_string($rownum, 11, \local_realtime_tracker\local\helper::format_duration((int)$record->total_connection));
        $sheet->write_string($rownum, 12, \local_realtime_tracker\local\helper::format_duration((int)$record->total_creation));
        $rownum++;
    }
    $workbook->close();
} else if ($format === 'xml') {
    header('Content-Type: text/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><report/>');
    foreach ($records as $record) {
        $row = $xml->addChild('record');
        $row->addChild('school', htmlspecialchars($record->categoryname));
        $row->addChild('course', htmlspecialchars($record->coursename));
        $row->addChild('teacher', htmlspecialchars($record->teachername));
        $row->addChild('url', $record->url_count);
        $row->addChild('label', $record->label_count);
        $row->addChild('assignment', $record->assign_count);
        $row->addChild('forums', $record->forum_count);
        $row->addChild('files', $record->file_count);
        $row->addChild('teams', $record->teams_count);
        $row->addChild('folder', $record->folder_count);
        $row->addChild('students', $record->student_count);
        $row->addChild('connection_time', htmlspecialchars(
            \local_realtime_tracker\local\helper::format_duration((int)$record->total_connection)
        ));
        $row->addChild('creation_time', htmlspecialchars(
            \local_realtime_tracker\local\helper::format_duration((int)$record->total_creation)
        ));
    }
    echo $xml->asXML();
}

die;
