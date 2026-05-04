<?php
// This file is part of Moodle - http://moodle.org/.
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
 * English language strings for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessmode_public'] = 'Truly public URLs';
$string['accessmode_token'] = 'Tokenized public URLs';
$string['addepisode'] = 'Add episode';
$string['allowedaudiomimetypes'] = 'Allowed audio MIME types';
$string['allowedaudiomimetypes_desc'] = 'Comma-separated MIME types used for episode upload validation.';
$string['allowepisodeattachments'] = 'Allow episode attachments';
$string['allowepisodeattachments_desc'] = 'Allow teachers to upload downloadable attachments per episode.';
$string['allowpublictokenmode'] = 'Require tokenized access for public URLs';
$string['allowpublictokenmode_desc'] = 'If enabled, public page, episode, RSS, and media links require a generated token.';
$string['allowtranscripts'] = 'Allow transcripts';
$string['allowtranscripts_desc'] = 'Allow teachers to upload transcript files or transcript text.';
$string['analytics_avglistened'] = 'Avg listened';
$string['analytics_completionrate'] = 'Completion rate';
$string['analytics_duration'] = 'Duration';
$string['analytics_engagement'] = 'Engagement';
$string['analytics_episode'] = 'Episode';
$string['analytics_lastactivity'] = 'Last activity';
$string['analytics_listeners'] = 'Listeners';
$string['analytics_status'] = 'Status';
$string['analytics_totallistened'] = 'Total listened';
$string['analyticscard_activityengagement'] = 'Activity engagement';
$string['analyticscard_activityengagement_sub'] = 'Learners with tracked listening progress.';
$string['analyticscard_avglistened'] = 'Average listened';
$string['analyticscard_avglistened_sub'] = 'Average listened percent across learner-episode pairs.';
$string['analyticscard_completionrate'] = 'Completion rate';
$string['analyticscard_completionrate_sub'] = 'Rows marked completed in listening progress.';
$string['analyticscard_enrolledlearners'] = 'Enrolled learners';
$string['analyticscard_enrolledlearners_sub'] = 'Users with view permission in this activity.';
$string['analyticscard_episodecoverage'] = 'Published episodes';
$string['analyticscard_episodecoverage_sub'] = 'Published episode count out of total episodes.';
$string['analyticscard_totallisteningtime'] = 'Total listening time';
$string['analyticscard_totallisteningtime_sub'] = 'Cumulative listened time across all learners.';
$string['analyticssubtitle'] = 'Overall activity performance and per-episode listening analytics.';
$string['analyticstitle'] = 'Listening analytics';
$string['attachments'] = 'Attachments';
$string['audiofile'] = 'Audio file';
$string['authorname'] = 'Author name';
$string['brandingsupportemail'] = 'Branding support email';
$string['brandingsupportemail_desc'] = 'Optional support contact shown in teacher help text.';
$string['cap:addinstance'] = 'Add LearnPlug Podcasts activity';
$string['cap:downloadmedia'] = 'Download LearnPlug Podcasts media';
$string['cap:grade'] = 'Grade LearnPlug Podcasts activity';
$string['cap:manageepisodes'] = 'Manage LearnPlug Podcasts episodes';
$string['cap:publish'] = 'Publish LearnPlug Podcasts episodes';
$string['cap:view'] = 'View LearnPlug Podcasts activity';
$string['cap:viewpublic'] = 'View public LearnPlug Podcasts pages';
$string['cap:viewreports'] = 'View LearnPlug Podcasts reports';
$string['learnplugpodcasts:addinstance'] = 'Add LearnPlug Podcasts activity';
$string['captionfiles'] = 'Caption tracks (.vtt)';
$string['captionfiles_help'] =
    'Automatic language detection is based on the caption filename. '
    . 'Upload one .vtt file per language using names like episode.en.vtt, '
    . 'episode.pt.vtt, or episode.fr-ca.vtt.';
