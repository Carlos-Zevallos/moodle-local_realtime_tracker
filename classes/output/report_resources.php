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

namespace local_realtime_tracker\output;

defined('MOODLE_INTERNAL') || die();

use local_realtime_tracker\local\helper;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Institutional resources report renderable.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_resources implements renderable, templatable {

    /** @var int Top-level category ID. */
    protected $categoryid;

    /** @var int Subcategory ID. */
    protected $subcategoryid;

    /** @var string|int Year filter. */
    protected $year;

    /** @var string Semester filter. */
    protected $semester;

    /**
     * Constructor.
     *
     * @param int $categoryid Top-level category ID.
     * @param int $subcategoryid Subcategory ID.
     * @param string|int $year Year filter.
     * @param string $semester Semester filter.
     */
    public function __construct(int $categoryid, int $subcategoryid, $year, string $semester) {
        $this->categoryid = $categoryid;
        $this->subcategoryid = $subcategoryid;
        $this->year = $year;
        $this->semester = $semester;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $OUTPUT, $PAGE;

        list($topcategories, $subcategories) = helper::get_category_options($this->categoryid, $this->subcategoryid);
        $rows = helper::get_resource_report_rows(
            $this->categoryid,
            $this->subcategoryid,
            $this->year,
            $this->semester
        );

        $totals = [
            'url' => 0,
            'label' => 0,
            'assign' => 0,
            'forum' => 0,
            'file' => 0,
            'folder' => 0,
            'shared' => 0,
        ];
        
        $unique_schools = [];
        $unique_courses = [];
        $unique_teachers = [];
        $total_ai_credits = 0;
        $school_stats = [];
        $teacher_stats = [];
        $zero_rec_teachers = [];
        
        $processed_teachers_ai = [];
        
        $results = [];
        foreach ($rows as $row) {
            $totals['url'] += $row->url_count;
            $totals['label'] += $row->label_count;
            $totals['assign'] += $row->assign_count;
            $totals['forum'] += $row->forum_count;
            $totals['file'] += $row->file_count;
            $totals['folder'] += $row->folder_count;
            $totals['shared'] += $row->shared_count;
            
            $unique_schools[$row->categoryname] = true;
            $unique_courses[$row->courseid] = true;
            $unique_teachers[$row->userid] = true;
            
            if (!isset($school_stats[$row->categoryname])) {
                $school_stats[$row->categoryname] = ['url' => 0, 'file' => 0, 'assign' => 0, 'forum' => 0, 'total' => 0, 'ai' => 0];
            }
            $school_stats[$row->categoryname]['url'] += $row->url_count;
            $school_stats[$row->categoryname]['file'] += $row->file_count;
            $school_stats[$row->categoryname]['assign'] += $row->assign_count;
            $school_stats[$row->categoryname]['forum'] += $row->forum_count;
            $school_stats[$row->categoryname]['total'] += $row->shared_count;
            
            $tkey = $row->userid . '-' . $row->teachername;
            if (!isset($teacher_stats[$tkey])) {
                $teacher_stats[$tkey] = ['name' => $row->teachername, 'school' => $row->categoryname, 'total' => 0, 'ai' => 0];
            }
            $teacher_stats[$tkey]['total'] += $row->shared_count;
            
            // Sum AI credits for the global counter only
            $total_ai_credits += $row->ai_credits;

            $has_base_reqs = ($row->assign_count >= 2 && $row->forum_count >= 2);
            $is_excellent = $has_base_reqs && ($row->shared_count >= 10);
            $is_congrats = $has_base_reqs && ($row->shared_count >= 6 && $row->shared_count < 10);

            $courseurl = new moodle_url('/course/view.php', ['id' => $row->courseid]);
            $results[] = [
                'courseid' => $row->courseid,
                'userid' => $row->userid,
                'categoryname' => $row->categoryname,
                'coursename' => $row->coursename,
                'shortname' => $row->shortname,
                'courseurl' => $courseurl->out(false),
                'teachername' => $row->teachername,
                'ai_credits' => $row->ai_credits,
                'url_count' => $row->url_count,
                'url_iszero' => ($row->url_count == 0),
                'url_has_data' => ($row->url_count > 0),
                'label_count' => $row->label_count,
                'label_iszero' => ($row->label_count == 0),
                'assign_count' => $row->assign_count,
                'assign_is_low' => ($row->assign_count <= 1),
                'forum_count' => $row->forum_count,
                'forum_is_low' => ($row->forum_count <= 1),
                'is_excellent' => $is_excellent,
                'is_congrats' => $is_congrats,
                'file_count' => $row->file_count,
                'file_iszero' => ($row->file_count + $row->folder_count == 0),
                'file_has_data' => ($row->file_count > 0),
                'teams_count' => $row->teams_count,
                'teams_iszero' => ($row->teams_count == 0),
                'folder_count' => $row->folder_count,
                'folder_iszero' => ($row->file_count + $row->folder_count == 0),
                'folder_has_data' => ($row->folder_count > 0),
                'shared_count' => $row->shared_count,
                'shared_iszero' => ($row->shared_count == 0),
                'shared_is_good' => ($row->shared_count >= 6),
                'student_count' => $row->student_count,
                'student_iszero' => ($row->student_count == 0),
                'connectiontime' => helper::format_duration((int)$row->total_connection),
                'creationtime' => helper::format_duration((int)$row->total_creation),
                'has_accessed' => $row->has_accessed,
                'detailurl' => (new moodle_url('/local/realtime_tracker/reports.php', [
                    'courseid' => $row->courseid,
                    'action' => 'teacher',
                    'userid' => $row->userid,
                ]))->out(false),
            ];
        }
        
        // Ordenar $results por: Escuela (ASC) > Condicion (Excelente=1, Congrats=2, Rest=3) > Docente (ASC)
        usort($results, function($a, $b) {
            $cmp_school = strcmp($a['categoryname'], $b['categoryname']);
            if ($cmp_school !== 0) {
                return $cmp_school;
            }
            
            $condA = $a['is_excellent'] ? 1 : ($a['is_congrats'] ? 2 : 3);
            $condB = $b['is_excellent'] ? 1 : ($b['is_congrats'] ? 2 : 3);
            if ($condA !== $condB) {
                return $condA <=> $condB;
            }
            
            return strcmp($a['teachername'], $b['teachername']);
        });
        
        foreach ($teacher_stats as $ts) {
            if ($ts['total'] == 0) {
                $zero_rec_teachers[] = $ts;
            }
        }
        
        // Fetch TRUE Absolute AI Totals for all teachers in this report to populate the Top 10 lists
        global $DB;
        $all_teacher_ids = array_keys($unique_teachers);
        if (!empty($all_teacher_ids)) {
            list($tsql, $tparams) = $DB->get_in_or_equal($all_teacher_ids);
            try {
                $true_ai_sql = "SELECT userid, SUM(credits) as totalai FROM {aiprovider_datacurso_consumption} WHERE userid $tsql GROUP BY userid";
                $true_ai_records = $DB->get_records_sql($true_ai_sql, $tparams);
                
                // Keep track of which teachers we've already added to a school's total to prevent double-counting 
                // if a teacher teaches multiple courses in the SAME school.
                $processed_teacher_schools = [];
                
                foreach ($teacher_stats as $tkey => &$ts) {
                    $tid = (int)explode('-', $tkey)[0];
                    if (isset($true_ai_records[$tid])) {
                        $ts['ai'] = (float)$true_ai_records[$tid]->totalai;
                        
                        // Add to school stats, but only once per teacher per school
                        $schoolkey = $tid . '-' . $ts['school'];
                        if (!isset($processed_teacher_schools[$schoolkey])) {
                            $school_stats[$ts['school']]['ai'] += $ts['ai'];
                            $processed_teacher_schools[$schoolkey] = true;
                        }
                    }
                }
                unset($ts); // break reference
            } catch (\Exception $e) {
                // Ignore if table doesn't exist
            }
        }
        
        // Sort Top 10 Schools
        $top_schools = [];
        foreach ($school_stats as $name => $stat) {
            $top_schools[] = ['name' => $name, 'total' => $stat['total']];
        }
        usort($top_schools, function($a, $b) { return $b['total'] <=> $a['total']; });
        $top_schools = array_slice($top_schools, 0, 10);
        foreach ($top_schools as $idx => &$ts) { $ts['rank'] = $idx + 1; }
        
        // Sort Top 10 Teachers
        $top_teachers = array_values($teacher_stats);
        usort($top_teachers, function($a, $b) { return $b['total'] <=> $a['total']; });
        $top_teachers = array_slice($top_teachers, 0, 10);
        foreach ($top_teachers as $idx => &$tt) { $tt['rank'] = $idx + 1; }
        
        // Sort Top 10 Teachers AI
        $top_teachers_ai = array_values($teacher_stats);
        usort($top_teachers_ai, function($a, $b) { return $b['ai'] <=> $a['ai']; });
        $top_teachers_ai = array_filter($top_teachers_ai, function($a) { return $a['ai'] > 0; });
        $top_teachers_ai = array_slice($top_teachers_ai, 0, 10);
        foreach ($top_teachers_ai as $idx => &$tt) { $tt['rank'] = $idx + 1; }
        
        // Sort Zero Rec Teachers
        usort($zero_rec_teachers, function($a, $b) { return strcmp($a['name'], $b['name']); });
        $zero_rec_teachers_count = count($zero_rec_teachers);
        $zero_rec_teachers = array_slice($zero_rec_teachers, 0, 10); // Show only top 10 in the list for UI

        // Chart Data: Recursos por Escuela (Bar)
        $chart_schools_labels = [];
        $chart_schools_url = [];
        $chart_schools_file = [];
        $chart_schools_assign = [];
        $chart_schools_forum = [];
        $chart_schools_ai = [];
        
        // Sort schools by total resources for chart
        $sorted_schools = $school_stats;
        uasort($sorted_schools, function($a, $b) { return $b['total'] <=> $a['total']; });
        $sorted_schools = array_slice($sorted_schools, 0, 15); // limit to 15 to keep chart clean
        
        foreach ($sorted_schools as $name => $stat) {
            // abbreviate long names for chart labels
            $shortname = mb_strlen($name) > 20 ? mb_substr($name, 0, 17) . '...' : $name;
            $chart_schools_labels[] = $shortname;
            $chart_schools_url[] = $stat['url'];
            $chart_schools_file[] = $stat['file'];
            $chart_schools_assign[] = $stat['assign'];
            $chart_schools_forum[] = $stat['forum'];
            $chart_schools_ai[] = $stat['ai'];
        }
        
        // Chart Data: Composición de Recursos (Pie/Donut)
        $chart_comp_labels = [];
        $chart_comp_data = [];
        $chartmap = [
            'url' => get_string('url', 'local_realtime_tracker'),
            'label' => get_string('label', 'local_realtime_tracker'),
            'assign' => get_string('assignment', 'local_realtime_tracker'),
            'forum' => get_string('forums', 'local_realtime_tracker'),
            'file' => get_string('files', 'local_realtime_tracker'),
            'folder' => get_string('folder', 'local_realtime_tracker'),
        ];
        foreach ($totals as $type => $count) {
            if ($type !== 'shared' && $count > 0) {
                $chart_comp_labels[] = $chartmap[$type];
                $chart_comp_data[] = $count;
            }
        }
        
        // Chart Data: Uso de IA por Escuela
        $sorted_ai_schools = $school_stats;
        uasort($sorted_ai_schools, function($a, $b) { return $b['ai'] <=> $a['ai']; });
        $sorted_ai_schools = array_slice($sorted_ai_schools, 0, 15);
        $chart_ai_labels = [];
        $chart_ai_data = [];
        foreach ($sorted_ai_schools as $name => $stat) {
            if ($stat['ai'] > 0) {
                $shortname = mb_strlen($name) > 20 ? mb_substr($name, 0, 17) . '...' : $name;
                $chart_ai_labels[] = $shortname;
                $chart_ai_data[] = $stat['ai'];
            }
        }

        return [
            'topcategories' => $topcategories,
            'subcategories' => $subcategories,
            'categoryid' => $this->categoryid,
            'subcategoryid' => $this->subcategoryid,
            'year' => $this->year,
            'semester' => $this->semester,
            'sem01' => ($this->semester === '01'),
            'sem02' => ($this->semester === '02'),
            
            // Dashboard Metrics
            'total_schools' => count($unique_schools),
            'total_courses' => count($unique_courses),
            'total_teachers' => count($unique_teachers),
            'total_shared' => $totals['shared'],
            'total_ai_credits' => $total_ai_credits,
            
            // Top 10 Lists
            'top_schools' => $top_schools,
            'top_teachers' => $top_teachers,
            'top_teachers_ai' => $top_teachers_ai,
            'zero_rec_teachers' => $zero_rec_teachers,
            'zero_rec_teachers_count' => $zero_rec_teachers_count,
            
            // Chart Data (JSON)
            'chart_schools_labels' => json_encode($chart_schools_labels),
            'chart_schools_url' => json_encode($chart_schools_url),
            'chart_schools_file' => json_encode($chart_schools_file),
            'chart_schools_assign' => json_encode($chart_schools_assign),
            'chart_schools_forum' => json_encode($chart_schools_forum),
            
            'chart_comp_labels' => json_encode($chart_comp_labels),
            'chart_comp_data' => json_encode($chart_comp_data),
            
            'chart_ai_labels' => json_encode($chart_ai_labels),
            'chart_ai_data' => json_encode($chart_ai_data),
            
            'results' => $results,
            'has_results' => count($results) > 0,
            'currenturl' => $PAGE->url->out(false),
            'sesskey' => sesskey(),
        ];
    }
}
