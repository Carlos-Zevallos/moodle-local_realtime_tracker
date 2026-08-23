<?php
/**
 * Institutional Resources Report - Export
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/pdflib.php');
require_once(__DIR__ . '/lib.php');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$subcategoryid = optional_param('subcategoryid', 0, PARAM_INT);
$year = optional_param('year', '', PARAM_INT);
$semester = optional_param('semester', '', PARAM_ALPHANUM);
$format = optional_param('format', 'pdf', PARAM_ALPHA);
$chart_image = optional_param('chart_image', '', PARAM_RAW);

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context); // Assuming admin access is needed, or adjust to local capability

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
          ORDER BY c.fullname, u.lastname, u.firstname";

$records = $DB->get_records_sql($sql, $params);
if (!$records) {
    $records = [];
}

$filename_base = 'Reporte_Recursos_' . date('Ymd');

$totals = [
    'url' => 0, 'etiqueta' => 0, 'tarea' => 0,
    'foros' => 0, 'archivos' => 0, 'teams' => 0, 'folder' => 0
];
foreach ($records as $r) {
    $totals['url'] += $r->url_count;
    $totals['etiqueta'] += $r->etiqueta_count;
    $totals['tarea'] += $r->tarea_count;
    $totals['foros'] += $r->foros_count;
    $totals['archivos'] += $r->archivos_count;
    $totals['teams'] += $r->teams_count;
    $totals['folder'] += $r->folder_count;
}

if ($format === 'pdf') {
    $pdf = new \pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    // Use landscape for wide table
    $pdf->AddPage('L');
    
    $pdf->SetFillColor(0, 80, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ' Reporte de Recursos Mauri ', 0, 1, 'L', true);
    $pdf->Ln(5);

    if (!empty($chart_image)) {
        // Remove "data:image/png;base64," prefix
        $base64data = preg_replace('#^data:image/[^;]+;base64,#', '', $chart_image);
        $imgdata = base64_decode($base64data);
        if ($imgdata !== false) {
            $pdf->Image('@' . $imgdata, 20, $pdf->GetY(), 150, 0, 'PNG');
            // Move cursor down past the image
            $pdf->Ln(85);
        }
    }
    
    // Add Totals Table
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 10, 'Totales de Recursos:', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(40, 6, 'URL', 1, 0, 'L'); $pdf->Cell(20, 6, $totals['url'], 1, 1, 'C');
    $pdf->Cell(40, 6, 'Etiqueta', 1, 0, 'L'); $pdf->Cell(20, 6, $totals['etiqueta'], 1, 1, 'C');
    $pdf->Cell(40, 6, 'Tarea', 1, 0, 'L'); $pdf->Cell(20, 6, $totals['tarea'], 1, 1, 'C');
    $pdf->Cell(40, 6, 'Foros', 1, 0, 'L'); $pdf->Cell(20, 6, $totals['foros'], 1, 1, 'C');
    $pdf->Cell(40, 6, 'Archivos', 1, 0, 'L'); $pdf->Cell(20, 6, $totals['archivos'], 1, 1, 'C');
    $pdf->Cell(40, 6, 'Teams', 1, 0, 'L'); $pdf->Cell(20, 6, $totals['teams'], 1, 1, 'C');
    $pdf->Cell(40, 6, 'Folder', 1, 0, 'L'); $pdf->Cell(20, 6, $totals['folder'], 1, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 8);
    
    // Header
    $pdf->Cell(46, 6, 'Escuela', 1, 0, 'C', true);
    $pdf->Cell(61, 6, 'Curso', 1, 0, 'C', true);
    $pdf->Cell(35, 6, 'Docente', 1, 0, 'C', true);
    $pdf->Cell(12, 6, 'URL', 1, 0, 'C', true);
    $pdf->Cell(12, 6, 'Etiq.', 1, 0, 'C', true);
    $pdf->Cell(12, 6, 'Tarea', 1, 0, 'C', true);
    $pdf->Cell(12, 6, 'Foros', 1, 0, 'C', true);
    $pdf->Cell(12, 6, 'Arch.', 1, 0, 'C', true);
    $pdf->Cell(12, 6, 'Teams', 1, 0, 'C', true);
    $pdf->Cell(12, 6, 'Fold.', 1, 0, 'C', true);
    $pdf->Cell(12, 6, 'Alum.', 1, 0, 'C', true);
    $pdf->Cell(17, 6, 'T. Conx', 1, 0, 'C', true);
    $pdf->Cell(17, 6, 'T. Cre.', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 7);
    foreach ($records as $r) {
        list($tracker_conn, $tracker_creat) = local_realtime_tracker_get_fallback_times($r->courseid, $r->userid, $r->total_connection, $r->total_creation);
        
        $c_hrs = floor($tracker_creat / 3600);
        $c_mins = floor(($tracker_creat % 3600) / 60);
        $a_hrs = floor($tracker_conn / 3600);
        $a_mins = floor(($tracker_conn % 3600) / 60);

        // Using $stretch = 1 (11th parameter) to auto-shrink font size to fit width
        $pdf->Cell(46, 6, $r->escuela, 1, 0, 'C', false, '', 1);
        $pdf->Cell(61, 6, $r->cursos, 1, 0, 'C', false, '', 1);
        $pdf->Cell(35, 6, $r->docente, 1, 0, 'C', false, '', 1);
        
        $print_cell = function($val, $w) use ($pdf) {
            if ($val == 0) {
                $pdf->SetTextColor(255, 0, 0);
            }
            $pdf->Cell($w, 6, $val, 1, 0, 'C');
            if ($val == 0) {
                $pdf->SetTextColor(0, 0, 0);
            }
        };

        $print_cell($r->url_count, 12);
        $print_cell($r->etiqueta_count, 12);
        $print_cell($r->tarea_count, 12);
        $print_cell($r->foros_count, 12);
        $print_cell($r->archivos_count, 12);
        $print_cell($r->teams_count, 12);
        $print_cell($r->folder_count, 12);
        $print_cell($r->alumnos_count, 12);
        $pdf->Cell(17, 6, sprintf('%dh %dm', $a_hrs, $a_mins), 1, 0, 'C');
        $pdf->Cell(17, 6, sprintf('%dh %dm', $c_hrs, $c_mins), 1, 1, 'C');
    }
    
    $pdf->Output($filename_base . '.pdf', 'D');

} elseif ($format === 'doc') {
    header("Content-Type: application/vnd.ms-word; charset=UTF-8");
    header("Content-Disposition: attachment;Filename=" . $filename_base . ".doc");
    header("Pragma: no-cache");
    header("Expires: 0");

    $html = "<html><head><meta charset='UTF-8'></head><body>";
    $html .= "<h1>Reporte de Recursos Mauri</h1>";
    
    if (!empty($chart_image)) {
        $html .= "<div style='margin-bottom: 20px;'><img src='cid:chart_image' style='max-width: 600px;'/></div>";
    }

    $html .= "<h3>Totales de Recursos</h3>";
    $html .= "<table border='1' cellpadding='5' cellspacing='0' style='margin-bottom: 20px; width: 300px;'>";
    $html .= "<tr style='background-color:#ccc;'><th>Recurso</th><th>Total</th></tr>";
    $html .= "<tr><td>URL</td><td align='center'>" . $totals['url'] . "</td></tr>";
    $html .= "<tr><td>Etiqueta</td><td align='center'>" . $totals['etiqueta'] . "</td></tr>";
    $html .= "<tr><td>Tarea</td><td align='center'>" . $totals['tarea'] . "</td></tr>";
    $html .= "<tr><td>Foros</td><td align='center'>" . $totals['foros'] . "</td></tr>";
    $html .= "<tr><td>Archivos</td><td align='center'>" . $totals['archivos'] . "</td></tr>";
    $html .= "<tr><td>Teams</td><td align='center'>" . $totals['teams'] . "</td></tr>";
    $html .= "<tr><td>Folder</td><td align='center'>" . $totals['folder'] . "</td></tr>";
    $html .= "</table>";

    $html .= "<table border='1' cellpadding='5' cellspacing='0'>";
    $html .= "<tr style='background-color:#ccc;'>
            <th>Escuela</th><th>Curso</th><th>Docente</th>
            <th>URL</th><th>Etiqueta</th>
            <th>Tarea</th><th>Foros</th><th>Archivos</th>
            <th>Teams</th><th>Folder</th><th>Alumnos</th>
            <th>T. Conexión</th><th>T. Creación</th>
          </tr>";
    
    foreach ($records as $r) {
        list($tracker_conn, $tracker_creat) = local_realtime_tracker_get_fallback_times($r->courseid, $r->userid, $r->total_connection, $r->total_creation);
        
        $c_hrs = floor($tracker_creat / 3600);
        $c_mins = floor(($tracker_creat % 3600) / 60);
        $a_hrs = floor($tracker_conn / 3600);
        $a_mins = floor(($tracker_conn % 3600) / 60);
        
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($r->escuela) . "</td>";
        $html .= "<td>" . htmlspecialchars($r->cursos) . "</td>";
        $html .= "<td>" . htmlspecialchars($r->docente) . "</td>";
        
        $format_td = function($val) {
            return $val == 0 ? "<td align='center' style='color:red;'>" . $val . "</td>" : "<td align='center'>" . $val . "</td>";
        };

        $html .= $format_td($r->url_count);
        $html .= $format_td($r->etiqueta_count);
        $html .= $format_td($r->tarea_count);
        $html .= $format_td($r->foros_count);
        $html .= $format_td($r->archivos_count);
        $html .= $format_td($r->teams_count);
        $html .= $format_td($r->folder_count);
        $html .= $format_td($r->alumnos_count);
        
        $html .= "<td align='center'>" . sprintf('%dh %dm', $a_hrs, $a_mins) . "</td>";
        $html .= "<td align='center'>" . sprintf('%dh %dm', $c_hrs, $c_mins) . "</td>";
        $html .= "</tr>";
    }
    $html .= "</table>";
    $html .= "</body></html>";

    if (!empty($chart_image)) {
        $boundary = "----=_NextPart_" . md5(uniqid());
        echo "MIME-Version: 1.0\r\n";
        echo "Content-Type: multipart/related; boundary=\"$boundary\"\r\n\r\n";
        
        echo "--$boundary\r\n";
        echo "Content-Type: text/html; charset=UTF-8\r\n";
        echo "Content-Transfer-Encoding: 8bit\r\n\r\n";
        
        echo $html . "\r\n\r\n";
        
        echo "--$boundary\r\n";
        echo "Content-Type: image/png\r\n";
        echo "Content-Transfer-Encoding: base64\r\n";
        echo "Content-ID: <chart_image>\r\n\r\n";
        
        $base64data = preg_replace('#^data:image/[^;]+;base64,#', '', $chart_image);
        echo chunk_split($base64data) . "\r\n";
        
        echo "--$boundary--\r\n";
    } else {
        echo $html;
    }

} elseif ($format === 'xlsx') {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte');

    $dataStartRow = 18;
    $headers = ['Escuela', 'Curso', 'Docente', 'URL', 'Etiqueta', 'Tarea', 'Foros', 'Archivos', 'Teams', 'Folder', 'Recursos compartidos', 'Alumnos', 'T. Conexión', 'T. Creación'];
    $sheet->fromArray($headers, null, 'A' . $dataStartRow);
    
    $sheet->getStyle('A'.$dataStartRow.':N'.$dataStartRow)->getFont()->setBold(true);
    $sheet->getStyle('A'.$dataStartRow.':N'.$dataStartRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
    
    $row = $dataStartRow + 1;
    foreach ($records as $r) {
        list($tracker_conn, $tracker_creat) = local_realtime_tracker_get_fallback_times($r->courseid, $r->userid, $r->total_connection, $r->total_creation);
        
        $c_hrs = floor($tracker_creat / 3600);
        $c_mins = floor(($tracker_creat % 3600) / 60);
        $a_hrs = floor($tracker_conn / 3600);
        $a_mins = floor(($tracker_conn % 3600) / 60);
        
        $sheet->setCellValue('A'.$row, $r->escuela);
        $sheet->setCellValue('B'.$row, $r->cursos);
        $sheet->setCellValue('C'.$row, $r->docente);
        $sheet->setCellValue('D'.$row, $r->url_count);
        $sheet->setCellValue('E'.$row, $r->etiqueta_count);
        $sheet->setCellValue('F'.$row, $r->tarea_count);
        $sheet->setCellValue('G'.$row, $r->foros_count);
        $sheet->setCellValue('H'.$row, $r->archivos_count);
        $sheet->setCellValue('I'.$row, $r->teams_count);
        $recursos_compartidos = $r->url_count + $r->archivos_count + $r->folder_count;
        $sheet->setCellValue('J'.$row, $r->folder_count);
        $sheet->setCellValue('K'.$row, $recursos_compartidos);
        $sheet->setCellValue('L'.$row, $r->alumnos_count);
        $sheet->setCellValue('M'.$row, sprintf('%dh %dm', $a_hrs, $a_mins));
        $sheet->setCellValue('N'.$row, sprintf('%dh %dm', $c_hrs, $c_mins));
        
        $cols = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
        foreach ($cols as $col) {
            if ($sheet->getCell($col.$row)->getValue() == 0) {
                $sheet->getStyle($col.$row)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
            }
        }
        $row++;
    }
    
    $lastDataRow = $row - 1;
    $sheet->setAutoFilter('A'.$dataStartRow.':N' . $lastDataRow);
    
    foreach(range('A','N') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $subtotalRow = 16;
    $sheet->setCellValue('C'.$subtotalRow, 'Totales (Visibles):');
    $cols = ['D', 'E', 'F', 'G', 'H', 'I', 'J'];
    foreach ($cols as $col) {
        $sheet->setCellValue($col.$subtotalRow, '=SUBTOTAL(109,'.$col.($dataStartRow + 1).':'.$col.$lastDataRow.')');
    }
    $sheet->getStyle('C'.$subtotalRow.':J'.$subtotalRow)->getFont()->setBold(true);
    
    try {
        $dataSeriesLabels = [
            new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING, 'Reporte!$C$' . $subtotalRow, null, 1),
        ];
        $xAxisTickValues = [
            new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING, 'Reporte!$D$'.$dataStartRow.':$J$'.$dataStartRow, null, 7),
        ];
        $dataSeriesValues = [
            new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Reporte!$D$' . $subtotalRow . ':$J$' . $subtotalRow, null, 7),
        ];

        $series = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
            \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_PIECHART,
            null,
            range(0, count($dataSeriesValues)-1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        
        $layout = new \PhpOffice\PhpSpreadsheet\Chart\Layout();
        $layout->setShowVal(true);
        $layout->setShowPercent(true);
        
        $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea($layout, [$series]);
        $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_RIGHT, null, false);
        $title = new \PhpOffice\PhpSpreadsheet\Chart\Title('Distribución de Recursos');
        
        $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
            'chart1',
            $title,
            $legend,
            $plotArea,
            true,
            \PhpOffice\PhpSpreadsheet\Chart\DataSeries::EMPTY_AS_GAP,
            null,
            null
        );
        
        $chart->setTopLeftPosition('A1');
        $chart->setBottomRightPosition('H14');
        $sheet->addChart($chart);
    } catch (\Exception $e) {
        // Fallback if charts fail
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename_base . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->setIncludeCharts(true);
    $writer->save('php://output');

} elseif ($format === 'xml') {
    header('Content-Type: text/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename_base . '.xml"');
    
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><reporte/>');
    
    foreach ($records as $r) {
        $c_hrs = floor($r->total_creation / 3600);
        $c_mins = floor(($r->total_creation % 3600) / 60);
        $a_hrs = floor($r->total_connection / 3600);
        $a_mins = floor(($r->total_connection % 3600) / 60);

        $row = $xml->addChild('registro');
        $row->addChild('escuela', htmlspecialchars($r->escuela));
        $row->addChild('curso', htmlspecialchars($r->cursos));
        $row->addChild('docente', htmlspecialchars($r->docente));
        $row->addChild('url', $r->url_count);
        $row->addChild('etiqueta', $r->etiqueta_count);
        $row->addChild('tarea', $r->tarea_count);
        $row->addChild('foros', $r->foros_count);
        $row->addChild('archivos', $r->archivos_count);
        $row->addChild('teams', $r->teams_count);
        $row->addChild('folder', $r->folder_count);
        $row->addChild('alumnos', $r->alumnos_count);
        $row->addChild('tiempo_conexion', sprintf('%dh %dm', $a_hrs, $a_mins));
        $row->addChild('tiempo_creacion', sprintf('%dh %dm', $c_hrs, $c_mins));
    }
    
    echo $xml->asXML();
}
die;
