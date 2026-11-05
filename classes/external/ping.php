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

namespace local_realtime_tracker\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * External API for realtime tracker heartbeats.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ping extends external_api {

    /**
     * Describe parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id (0 if not an activity)', VALUE_DEFAULT, 0),
            'actiontype' => new external_value(PARAM_ALPHANUMEXT, 'Action type (view, create, ai)', VALUE_DEFAULT, 'view'),
            'sessionid' => new external_value(PARAM_ALPHANUMEXT, 'Session identifier'),
            'deltatime' => new external_value(PARAM_INT, 'Time spent active in seconds since last ping'),
        ]);
    }

    /**
     * Record active time for the current user.
     *
     * @param int $courseid Course ID.
     * @param int $cmid Course module ID.
     * @param string $actiontype Action type.
     * @param string $sessionid Session identifier.
     * @param int $deltatime Seconds since the last heartbeat.
     * @return array
     */
    public static function execute($courseid, $cmid, $actiontype, $sessionid, $deltatime) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'actiontype' => $actiontype,
            'sessionid' => $sessionid,
            'deltatime' => $deltatime,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        $allowedactions = ['view', 'create', 'ai'];
        if (!in_array($params['actiontype'], $allowedactions, true)) {
            $params['actiontype'] = 'view';
        }

        $deltatime = max(0, min((int)$params['deltatime'], 120));
        $now = time();

        $log = new \stdClass();
        $log->user_id = $USER->id;
        $log->course_id = $params['courseid'];
        $log->cmid = $params['cmid'];
        $log->time_spent = $deltatime;
        $log->action_type = $params['actiontype'];
        $log->last_ping = $now;
        $log->session_id = $params['sessionid'];
        $DB->insert_record('local_realtime_tracker_logs', $log);

        $summary = $DB->get_record('local_realtime_tracker_summ', [
            'user_id' => $USER->id,
            'course_id' => $params['courseid'],
        ]);
        if ($summary) {
            $previousaccess = (int)$summary->last_access;
            $summary->total_active_seconds += $deltatime;
            $summary->last_access = $now;
            if ($now - $previousaccess > 600) {
                $summary->access_count += 1;
            }
            $DB->update_record('local_realtime_tracker_summ', $summary);
        } else {
            $summary = new \stdClass();
            $summary->user_id = $USER->id;
            $summary->course_id = $params['courseid'];
            $summary->total_active_seconds = $deltatime;
            $summary->access_count = 1;
            $summary->first_access = $now;
            $summary->last_access = $now;
            $DB->insert_record('local_realtime_tracker_summ', $summary);
        }

        if ($params['cmid'] > 0) {
            $act = $DB->get_record('local_realtime_tracker_acts', [
                'user_id' => $USER->id,
                'course_id' => $params['courseid'],
                'cmid' => $params['cmid'],
            ]);
            if ($act) {
                if ($params['actiontype'] === 'create') {
                    $act->create_seconds += $deltatime;
                } else if ($params['actiontype'] === 'ai') {
                    $act->ai_seconds += $deltatime;
                } else {
                    $act->active_seconds += $deltatime;
                }
                $DB->update_record('local_realtime_tracker_acts', $act);
            } else {
                $act = new \stdClass();
                $act->user_id = $USER->id;
                $act->course_id = $params['courseid'];
                $act->cmid = $params['cmid'];
                $act->active_seconds = $params['actiontype'] === 'view' ? $deltatime : 0;
                $act->create_seconds = $params['actiontype'] === 'create' ? $deltatime : 0;
                $act->ai_seconds = $params['actiontype'] === 'ai' ? $deltatime : 0;
                $act->entries = 1;
                $DB->insert_record('local_realtime_tracker_acts', $act);
            }
        }

        return ['status' => true];
    }

    /**
     * Describe the return value for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
        ]);
    }
}