$string['captionlang_unknown'] = 'Unknown language';
$string['captionnamingguide'] =
    'Language is detected automatically from the filename suffix. '
    . 'Use <code>.en.vtt</code>, <code>.pt.vtt</code>, <code>.fr-ca.vtt</code>, etc.';
$string['captiontrackoff'] = 'Off';
$string['captiontrackselect'] = 'Captions';
$string['category'] = 'Category';
$string['completionepisodecount'] = 'Completion required episode count';
$string['completionlistenmode'] = 'Completion by listening';
$string['completionlistenmode_channelrecommended'] =
    'Channel completion: at least N episodes listened at X% each (recommended)';
$string['completionlistenmode_episodecount'] = 'Listen to at least N episodes';
$string['completionlistenmode_none'] = 'No listening completion rule';
$string['completionlistenmode_percent'] = 'Listen to at least X% of one episode';
$string['completionlistenmode_started'] = 'Any playback started';
$string['completionlistenpercent'] = 'Completion listen percent';
$string['completionrule_channelrecommended'] =
    'Listen to at least {$a->count} episodes with at least {$a->percent}% listened on each.';
$string['completionstatus'] = 'Completion status';
$string['completionstatus_complete'] = 'Completed';
$string['completionstatus_incomplete'] = 'Not completed';
$string['copyrightnotice'] = 'Copyright notice';
$string['copyurl'] = 'Copy URL';
$string['coverimage'] = 'Cover image';
$string['defaultcompletionepisodecount'] = 'Default completion episode count';
$string['defaultcompletionepisodecount_desc'] = 'Recommended: 3. Number of episodes required for channel completion mode.';
$string['defaultcompletionmode'] = 'Default completion mode';
$string['defaultcompletionmode_desc'] =
    'Default listening completion mode for new activities. Recommended: channel completion mode.';
$string['defaultcompletionpercent'] = 'Default completion percent';
$string['defaultcompletionpercent_desc'] =
    'Recommended: 70. Minimum listen percent per episode for completion rules that use percent.';
$string['defaultepisodesperpage'] = 'Default episodes per page';
$string['defaultepisodesperpage_desc'] = 'Default pagination size when creating a new activity.';
$string['defaultnotifynewepisodes'] = 'Default new episode notifications';
$string['defaultnotifynewepisodes_desc'] = 'Default value for "Notify learners about new episodes" in new podcast activities.';
$string['defaultpublicaccessmode'] = 'Default public access mode';
$string['defaultpublicaccessmode_desc'] = 'Default mode for newly created activities.';
$string['defaultsort'] = 'Default sort';
$string['deleteepisode'] = 'Delete episode';
$string['deleteepisodeconfirm'] = 'Are you sure you want to delete episode "{$a}"?';
$string['description'] = 'Description';
$string['downloadallepisodes'] = 'Download all episodes';
$string['downloadattachment'] = 'Download attachment';
$string['downloadthisepisode'] = 'Download this episode';
$string['downloadtranscript'] = 'Download transcript';
$string['draftlabel'] = 'Draft';
$string['draftstatus'] = 'Publication status';
$string['durationsecs'] = 'Duration (seconds)';
$string['editepisode'] = 'Edit episode';
$string['email'] = 'Contact email';
$string['emptypublicdescription'] = 'No public description is available for this podcast yet.';
$string['enableepisodenotifications'] = 'Enable episode notifications';
$string['enableepisodenotifications_desc'] =
    'Allow podcast activities to notify enrolled learners when new episodes are published.';
