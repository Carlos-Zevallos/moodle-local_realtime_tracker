<?php
/**
 * Hooks for the local realtime tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend course navigation to add Mauri Reports link.
 */
function local_realtime_tracker_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/realtime_tracker:viewreports', $context)) {
        $url = new moodle_url('/local/realtime_tracker/reports.php', ['courseid' => $course->id]);
        $node = navigation_node::create(
            get_string('maurireports', 'local_realtime_tracker'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_realtime_tracker_reports',
            new pix_icon('i/report', '')
        );
        $navigation->add_node($node);
    }
}

/**
 * Inject the JS tracker into every page.
 */
function local_realtime_tracker_before_footer() {
    global $PAGE, $COURSE, $USER;

    // Do not track guests or logged-out users, and only track inside courses (not site home usually).
    if (isloggedin() && !isguestuser() && !empty($COURSE) && $COURSE->id !== SITEID) {
        
        // Determine course module ID if we are inside an activity
        $cmid = 0;
        if ($PAGE->cm) {
            $cmid = $PAGE->cm->id;
        }

        // Determine Action Type (View, Create, AI)
        $actiontype = 'view';
        $currenturl = $PAGE->url ? $PAGE->url->out(false) : '';
        if (strpos($currenturl, 'modedit.php') !== false) {
            $actiontype = 'create';
        } else {
            $aikeywords = explode(',', get_config('local_realtime_tracker', 'aikeywords'));
            foreach ($aikeywords as $kw) {
                $kw = trim($kw);
                if (!empty($kw) && strpos($currenturl, $kw) !== false) {
                    $actiontype = 'ai';
                    break;
                }
            }
        }

        $PAGE->requires->js_call_amd('local_realtime_tracker/tracker', 'init', [
            'courseid' => $COURSE->id,
            'userid' => $USER->id,
            'cmid' => $cmid,
            'actiontype' => $actiontype,
            'sessionid' => sesskey() . '_' . time(),
            'interval' => 30000 // 30s heartbeat
        ]);
    }
}

/**
 * Calculates fallback times from logstore_standard_log if realtime tracking is missing or empty.
 */
function local_realtime_tracker_get_fallback_times($courseid, $userid, $tracker_conn, $tracker_creat) {
    global $DB;
    
    // Connection fallback
    if ($tracker_conn == 0) {
        $logs = $DB->get_records_sql("SELECT id, timecreated FROM {logstore_standard_log} WHERE courseid = ? AND userid = ? ORDER BY timecreated ASC", [$courseid, $userid]);
        $total_lms_time = 0;
        $session_start = 0;
        $last_time = 0;
        foreach ($logs as $log) {
            if ($session_start == 0) {
                $session_start = $log->timecreated;
                $last_time = $log->timecreated;
            } else {
                if ($log->timecreated - $last_time > 1800) {
                    $total_lms_time += ($last_time - $session_start);
                    $session_start = $log->timecreated;
                }
                $last_time = $log->timecreated;
            }
        }
        if ($session_start > 0) {
            $total_lms_time += ($last_time - $session_start);
        }
        $tracker_conn = max($tracker_conn, $total_lms_time);
    }
    
    // Creation fallback
    if ($tracker_creat == 0) {
        $creations = $DB->count_records_sql("SELECT COUNT(id) FROM {logstore_standard_log} WHERE courseid = ? AND userid = ? AND action IN ('created', 'updated')", [$courseid, $userid]);
        $tracker_creat = max($tracker_creat, $creations * 300); // Assign 5 minutes per creation/update
    }
    
    return [$tracker_conn, $tracker_creat];
}
