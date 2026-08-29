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
use stdClass;
use templatable;

/**
 * Student detail report renderable.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_detail implements renderable, templatable {

    /** @var stdClass Course record. */
    protected $course;

    /** @var stdClass User record. */
    protected $user;

    /**
     * Constructor.
     *
     * @param stdClass $course Course record.
     * @param stdClass $user User record.
     */
    public function __construct(stdClass $course, stdClass $user) {
        $this->course = $course;
        $this->user = $user;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $never = get_string('never', 'local_realtime_tracker');
        $params = [
            'courseid' => $this->course->id,
            'userid' => $this->user->id,
            'sesskey' => sesskey(),
        ];

        return [
            'courseid' => $this->course->id,
            'userid' => $this->user->id,
            'studentname' => fullname($this->user),
            'studentemail' => $this->user->email,
            'firstaccess' => !empty($this->user->firstaccess) ? userdate($this->user->firstaccess) : $never,
            'lastaccess' => !empty($this->user->lastaccess) ? userdate($this->user->lastaccess) : $never,
            'activities' => helper::get_student_activities($this->course, $this->user->id),
            'pdfurl' => (new moodle_url('/local/realtime_tracker/export.php', $params + ['format' => 'pdf']))->out(false),
            'docurl' => (new moodle_url('/local/realtime_tracker/export.php', $params + ['format' => 'doc']))->out(false),
            'xmlurl' => (new moodle_url('/local/realtime_tracker/export.php', $params + ['format' => 'xml']))->out(false),
        ];
    }
}
