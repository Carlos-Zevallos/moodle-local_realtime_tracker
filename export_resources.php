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

// Sort records alphabetically and by condition to match the UI
usort($records, function($a, $b) {
    // 1. Sort by School (Category Name) ascending
    $catCmp = strcmp(mb_strtolower($a->categoryname), mb_strtolower($b->categoryname));
    if ($catCmp !== 0) {
        return $catCmp;
    }

    // 2. Sort by Condition (Excelente > Felicitaciones > Ninguno)
    $get_condition_rank = function($row) {
        $has_minimum_tasks = ($row->assign_count >= 2) && ($row->forum_count >= 2);
        $is_excellent = $has_minimum_tasks && ($row->shared_count >= 10);
        $is_congrats  = $has_minimum_tasks && ($row->shared_count >= 6 && $row->shared_count < 10);
        if ($is_excellent) return 1;
        if ($is_congrats) return 2;
        return 3;
    };
    
    $rankA = $get_condition_rank($a);
    $rankB = $get_condition_rank($b);
    if ($rankA !== $rankB) {
        return $rankA <=> $rankB;
    }

    // 3. Sort by Teacher Name ascending
    return strcmp(mb_strtolower($a->teachername), mb_strtolower($b->teachername));
});

$filename = get_string('resourcesfilename', 'local_realtime_tracker', date('Ymd'));

$totals = [
    'url' => 0,
    'label' => 0,
    'assign' => 0,
    'forum' => 0,
    'file' => 0,
    'folder' => 0,
    'shared' => 0,
];
foreach ($records as $record) {
    $totals['url'] += $record->url_count;
    $totals['label'] += $record->label_count;
    $totals['assign'] += $record->assign_count;
    $totals['forum'] += $record->forum_count;
    $totals['file'] += $record->file_count;
    $totals['folder'] += $record->folder_count;
    $totals['shared'] += $record->shared_count;
}

$school_stats = [];
$total_ai_credits = 0;
foreach ($records as $record) {
    $school = $record->categoryname;
    if (!isset($school_stats[$school])) {
        $school_stats[$school] = ['url'=>0, 'file'=>0, 'assign'=>0, 'forum'=>0, 'total'=>0, 'ai'=>0];
    }
    $school_stats[$school]['url'] += $record->url_count;
    $school_stats[$school]['file'] += $record->file_count;
    $school_stats[$school]['assign'] += $record->assign_count;
    $school_stats[$school]['forum'] += $record->forum_count;
    $school_stats[$school]['total'] += $record->shared_count;
    $ai = isset($record->ai_credits) ? $record->ai_credits : 0;
    $school_stats[$school]['ai'] += $ai;
    $total_ai_credits += $ai;
}

$headers = [
    get_string('school', 'local_realtime_tracker'),
    get_string('courses', 'local_realtime_tracker'),
    get_string('teacher', 'local_realtime_tracker'),
    'CRÉDITOS IA',
    get_string('url', 'local_realtime_tracker'),
    get_string('label', 'local_realtime_tracker'),
    get_string('assignment', 'local_realtime_tracker'),
    get_string('forums', 'local_realtime_tracker'),
    get_string('files', 'local_realtime_tracker'),
    get_string('folder', 'local_realtime_tracker'),
    get_string('sharedresources', 'local_realtime_tracker'),
    get_string('students', 'local_realtime_tracker'),
    get_string('connectiontime', 'local_realtime_tracker'),
    get_string('creationtime', 'local_realtime_tracker'),
];

// QuickChart URLs generation
// 1. Chart Comp (Donut)
$totallabels = [
    'url' => get_string('url', 'local_realtime_tracker'),
    'label' => get_string('label', 'local_realtime_tracker'),
    'assign' => get_string('assignment', 'local_realtime_tracker'),
    'forum' => get_string('forums', 'local_realtime_tracker'),
    'file' => get_string('files', 'local_realtime_tracker'),
    'folder' => get_string('folder', 'local_realtime_tracker'),
];
$comp_data_pairs = [];
foreach ($totals as $type => $count) {
    if ($type !== 'shared' && $count > 0) {
        $comp_data_pairs[] = ['label' => $totallabels[$type], 'value' => $count];
    }
}
$clabels = []; $cdata = [];
foreach ($comp_data_pairs as $pair) { $clabels[] = $pair['label']; $cdata[] = $pair['value']; }
if (empty($cdata)) { $clabels = [get_string('nodata', 'local_realtime_tracker')]; $cdata = [1]; }
$chart_comp_url = 'https://quickchart.io/chart?w=350&h=200&c=' . urlencode(json_encode([
    'type' => 'pie',
    'data' => ['labels' => $clabels, 'datasets' => [['data' => $cdata, 'backgroundColor' => ['#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f','#edc948']]]],
    'options' => ['plugins' => ['datalabels' => ['display' => true, 'color' => '#fff', 'font' => ['weight' => 'bold']]]]
]));

