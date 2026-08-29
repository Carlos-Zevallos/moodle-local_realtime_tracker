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
 * Real-time activity tracker AMD module.
 *
 * @module     local_realtime_tracker/tracker
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    var Tracker = {
        courseId: 0,
        cmId: 0,
        actionType: 'view',
        sessionId: '',
        pingInterval: 30000,
        timer: null,
        lastActivityTime: Date.now(),
        inactivityTimeout: 60000,
        isActive: true,

        /**
         * Initialise tracking on the current course page.
         *
         * @param {Object} config Configuration from PHP.
         */
        init: function(config) {
            this.courseId = config.courseid;
            this.cmId = config.cmid || 0;
            this.actionType = config.actiontype || 'view';
            this.sessionId = config.sessionid;
            this.pingInterval = config.interval || 30000;
            this.bindEvents();
            this.startTracking();
        },

        /**
         * Bind activity and visibility listeners.
         */
        bindEvents: function() {
            var self = this;

            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    self.isActive = false;
                } else {
                    self.isActive = true;
                    self.lastActivityTime = Date.now();
                }
            });

            var resetActivity = function() {
                self.lastActivityTime = Date.now();
                if (!self.isActive && !document.hidden) {
                    self.isActive = true;
                }
            };

            document.addEventListener('mousemove', resetActivity);
            document.addEventListener('keydown', resetActivity);
            document.addEventListener('scroll', resetActivity);
            document.addEventListener('click', resetActivity);

            setInterval(function() {
                if (self.isActive && (Date.now() - self.lastActivityTime > self.inactivityTimeout)) {
                    self.isActive = false;
                }
            }, 5000);
        },

        /**
         * Start the heartbeat timer.
         */
        startTracking: function() {
            var self = this;
            if (this.timer !== null) {
                clearInterval(this.timer);
            }
            this.timer = setInterval(function() {
                self.sendPing();
            }, this.pingInterval);
        },

        /**
         * Send an active-time heartbeat.
         */
        sendPing: function() {
            if (!this.isActive) {
                return;
            }

            Ajax.call([{
                methodname: 'local_realtime_tracker_ping',
                args: {
                    courseid: this.courseId,
                    cmid: this.cmId,
                    actiontype: this.actionType,
                    sessionid: this.sessionId,
                    deltatime: this.pingInterval / 1000
                }
            }])[0].then(null, function() {
                // Ignore transient failures while the tab is closing.
            });
        }
    };

    return Tracker;
});
