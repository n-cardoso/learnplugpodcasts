# LearnPlug Podcasts Feature Report

Version: `1.0.3 Stable`  
Build: `2026060604`

## Summary

LearnPlug Podcasts is a Moodle activity module for publishing structured podcast channels inside a course, tracking learner listening behaviour, supporting captions and transcripts, and exposing teacher-facing listening analytics.

This build adds stronger listening analytics, better teacher reset controls, and reporting fixes around gradebook visibility and user-name rendering.

## Core activity capabilities

- Create podcast-channel style learning activities inside Moodle courses.
- Add, edit, publish, unpublish, reorder, and delete episodes.
- Upload episode audio, episode images, transcripts, caption tracks (`.vtt`), and attachments.
- Support external episode links.
- Provide public podcast pages and public RSS feeds when enabled.
- Support backup and restore between Moodle installations.

## Learner experience

- Stream podcast episodes directly inside the activity.
- Track listening progress and resume playback position.
- View transcripts inside the activity.
- Select available caption languages from uploaded `.vtt` files.
- Like episodes.
- Use the activity from the Moodle Mobile App and in the browser.
- Download episodes for offline access where available.

## Teacher and admin experience

- Manage episodes from inside the activity.
- Review listening analytics for the whole podcast activity.
- View learner listening progress, listening duration, and last access.
- Review gradebook-linked grading values in the listening report.
- Reset listening analytics and learner progress:
  - per learner
  - for all learners in the podcast

## New and improved in this build

### 1. Listening heatmap analytics

The plugin now stores listening activity in time buckets so teachers can understand which parts of an episode are most replayed or most listened.

New analytics include:

- **Most listened zones per episode**
- **Strongest listened zone per learner and episode**

This allows teachers to identify:

- the moments learners revisit most
- the sections that attract the most attention
- learner-level listening hotspots

### 2. Teacher reset controls for analytics/progress

Teachers and admins can now reset listening data without going directly to the database.

Available reset actions:

- **Reset all learner progress** for the current podcast
- **Reset progress** for an individual learner

These actions clear:

- learner listening progress
- listening heatmap analytics

These actions do **not** clear learner likes.

### 3. Grade report fixes

The listening analytics report was improved so that:

- Moodle user names render correctly using the full Moodle name fields
- gradebook-linked values are read more safely from Moodle grade data
- the report no longer depends only on one grade property when Moodle exposes a different numeric field

### 4. Reset workflow fixes

The progress reset workflow now clears gradebook values using the correct Moodle grade array structure, avoiding grade update warnings during reset.

### 5. Course reset support improvement

Course/activity reset now also clears the newer listening heatmap data, not only standard progress rows.

## Analytics and reporting coverage

Current reporting areas include:

- enrolled learners
- activity engagement
- average listened percent
- completion rate
- total listening time
- published episode coverage
- total likes
- per-episode analytics
- most listened zones
- learner hotspots

## Privacy and data handling

The plugin includes Privacy API support for learner listening data, including:

- listening progress rows
- likes
- listening heatmap bucket data

The new heatmap analytics table is included in privacy metadata and deletion/export handling.

## Backup and restore

The plugin supports Moodle backup and restore, including the newer listening analytics data structures introduced in this build.

## Mobile support

The activity is designed to work in:

- Moodle web browser experience
- Moodle Mobile App

Mobile support includes:

- playback
- captions selection
- likes
- progress synchronisation
- offline episode access where supported

## Recommended release note text

This update improves teacher reporting and learner listening analytics in LearnPlug Podcasts. It adds listening heatmap analytics, including the most listened zones per episode and learner hotspots, plus new teacher/admin controls to reset learner progress either per user or across the whole podcast. It also includes fixes for gradebook visibility in the listening report, user-name rendering in analytics, and reset workflow stability.
