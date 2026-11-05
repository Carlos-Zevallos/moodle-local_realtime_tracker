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
 * Upgrade steps for local_realtime_tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_realtime_tracker plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_realtime_tracker_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082800) {
        $logstable = new xmldb_table('local_realtime_tracker_logs');
        $logsindex = new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($logstable, $logsindex)) {
            $dbman->add_index($logstable, $logsindex);
        }

        $summtable = new xmldb_table('local_realtime_tracker_summ');
        $summindex = new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($summtable, $summindex)) {
            $dbman->add_index($summtable, $summindex);
        }

        $actstable = new xmldb_table('local_realtime_tracker_acts');
        $actsindex = new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($actstable, $actsindex)) {
            $dbman->add_index($actstable, $actsindex);
        }

        upgrade_plugin_savepoint(true, 2026082800, 'local', 'realtime_tracker');
    }

    if ($oldversion < 2026092100) {
        $tables = [
            'local_realtime_tracker_logs',
            'local_realtime_tracker_summ',
            'local_realtime_tracker_acts'
        ];

        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);
            
            $coursefield = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'userid');
            if ($dbman->field_exists($table, $coursefield)) {
                $dbman->rename_field($table, $coursefield, 'course_id');
            }

            $userfield = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
            if ($dbman->field_exists($table, $userfield)) {
                $dbman->rename_field($table, $userfield, 'user_id');
            }
        }

        upgrade_plugin_savepoint(true, 2026092100, 'local', 'realtime_tracker');
    }

    return true;
}
