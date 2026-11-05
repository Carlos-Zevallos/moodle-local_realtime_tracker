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
 * Backup class for local_realtime_tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup plugin class that includes course-scoped tracker data.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_realtime_tracker_plugin extends backup_local_plugin {

    /**
     * Define the plugin structure for course backups.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $userinfo = $this->get_setting_value('users');

        $plugin = $this->get_plugin_element(null, $userinfo, 'users');
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        $logs = new backup_nested_element('logs');
        $log = new backup_nested_element('log', ['id'], [
            'user_id', 'cmid', 'time_spent', 'action_type', 'last_ping', 'session_id',
        ]);
        $wrapper->add_child($logs);
        $logs->add_child($log);

        $summaries = new backup_nested_element('summaries');
        $summary = new backup_nested_element('summary', ['id'], [
            'user_id', 'total_active_seconds', 'access_count', 'first_access', 'last_access',
        ]);
        $wrapper->add_child($summaries);
        $summaries->add_child($summary);

        $activities = new backup_nested_element('activities');
        $activity = new backup_nested_element('activity', ['id'], [
            'user_id', 'cmid', 'active_seconds', 'create_seconds', 'ai_seconds', 'entries',
        ]);
        $wrapper->add_child($activities);
        $activities->add_child($activity);

        if ($userinfo) {
            $log->set_source_table('local_realtime_tracker_logs', ['course_id' => backup::VAR_COURSEID]);
            $summary->set_source_table('local_realtime_tracker_summ', ['course_id' => backup::VAR_COURSEID]);
            $activity->set_source_table('local_realtime_tracker_acts', ['course_id' => backup::VAR_COURSEID]);
        }

        $log->annotate_ids('user', 'user_id');
        $log->annotate_ids('course_module', 'cmid');
        $summary->annotate_ids('user', 'user_id');
        $activity->annotate_ids('user', 'user_id');
        $activity->annotate_ids('course_module', 'cmid');

        return $plugin;
    }
}