$string['enablepublicpages'] = 'Enable public podcast pages';
$string['enablepublicpages_desc'] = 'Allow plugin instances to expose public landing and episode pages.';
$string['enablepublicrss'] = 'Enable public RSS feeds';
$string['enablepublicrss_desc'] = 'Allow plugin instances to expose a public RSS feed endpoint.';
$string['episode'] = 'Episode';
$string['episodeimage'] = 'Episode image';
$string['episodelistempty'] = 'No episodes yet.';
$string['episodemanagement'] = 'Episode management';
$string['episodenumber'] = 'Episode number';
$string['episodes'] = 'Episodes';
$string['episodesperpage'] = 'Episodes per page';
$string['episodesubtitle'] = 'Episode subtitle';
$string['episodetitle'] = 'Episode title';
$string['errorcapability'] = 'You do not have permission to perform this action.';
$string['errornoepisode'] = 'Episode not found.';
$string['errornopodcast'] = 'Podcast series not found.';
$string['errorvalidation'] = 'Please review the highlighted fields.';
$string['event:episodecreated'] = 'Episode created';
$string['event:episodedeleted'] = 'Episode deleted';
$string['event:episodepublished'] = 'Episode published/unpublished';
$string['event:episodeupdated'] = 'Episode updated';
$string['event:progressupdated'] = 'Listening progress updated';
$string['event:viewed'] = 'Podcast activity viewed';
$string['explicitflag'] = 'Contains explicit content';
$string['externalepisodelink'] = 'Open external episode link';
$string['externalurl'] = 'External episode URL (optional)';
$string['feedtitle'] = '{$a} podcast feed';
$string['filterall'] = 'All';
$string['filteraudiofile'] = 'Audio file';
$string['filterexternallink'] = 'External link';
$string['filtermedia'] = 'Media source';
$string['filterseason'] = 'Season';
$string['filterseasonplaceholder'] = 'Any season';
$string['filterstatus'] = 'Status';
$string['filtertranscript'] = 'Transcript';
$string['filterwithouttranscript'] = 'Without transcript';
$string['filterwithtranscript'] = 'With transcript';
$string['gradebookdisabled'] = 'Gradebook integration is disabled for this activity.';
$string['gradeenabled'] = 'Enable gradebook integration';
$string['gradeheader'] = 'Grading';
$string['invalidcaptionfile'] = 'Only .vtt caption files are allowed: {$a}';
$string['invalidmimetype'] = 'Unsupported audio MIME type: {$a}';
$string['invalidpublictoken'] = 'Invalid public access token.';
$string['languagecode'] = 'Language';
$string['learnerlistempty'] = 'No published episodes are currently available.';
$string['learnplugpodcastsfieldset'] = 'Podcast details';
$string['learnplugpodcastsname'] = 'Podcast series name';
$string['listenedprogress'] = 'Listened: {$a}%';
$string['manageepisodeshelp'] =
    'Create episodes, upload media, publish content, and maintain ordering for learners and public feed delivery.';
$string['managetitle'] = 'Manage episodes';
$string['maxuploadnote'] = 'Max episode upload size note';
$string['maxuploadnote_desc'] = 'Optional guidance displayed to teachers about recommended upload limits.';
$string['messageprovider:newepisode'] = 'New podcast episode notification';
$string['modulename'] = 'LearnPlug Podcasts';
$string['modulenameplural'] = 'LearnPlug Podcasts';
$string['movedown'] = 'Move down';
$string['moveup'] = 'Move up';
$string['noprogress'] = 'No listening progress available yet.';
$string['notfoundepisode'] = 'Episode not found.';
$string['notificationnewepisodebody'] = 'A new episode "{$a->episode}" was published in "{$a->podcast}".';
$string['notificationnewepisodesubject'] = 'New podcast episode: {$a->episode}';
$string['notifynewepisodes'] = 'Notify learners about new episodes';
$string['offlineavailable'] = 'Available offline';
$string['offlinelisteninghint'] = 'Offline listening: download episodes for commuting or limited data access.';
$string['pluginadministration'] = 'LearnPlug Podcasts administration';
$string['pluginname'] = 'LearnPlug Podcasts';
$string['privacy:metadata'] =
    'The LearnPlug Podcasts activity stores learner listening progress and teacher-authored podcast data.';
$string['privacy:metadata:coremessage'] = 'The plugin can send new-episode notifications using Moodle messaging.';
$string['privacy:metadata:learnplugpodcasts'] = 'Stores podcast activity settings including ownership metadata.';
$string['privacy:metadata:learnplugpodcasts:owneruserid'] =
    'The user ID of the teacher recorded as podcast owner.';
