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

namespace local_realtime_tracker\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared helpers for reports and tracking summaries.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Format a duration in hours and minutes.
     *
     * @param int $seconds Duration in seconds.
     * @return string
     */
    public static function format_duration(int $seconds): string {
        $hours = (int)floor($seconds / 3600);
        $mins = (int)floor(($seconds % 3600) / 60);
        return get_string('timeformat', 'local_realtime_tracker', [
            'hours' => $hours,
            'mins' => $mins,
        ]);
    }

    /**
     * Format a duration as HH:MM.
     *
     * @param int $seconds Duration in seconds.
     * @return string
     */
    public static function format_clock(int $seconds): string {
        $hours = (int)floor($seconds / 3600);
        $mins = (int)floor(($seconds % 3600) / 60);
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Return top-level and child course categories for report filters.
     *
     * @param int $categoryid Selected top-level category ID.
     * @param int $subcategoryid Selected subcategory ID.
     * @return array{0: array, 1: array}
     */
    public static function get_category_options(int $categoryid, int $subcategoryid): array {
        global $DB;

        $categories = $DB->get_records('course_categories', [], 'sortorder', 'id, name, parent');
        $topcategories = [];
        $subcategories = [];
        foreach ($categories as $category) {
            $option = [
                'id' => $category->id,
                'name' => format_string($category->name),
                'selected' => false,
            ];
            if ((int)$category->parent === 0) {
                $option['selected'] = ((int)$category->id === $categoryid);
                $topcategories[] = $option;
            } else {
                $option['parent'] = (int)$category->parent;
                $option['selected'] = ((int)$category->id === $subcategoryid);
                $subcategories[] = $option;
            }
        }

        return [$topcategories, $subcategories];
    }

    /**
     * Load users for the course dashboard, filtered by role.
     *
     * @param int $courseid Course ID.
     * @param string $rolefilter One of student, teacher, all.
     * @return array
     */
    public static function get_course_dashboard_users(int $courseid, string $rolefilter): array {
        global $DB;

        $context = \context_course::instance($courseid);
        $students = get_enrolled_users($context, 'moodle/course:participate', 0, 'u.id');
        $teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.id');
        foreach (array_keys($teachers) as $teacherid) {
            unset($students[$teacherid]);
        }

        $summaries = $DB->get_records('local_realtime_tracker_summ', ['course_id' => $courseid]);
        if (!$summaries) {
            return [];
        }

        $userids = [];
        foreach ($summaries as $summary) {
            $userids[] = $summary->user_id;
        }
        $users = $DB->get_records_list('user', 'id', $userids);

        $result = [];
        foreach ($summaries as $summary) {
            if (!isset($users[$summary->user_id])) {
                continue;
            }
            $user = $users[$summary->user_id];
            if (is_siteadmin($user->id)) {
                continue;
            }

            $isteacher = isset($teachers[$user->id]);
            $isstudent = isset($students[$user->id]) || !$isteacher;
            if ($rolefilter === 'student' && !$isstudent) {
                continue;
            }
            if ($rolefilter === 'teacher' && !$isteacher) {
                continue;
            }

            $never = get_string('never', 'local_realtime_tracker');
            $result[] = [
                'userid' => $user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
                'usertype' => $isteacher ? 'teacher' : 'student',
                'firstaccess' => $summary->first_access ? userdate($summary->first_access) : $never,
                'lastaccess' => $summary->last_access ? userdate($summary->last_access) : $never,
                'accesses' => $summary->access_count,
                'activetime' => self::format_clock((int)$summary->total_active_seconds),
            ];
        }

        return $result;
    }

    /**
     * Load activity rows for a student in a course.
     *
     * @param \stdClass $course Course record.
     * @param int $userid User ID.
     * @return array
     */
    public static function get_student_activities(\stdClass $course, int $userid): array {
        global $DB;

        $acts = $DB->get_records('local_realtime_tracker_acts', [
            'course_id' => $course->id,
            'user_id' => $userid,
        ]);
        if (!$acts) {
            return [];
        }

        $modinfo = get_fast_modinfo($course);
        $cms = $modinfo->get_cms();
        $activities = [];
        foreach ($acts as $act) {
            if (empty($act->cmid) || empty($cms[$act->cmid])) {
                continue;
            }
            $cm = $cms[$act->cmid];
            $activities[] = [
                'name' => $cm->name,
                'type' => $cm->modname,
                'entries' => $act->entries,
                'time' => self::format_duration((int)$act->active_seconds),
                'status' => get_string('completed', 'local_realtime_tracker'),
            ];
        }

        return $activities;
    }

    /**
     * Load activity rows for a teacher in a course using plugin tables only.
     *
     * @param \stdClass $course Course record.
     * @param int $userid User ID.
     * @return array{0: array, 1: int} Activities and total LMS seconds.
     */
    public static function get_teacher_activities(\stdClass $course, int $userid): array {
        global $DB;

        $summary = $DB->get_record('local_realtime_tracker_summ', [
            'course_id' => $course->id,
            'user_id' => $userid,
        ]);
        $totalseconds = $summary ? (int)$summary->total_active_seconds : 0;

        $acts = $DB->get_records('local_realtime_tracker_acts', [
            'course_id' => $course->id,
            'user_id' => $userid,
        ], '', 'cmid, entries, active_seconds, create_seconds, ai_seconds');

        $ai_consumptions = $DB->get_records('aiprovider_datacurso_consumption', ['userid' => $userid], '', 'id, externalid, credits');
        $ai_by_cmid = [];
        foreach ($ai_consumptions as $c) {
            $extid = (int)$c->externalid;
            if ($extid > 0) {
                if (!isset($ai_by_cmid[$extid])) {
                    $ai_by_cmid[$extid] = 0;
                }
                $ai_by_cmid[$extid] += (int)$c->credits;
            }
        }

        $modinfo = get_fast_modinfo($course);
        $activities = [];
        foreach ($modinfo->get_cms() as $cm) {
            $act = $acts[$cm->id] ?? null;
            $entries = $act ? (int)$act->entries : 0;
            $active = $act ? (int)$act->active_seconds : 0;
            $create = $act ? (int)$act->create_seconds : 0;
            $ai = $act ? (int)$act->ai_seconds : 0;

            if (isset($ai_by_cmid[$cm->id])) {
                $aiseconds = $ai_by_cmid[$cm->id] * 60; // 1 credit = 1 minute
                $active = max(0, $active - $aiseconds);
                $ai += $aiseconds;
            }

            $activities[] = [
                'name' => $cm->name,
                'type' => $cm->modname,
                'entries' => $entries,
                'connection_time' => self::format_duration($active),
                'creation_time' => self::format_duration($create),
                'ai_time' => self::format_duration($ai),
            ];
        }

        return [$activities, $totalseconds];
    }

    /**
     * Optional Datacurso token totals keyed by inferred module name.
     *
     * @param int|null $userid Optional user filter.
     * @return array<string,int>
     */
    public static function get_datacurso_tokens_by_modname(?int $userid = null): array {
        global $DB;
        $usage = [];

        try {
            if ($userid) {
                $records = $DB->get_records('aiprovider_datacurso_consumption', ['userid' => $userid]);
            } else {
                $records = $DB->get_records('aiprovider_datacurso_consumption');
            }
        } catch (\Exception $exception) {
            return [];
        }

        if (empty($records)) {
            return [];
        }

        foreach ($records as $item) {
            $service = strtolower($item->service ?? '');
            $actionname = strtolower($item->action ?? '');
            $tokens = (int)($item->credits ?? 0);
            $modtype = 'unknown';
            if (strpos($service, 'forum') !== false || strpos($actionname, 'forum') !== false) {
                $modtype = 'forum';
            } else if (strpos($service, 'assign') !== false || strpos($actionname, 'assign') !== false) {
                $modtype = 'assign';
            } else if (strpos($service, 'quiz') !== false || strpos($actionname, 'quiz') !== false) {
                $modtype = 'quiz';
            } else if (strpos($actionname, 'resource') !== false) {
                $modtype = 'resource';
            } else if (strpos($service, 'coursegen') !== false || strpos($service, 'page') !== false
                    || strpos($actionname, 'page') !== false) {
                $modtype = 'page';
            }
            if (!isset($usage[$modtype])) {
                $usage[$modtype] = 0;
            }
            $usage[$modtype] += $tokens;
        }

        return $usage;
    }

    /**
     * Institutional teacher metrics for charts.
     *
     * @param int $categoryid Top-level category filter.
     * @param int $subcategoryid Subcategory filter.
     * @return array{0: int, 1: int, 2: array<string,int>}
     */
    public static function get_institutional_metrics(int $categoryid, int $subcategoryid): array {
        global $DB;

        $sql = "SELECT a.id, a.user_id AS userid, a.course_id AS courseid, a.create_seconds, a.ai_seconds, m.name AS modname
                  FROM {local_realtime_tracker_acts} a
                  JOIN {course} c ON c.id = a.course_id
                  JOIN {course_modules} cm ON cm.id = a.cmid
                  JOIN {modules} m ON m.id = cm.module";
        $params = [];
        $filtercategory = $subcategoryid > 0 ? $subcategoryid : $categoryid;
        if ($filtercategory > 0) {
            $sql .= " WHERE c.category = :categoryid";
            $params['categoryid'] = $filtercategory;
        }

        $acts = $DB->get_records_sql($sql, $params);
        $courseids = [];
        foreach ($acts as $act) {
            $courseids[$act->courseid] = $act->courseid;
        }

        $teachersbycourse = [];
        foreach ($courseids as $courseid) {
            $context = \context_course::instance($courseid);
            $teachersbycourse[$courseid] = get_enrolled_users($context, 'moodle/course:update', 0, 'u.id');
        }

        $totalcreation = 0;
        $totalai = 0;
        $resourcecreation = [];
        foreach ($acts as $act) {
            if (is_siteadmin($act->userid)) {
                continue;
            }
            if (empty($teachersbycourse[$act->courseid][$act->userid])) {
                continue;
            }
            $totalcreation += (int)$act->create_seconds;
            $totalai += (int)$act->ai_seconds;
            if ((int)$act->create_seconds > 0) {
                if (!isset($resourcecreation[$act->modname])) {
                    $resourcecreation[$act->modname] = 0;
                }
                $resourcecreation[$act->modname] += (int)$act->create_seconds;
            }
        }

        foreach (self::get_datacurso_tokens_by_modname() as $tokens) {
            $totalai += ((int)$tokens * 60);
        }

        return [$totalcreation, $totalai, $resourcecreation];
    }

    /**
     * Resource report rows without correlated subqueries.
     *
     * @param int $categoryid Top-level category filter.
     * @param int $subcategoryid Subcategory filter.
     * @param string|int $year Optional year fragment for shortname search.
     * @param string $semester Optional semester fragment for shortname search.
     * @return array
     */
    public static function get_resource_report_rows(
        int $categoryid,
        int $subcategoryid,
        $year,
        string $semester
    ): array {
        global $DB;

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'siteid' => SITEID,
        ];
        $sql = "SELECT " . $DB->sql_concat('c.id', "'-'", 'u.id') . " AS uniqueid,
                       c.id AS courseid,
                       cc.name AS categoryname,
                       c.fullname AS coursename,
                       c.shortname,
                       u.id AS userid,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname NOT IN ('student', 'guest')
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                 WHERE c.id <> :siteid AND c.visible = 1";

        $filtercategory = $subcategoryid > 0 ? $subcategoryid : $categoryid;
        if ($filtercategory > 0) {
            $sql .= " AND c.category = :categoryid";
            $params['categoryid'] = $filtercategory;
        }

        $search = '';
        if (!empty($year)) {
            $search .= (string)$year;
        }
        if ($semester !== '') {
            $search .= $semester;
        }
        if ($search !== '') {
            $sql .= " AND " . $DB->sql_like('c.shortname', ':shortnamesearch', false, false);
            $params['shortnamesearch'] = '%' . $DB->sql_like_escape($search) . '%';
        }

        $sql .= " ORDER BY c.fullname, u.lastname, u.firstname";
        $rows = $DB->get_records_sql($sql, $params);
        if (!$rows) {
            return [];
        }

        $courseids = [];
        foreach ($rows as $row) {
            $courseids[$row->courseid] = $row->courseid;
        }
        list($csql, $cparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');

        $modcounts = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat('cm.course', "'-'", 'm.name') . " AS uniqueid,
                    cm.course AS courseid,
                    m.name AS modname,
                    COUNT(cm.id) AS cnt
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course $csql AND cm.visible = 1
           GROUP BY cm.course, m.name",
            $cparams
        );
        $modsbycourse = [];
        foreach ($modcounts as $mod) {
            $modsbycourse[$mod->courseid][$mod->modname] = (int)$mod->cnt;
        }

        $forumcounts = $DB->get_records_sql(
            "SELECT cm.course AS courseid, COUNT(cm.id) AS cnt
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
               JOIN {forum} f ON f.id = cm.instance AND f.type <> 'news'
              WHERE cm.course $csql AND cm.visible = 1
           GROUP BY cm.course",
            $cparams
        );

        $studentparams = $cparams + ['sctx' => CONTEXT_COURSE];
        $studentcounts = $DB->get_records_sql(
            "SELECT ctx.instanceid AS courseid, COUNT(DISTINCT ra.userid) AS cnt
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :sctx
               JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
              WHERE ctx.instanceid $csql
           GROUP BY ctx.instanceid",
            $studentparams
        );

        $summaries = $DB->get_records_select('local_realtime_tracker_summ', "course_id $csql", $cparams);
        $summindex = [];
        foreach ($summaries as $summary) {
            $summindex[$summary->course_id . '-' . $summary->user_id] = (int)$summary->total_active_seconds;
        }

        $accesses = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat('courseid', "'-'", 'userid') . " AS uniqueid, courseid, userid, timeaccess 
               FROM {user_lastaccess} 
              WHERE courseid $csql",
            $cparams
        );

        $creations = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat('course_id', "'-'", 'user_id') . " AS uniqueid,
                    course_id AS courseid,
                    user_id AS userid,
                    SUM(create_seconds) AS totalcreation
               FROM {local_realtime_tracker_acts}
              WHERE course_id $csql
           GROUP BY course_id, user_id",
            $cparams
        );

        // BULK FETCH AI CREDITS - PHP Side mapping to avoid DB dialect JOIN exceptions (e.g. Postgres VARCHAR = BIGINT)
        $ai_credits_by_course_user = [];
        $ai_credits_total_by_user = []; // Fallback if externalid doesn't match cmid/courseid
        $teachers_with_course_match = [];
        
        try {
            $teacher_ids = array_filter(array_unique(array_map(function($r) { return $r->userid; }, $rows)));
            if (!empty($teacher_ids)) {
                list($usql, $uparams) = $DB->get_in_or_equal($teacher_ids);
                $consumptions = $DB->get_records_select('aiprovider_datacurso_consumption', "userid $usql", $uparams, '', 'id, externalid, userid, credits');
                
                if (!empty($consumptions)) {
                    $externalids = array_unique(array_map(function($c) { return $c->externalid; }, $consumptions));
                    $course_records = [];
                    
                    $numeric_ext_ids = array_filter(array_map('intval', $externalids));
                    if (!empty($numeric_ext_ids)) {
                        list($esql, $eparams) = $DB->get_in_or_equal($numeric_ext_ids);
                        // Safe to query since we are passing integers
                        $course_records = $DB->get_records_select('course', "id $esql", $eparams, '', 'id');
                    }
                    
                    foreach ($consumptions as $c) {
                        $ext_id = (int)$c->externalid;
                        $courseid = 0;
                        
                        if (!isset($ai_credits_total_by_user[$c->userid])) {
                            $ai_credits_total_by_user[$c->userid] = 0;
                        }
                        $ai_credits_total_by_user[$c->userid] += (float)$c->credits;
                        
                        if (isset($course_records[$ext_id])) {
                            $courseid = $course_records[$ext_id]->id;
                        }
                        
                        if ($courseid > 0) {
                            $teachers_with_course_match[$c->userid] = true;
                            $unique_key = $courseid . '-' . $c->userid;
                            if (!isset($ai_credits_by_course_user[$unique_key])) {
                                $ai_credits_by_course_user[$unique_key] = 0;
                            }
                            $ai_credits_by_course_user[$unique_key] += (float)$c->credits;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("AI Credits Mapping Error: " . $e->getMessage());
        }

        $result = [];
        foreach ($rows as $row) {
            $key = $row->courseid . '-' . $row->userid;
            $has_accessed = isset($accesses[$key]);
            
            $mods = $modsbycourse[$row->courseid] ?? [];
            $urlcount = $mods['url'] ?? 0;
            $labelcount = $mods['label'] ?? 0;
            $assigncount = ($mods['assign'] ?? 0) + ($mods['assignment'] ?? 0);
            $forumcount = isset($forumcounts[$row->courseid]) ? (int)$forumcounts[$row->courseid]->cnt : 0;
            $filecount = $mods['resource'] ?? 0;
            $teamscount = $mods['msteams'] ?? 0;
            $foldercount = $mods['folder'] ?? 0;
            $studentcount = isset($studentcounts[$row->courseid]) ? (int)$studentcounts[$row->courseid]->cnt : 0;
            $connection = $summindex[$key] ?? 0;
            $creation = isset($creations[$key]) ? (int)$creations[$key]->totalcreation : 0;

            $teacher_id = (int)$row->userid;
            $ai_credits = 0;
            if ($teacher_id > 0) {
                if (isset($teachers_with_course_match[$teacher_id])) {
                    $unique_ai_key = $row->courseid . '-' . $teacher_id;
                    $ai_credits = $ai_credits_by_course_user[$unique_ai_key] ?? 0;
                } else {
                    if (!isset($fallback_assigned[$teacher_id])) {
                        $ai_credits = $ai_credits_total_by_user[$teacher_id] ?? 0;
                        $fallback_assigned[$teacher_id] = true;
                    }
                }
            }

            $result[] = (object)[
                'courseid' => $row->courseid,
                'userid' => $row->userid,
                'categoryname' => $row->categoryname,
                'coursename' => $row->coursename,
                'shortname' => $row->shortname,
                'teachername' => fullname($row),
                'url_count' => $urlcount,
                'label_count' => $labelcount,
                'assign_count' => $assigncount,
                'forum_count' => $forumcount,
                'file_count' => $filecount,
                'teams_count' => $teamscount,
                'folder_count' => $foldercount,
                'shared_count' => $urlcount + $filecount + $foldercount,
                'student_count' => $studentcount,
                'total_connection' => $connection,
                'total_creation' => $creation,
                'has_accessed' => $has_accessed,
                'ai_credits' => $ai_credits,
            ];
        }

        return $result;
    }
}
