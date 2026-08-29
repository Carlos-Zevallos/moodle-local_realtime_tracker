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

use plugin_renderer_base;

/**
 * Renderer for local_realtime_tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the course dashboard.
     *
     * @param dashboard $page Dashboard renderable.
     * @return string
     */
    protected function render_dashboard(dashboard $page): string {
        return $this->render_from_template('local_realtime_tracker/dashboard', $page->export_for_template($this));
    }

    /**
     * Render a student detail report.
     *
     * @param student_detail $page Student detail renderable.
     * @return string
     */
    protected function render_student_detail(student_detail $page): string {
        return $this->render_from_template('local_realtime_tracker/student_detail', $page->export_for_template($this));
    }

    /**
     * Render a teacher detail report.
     *
     * @param teacher_detail $page Teacher detail renderable.
     * @return string
     */
    protected function render_teacher_detail(teacher_detail $page): string {
        return $this->render_from_template('local_realtime_tracker/teacher_detail', $page->export_for_template($this));
    }

    /**
     * Render the institutional teacher dashboard.
     *
     * @param teacher_dashboard $page Teacher dashboard renderable.
     * @return string
     */
    protected function render_teacher_dashboard(teacher_dashboard $page): string {
        return $this->render_from_template('local_realtime_tracker/teacher_dashboard', $page->export_for_template($this));
    }

    /**
     * Render the institutional resources report.
     *
     * @param report_resources $page Resources report renderable.
     * @return string
     */
    protected function render_report_resources(report_resources $page): string {
        return $this->render_from_template('local_realtime_tracker/report_resources', $page->export_for_template($this));
    }
}
