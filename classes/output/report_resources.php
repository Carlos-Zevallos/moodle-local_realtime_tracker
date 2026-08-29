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
            'teams' => 0,
            'folder' => 0,
        ];
        $results = [];
        foreach ($rows as $row) {
            $totals['url'] += $row->url_count;
            $totals['label'] += $row->label_count;
            $totals['assign'] += $row->assign_count;
            $totals['forum'] += $row->forum_count;
            $totals['file'] += $row->file_count;
            $totals['teams'] += $row->teams_count;
            $totals['folder'] += $row->folder_count;

            $courseurl = new moodle_url('/course/view.php', ['id' => $row->courseid]);
            $results[] = [
                'courseid' => $row->courseid,
                'userid' => $row->userid,
                'categoryname' => $row->categoryname,
                'coursename' => $row->coursename,
                'shortname' => $row->shortname,
                'courseurl' => $courseurl->out(false),
                'teachername' => $row->teachername,
                'url_count' => $row->url_count,
                'url_iszero' => ($row->url_count == 0),
                'label_count' => $row->label_count,
                'label_iszero' => ($row->label_count == 0),
                'assign_count' => $row->assign_count,
                'assign_iszero' => ($row->assign_count == 0),
                'forum_count' => $row->forum_count,
                'forum_iszero' => ($row->forum_count == 0),
                'file_count' => $row->file_count,
                'file_iszero' => ($row->file_count == 0),
                'teams_count' => $row->teams_count,
                'teams_iszero' => ($row->teams_count == 0),
                'folder_count' => $row->folder_count,
                'folder_iszero' => ($row->folder_count == 0),
                'shared_count' => $row->shared_count,
                'shared_iszero' => ($row->shared_count == 0),
                'student_count' => $row->student_count,
                'student_iszero' => ($row->student_count == 0),
                'connectiontime' => helper::format_duration((int)$row->total_connection),
                'creationtime' => helper::format_duration((int)$row->total_creation),
                'detailurl' => (new moodle_url('/local/realtime_tracker/reports.php', [
                    'courseid' => $row->courseid,
                    'action' => 'teacher',
                    'userid' => $row->userid,
                ]))->out(false),
            ];
        }

        $chart1 = new \core\chart_pie();
        $seriesdata = [];
        $labels = [];
        $chartmap = [
            'url' => get_string('url', 'local_realtime_tracker'),
            'label' => get_string('label', 'local_realtime_tracker'),
            'assign' => get_string('assignment', 'local_realtime_tracker'),
            'forum' => get_string('forums', 'local_realtime_tracker'),
            'file' => get_string('files', 'local_realtime_tracker'),
            'teams' => get_string('teams', 'local_realtime_tracker'),
            'folder' => get_string('folder', 'local_realtime_tracker'),
        ];
        foreach ($totals as $type => $count) {
            if ($count > 0) {
                $labels[] = $chartmap[$type];
                $seriesdata[] = $count;
            }
        }
        if (empty($seriesdata)) {
            $seriesdata = [1];
            $labels = [get_string('nodata', 'local_realtime_tracker')];
        }
        $series1 = new \core\chart_series(get_string('resources', 'local_realtime_tracker'), $seriesdata);
        $chart1->add_series($series1);
        $chart1->set_labels($labels);

        return [
            'topcategories' => $topcategories,
            'subcategories' => $subcategories,
            'categoryid' => $this->categoryid,
            'subcategoryid' => $this->subcategoryid,
            'year' => $this->year,
            'semester' => $this->semester,
            'sem01' => ($this->semester === '01'),
            'sem02' => ($this->semester === '02'),
            'chart1' => $OUTPUT->render($chart1),
            'chart_data' => [
                ['label' => get_string('url', 'local_realtime_tracker'), 'value' => $totals['url']],
                ['label' => get_string('label', 'local_realtime_tracker'), 'value' => $totals['label']],
                ['label' => get_string('assignment', 'local_realtime_tracker'), 'value' => $totals['assign']],
                ['label' => get_string('forums', 'local_realtime_tracker'), 'value' => $totals['forum']],
                ['label' => get_string('files', 'local_realtime_tracker'), 'value' => $totals['file']],
                ['label' => get_string('teams', 'local_realtime_tracker'), 'value' => $totals['teams']],
                ['label' => get_string('folder', 'local_realtime_tracker'), 'value' => $totals['folder']],
            ],
            'currenturl' => $PAGE->url->out(false),
            'results' => $results,
            'sesskey' => sesskey(),
        ];
    }
}