$string['privacy:metadata:learnplugpodcasts_prog'] = 'Stores user listening progress for podcast episodes.';
$string['privacy:metadata:learnplugpodcasts_prog:completed'] = 'Whether completion threshold for this episode was met.';
$string['privacy:metadata:learnplugpodcasts_prog:episodeid'] = 'The episode ID.';
$string['privacy:metadata:learnplugpodcasts_prog:lastplaystate'] = 'Last known playback state.';
$string['privacy:metadata:learnplugpodcasts_prog:lastpositionsecs'] = 'Last known playback position in seconds.';
$string['privacy:metadata:learnplugpodcasts_prog:listenedpercent'] = 'Calculated listened percent for this episode.';
$string['privacy:metadata:learnplugpodcasts_prog:listenedsecs'] = 'Cumulative listened seconds counted by anti-abuse logic.';
$string['privacy:metadata:learnplugpodcasts_prog:podcastid'] = 'The podcast series ID.';
$string['privacy:metadata:learnplugpodcasts_prog:timecreated'] = 'Time this progress record was first created.';
$string['privacy:metadata:learnplugpodcasts_prog:timemodified'] = 'Time this progress record was last updated.';
$string['privacy:metadata:learnplugpodcasts_prog:userid'] = 'The user ID of the learner.';
$string['publicbasepath'] = 'Public base path behavior';
$string['publicbasepath_desc'] = 'Display-only setting for documentation of public URL style.';
$string['publicdisabled'] = 'Public access for this podcast is disabled.';
$string['publicenabled'] = 'Enable public page for this podcast';
$string['publicslug'] = 'Public slug';
$string['publicurl'] = 'Public podcast URL';
$string['publishedlabel'] = 'Published';
$string['publishepisode'] = 'Publish episode';
$string['publishing'] = 'Publishing';
$string['publishtime'] = 'Publish date/time';
$string['reorderepisodes'] = 'Reorder episodes';
$string['resumeat'] = 'Resume at {$a}';
$string['rssdisabled'] = 'RSS feed is disabled for this podcast.';
$string['rssdistributionnote'] = 'Submit this RSS URL manually to Apple Podcasts, Spotify, and other directories.';
$string['rssenabled'] = 'Enable public RSS feed';
$string['rssurl'] = 'RSS feed URL';
$string['saveepisode'] = 'Save episode';
$string['searchplaceholder'] = 'Search episodes';
$string['seasonnumber'] = 'Season number';
$string['seriesmetadata'] = 'Series metadata';
$string['settingsheading'] = 'LearnPlug Podcasts settings';
$string['showdescriptions'] = 'Show episode descriptions';
$string['showsearch'] = 'Show search';
$string['showsubscribe'] = 'Show subscribe/share block';
$string['showtranscripts'] = 'Show transcripts';
$string['sort_durationlong'] = 'Longest first';
$string['sort_durationshort'] = 'Shortest first';
$string['sort_newest'] = 'Newest first';
$string['sort_oldest'] = 'Oldest first';
$string['sort_titleaz'] = 'Title A-Z';
$string['sort_titleza'] = 'Title Z-A';
$string['sortlabel'] = 'Sort episodes';
$string['status_draft'] = 'Draft';
$string['status_published'] = 'Published';
$string['status_unpublished'] = 'Unpublished';
$string['subtitle'] = 'Subtitle';
$string['supportcontact'] = 'Support contact: {$a}';
$string['task_cleanup_temp_files'] = 'Cleanup temporary LearnPlug Podcasts records';
$string['task_refresh_metadata'] = 'Refresh podcast episode metadata';
$string['title'] = 'Title';
$string['transcriptfile'] = 'Transcript file';
$string['transcripttext'] = 'Transcript text';
$string['unpublishedlabel'] = 'Unpublished';
$string['unpublishepisode'] = 'Unpublish episode';
$string['viewreports'] = 'Listening report';
$string['websiteurl'] = 'Website URL';
