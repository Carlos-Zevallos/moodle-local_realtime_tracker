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
use stdClass;
use templatable;

/**
 * Course dashboard renderable.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {

    /** @var stdClass Course record. */
    protected $course;

    /** @var string Role filter. */
    protected $rolefilter;

    /**
     * Constructor.
     *
     * @param stdClass $course Course record.
     * @param string $rolefilter Role filter.
     */
    public function __construct(stdClass $course, string $rolefilter) {
        $this->course = $course;
        $this->rolefilter = $rolefilter;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $users = helper::get_course_dashboard_users($this->course->id, $this->rolefilter);
        foreach ($users as $index => $user) {
            $users[$index]['detailurl'] = (new \moodle_url('/local/realtime_tracker/reports.php', [
                'courseid' => $this->course->id,
                'action' => $user['usertype'],
                'userid' => $user['userid'],
            ]))->out(false);
        }

        return [
            'courseid' => $this->course->id,
            'coursename' => format_string($this->course->fullname),
            'cif' => get_config('local_realtime_tracker', 'cif'),
            'centername' => get_config('local_realtime_tracker', 'centername'),
            'modalidad' => get_config('local_realtime_tracker', 'modalidad'),
            'isstudent' => ($this->rolefilter === 'student'),
            'isteacher' => ($this->rolefilter === 'teacher'),
            'isall' => ($this->rolefilter === 'all'),
            'studenturl' => (new \moodle_url('/local/realtime_tracker/reports.php', [
                'courseid' => $this->course->id,
                'action' => 'general',
                'role' => 'student',
            ]))->out(false),
            'teacherurl' => (new \moodle_url('/local/realtime_tracker/reports.php', [
                'courseid' => $this->course->id,
                'action' => 'general',
                'role' => 'teacher',
            ]))->out(false),
            'allurl' => (new \moodle_url('/local/realtime_tracker/reports.php', [
                'courseid' => $this->course->id,
                'action' => 'general',
                'role' => 'all',
            ]))->out(false),
            'users' => $users,
        ];
    }
}
