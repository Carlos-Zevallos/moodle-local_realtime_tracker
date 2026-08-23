<?php
/**
 * Renderer for local realtime tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_realtime_tracker\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

class renderer extends plugin_renderer_base {

    public function render_dashboard($data) {
        return $this->render_from_template('local_realtime_tracker/dashboard', $data);
    }

    public function render_student_detail($data) {
        return $this->render_from_template('local_realtime_tracker/student_detail', $data);
    }

    public function render_teacher_detail($data) {
        return $this->render_from_template('local_realtime_tracker/teacher_detail', $data);
    }

    public function render_report_resources($data) {
        return $this->render_from_template('local_realtime_tracker/report_resources', $data);
    }
}