// Sort schools for Chart Schools
$sorted_schools = $school_stats;
uasort($sorted_schools, function($a, $b) { return $b['total'] <=> $a['total']; });
$sorted_schools = array_slice($sorted_schools, 0, 15);
$sl_labels = []; $sl_url = []; $sl_file = []; $sl_assign = []; $sl_forum = [];
foreach ($sorted_schools as $name => $stat) {
    $sl_labels[] = mb_strlen($name) > 20 ? mb_substr($name, 0, 17) . '...' : $name;
    $sl_url[] = $stat['url']; $sl_file[] = $stat['file']; $sl_assign[] = $stat['assign']; $sl_forum[] = $stat['forum'];
}
$chart_schools_url = 'https://quickchart.io/chart?w=500&h=250&c=' . urlencode(json_encode([
    'type' => 'bar',
    'data' => [
        'labels' => $sl_labels,
        'datasets' => [
            ['label' => 'URL', 'data' => $sl_url, 'backgroundColor' => '#4e79a7'],
            ['label' => 'Archivos', 'data' => $sl_file, 'backgroundColor' => '#f28e2b'],
            ['label' => 'Tareas', 'data' => $sl_assign, 'backgroundColor' => '#e15759'],
            ['label' => 'Foros', 'data' => $sl_forum, 'backgroundColor' => '#76b7b2']
        ]
    ],
    'options' => ['scales' => ['xAxes' => [['stacked' => true]], 'yAxes' => [['stacked' => true]]]]
]));

