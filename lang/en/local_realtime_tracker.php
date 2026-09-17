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
 * English language strings for local_realtime_tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Mauri Real-Time Tracker';
$string['privacy:metadata'] = 'The Mauri Real-Time Tracker plugin stores the time users spend in courses and activities.';

// Settings.
$string['settings'] = 'Mauri Tracker Settings';
$string['cif'] = 'Centre CIF';
$string['cif_desc'] = 'Default CIF for the training centre to appear in PDF reports.';
$string['centername'] = 'Centre name';
$string['centername_desc'] = 'Name of the training centre.';
$string['modalidad'] = 'Default modality';
$string['modalidad_desc'] = 'Delivery mode shown on reports, for example Distance learning.';
$string['aikeywords'] = 'AI plugin keywords';
$string['aikeywords_desc'] = 'Comma-separated URL fragments used to detect AI usage (for example openai, chatgpt).';
$string['institutionalreports'] = 'Institutional teacher reports';
$string['resourcesreport'] = 'Institutional resources report';

// Capabilities.
$string['realtime_tracker:viewreports'] = 'View Mauri tracking reports';

// Navigation.
$string['maurireports'] = 'Mauri reports';

// Reports UI.
$string['generalreport'] = 'Mauri general report';
$string['studentreport'] = 'Individual communication record';
$string['student'] = 'Student';
$string['students'] = 'Estudiantes';
$string['teacher'] = 'Docente';
$string['teachers'] = 'Teachers';
$string['allusers'] = 'All';
$string['activetime'] = 'Active time';
$string['completion'] = 'Completed activities';
$string['firstaccess'] = 'First course access';
$string['lastaccess'] = 'Last course access';
$string['totalaccesses'] = 'Course accesses';
$string['exportpdf'] = 'PDF record';
$string['action'] = 'Action';
$string['viewdetail'] = 'View';
$string['never'] = 'Never';
$string['completed'] = 'Completed';
$string['unknown'] = 'Unknown';
$string['nodata'] = 'No data';
$string['norecords'] = 'No records found.';
$string['noactivityrecords'] = 'No activity records.';
$string['noactivityrecords_teacher'] = 'No activity records for this teacher.';
$string['trainingcentre'] = 'Training centre';
$string['filenumber'] = 'File number';
$string['actionname'] = 'Action name';
$string['accesslabel'] = 'Access';
$string['companygroup'] = 'Company group';
$string['modality'] = 'Modality';
$string['footercredit'] = 'Developed by Ing. Carlos Zevallos | MAURI PLUGIN';

// Activity details.
$string['activity'] = 'Activity';
$string['type'] = 'Type';
$string['entries'] = 'Entries';
$string['time'] = 'Time';
$string['status'] = 'Status';
$string['accesses'] = 'Accesses';
$string['timebrowsing'] = 'Browsing time';
$string['timecreating'] = 'Creating/editing time';
$string['timeai'] = 'Time using AI';
$string['activitytracking'] = 'Total tracking by activity';
$string['teacheranalysis'] = 'Teacher time analysis';
$string['connectionandcreation'] = 'Connection and creation time by resource';
$string['totallmstime'] = 'Total time in LMS';
$string['timeformat'] = '{$a->hours}h {$a->mins}m';
$string['hoursvalue'] = '{$a} h';
$string['unknownactivity'] = 'Unknown';

// Institutional dashboard.
$string['institutionaldashboard'] = 'Institutional report: Teacher performance';
$string['downloadpdf'] = 'Download PDF';
$string['faculty'] = 'Faculty / School';
$string['allfaculties'] = 'All faculties';
$string['subcategory'] = 'Subcategory';
$string['all'] = 'All';
$string['filter'] = 'Filter';
$string['totalcreationtime'] = 'Total time creating courses';
$string['totalcreationtime_desc'] = 'Configuring forums, assignments, files and URLs';
$string['totalaitime'] = 'Total time using AI';
$string['totalaitime_desc'] = 'Artificial intelligence assistance';
$string['chartcreationbyresource'] = 'Time spent creating by resource';
$string['chartmanualvsai'] = 'Manual creation vs AI';
$string['creationminutes'] = 'Creation minutes';
$string['timeminutes'] = 'Time (minutes)';
$string['manualcreation'] = 'Manual creation';
$string['aiusage'] = 'AI usage';

// Resources report.
$string['category'] = 'Category';
$string['allcategories'] = 'All categories';
$string['resources'] = 'Resources';
$string['school'] = 'Escuela';
$string['courses'] = 'Cursos';
$string['year'] = 'Year';
$string['semester'] = 'Semester';
$string['allsemesters'] = 'All';
$string['firstsemester'] = '01 (First)';
$string['secondsemester'] = '02 (Second)';
$string['yearplaceholder'] = 'e.g. 2026';
$string['resourcedistribution'] = 'Total resource distribution';
$string['resource'] = 'Resource';
$string['total'] = 'Total';
$string['results'] = 'Results';
$string['sendtoteachers'] = 'Email teachers';
$string['emailconfirm'] = 'Are you sure you want to send personalised reports to the filtered teachers? This may take a few moments.';
$string['url'] = 'URL';
$string['label'] = 'Etiqueta';
$string['assignment'] = 'Tarea';
$string['forums'] = 'Foros';
$string['files'] = 'Archivos';
$string['folder'] = 'Carpeta';
$string['sharedresources'] = 'Recursos compartidos';
$string['connectiontime'] = 'Tiempo de conexión';
$string['creationtime'] = 'Tiempo de creación';
$string['pdfnote'] = 'Nota: Se está considerando a los docentes que usan CARPETA en consideración de ARCHIVOS, en este sentido solo es cumplir uno de los dos (2) recursos.';
$string['resourcetotals'] = 'Resource totals:';

