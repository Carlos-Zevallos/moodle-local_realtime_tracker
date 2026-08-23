<?php
/**
 * Reports entry point for Mauri Tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'general', PARAM_ALPHA); // general, student
$userid = optional_param('userid', 0, PARAM_INT);
$rolefilter = optional_param('role', 'student', PARAM_ALPHA); // student, teacher, all

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/realtime_tracker:viewreports', $context);

$PAGE->set_url(new moodle_url('/local/realtime_tracker/reports.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('maurireports', 'local_realtime_tracker'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

$renderer = $PAGE->get_renderer('local_realtime_tracker');
$data = new stdClass();
$data->courseid = $courseid;
$data->coursename = $course->fullname;
$data->cif = get_config('local_realtime_tracker', 'cif');
$data->centername = get_config('local_realtime_tracker', 'centername');
$data->modalidad = get_config('local_realtime_tracker', 'modalidad');
$data->rolefilter = $rolefilter;

echo $OUTPUT->header();

if ($action === 'general') {
    // Determine which users to show based on role
    $course_context = context_course::instance($courseid);
    $students = get_enrolled_users($course_context, 'moodle/course:participate', 0, 'u.id');
    $teachers = get_enrolled_users($course_context, 'moodle/course:update', 0, 'u.id');
    
    // Remove teachers from students list if they have both
    foreach ($teachers as $tid => $t) {
        if (isset($students[$tid])) {
            unset($students[$tid]);
        }
    }

    $summaries = $DB->get_records('local_realtime_tracker_summ', ['courseid' => $courseid]);
    $users = [];
    foreach ($summaries as $summ) {
        $user = $DB->get_record('user', ['id' => $summ->userid]);
        if ($user) {
            $is_teacher = isset($teachers[$user->id]);
            $is_student = isset($students[$user->id]) || (!$is_teacher); // Fallback if no specific role
            
            if ($rolefilter === 'student' && !$is_student) continue;
            if ($rolefilter === 'teacher' && !$is_teacher) continue;
            
            // Exclude site administrators from the report
            if (is_siteadmin($user->id)) continue;
            
            $hrs = floor($summ->total_active_seconds / 3600);
            $mins = floor(($summ->total_active_seconds % 3600) / 60);
            
            $users[] = [
                'userid' => $user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
                'usertype' => $is_teacher ? 'teacher' : 'student',
                'firstaccess' => $summ->first_access ? userdate($summ->first_access) : 'Nunca',
                'lastaccess' => $summ->last_access ? userdate($summ->last_access) : 'Nunca',
                'accesses' => $summ->access_count,
                'activetime' => sprintf('%02d:%02d', $hrs, $mins)
            ];
        }
    }
    $data->users = $users;
    echo $renderer->render_dashboard($data);

} elseif ($action === 'student' && $userid) {
    // Individual Student Report (per activity drilldown)
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $summ = $DB->get_record('local_realtime_tracker_summ', ['courseid' => $courseid, 'userid' => $userid]);
    
    $data->studentname = fullname($user);
    $data->studentemail = $user->email;
    $data->userid = $userid;
    $data->firstaccess = !empty($user->firstaccess) ? userdate($user->firstaccess) : 'Nunca';
    $data->lastaccess = !empty($user->lastaccess) ? userdate($user->lastaccess) : 'Nunca';
    
    $acts = $DB->get_records('local_realtime_tracker_acts', ['courseid' => $courseid, 'userid' => $userid]);
    $activities = [];
    
    foreach ($acts as $act) {
        $cm = $DB->get_record('course_modules', ['id' => $act->cmid]);
        if ($cm) {
            $module = $DB->get_record('modules', ['id' => $cm->module]);
            $instance = $DB->get_record($module->name, ['id' => $cm->instance]);
            
            $hrs = floor($act->active_seconds / 3600);
            $mins = floor(($act->active_seconds % 3600) / 60);
            
            $activities[] = [
                'name' => $instance ? $instance->name : 'Unknown',
                'type' => $module->name,
                'entries' => $act->entries,
                'time' => sprintf('%dh %dm', $hrs, $mins),
                'status' => 'Completada' // Pseudo-status for mockup
            ];
        }
    }
    $data->activities = $activities;
    echo $renderer->render_student_detail($data);

} elseif ($action === 'teacher' && $userid) {
    // Individual Teacher Report (per activity drilldown)
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $summ = $DB->get_record('local_realtime_tracker_summ', ['courseid' => $courseid, 'userid' => $userid]);
    
    $data->teachername = fullname($user);
    $data->teacheremail = $user->email;
    $data->userid = $userid;
    $data->firstaccess = !empty($user->firstaccess) ? userdate($user->firstaccess) : 'Nunca';
    $data->lastaccess = !empty($user->lastaccess) ? userdate($user->lastaccess) : 'Nunca';
    
    $activities = [];
    
    // Calculate total LMS time based on teacher's standard logs (logins/clicks)
    $logs = $DB->get_records_sql("SELECT id, timecreated FROM {logstore_standard_log} WHERE courseid = ? AND userid = ? ORDER BY timecreated ASC", [$courseid, $userid]);
    $total_lms_time = 0;
    $session_start = 0;
    $last_time = 0;
    
    foreach ($logs as $log) {
        if ($session_start == 0) {
            $session_start = $log->timecreated;
            $last_time = $log->timecreated;
        } else {
            // 30 minutes of inactivity = new session
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
    
    // Fallback to tracker data if logs didn't capture enough time and tracker has more
    $tracker_time = $summ ? $summ->total_active_seconds : 0;
    if ($tracker_time > $total_lms_time) {
        $total_lms_time = $tracker_time;
    }
    
    // Get all activities in the course
    $modinfo = get_fast_modinfo($courseid);
    $cms = $modinfo->get_cms();
    
    // Prepare fallback data per module
    $mod_logs = [];
    $all_mod_logs = $DB->get_records_sql("
        SELECT id, timecreated, action, contextinstanceid 
        FROM {logstore_standard_log} 
        WHERE courseid = ? AND userid = ? AND contextlevel = 70 
        ORDER BY timecreated ASC
    ", [$courseid, $userid]);
    
    foreach ($all_mod_logs as $log) {
        $mod_logs[$log->contextinstanceid][] = $log;
    }
    
    // Fetch Datacurso AI consumption for this teacher (if provider is installed)
    $datacurso_usage = [];
    $datacurso_usage_cm = [];
    
    if (class_exists('\aiprovider_datacurso\local\service\consumption_service')) {
        try {
            // Call internal service to avoid external_api::validate_context() after output started
            $dc_response = \aiprovider_datacurso\local\service\consumption_service::get_all_consumption(null, null, $userid);
            if (!empty($dc_response['consumption'])) {
                foreach ($dc_response['consumption'] as $dc_act) {
                    $service = strtolower($dc_act['id_service'] ?? '');
                    $action_name = strtolower($dc_act['action'] ?? '');
                    $tokens = $dc_act['cant_tokens'] ?? 0;
                    
                    // Map service or action to Moodle module types
                    $modtype = 'unknown';
                    if (strpos($service, 'forum') !== false || strpos($action_name, 'forum') !== false) {
                        $modtype = 'forum';
                    } elseif (strpos($service, 'assign') !== false || strpos($action_name, 'assign') !== false) {
                        $modtype = 'assign';
                    } elseif (strpos($service, 'quiz') !== false || strpos($action_name, 'quiz') !== false) {
                        $modtype = 'quiz';
                    } elseif (strpos($action_name, 'resource') !== false) {
                        $modtype = 'resource';
                    } elseif (strpos($service, 'coursegen') !== false || strpos($service, 'page') !== false || strpos($action_name, 'page') !== false) {
                        $modtype = 'page'; 
                    }
                    
                    $date_str = $dc_act['created_at'] ?? $dc_act['date'] ?? '';
                    $dc_time = strtotime($date_str);
                    $matched_cmid = null;
                    
                    if ($modtype !== 'unknown' && $dc_time) {
                        $smallest_diff = PHP_INT_MAX;
                        foreach ($all_mod_logs as $log) {
                            $log_cm = null;
                            foreach ($cms as $cm) {
                                if ($cm->id == $log->contextinstanceid) {
                                    $log_cm = $cm;
                                    break;
                                }
                            }
                            
                            if ($log_cm && $log_cm->modname === $modtype) {
                                if ($log->action === 'created' || $log->action === 'updated') {
                                    $diff = abs($log->timecreated - $dc_time);
                                    $min_sec_diff = $diff % 3600;
                                    if ($min_sec_diff > 1800) {
                                        $min_sec_diff = 3600 - $min_sec_diff;
                                    }
                                    
                                    if ($min_sec_diff < 120 && $min_sec_diff < $smallest_diff) {
                                        $smallest_diff = $min_sec_diff;
                                        $matched_cmid = $log->contextinstanceid;
                                    }
                                }
                            }
                        }
                    }
                    
                    if ($matched_cmid) {
                        if (!isset($datacurso_usage_cm[$matched_cmid])) {
                            $datacurso_usage_cm[$matched_cmid] = 0;
                        }
                        $datacurso_usage_cm[$matched_cmid] += $tokens;
                    } else {
                        if (!isset($datacurso_usage[$modtype])) {
                            $datacurso_usage[$modtype] = 0;
                        }
                        $datacurso_usage[$modtype] += $tokens;
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if Datacurso isn't configured or API fails
        }
    }
    
    foreach ($cms as $cm) {
        $module = $DB->get_record('modules', ['name' => $cm->modname]);
        if (!$module) continue;
        
        $act = $DB->get_record('local_realtime_tracker_acts', ['courseid' => $courseid, 'userid' => $userid, 'cmid' => $cm->id]);
        
        $act_entries = $act ? $act->entries : 0;
        $act_active = $act ? $act->active_seconds : 0;
        $act_create = $act ? $act->create_seconds : 0;
        $act_ai = $act ? $act->ai_seconds : 0;

        // Fallback for module times
        if ($act_active == 0 && isset($mod_logs[$cm->id])) {
            $mlogs = $mod_logs[$cm->id];
            $act_entries = max($act_entries, count($mlogs));
            
            $m_time = 0;
            $m_start = 0;
            $m_last = 0;
            $m_creates = 0;
            foreach ($mlogs as $mlog) {
                if ($mlog->action === 'created' || $mlog->action === 'updated') {
                    $m_creates++;
                }
                if ($m_start == 0) {
                    $m_start = $mlog->timecreated;
                    $m_last = $mlog->timecreated;
                } else {
                    if ($mlog->timecreated - $m_last > 1800) {
                        $m_time += ($m_last - $m_start);
                        $m_start = $mlog->timecreated;
                    }
                    $m_last = $mlog->timecreated;
                }
            }
            if ($m_start > 0) {
                $m_time += ($m_last - $m_start);
            }
            
            $act_active = max($act_active, $m_time);
            $act_create = max($act_create, $m_creates * 300);
        }
        
        // Append Datacurso AI tokens for this module
        $dc_tokens = 0;
        
        // 1. Exact CMID match
        if (!empty($datacurso_usage_cm[$cm->id])) {
            $dc_tokens += $datacurso_usage_cm[$cm->id];
            $datacurso_usage_cm[$cm->id] = 0; // Clear it
        }
        
        // 2. Fallback type match
        if (!empty($datacurso_usage[$module->name])) {
            $dc_tokens += $datacurso_usage[$module->name];
            // Clear it so we don't duplicate it across multiple instances
            $datacurso_usage[$module->name] = 0;
        }

        if ($dc_tokens > 0) {
            // Convert tokens to seconds (1 token = 1 minute)
            $ai_seconds_dc = $dc_tokens * 60;
            $act_active = max(0, $act_active - $ai_seconds_dc);
            $act_ai += $ai_seconds_dc;
        }
        
        // Format Creation Time
        $c_hrs = floor($act_create / 3600);
        $c_mins = floor(($act_create % 3600) / 60);
        
        // Format Connection Time (LMS Time for this activity)
        $a_hrs = floor($act_active / 3600);
        $a_mins = floor(($act_active % 3600) / 60);

        // Format AI Time
        $ai_hrs = floor($act_ai / 3600);
        $ai_mins = floor(($act_ai % 3600) / 60);
        $ai_display = sprintf('%dh %dm', $ai_hrs, $ai_mins);
        
        $activities[] = [
            'name' => $cm->name,
            'type' => $module->name,
            'entries' => $act_entries,
            'connection_time' => sprintf('%dh %dm', $a_hrs, $a_mins),
            'creation_time' => sprintf('%dh %dm', $c_hrs, $c_mins),
            'ai_time' => $ai_display
        ];
    }
    
    $t_hrs = floor($total_lms_time / 3600);
    $t_mins = floor(($total_lms_time % 3600) / 60);
    $data->total_lms_time = sprintf('%dh %dm', $t_hrs, $t_mins);
    $data->activities = $activities;
    
    echo $renderer->render_teacher_detail($data);
}

echo $OUTPUT->footer();
