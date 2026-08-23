/**
 * Real-time tracker module for Moodle (Global Context).
 *
 * @module     local_realtime_tracker/tracker
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {

    var Tracker = {
        courseId: 0,
        userId: 0,
        cmId: 0,
        actionType: 'view',
        sessionId: '',
        pingInterval: 30000,
        timer: null,
        lastActivityTime: Date.now(),
        inactivityTimeout: 60000,
        isActive: true,

        init: function(config) {
            this.courseId = config.courseid;
            this.userId = config.userid;
            this.cmId = config.cmid || 0;
            this.actionType = config.actiontype || 'view';
            this.sessionId = config.sessionid;
            this.pingInterval = config.interval || 30000;
            
            this.bindEvents();
            this.startTracking();
        },

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

            $(document).on('mousemove keydown scroll click', resetActivity);
            
            setInterval(function() {
                if (self.isActive && (Date.now() - self.lastActivityTime > self.inactivityTimeout)) {
                    self.isActive = false;
                }
            }, 5000);
        },

        startTracking: function() {
            var self = this;
            if (this.timer !== null) {
                clearInterval(this.timer);
            }
            
            this.timer = setInterval(function() {
                self.sendPing();
            }, this.pingInterval);
        },

        sendPing: function() {
            if (!this.isActive) {
                return;
            }
            
            var request = {
                methodname: 'local_realtime_tracker_ping',
                args: {
                    courseid: this.courseId,
                    cmid: this.cmId,
                    actiontype: this.actionType,
                    sessionid: this.sessionId,
                    deltatime: this.pingInterval / 1000
                }
            };

            ajax.call([request])[0].done(function(response) {
                // Success
            }).fail(function(ex) {
                // Ignore silent failures if tab is just closing, otherwise log
                console.log(ex);
            });
        }
    };

    return Tracker;
});
