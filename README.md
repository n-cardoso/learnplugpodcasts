# LearnPlug Podcasts (mod_learnplugpodcasts)

LearnPlug Podcasts is a Moodle activity module for teacher-authored podcast publishing inside a course, with optional public distribution via RSS and public landing pages.

## What this plugin does

- Creates one podcast series per activity instance.
- Lets teachers create, edit, publish, unpublish, delete, and reorder episodes.
- Supports episode audio, episode artwork, transcripts, and optional attachments.
- Supports timed caption tracks (`.vtt`) and subtitle rendering over the episode image.
- Provides learner-friendly in-course playback with resume and progress tracking.
- Supports optional learner notifications when new episodes are published.
- Supports completion rules based on playback and listening thresholds.
- Optionally pushes deterministic grades (0-100) to the gradebook.
- Generates public podcast RSS feeds suitable for manual submission to podcast directories.
- Optionally exposes public podcast and episode pages.
- Includes native Moodle App support with episode playback and offline download actions.
- Includes teacher analytics reports with overall activity KPIs and per-episode listening performance.

## Platform and compatibility

- Moodle `4.5+`
- PHP `8.1+`
- MariaDB/MySQL compatible

## Installation

1. Copy folder `learnplugpodcasts` into `mod/`.
2. Visit **Site administration > Notifications**.
3. Complete database upgrade and capability installation.
4. Configure plugin defaults in **Site administration > Plugins > Activity modules > LearnPlug Podcasts**.

## Teacher workflow

1. Add **LearnPlug Podcasts** activity to a course.
2. Fill series metadata (title, subtitle, author, language, category, cover image).
3. Configure display, completion, and optional gradebook behavior.
4. Add episodes from the activity page:
   - Title/subtitle
   - Description/show notes
   - Publish date/time
   - Season/Episode number
   - Audio file
   - Optional artwork, transcript, attachments
   - Draft/published state
5. Publish episodes when ready.
6. Optional timed captions workflow:
   - Upload one or more `.vtt` files per episode.
   - Select caption track in the learner player.
7. Optional notifications:
   - Admin can enable/disable notifications globally in plugin settings.
   - Teacher can enable/disable **Notify learners about new episodes** per activity.

## Public distribution workflow (Apple/Spotify/etc.)

1. Enable public pages and RSS at site level.
2. Enable public access and RSS on the activity.
3. Copy the RSS URL from the activity page.
4. Submit this RSS URL manually in the external podcast platform.

This plugin does not push directly to Apple/Spotify APIs in v1.

## New episode notifications

- Provider: `mod_learnplugpodcasts:newepisode`.
- Trigger: when an episode transitions to **published**.
- Delivery channels depend on Moodle messaging settings and user preferences (web, email, mobile push).
- If Moodle App push is configured on your site, learners receive push notifications on mobile devices.

## Completion and grading behavior

Supported completion rules:

- Completion on view (core Moodle option)
- Any playback started
- Listen to at least X% of one episode
- Listen to at least N completed episodes

Gradebook integration:

- Optional, activity-level toggle.
- Grade range is `0-100`.
- Grade is computed deterministically from configured completion/listening rule.

## Capabilities

- `mod/learnplugpodcasts:addinstance`
- `mod/learnplugpodcasts:view`
- `mod/learnplugpodcasts:manageepisodes`
- `mod/learnplugpodcasts:publish`
- `mod/learnplugpodcasts:viewreports`
- `mod/learnplugpodcasts:viewpublic`
- `mod/learnplugpodcasts:downloadmedia`
- `mod/learnplugpodcasts:grade`

## Privacy notes

The plugin stores learner listening progress:

- Playback position
- Accumulated listened seconds
- Listened percent
- Completion state
- Last playback state

Progress is exportable and deletable through Moodle privacy APIs.

## New CSV Export Screen

- Teachers and administrators can now choose focused CSV reports or download a normalized export containing all analytics data.

## Developer notes

- Uses Moodle File API for all media and assets.
- Uses Mustache templates and AMD modules for frontend behavior.
- Provides external AJAX functions for search/progress/publish/reorder.
- Includes scheduled tasks for metadata refresh and log cleanup.
