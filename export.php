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
 * Student report export.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$format = optional_param('format', 'pdf', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/realtime_tracker:viewreports', $context);
require_sesskey();

$cif = get_config('local_realtime_tracker', 'cif');
$center = get_config('local_realtime_tracker', 'centername');
$activities = \local_realtime_tracker\local\helper::get_student_activities($course, $userid);
$filename = get_string('exportfilename', 'local_realtime_tracker', [
    'username' => $user->username,
    'date' => date('Ymd'),
]);

if ($format === 'pdf') {
    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ' ' . get_string('pdfgeneralreport', 'local_realtime_tracker', format_string($course->fullname)),
        0, 1, 'L', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(45, 6, get_string('trainingcentre', 'local_realtime_tracker') . ':', 0, 0);
    $pdf->Cell(45, 6, $center, 0, 0);
    $pdf->Cell(45, 6, get_string('cifnumber', 'local_realtime_tracker') . ':', 0, 0);
    $pdf->Cell(45, 6, $cif, 0, 1);
    $pdf->Ln(5);

    $pdf->SetFillColor(230, 240, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, ' ' . get_string('student', 'local_realtime_tracker') . ': ' . fullname($user) . ' | ' . $user->email,
        0, 1, 'L', true);
    $pdf->Ln(2);

    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, 6, get_string('activity', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(30, 6, get_string('type', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(20, 6, get_string('entries', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(40, 6, get_string('time', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(40, 6, get_string('status', 'local_realtime_tracker'), 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 9);
    foreach ($activities as $activity) {
        $pdf->Cell(60, 6, substr($activity['name'], 0, 30), 1);
        $pdf->Cell(30, 6, $activity['type'], 1);
        $pdf->Cell(20, 6, $activity['entries'], 1, 0, 'C');
        $pdf->Cell(40, 6, $activity['time'], 1, 0, 'C');
        $pdf->Cell(40, 6, $activity['status'], 1, 1, 'C');
    }

    $pdf->Output($filename . '.pdf', 'D');
} else if ($format === 'doc') {
    header('Content-Type: application/vnd.ms-word; charset=UTF-8');
    header('Content-Disposition: attachment;Filename=' . $filename . '.doc');

    echo '<html><body>';
    echo '<h1>' . s(get_string('pdfgeneralreport', 'local_realtime_tracker', format_string($course->fullname))) . '</h1>';
    echo '<p><b>' . s(get_string('trainingcentre', 'local_realtime_tracker')) . ':</b> ' . s($center) . '</p>';
    echo '<p><b>' . s(get_string('cifnumber', 'local_realtime_tracker')) . ':</b> ' . s($cif) . '</p>';
    echo '<h3>' . s(get_string('student', 'local_realtime_tracker')) . ': ' . s(fullname($user)) . ' | ' . s($user->email) . '</h3>';
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo '<tr style="background-color:#ccc;"><th>' . s(get_string('activity', 'local_realtime_tracker')) . '</th>';
    echo '<th>' . s(get_string('type', 'local_realtime_tracker')) . '</th>';
    echo '<th>' . s(get_string('entries', 'local_realtime_tracker')) . '</th>';
    echo '<th>' . s(get_string('time', 'local_realtime_tracker')) . '</th>';
    echo '<th>' . s(get_string('status', 'local_realtime_tracker')) . '</th></tr>';
    foreach ($activities as $activity) {
        echo '<tr>';
        echo '<td>' . s($activity['name']) . '</td>';
        echo '<td>' . s($activity['type']) . '</td>';
        echo '<td align="center">' . s($activity['entries']) . '</td>';
        echo '<td align="center">' . s($activity['time']) . '</td>';
        echo '<td align="center">' . s($activity['status']) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
} else if ($format === 'xml') {
    header('Content-Type: text/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xml"');

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><report/>');
    $xml->addChild('course', htmlspecialchars(format_string($course->fullname)));
    $xml->addChild('centre', htmlspecialchars((string)$center));
    $xml->addChild('cif', htmlspecialchars((string)$cif));
    $student = $xml->addChild('student');
    $student->addChild('name', htmlspecialchars(fullname($user)));
    $student->addChild('email', htmlspecialchars($user->email));
    $activitiesnode = $xml->addChild('activities');
    foreach ($activities as $activity) {
        $node = $activitiesnode->addChild('activity');
        $node->addChild('name', htmlspecialchars($activity['name']));
        $node->addChild('type', htmlspecialchars($activity['type']));
        $node->addChild('entries', $activity['entries']);
        $node->addChild('time', htmlspecialchars($activity['time']));
        $node->addChild('status', htmlspecialchars($activity['status']));
    }
    echo $xml->asXML();
}

die;
