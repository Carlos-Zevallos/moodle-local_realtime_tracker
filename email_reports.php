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
    $pdf->AddPage('L');
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ' ' . get_string('pdfteacherresources', 'local_realtime_tracker'), 0, 1, 'L', true);
    $pdf->Ln(5);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(46, 6, get_string('school', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(55, 6, get_string('courses', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(32, 6, get_string('teacher', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(10, 6, get_string('url', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(10, 6, get_string('label', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(10, 6, get_string('assignment', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(10, 6, get_string('forums', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(10, 6, get_string('files', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(10, 6, get_string('teams', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(10, 6, get_string('folder', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(10, 6, get_string('students', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(16, 6, get_string('connectiontime', 'local_realtime_tracker'), 1, 0, 'C', true);
    $pdf->Cell(16, 6, get_string('creationtime', 'local_realtime_tracker'), 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 7);
    foreach ($userrecords as $record) {
        $pdf->Cell(46, 6, $record->categoryname, 1, 0, 'C', false, '', 1);
        $pdf->Cell(55, 6, $record->coursename, 1, 0, 'C', false, '', 1);
        $pdf->Cell(32, 6, $record->teachername, 1, 0, 'C', false, '', 1);

        $values = [
            $record->url_count, $record->label_count, $record->assign_count, $record->forum_count,
            $record->file_count, $record->teams_count, $record->folder_count, $record->student_count,
        ];
        foreach ($values as $value) {
            if ($value == 0) {
                $pdf->SetTextColor(255, 0, 0);
            }
            $pdf->Cell(10, 6, $value, 1, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Cell(16, 6, \local_realtime_tracker\local\helper::format_duration((int)$record->total_connection), 1, 0, 'C');
        $pdf->Cell(16, 6, \local_realtime_tracker\local\helper::format_duration((int)$record->total_creation), 1, 1, 'C');
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
