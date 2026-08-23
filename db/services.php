<?php
/**
 * Services definition for the local realtime tracker.
 *
 * @package    local_realtime_tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [
    'local_realtime_tracker_ping' => [
        'classname' => 'local_realtime_tracker\external\ping',
        'methodname' => 'execute',
        'classpath' => 'local/realtime_tracker/classes/external/ping.php',
        'description' => 'Records active time spent by user on a course or activity page.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ]
];
