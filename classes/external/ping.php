<?php
/**
 * External API for realtime tracker ping.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_realtime_tracker\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

class ping extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id (0 if not an activity)', VALUE_DEFAULT, 0),
            'actiontype' => new external_value(PARAM_ALPHANUMEXT, 'Action type (view, create, ai)', VALUE_DEFAULT, 'view'),
            'sessionid' => new external_value(PARAM_ALPHANUMEXT, 'Session identifier'),
            'deltatime' => new external_value(PARAM_INT, 'Time spent active in seconds since last ping')
        ]);
    }

    public static function execute($courseid, $cmid, $actiontype, $sessionid, $deltatime) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'actiontype' => $actiontype,
            'sessionid' => $sessionid,
            'deltatime' => $deltatime
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        $now = time();

        // 1. Record log entry
        $log = new \stdClass();
        $log->userid = $USER->id;
        $log->courseid = $params['courseid'];
        $log->cmid = $params['cmid'];
        $log->time_spent = $params['deltatime'];
        $log->action_type = $params['actiontype'];
        $log->last_ping = $now;
        $log->session_id = $params['sessionid'];
        $DB->insert_record('local_realtime_tracker_logs', $log);

        // 2. Update Course Summary
        $summary = $DB->get_record('local_realtime_tracker_summ', ['userid' => $USER->id, 'courseid' => $params['courseid']]);
        if ($summary) {
            $summary->total_active_seconds += $params['deltatime'];
            $summary->last_access = $now;
            // Hacky way to detect new sessions (more than 10 mins since last ping)
            if ($now - $log->last_ping > 600) {
                $summary->access_count += 1;
            }
            $DB->update_record('local_realtime_tracker_summ', $summary);
        } else {
            $summary = new \stdClass();
            $summary->userid = $USER->id;
            $summary->courseid = $params['courseid'];
            $summary->total_active_seconds = $params['deltatime'];
            $summary->access_count = 1;
            $summary->first_access = $now;
            $summary->last_access = $now;
            $DB->insert_record('local_realtime_tracker_summ', $summary);
        }

        // 3. Update Activity Summary (if inside an activity)
        if ($params['cmid'] > 0) {
            $act = $DB->get_record('local_realtime_tracker_acts', [
                'userid' => $USER->id, 
                'courseid' => $params['courseid'],
                'cmid' => $params['cmid']
            ]);
            if ($act) {
                if ($params['actiontype'] === 'create') {
                    $act->create_seconds += $params['deltatime'];
                } elseif ($params['actiontype'] === 'ai') {
                    $act->ai_seconds += $params['deltatime'];
                } else {
                    $act->active_seconds += $params['deltatime'];
                }
                
                if ($now - $log->last_ping > 600) {
                    $act->entries += 1;
                }
                $DB->update_record('local_realtime_tracker_acts', $act);
            } else {
                $act = new \stdClass();
                $act->userid = $USER->id;
                $act->courseid = $params['courseid'];
                $act->cmid = $params['cmid'];
                $act->active_seconds = $params['actiontype'] === 'view' ? $params['deltatime'] : 0;
                $act->create_seconds = $params['actiontype'] === 'create' ? $params['deltatime'] : 0;
                $act->ai_seconds = $params['actiontype'] === 'ai' ? $params['deltatime'] : 0;
                $act->entries = 1;
                $DB->insert_record('local_realtime_tracker_acts', $act);
            }
        }

        return ['status' => true];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status')
        ]);
    }
}
