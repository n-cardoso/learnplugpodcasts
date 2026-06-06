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
 * Main activity view page for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_learnplugpodcasts\event\course_module_viewed;
use mod_learnplugpodcasts\local\form\episode_form;
use mod_learnplugpodcasts\local\output\podcast_view;
use mod_learnplugpodcasts\local\repository\episode_repository;
use mod_learnplugpodcasts\local\service\analytics_service;
use mod_learnplugpodcasts\local\service\caption_service;
use mod_learnplugpodcasts\local\service\episode_service;
use mod_learnplugpodcasts\local\service\like_service;
use mod_learnplugpodcasts\local\service\public_access_service;
use mod_learnplugpodcasts\local\service\transcript_service;
use mod_learnplugpodcasts\local\util\mime;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$episodeid = optional_param('episodeid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$sort = optional_param('sort', '', PARAM_ALPHA);
$search = optional_param('q', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('learnplugpodcasts', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/learnplugpodcasts:view', $context);

$PAGE->set_url('/mod/learnplugpodcasts/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($podcast->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($podcast);
$PAGE->set_cm($cm, $course);
$PAGE->set_pagelayout('incourse');
if (isset($PAGE->activityheader) && method_exists($PAGE->activityheader, 'set_description')) {
    // Keep podcast description only in our custom player header to avoid duplicate intro blocks.
    $PAGE->activityheader->set_description('');
}

$event = course_module_viewed::create([
    'objectid' => $podcast->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('learnplugpodcasts', $podcast);
$event->trigger();

$episodeservice = new episode_service();
$analyticsservice = new analytics_service();
$transcriptservice = new transcript_service();
$captionservice = new caption_service();
$likeservice = new like_service();
$publicaccess = new public_access_service();
$episoderepo = new episode_repository();

$canmanage = has_capability('mod/learnplugpodcasts:manageepisodes', $context);
$canviewreports = has_capability('mod/learnplugpodcasts:viewreports', $context);
$canpublish = has_capability('mod/learnplugpodcasts:publish', $context);

$sort = in_array($sort, ['newest', 'oldest'], true) ? $sort : ($podcast->defaultsort ?: 'newest');
$page = max(0, $page);

$baseurlparams = ['id' => $cm->id, 'sort' => $sort, 'q' => $search, 'page' => $page];
$returnurl = new moodle_url('/mod/learnplugpodcasts/view.php', $baseurlparams);

$form = null;
if ($canmanage && in_array($action, ['add', 'edit'], true)) {
    $formurl = new moodle_url('/mod/learnplugpodcasts/view.php', [
        'id' => $cm->id,
        'action' => $action,
        'episodeid' => $episodeid,
    ]);

    $form = new episode_form($formurl, ['action' => $action, 'episodeid' => $episodeid]);

    if ($action === 'edit') {
        $episode = $episodeservice->get_by_id($episodeid);
        if (!$episode || (int)$episode->podcastid !== (int)$podcast->id) {
            throw new moodle_exception('errornoepisode', 'learnplugpodcasts');
        }
        $formdata = $episodeservice->prepare_form_data($context, $episode);
        $formdata->id = $cm->id;
        $formdata->episodeid = $episode->id;
        $formdata->action = 'edit';
        $form->set_data($formdata);
    } else {
        $defaults = (object)[
            'id' => $cm->id,
            'episodeid' => 0,
            'action' => 'add',
            'publishtime' => time(),
            'draftstatus' => episode_service::STATUS_DRAFT,
            'audiofile' => file_get_submitted_draft_itemid('audiofile'),
            'episodeimage' => file_get_submitted_draft_itemid('episodeimage'),
            'transcriptfile' => file_get_submitted_draft_itemid('transcriptfile'),
            'episodecaption' => file_get_submitted_draft_itemid('episodecaption'),
            'attachments' => file_get_submitted_draft_itemid('attachments'),
        ];
        $form->set_data($defaults);
    }

    if ($form->is_cancelled()) {
        redirect($returnurl);
    }

    if ($data = $form->get_data()) {
        require_sesskey();
        if ($data->action === 'edit' && !empty($data->episodeid)) {
            $episode = $episodeservice->get_by_id((int)$data->episodeid);
            if (!$episode || (int)$episode->podcastid !== (int)$podcast->id) {
                throw new moodle_exception('errornoepisode', 'learnplugpodcasts');
            }
            $episodeservice->update_episode($podcast, $context, $episode, $data);
        } else {
            $episodeservice->create_episode($podcast, $context, $data);
        }
        redirect($returnurl);
    }
}

if ($canmanage && $action === 'delete' && $episodeid) {
    $episode = $episodeservice->get_by_id($episodeid);
    if (!$episode || (int)$episode->podcastid !== (int)$podcast->id) {
        throw new moodle_exception('errornoepisode', 'learnplugpodcasts');
    }

    if (!$confirm) {
        $continue = new moodle_url('/mod/learnplugpodcasts/view.php', [
            'id' => $cm->id,
            'action' => 'delete',
            'episodeid' => $episodeid,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('deleteepisodeconfirm', 'learnplugpodcasts', format_string($episode->title)),
            $continue,
            $returnurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    require_sesskey();
    $episodeservice->delete_episode($podcast, $context, $episode);
    redirect($returnurl);
}

if ($canmanage && $canpublish && in_array($action, ['publish', 'unpublish'], true) && $episodeid) {
    require_sesskey();
    $episode = $episodeservice->get_by_id($episodeid);
    if ($episode && (int)$episode->podcastid === (int)$podcast->id) {
        $episodeservice->set_published($podcast, $context, $episode, $action === 'publish');
    }
    redirect($returnurl);
}


if ($canmanage && in_array($action, ['moveup', 'movedown'], true) && $episodeid) {
    require_sesskey();

    $episodes = $episoderepo->get_by_podcast((int)$podcast->id, false, 'manual');
    $order = array_values(array_map(static fn($e) => (int)$e->id, $episodes));
    $index = array_search($episodeid, $order, true);
    if ($index !== false) {
        $swapwith = $action === 'moveup' ? $index - 1 : $index + 1;
        if (isset($order[$swapwith])) {
            [$order[$index], $order[$swapwith]] = [$order[$swapwith], $order[$index]];
            $episodeservice->reorder((int)$podcast->id, $order);
        }
    }
    redirect($returnurl);
}

$onlypublished = !$canmanage;
$perpage = max(1, (int)$podcast->episodesperpage);
$episodes = $episodeservice->get_for_display((int)$podcast->id, $onlypublished, $sort, $page, $perpage, $search);
$totalepisodes = $episodeservice->count((int)$podcast->id, $onlypublished, $search);
$episodeids = array_values(array_map(static fn($episode) => (int)$episode->id, $episodes));
$episodeids = array_values(array_unique($episodeids));
$likecounts = $likeservice->get_episode_like_counts($episodeids);
$canlike = isloggedin() && !isguestuser();
$userlikedmap = [];
if ($canlike) {
    $userlikedmap = $likeservice->get_user_liked_episode_map((int)$USER->id, $episodeids);
}

$progressmap = [];
if (isloggedin() && !isguestuser()) {
    $progressrows = $DB->get_records('learnplugpodcasts_prog', ['podcastid' => $podcast->id, 'userid' => $USER->id]);
    foreach ($progressrows as $row) {
        $progressmap[(int)$row->episodeid] = $row;
    }
}

$fs = get_file_storage();
$coverfiles = $fs->get_area_files($context->id, 'mod_learnplugpodcasts', 'coverimage', 0, 'filename', false);
$coverimage = '';
if ($coverfiles) {
    $file = reset($coverfiles);
    $coverimage = moodle_url::make_pluginfile_url(
        $context->id,
        'mod_learnplugpodcasts',
        'coverimage',
        0,
        $file->get_filepath(),
        $file->get_filename()
    )->out(false);
}

foreach ($episodes as $episode) {
    $audio = $episodeservice->get_episode_audio_file($context, (int)$episode->id);
    $episode->audiourl = '';
    if ($audio) {
        // Keep stored duration aligned with actual audio metadata.
        $episode = $episodeservice->refresh_duration_from_audio($context, $episode);
        $episode->audiomimetype = mime::canonical_audio_type((string)$audio->get_mimetype());
        $episode->audiourl = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_learnplugpodcasts',
            'episodeaudio',
            $episode->id,
            $audio->get_filepath(),
            $audio->get_filename()
        )->out(false);
    }
    if (empty($episode->audiomimetype)) {
        $episode->audiomimetype = 'audio/mpeg';
    }

    $image = $episodeservice->get_episode_image_file($context, (int)$episode->id);
    $episode->imageurl = '';
    if ($image) {
        $episode->imageurl = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_learnplugpodcasts',
            'episodeimage',
            $episode->id,
            $image->get_filepath(),
            $image->get_filename()
        )->out(false);
    }

    $transcript = $transcriptservice->get_transcript_data($context, $episode);
    $episode->transcripttextformatted = $transcript['text'];
    $episode->transcriptfileurl = $transcript['fileurl'];
    $captiontracks = $captionservice->get_caption_tracks($context, (int)$episode->id);
    $captiontrack = $captionservice->get_primary_caption_track(
        $context,
        (int)$episode->id,
        current_language()
    );
    $episode->captiontrackurl = $captiontrack['url'] ?? '';
    $episode->captiontracklang = $captiontrack['lang'] ?? '';
    $episode->captiontracks = [];
    foreach ($captiontracks as $track) {
        $episode->captiontracks[] = [
            'lang' => (string)($track['lang'] ?? ''),
            'label' => (string)($track['label'] ?? ''),
            'url' => (string)($track['url'] ?? ''),
            'isselected' => !empty($episode->captiontrackurl) &&
                ((string)($track['url'] ?? '') === (string)$episode->captiontrackurl),
        ];
    }
    $episode->attachments = [];
    $attachmentfiles = $fs->get_area_files(
        $context->id,
        'mod_learnplugpodcasts',
        'episodeattachment',
        $episode->id,
        'filename',
        false
    );
    foreach ($attachmentfiles as $attachment) {
        $attachmenturl = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_learnplugpodcasts',
            'episodeattachment',
            $episode->id,
            $attachment->get_filepath(),
            $attachment->get_filename()
        )->out(false);
        $episode->attachments[] = [
            'name' => $attachment->get_filename(),
            'url' => $attachmenturl,
        ];
    }

    $episodeprogress = $progressmap[(int)$episode->id] ?? null;
    $episode->listenedpercent = (float)($episodeprogress->listenedpercent ?? 0);
    $episode->lastpositionsecs = (int)($episodeprogress->lastpositionsecs ?? 0);
    $episode->iscompleted = !empty($episodeprogress->completed);
    $episode->likecount = (int)($likecounts[(int)$episode->id] ?? 0);
    $episode->userliked = !empty($userlikedmap[(int)$episode->id]);

    if ($canmanage) {
        $episode->manageediturl = (new moodle_url('/mod/learnplugpodcasts/view.php', [
            'id' => $cm->id,
            'action' => 'edit',
            'episodeid' => $episode->id,
        ]))->out(false);

        $episode->managedeleteurl = (new moodle_url('/mod/learnplugpodcasts/view.php', [
            'id' => $cm->id,
            'action' => 'delete',
            'episodeid' => $episode->id,
            'sesskey' => sesskey(),
        ]))->out(false);

        $toggleaction = $episode->draftstatus === episode_service::STATUS_PUBLISHED ? 'unpublish' : 'publish';
        $episode->managetoggleurl = (new moodle_url('/mod/learnplugpodcasts/view.php', [
            'id' => $cm->id,
            'action' => $toggleaction,
            'episodeid' => $episode->id,
            'sesskey' => sesskey(),
        ]))->out(false);
        $episode->managetogglelabel = $toggleaction === 'publish' ?
            get_string('publishepisode', 'learnplugpodcasts') :
            get_string('unpublishepisode', 'learnplugpodcasts');
    }
}

$publicurl = '';
$rssurl = '';
if (!empty($podcast->publicenabled) && $publicaccess->is_site_public_enabled()) {
    $url = new moodle_url('/mod/learnplugpodcasts/public.php', ['id' => $cm->id]);
    $publicurl = $publicaccess->with_optional_token($url, (int)$podcast->id)->out(false);
}
if (!empty($podcast->rssenabled) && $publicaccess->is_site_rss_enabled()) {
    $url = new moodle_url('/mod/learnplugpodcasts/rss.php', ['id' => $cm->id]);
    $rssurl = $publicaccess->with_optional_token($url, (int)$podcast->id)->out(false);
}

$analytics = [];
if ($canviewreports) {
    $enrolledcount = (int)count_enrolled_users($context, 'mod/learnplugpodcasts:view');
    $analytics = $analyticsservice->get_report_data($podcast, $enrolledcount);
}

$renderer = $PAGE->get_renderer('mod_learnplugpodcasts');
$renderable = new podcast_view($podcast, array_values($episodes), [
    'context' => $context,
    'cmid' => $cm->id,
    'canmanage' => $canmanage,
    'canviewreports' => $canviewreports,
    'canmanageprogress' => $canviewreports && $canmanage,
    'coverimage' => $coverimage,
    'rssurl' => $rssurl,
    'publicurl' => $publicurl,
    'reporturl' => (new moodle_url('/mod/learnplugpodcasts/grade.php', ['id' => $cm->id]))->out(false),
    'resetallprogressurl' => (new moodle_url('/mod/learnplugpodcasts/grade.php', [
        'id' => $cm->id,
        'action' => 'resetallprogress',
        'sesskey' => sesskey(),
    ]))->out(false),
    'manageaddurl' => (new moodle_url('/mod/learnplugpodcasts/view.php', ['id' => $cm->id, 'action' => 'add']))->out(false),
    'sort' => $sort,
    'page' => $page,
    'analytics' => $analytics,
    'canlike' => $canlike,
]);

$PAGE->requires->js_call_amd('mod_learnplugpodcasts/player', 'init');
$PAGE->requires->js_call_amd('mod_learnplugpodcasts/progress', 'init', [[
    'cmid' => (int)$cm->id,
]]);
$PAGE->requires->js_call_amd('mod_learnplugpodcasts/search', 'init');
if ($canmanage) {
    $PAGE->requires->js_call_amd('mod_learnplugpodcasts/episodemanager', 'init', [[
        'cmid' => (int)$cm->id,
    ]]);
}

echo $OUTPUT->header();

if ($form) {
    echo html_writer::div('', 'lp-podcasts-form-anchor');
    $form->display();
}

echo $renderer->render($renderable);

echo $OUTPUT->paging_bar($totalepisodes, $page, $perpage, $returnurl);

echo $OUTPUT->footer();
