# Mauri Real-Time Tracker

**Mauri Real-Time Tracker** (`local_realtime_tracker`) measures active time that students and teachers spend in Moodle courses and activities. It records connection time, content-creation time, and AI-assisted work, then presents course and institutional reports that can be exported or emailed.

This README is the English Marketplace description for the plugin.

## Features

- Real-time activity tracking with an inactivity timeout so idle tabs are not counted
- Course reports for students and teachers, including first/last access and active time
- Activity-level drill-down: browsing time, creating/editing time, and AI time
- Institutional dashboards for teacher performance and resource usage
- PDF, Word, Excel, and XML exports
- Optional email of personalised resource reports to teachers
- Privacy API support, plus course backup and restore of tracking data

## Requirements

- Moodle 4.0 or later
- JavaScript enabled in the browser (tracking uses an AMD module)

## Installing via uploaded ZIP file

1. Log in to your Moodle site as an administrator and go to `Site administration > Plugins > Install plugins`.
2. Upload the ZIP file that contains this plugin. Moodle should detect the `local` plugin type automatically.
3. Review the validation report and finish the installation.

## Installing manually

Copy the contents of this directory to:

```
{your/moodle/dirroot}/local/realtime_tracker
```

Then log in as an administrator and go to `Site administration > Notifications` to complete the installation.

Alternatively, run:

```bash
php admin/cli/upgrade.php
```

## Configuration

Go to `Site administration > Plugins > Local plugins > Mauri Tracker Settings` to set:

- **Centre CIF** – identifier printed on PDF reports
- **Centre name** – training centre name printed on reports
- **Default modality** – delivery mode shown on reports
- **AI plugin keywords** – comma-separated URL fragments used to detect AI-related pages

Institutional reports are available under `Site administration > Reports`.

Course reports appear in the course administration menu for users with the `local/realtime_tracker:viewreports` capability (teachers, editing teachers, and managers by default).

## How tracking works

While a logged-in user is active in a course page, the plugin sends a periodic heartbeat. Time is stored per user, course, and activity, and is summarised for reporting. Guests and the site home page are not tracked.

## Privacy

The plugin stores user IDs together with course and activity time totals. Data can be exported or deleted through Moodle's Privacy API, and course-scoped records are included in course backup and restore when user data is selected.

## License

2026 Carlos Zevallos

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

See [LICENSE.md](LICENSE.md) for details.
