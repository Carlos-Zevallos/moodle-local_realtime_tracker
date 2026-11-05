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
 * Report UI helpers for category filters, export and email.
 *
 * @module     local_realtime_tracker/reports
 * @copyright  2026 Carlos Zevallos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/config', 'core/str'], function(Config, Str) {

    /**
     * Populate the subcategory select from the chosen parent category.
     *
     * @param {Array} subcategories Subcategory objects with id, name and parent.
     * @param {Number|String} selectedId Currently selected subcategory id.
     */
    var initFilters = function(subcategories, selectedId) {
        var categorySelect = document.getElementById('categoryid');
        var subcategorySelect = document.getElementById('subcategoryid');
        if (!categorySelect || !subcategorySelect) {
            return;
        }

        var selectedSubcategoryId = String(selectedId || '0');

        var updateSubcategories = function() {
            var parentId = categorySelect.value;
            Str.get_string('all', 'local_realtime_tracker').then(function(allLabel) {
                subcategorySelect.innerHTML = '';
                var defaultOption = document.createElement('option');
                defaultOption.value = '0';
                defaultOption.textContent = allLabel;
                subcategorySelect.appendChild(defaultOption);

                if (parentId === '0') {
                    return;
                }

                subcategories.filter(function(sub) {
                    return String(sub.parent) === String(parentId);
                }).forEach(function(sub) {
                    var option = document.createElement('option');
                    option.value = sub.id;
                    option.textContent = sub.name;
                    if (String(sub.id) === selectedSubcategoryId) {
                        option.selected = true;
                    }
                    subcategorySelect.appendChild(option);
                });
            }).then(null, function() {
                // Keep the existing empty option if the string cannot be loaded.
            });
        };

        categorySelect.addEventListener('change', function() {
            selectedSubcategoryId = '0';
            updateSubcategories();
        });

        updateSubcategories();

        var printButton = document.querySelector('[data-action="print-report"]');
        if (printButton) {
            printButton.addEventListener('click', function() {
                window.print();
            });
        }
    };

    /**
     * Submit a hidden POST form.
     *
     * @param {String} action Form action URL.
     * @param {Object} params Form fields.
     */
    var submitForm = function(action, params) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        Object.keys(params).forEach(function(key) {
            var field = document.createElement('input');
            field.type = 'hidden';
            field.name = key;
            field.value = params[key];
            form.appendChild(field);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    };

    /**
     * Bind export and email actions for the resources report.
     *
     * @param {Number} categoryId Selected category.
     * @param {Number} subcategoryId Selected subcategory.
     * @param {String} year Year filter.
     * @param {String} semester Semester filter.
     */
    var initExport = function(categoryId, subcategoryId, year, semester) {
        var baseParams = {
            categoryid: categoryId,
            subcategoryid: subcategoryId,
            year: year,
            semester: semester,
            sesskey: Config.sesskey
        };

        document.querySelectorAll('[data-action="export-report"]').forEach(function(link) {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                var params = Object.assign({}, baseParams, {format: link.getAttribute('data-format')});
                submitForm('export_resources.php', params);
            });
        });

        var emailButton = document.querySelector('[data-action="email-report"]');
        if (emailButton) {
            emailButton.addEventListener('click', function() {
                Str.get_string('emailconfirm', 'local_realtime_tracker').then(function(message) {
                    if (window.confirm(message)) {
                        submitForm('email_reports.php', baseParams);
                    }
                }).then(null, function() {
                    // Do not send mail if the confirmation string cannot be loaded.
                });
            });
        }

        var emailZeroButton = document.querySelector('[data-action="email-zero-report"]');
        if (emailZeroButton) {
            emailZeroButton.addEventListener('click', function() {
                Str.get_string('emailconfirm', 'local_realtime_tracker').then(function(message) {
                    if (window.confirm(message)) {
                        var params = Object.assign({}, baseParams, {zerorec: 1});
                        submitForm('email_reports.php', params);
                    }
                }).then(null, function() {});
            });
        }
    };

    return {
        initFilters: initFilters,
        initExport: initExport
    };
});
