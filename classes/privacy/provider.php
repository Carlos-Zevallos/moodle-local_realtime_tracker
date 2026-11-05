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

namespace local_realtime_tracker\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for local_realtime_tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider {

    /**
     * Return metadata about the plugin's stored user data.
     *
     * @param collection $collection The metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_realtime_tracker_logs', [
            'userid' => 'privacy:metadata:local_realtime_tracker_logs:userid',
            'courseid' => 'privacy:metadata:local_realtime_tracker_logs:courseid',
            'cmid' => 'privacy:metadata:local_realtime_tracker_logs:cmid',
            'time_spent' => 'privacy:metadata:local_realtime_tracker_logs:time_spent',
            'action_type' => 'privacy:metadata:local_realtime_tracker_logs:action_type',
            'last_ping' => 'privacy:metadata:local_realtime_tracker_logs:last_ping',
            'session_id' => 'privacy:metadata:local_realtime_tracker_logs:session_id',
        ], 'privacy:metadata:local_realtime_tracker_logs');

        $collection->add_database_table('local_realtime_tracker_summ', [
            'userid' => 'privacy:metadata:local_realtime_tracker_summ:userid',
            'courseid' => 'privacy:metadata:local_realtime_tracker_summ:courseid',
            'total_active_seconds' => 'privacy:metadata:local_realtime_tracker_summ:total_active_seconds',
            'access_count' => 'privacy:metadata:local_realtime_tracker_summ:access_count',
            'first_access' => 'privacy:metadata:local_realtime_tracker_summ:first_access',
            'last_access' => 'privacy:metadata:local_realtime_tracker_summ:last_access',
        ], 'privacy:metadata:local_realtime_tracker_summ');

        $collection->add_database_table('local_realtime_tracker_acts', [
            'userid' => 'privacy:metadata:local_realtime_tracker_acts:userid',
            'courseid' => 'privacy:metadata:local_realtime_tracker_acts:courseid',
            'cmid' => 'privacy:metadata:local_realtime_tracker_acts:cmid',
            'active_seconds' => 'privacy:metadata:local_realtime_tracker_acts:active_seconds',
            'create_seconds' => 'privacy:metadata:local_realtime_tracker_acts:create_seconds',
            'ai_seconds' => 'privacy:metadata:local_realtime_tracker_acts:ai_seconds',
            'entries' => 'privacy:metadata:local_realtime_tracker_acts:entries',
        ], 'privacy:metadata:local_realtime_tracker_acts');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_realtime_tracker_logs} l ON l.course_id = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel1
                   AND l.user_id = :userid1
                 UNION
                SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_realtime_tracker_summ} s ON s.course_id = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel2
                   AND s.user_id = :userid2
                 UNION
                SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_realtime_tracker_acts} a ON a.course_id = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel3
                   AND a.user_id = :userid3";
        $params = [
            'contextlevel1' => CONTEXT_COURSE,
            'userid1' => $userid,
            'contextlevel2' => CONTEXT_COURSE,
            'userid2' => $userid,
            'contextlevel3' => CONTEXT_COURSE,
            'userid3' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to add user IDs to.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $params = ['courseid' => $context->instanceid];
        $userlist->add_from_sql('userid', 'SELECT user_id AS userid FROM {local_realtime_tracker_logs} WHERE course_id = :courseid', $params);
        $userlist->add_from_sql('userid', 'SELECT user_id AS userid FROM {local_realtime_tracker_summ} WHERE course_id = :courseid', $params);
        $userlist->add_from_sql('userid', 'SELECT user_id AS userid FROM {local_realtime_tracker_acts} WHERE course_id = :courseid', $params);
    }

    /**
     * Export all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $component = 'local_realtime_tracker';

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $courseid = $context->instanceid;
            $subcontext = [get_string('pluginname', $component)];

            $logs = $DB->get_records('local_realtime_tracker_logs', [
                'user_id' => $userid,
                'course_id' => $courseid,
            ]);
            if ($logs) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:metadata:local_realtime_tracker_logs', $component)]),
                    (object)['logs' => array_values($logs)]
                );
            }

            $summary = $DB->get_record('local_realtime_tracker_summ', [
                'user_id' => $userid,
                'course_id' => $courseid,
            ]);
            if ($summary) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:metadata:local_realtime_tracker_summ', $component)]),
                    $summary
                );
            }

            $acts = $DB->get_records('local_realtime_tracker_acts', [
                'user_id' => $userid,
                'course_id' => $courseid,
            ]);
            if ($acts) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:metadata:local_realtime_tracker_acts', $component)]),
                    (object)['activities' => array_values($acts)]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;
        $DB->delete_records('local_realtime_tracker_logs', ['course_id' => $courseid]);
        $DB->delete_records('local_realtime_tracker_summ', ['course_id' => $courseid]);
        $DB->delete_records('local_realtime_tracker_acts', ['course_id' => $courseid]);
    }

    /**
     * Delete all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $params = ['user_id' => $userid, 'course_id' => $context->instanceid];
            $DB->delete_records('local_realtime_tracker_logs', $params);
            $DB->delete_records('local_realtime_tracker_summ', $params);
            $DB->delete_records('local_realtime_tracker_acts', $params);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = $inparams + ['courseid' => $context->instanceid];
        $select = "course_id = :courseid AND user_id {$insql}";

        $DB->delete_records_select('local_realtime_tracker_logs', $select, $params);
        $DB->delete_records_select('local_realtime_tracker_summ', $select, $params);
        $DB->delete_records_select('local_realtime_tracker_acts', $select, $params);
    }
}
