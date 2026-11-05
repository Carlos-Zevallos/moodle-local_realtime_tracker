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
 * Restore class for local_realtime_tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore plugin class that restores course-scoped tracker data.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_realtime_tracker_plugin extends restore_local_plugin {

    /**
     * Define the restore paths for course-level plugin data.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('users');
        if (!$userinfo) {
            return $paths;
        }

        $paths[] = new restore_path_element($this->get_namefor('log'), $this->get_pathfor('/logs/log'));
        $paths[] = new restore_path_element($this->get_namefor('summary'), $this->get_pathfor('/summaries/summary'));
        $paths[] = new restore_path_element($this->get_namefor('activity'), $this->get_pathfor('/activities/activity'));

        return $paths;
    }

    /**
     * Process a restored heartbeat log record.
     *
     * @param array|\stdClass $data Record data.
     */
    public function process_local_realtime_tracker_log($data) {
        global $DB;

        $data = (object)$data;
        $data->course_id = $this->task->get_courseid();
        $userid = !empty($data->user_id) ? $data->user_id : ($data->userid ?? 0);
        $data->user_id = $this->get_mappingid('user', $userid);
        if (!$data->user_id) {
            return;
        }
        if (isset($data->courseid)) {
            unset($data->courseid);
        }
        if (isset($data->userid)) {
            unset($data->userid);
        }
        if (!empty($data->cmid)) {
            $mappedcmid = $this->get_mappingid('course_module', $data->cmid);
            $data->cmid = $mappedcmid ? $mappedcmid : 0;
        }
        unset($data->id);
        $DB->insert_record('local_realtime_tracker_logs', $data);
    }

    /**
     * Process a restored course summary record.
     *
     * @param array|\stdClass $data Record data.
     */
    public function process_local_realtime_tracker_summary($data) {
        global $DB;

        $data = (object)$data;
        $data->course_id = $this->task->get_courseid();
        $userid = !empty($data->user_id) ? $data->user_id : ($data->userid ?? 0);
        $data->user_id = $this->get_mappingid('user', $userid);
        if (!$data->user_id) {
            return;
        }
        if (isset($data->courseid)) {
            unset($data->courseid);
        }
        if (isset($data->userid)) {
            unset($data->userid);
        }
        unset($data->id);
        if ($DB->record_exists('local_realtime_tracker_summ', [
            'course_id' => $data->course_id,
            'user_id' => $data->user_id,
        ])) {
            return;
        }
        $DB->insert_record('local_realtime_tracker_summ', $data);
    }

    /**
     * Process a restored activity summary record.
     *
     * @param array|\stdClass $data Record data.
     */
    public function process_local_realtime_tracker_activity($data) {
        global $DB;

        $data = (object)$data;
        $data->course_id = $this->task->get_courseid();
        $userid = !empty($data->user_id) ? $data->user_id : ($data->userid ?? 0);
        $data->user_id = $this->get_mappingid('user', $userid);
        if (!$data->user_id) {
            return;
        }
        if (isset($data->courseid)) {
            unset($data->courseid);
        }
        if (isset($data->userid)) {
            unset($data->userid);
        }
        if (!empty($data->cmid)) {
            $mappedcmid = $this->get_mappingid('course_module', $data->cmid);
            if (!$mappedcmid) {
                return;
            }
            $data->cmid = $mappedcmid;
        }
        unset($data->id);
        if ($DB->record_exists('local_realtime_tracker_acts', [
            'course_id' => $data->course_id,
            'user_id' => $data->user_id,
            'cmid' => $data->cmid,
        ])) {
            return;
        }
        $DB->insert_record('local_realtime_tracker_acts', $data);
    }
}