// Chart AI
$sorted_ai = $school_stats;
uasort($sorted_ai, function($a, $b) { return $b['ai'] <=> $a['ai']; });
$sorted_ai = array_slice($sorted_ai, 0, 15);
$ai_labels = []; $ai_data = [];
foreach ($sorted_ai as $name => $stat) {
    if ($stat['ai'] > 0) {
        $ai_labels[] = mb_strlen($name) > 20 ? mb_substr($name, 0, 17) . '...' : $name;
        $ai_data[] = $stat['ai'];
    }
}
if (empty($ai_data)) { $ai_labels = ['Sin datos']; $ai_data = [0]; }
$chart_ai_url = 'https://quickchart.io/chart?w=500&h=250&c=' . urlencode(json_encode([
    'type' => 'bar',
    'data' => ['labels' => $ai_labels, 'datasets' => [['label' => 'Créditos IA', 'data' => $ai_data, 'backgroundColor' => '#f472b6']]],
]));

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
    
    $start_y = $pdf->GetY();
    foreach ($totallabels as $key => $label) {
        $pdf->Cell(40, 5, $label, 1, 0, 'L');
        $pdf->Cell(20, 5, $totals[$key], 1, 1, 'C');
    }
    
    // Insert charts next to the totals table
    try {
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $tempdir = make_request_directory();
        
        $comp_file = $tempdir . '/comp.png';
        file_put_contents($comp_file, $curl->get($chart_comp_url));
        $schools_file = $tempdir . '/schools.png';
        file_put_contents($schools_file, $curl->get($chart_schools_url));
        $ai_file = $tempdir . '/ai.png';
        file_put_contents($ai_file, $curl->get($chart_ai_url));

        // Composición
        if (file_exists($comp_file) && filesize($comp_file) > 0) {
            $pdf->Image($comp_file, 80, $start_y, 60, 35, 'PNG');
        }
        // Recursos por escuela
        if (file_exists($schools_file) && filesize($schools_file) > 0) {
            $pdf->Image($schools_file, 145, $start_y, 70, 35, 'PNG');
        }
        // Uso de IA
        if (file_exists($ai_file) && filesize($ai_file) > 0) {
            $pdf->Image($ai_file, 220, $start_y, 70, 35, 'PNG');
        }
    } catch (\Exception $e) {
        // Fallback if image download fails
    }

    $pdf->SetY(max($pdf->GetY(), $start_y + 40));
    $pdf->Ln(5);

    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 7);
    $widths = [35, 52, 35, 12, 8, 8, 12, 10, 8, 10, 20, 16, 17, 17];
    
    // Header setup - Use MultiCell for the first row of headers to allow wrapping
    $start_x = $pdf->GetX();
    $current_y = $pdf->GetY();
    $max_h = 0;
    
    foreach ($headers as $index => $header) {
        $pdf->SetXY($start_x, $current_y);
        // MultiCell allows 2 lines if needed
        $pdf->MultiCell($widths[$index], 5, $header, 1, 'C', true);
        if ($pdf->GetY() - $current_y > $max_h) {
            $max_h = $pdf->GetY() - $current_y;
        }
        $start_x += $widths[$index];
    }
    // Set Y below the headers
    $pdf->SetY($current_y + $max_h);

    $pdf->SetFont('helvetica', '', 7);
    foreach ($records as $record) {
        $pdf->Cell($widths[0], 6, $record->categoryname, 1, 0, 'C', false, '', 1);
        $pdf->Cell($widths[1], 6, $record->coursename, 1, 0, 'C', false, '', 1);
        $pdf->Cell($widths[2], 6, $record->teachername, 1, 0, 'C', false, '', 1);
        
        $ai_credits = isset($record->ai_credits) ? $record->ai_credits : 0;
        if ($ai_credits > 0) $pdf->SetTextColor(244, 114, 182); // Pink for AI
        $pdf->Cell($widths[3], 6, $ai_credits, 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $values = [
            $record->url_count, $record->label_count, $record->assign_count, $record->forum_count,
            $record->file_count, $record->folder_count, $record->shared_count, $record->student_count,
        ];
        foreach ($values as $idx => $value) {
            if ($value == 0 || (($idx == 2 || $idx == 3) && $value <= 1)) {
                $pdf->SetTextColor(255, 0, 0);
            }
            $pdf->Cell($widths[$idx + 4], 6, $value, 1, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Cell($widths[12], 6, \local_realtime_tracker\local\helper::format_duration((int)$record->total_connection), 1, 0, 'C');
        $pdf->Cell($widths[13], 6, \local_realtime_tracker\local\helper::format_duration((int)$record->total_creation), 1, 1, 'C');
    }
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->MultiCell(0, 5, get_string('pdfnote', 'local_realtime_tracker'), 0, 'L');
    $pdf->Output($filename . '.pdf', 'D');
} else if ($format === 'doc') {
    header('Content-Type: application/vnd.ms-word; charset=UTF-8');
    header('Content-Disposition: attachment;Filename=' . $filename . '.doc');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">';
    echo '<style>
        @page Section1 {
            size: 841.9pt 595.3pt; /* A4 Landscape */
            mso-page-orientation: landscape;
            margin: 1.0in 1.0in 1.0in 1.0in;
        }
        div.Section1 { page: Section1; }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 9pt;
            font-family: Arial, sans-serif;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-left { text-align: left; }
    </style>';
    echo '</head><body>';
    echo '<div class="Section1">';
    echo '<h1 style="text-align:center; font-family: Arial, sans-serif;">' . s(get_string('pdfresourcesreport', 'local_realtime_tracker')) . '</h1>';
    
    echo '<table style="border:none; width:100%;">';
    echo '<tr><td style="border:none; width:30%; vertical-align:top;" class="text-left">';
    echo '<h3 style="font-family: Arial, sans-serif;">' . s(get_string('resourcetotals', 'local_realtime_tracker')) . '</h3>';
    echo '<table>';
    foreach ($totallabels as $key => $label) {
        if ($key == 'shared') continue;
        echo '<tr><td class="text-left">' . s($label) . '</td><td>' . $totals[$key] . '</td></tr>';
    }
    echo '</table>';
    echo '</td>';
    echo '<td style="border:none; width:35%; vertical-align:top; text-align:center;">';
    echo '<img src="' . htmlspecialchars($chart_comp_url) . '" width="350" height="200" alt="Chart Comp">';
    echo '</td>';
    echo '<td style="border:none; width:35%; vertical-align:top; text-align:center;">';
    echo '<img src="' . htmlspecialchars($chart_schools_url) . '" width="500" height="250" alt="Chart Schools">';
    echo '</td></tr>';
    echo '<tr><td colspan="3" style="border:none; text-align:center;"><img src="' . htmlspecialchars($chart_ai_url) . '" width="500" height="250" alt="Chart AI"></td></tr>';
    echo '</table>';

    echo '<p style="font-family: Arial, sans-serif;"><i>' . s(get_string('pdfnote', 'local_realtime_tracker')) . '</i></p>';
    echo '<table>';
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
        echo '<td>' . (isset($record->ai_credits) ? $record->ai_credits : 0) . '</td>';
        echo '<td>' . $record->url_count . '</td>';
        echo '<td>' . $record->label_count . '</td>';
        echo '<td>' . $record->assign_count . '</td>';
        echo '<td>' . $record->forum_count . '</td>';
        echo '<td>' . $record->file_count . '</td>';
        echo '<td>' . $record->folder_count . '</td>';
        echo '<td>' . $record->shared_count . '</td>';
        echo '<td>' . $record->student_count . '</td>';
        echo '<td>' . s(\local_realtime_tracker\local\helper::format_duration((int)$record->total_connection)) . '</td>';
        echo '<td>' . s(\local_realtime_tracker\local\helper::format_duration((int)$record->total_creation)) . '</td>';
        echo '</tr>';
    }
    echo '</table></div></body></html>';
} else if ($format === 'xlsx') {
    $data = [];
    foreach ($records as $record) {
        $data[] = [
            $record->categoryname,
            $record->coursename,
            $record->teachername,
            isset($record->ai_credits) ? $record->ai_credits : 0,
            $record->url_count,
            $record->label_count,
            $record->assign_count,
            $record->forum_count,
            $record->file_count,
            $record->folder_count,
            $record->shared_count,
            $record->student_count,
            \local_realtime_tracker\local\helper::format_duration((int)$record->total_connection),
            \local_realtime_tracker\local\helper::format_duration((int)$record->total_creation)
        ];
    }
    
    // Use Moodle's core dataformat writer which is memory-efficient and guaranteed to work
    \core\dataformat::download_data(
        $filename,
        'excel',
        $headers,
        $data
    );
    die;
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
        $row->addChild('folder', $record->folder_count);
        $row->addChild('shared_resources', $record->shared_count);
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
