# LearnPlug Podcasts Wiki

## 1. Overview
`mod_learnplugpodcasts` is a Moodle activity that lets teachers publish a podcast channel inside a course, manage episodes, track listening progress, and optionally expose a public RSS/public page.

Key goals:
- Keep content authored in Moodle.
- Provide learner-friendly playback on web and mobile.
- Support completion and gradebook from listening behavior.

## 2. Roles and Capabilities

Main capabilities:
- `mod/learnplugpodcasts:addinstance`
- `mod/learnplugpodcasts:view`
- `mod/learnplugpodcasts:manageepisodes`
- `mod/learnplugpodcasts:publish`
- `mod/learnplugpodcasts:viewreports`
- `mod/learnplugpodcasts:viewpublic`
- `mod/learnplugpodcasts:downloadmedia`
- `mod/learnplugpodcasts:grade`

Typical mapping:
- Teachers/editing teachers: create/manage/publish/report.
- Students: view/listen, progress tracking, like episodes (if enabled in UI).

## 3. Admin Setup

Go to:
`Site administration > Plugins > Activity modules > LearnPlug Podcasts`

Recommended checks:
1. Set allowed audio MIME types (include `audio/mpeg`, `audio/mp4`, `audio/wav` aliases as needed).
2. Configure default episodes per page.
3. Enable/disable public pages and public RSS globally.
4. Review transcript/attachments settings.
5. Confirm cron is active for scheduled maintenance tasks.

## 4. Teacher Workflow

### 4.1 Create a Podcast Activity
1. Turn editing on in a course.
2. Add activity: **LearnPlug Podcasts**.
3. Fill metadata:
   - Podcast name, subtitle, author, description
   - Language/category/explicit flag
   - Cover image
4. Set display options, completion rules, and gradebook behavior.
5. Save.

### 4.2 Manage Episodes
Inside the activity:
1. Add episode.
2. Fill title/subtitle/description.
3. Upload audio (required to play and track duration/progress).
4. Optionally upload:
   - Episode image
   - Transcript file/text
   - Caption tracks (`.vtt`)
   - Attachments
5. Publish/unpublish.
6. Reorder episodes.

## 5. Learner Experience

Learners can:
- Browse episode list.
- Search and sort episodes.
- Play audio and resume from saved position.
- See progress and completion status.
- Select available caption tracks.
- Open transcripts (if enabled and provided).
- Like/unlike episodes (web/mobile UI where available).

## 6. Progress Tracking, Completion, and Grading

### 6.1 Progress Data
Per learner and per episode:
- Last playback position
- Listened seconds
- Listened percent
- Completion flag

### 6.2 Completion Modes
Supported:
- Activity viewed
- Playback started
- Listen at least X% of one episode
- Listen at least N episodes

### 6.3 Gradebook
When gradebook integration is enabled:
- Plugin updates grade item (0-100).
- Grade derives from listening progress logic.

## 7. Captions and Transcripts

### 7.1 Caption Files (`.vtt`)
- Upload multiple language tracks per episode.
- Learners select caption language from player controls.
- Mobile renders selected caption text in sync under player.

Recommended naming:
- `episode-en.vtt`
- `episode-pt.vtt`
- `episode-fr.vtt`

### 7.2 Transcript
- Provide transcript text or transcript file.
- Learner can view transcript section and download transcript if available.

## 8. Public RSS and Public Pages

If enabled by admin + activity settings:
- Public podcast page:
  - `/mod/learnplugpodcasts/public.php?id={cmid}`
- Public RSS:
  - `/mod/learnplugpodcasts/rss.php?id={cmid}`
- Public episode page:
  - `/mod/learnplugpodcasts/episode.php?episode={episodeid}`

Use RSS URL for manual submission to podcast directories.

## 9. Moodle Mobile App

Mobile support includes:
- Podcast and episode listing
- Episode accordion view
- Playback and progress sync
- Offline file handling for episode audio
- Captions selector and synced caption text box
- Like/unlike episode action

If mobile UI behaves unexpectedly:
1. Upgrade plugin to latest build.
2. Purge Moodle caches.
3. In mobile app, log out/in or clear app cache.

## 10. Reports and Analytics

Teacher/reporting users can review:
- Overall listening analytics
- Per-episode metrics
- Episode like counts

Use these metrics to:
- Identify high/low engagement episodes
- Tune episode length/content strategy
- Validate completion rule effectiveness

## 11. Troubleshooting

### 11.1 Audio not playable
- Verify MIME type allowed by site settings.
- Re-upload file if source is corrupted.
- Check pluginfile access/capability permissions.

### 11.2 Episode duration incorrect
- Re-save episode audio.
- Ensure source file has valid metadata or WAV headers.

### 11.3 Mobile like/unlike errors
- Ensure latest plugin version is installed.
- Ensure webservice functions are present and enabled:
  - `mod_learnplugpodcasts_save_progress`
  - `mod_learnplugpodcasts_toggle_like`

### 11.4 Captions not showing
- Verify uploaded `.vtt` is valid WebVTT.
- Confirm captions are selected in player.
- Confirm track is attached to the same episode.

## 12. Privacy and Data Handling

The plugin stores listening progress and likes per user.
Privacy API support includes:
- Metadata declaration
- User data export
- User data deletion

Always review your institution policy for retention and legal notices.

## 13. Release and Maintenance

Current stable baseline:
- Release: `1.0.0 Stable`
- Build version: `2026050510`

Before production updates:
1. Test on staging.
2. Run Moodle upgrade.
3. Purge caches.
4. Validate web + mobile flows.