// Export.
$string['export'] = 'Export';
$string['exportdoc'] = 'Export DOC';
$string['exportxml'] = 'Export XML';
$string['exportexcel'] = 'Export Excel';
$string['pdfgeneralreport'] = 'Mauri general report: {$a}';
$string['pdfresourcesreport'] = 'Informe de uso de Recursos en Moodle';
$string['pdfteacherresources'] = 'Teacher resources report';
$string['exportfilename'] = 'Record_{$a->username}_{$a->date}';
$string['resourcesfilename'] = 'Resources_report_{$a}';
$string['cifnumber'] = 'CIF number';

// Email.
$string['emailsubject'] = 'Informe de uso del aula virtual - {$a}';
$string['emailbody'] = 'Adjunto encontrará su informe de uso del aula virtual.<br><br>Los indicadores en rojo señalan un uso por debajo del estándar institucional para los recursos correspondientes. Para la evaluación del criterio de archivos, la inclusión de carpetas es contabilizada como un equivalente válido.<br><br>Saludos cordiales,<br><br><img src="{$a->firmaurl}" alt="Firma" style="max-width: 400px;"><br><br><div style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #4caf50; font-style: italic; margin-top: 15px;">No imprima este correo a menos que sea necesario. Ayudemos a proteger el medio ambiente ...</div>';
$string['emailsent'] = '{$a->sent} of {$a->total} correos enviados exitosamente.';
$string['emailnone'] = 'No se enviaron correos. Por favor revise su configuración SMTP de Moodle.';
$string['emailempty'] = 'No hay datos para enviar.';
$string['emailattachment'] = 'Informe_de_Uso_del_Aula_Virtual_{$a}.pdf';

// Privacy metadata.
$string['privacy:metadata:local_realtime_tracker_logs'] = 'Heartbeat logs of active time per user session, course and activity.';
$string['privacy:metadata:local_realtime_tracker_logs:userid'] = 'The ID of the user being tracked.';
$string['privacy:metadata:local_realtime_tracker_logs:courseid'] = 'The course where the time was recorded.';
$string['privacy:metadata:local_realtime_tracker_logs:cmid'] = 'The course module, or 0 for the course page.';
$string['privacy:metadata:local_realtime_tracker_logs:time_spent'] = 'Seconds of active time in this heartbeat.';
$string['privacy:metadata:local_realtime_tracker_logs:action_type'] = 'The type of action: view, create or ai.';
$string['privacy:metadata:local_realtime_tracker_logs:last_ping'] = 'The timestamp of the heartbeat.';
$string['privacy:metadata:local_realtime_tracker_logs:session_id'] = 'The tracking session identifier.';
$string['privacy:metadata:local_realtime_tracker_summ'] = 'Course-level totals of active time and access counts.';
$string['privacy:metadata:local_realtime_tracker_summ:userid'] = 'The ID of the user being summarised.';
$string['privacy:metadata:local_realtime_tracker_summ:courseid'] = 'The course the summary belongs to.';
$string['privacy:metadata:local_realtime_tracker_summ:total_active_seconds'] = 'Total active seconds in the course.';
$string['privacy:metadata:local_realtime_tracker_summ:access_count'] = 'Number of detected access sessions.';
$string['privacy:metadata:local_realtime_tracker_summ:first_access'] = 'Timestamp of the first recorded access.';
$string['privacy:metadata:local_realtime_tracker_summ:last_access'] = 'Timestamp of the last recorded access.';
$string['privacy:metadata:local_realtime_tracker_acts'] = 'Activity-level totals of browsing, creation and AI time.';
$string['privacy:metadata:local_realtime_tracker_acts:userid'] = 'The ID of the user being summarised.';
$string['privacy:metadata:local_realtime_tracker_acts:courseid'] = 'The course the activity belongs to.';
$string['privacy:metadata:local_realtime_tracker_acts:cmid'] = 'The course module being summarised.';
$string['privacy:metadata:local_realtime_tracker_acts:active_seconds'] = 'Seconds spent viewing the activity.';
$string['privacy:metadata:local_realtime_tracker_acts:create_seconds'] = 'Seconds spent creating or editing.';
$string['privacy:metadata:local_realtime_tracker_acts:ai_seconds'] = 'Seconds spent using AI tools.';
$string['privacy:metadata:local_realtime_tracker_acts:entries'] = 'Number of recorded entries into the activity.';
