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
use renderable;
use renderer_base;
use templatable;

/**
 * Institutional teacher dashboard renderable.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_dashboard implements renderable, templatable {

    /** @var int Top-level category ID. */
    protected $categoryid;

    /** @var int Subcategory ID. */
    protected $subcategoryid;

    /**
     * Constructor.
     *
     * @param int $categoryid Top-level category ID.
     * @param int $subcategoryid Subcategory ID.
     */
    public function __construct(int $categoryid, int $subcategoryid) {
        $this->categoryid = $categoryid;
        $this->subcategoryid = $subcategoryid;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $OUTPUT;

        list($topcategories, $subcategories) = helper::get_category_options($this->categoryid, $this->subcategoryid);
        list($totalcreation, $totalai, $resourcecreation) = helper::get_institutional_metrics(
            $this->categoryid,
            $this->subcategoryid
        );

        $chart1 = new \core\chart_bar();
        $labels = [];
        $data = [];
        foreach ($resourcecreation as $type => $seconds) {
            $labels[] = ucfirst($type);
            $data[] = round($seconds / 60);
        }
        $series1 = new \core\chart_series(get_string('creationminutes', 'local_realtime_tracker'), $data);
        $chart1->add_series($series1);
        $chart1->set_labels($labels);
        $chart1->set_title(get_string('chartcreationbyresource', 'local_realtime_tracker'));

        $chart2 = new \core\chart_pie();
        $series2 = new \core\chart_series(get_string('timeminutes', 'local_realtime_tracker'), [
            round($totalcreation / 60),
            round($totalai / 60),
        ]);
        $chart2->add_series($series2);
        $chart2->set_labels([
            get_string('manualcreation', 'local_realtime_tracker'),
            get_string('aiusage', 'local_realtime_tracker'),
        ]);
        $chart2->set_title(get_string('chartmanualvsai', 'local_realtime_tracker'));

        return [
            'topcategories' => $topcategories,
            'subcategories' => $subcategories,
            'subcategoryid' => $this->subcategoryid,
            'chart1' => $OUTPUT->render($chart1),
            'chart2' => $OUTPUT->render($chart2),
            'total_creation_hours' => round($totalcreation / 3600, 1),
            'total_ai_hours' => round($totalai / 3600, 1),
        ];
    }
}
