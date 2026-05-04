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
 * Public episode page for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

use mod_learnplugpodcasts\local\service\episode_service;
use mod_learnplugpodcasts\local\service\public_access_service;
use mod_learnplugpodcasts\local\service\transcript_service;

$episodeid = required_param('episode', PARAM_INT);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);

$episode = $DB->get_record('learnplugpodcasts_eps', ['id' => $episodeid], '*', MUST_EXIST);
$podcast = $DB->get_record('learnplugpodcasts', ['id' => $episode->podcastid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('learnplugpodcasts', $podcast->id, $podcast->course, false, MUST_EXIST);
$course = get_course((int)$cm->course);
$context = context_module::instance($cm->id);

$publicaccess = new public_access_service();
if (!$publicaccess->can_access_public_podcast((int)$podcast->id, $token, (int)$episode->id)) {
    if ($publicaccess->is_token_mode()) {
        throw new moodle_exception('invalidpublictoken', 'learnplugpodcasts');
    }
    throw new moodle_exception('publicdisabled', 'learnplugpodcasts');
}

if ($episode->draftstatus !== episode_service::STATUS_PUBLISHED) {
    throw new moodle_exception('notfoundepisode', 'learnplugpodcasts');
}

$episodeservice = new episode_service();
$transcriptservice = new transcript_service();
$transcript = $transcriptservice->get_transcript_data($context, $episode);
$attachments = [];
$transcriptfileurl = '';
if (!empty($transcript['fileurl'])) {
    $transcripturl = new moodle_url($transcript['fileurl']);
    $transcriptfileurl = $publicaccess->with_optional_token($transcripturl, (int)$podcast->id, (int)$episode->id)->out(false);
}

$audio = $episodeservice->get_episode_audio_file($context, (int)$episode->id);
$audiourl = '';
if ($audio) {
    $audiofileurl = moodle_url::make_pluginfile_url(
        $context->id,
        'mod_learnplugpodcasts',
        'episodeaudio',
        $episode->id,
        $audio->get_filepath(),
        $audio->get_filename()
    );
    $audiourl = $publicaccess->with_optional_token($audiofileurl, (int)$podcast->id, (int)$episode->id)->out(false);
}

$image = $episodeservice->get_episode_image_file($context, (int)$episode->id);
$imageurl = '';
if ($image) {
    $imagefileurl = moodle_url::make_pluginfile_url(
        $context->id,
        'mod_learnplugpodcasts',
        'episodeimage',
        $episode->id,
        $image->get_filepath(),
        $image->get_filename()
    );
    $imageurl = $publicaccess->with_optional_token($imagefileurl, (int)$podcast->id, (int)$episode->id)->out(false);
}

$attachmentfiles = get_file_storage()->get_area_files(
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
    );
    $attachments[] = [
        'name' => $attachment->get_filename(),
        'url' => $publicaccess->with_optional_token($attachmenturl, (int)$podcast->id, (int)$episode->id)->out(false),
    ];
}

$PAGE->set_url('/mod/learnplugpodcasts/episode.php', ['episode' => $episode->id]);
$PAGE->set_course($course);
$PAGE->set_cm($cm, $course);
$PAGE->set_context($context);
$PAGE->set_pagelayout('base');
$PAGE->set_title(format_string($episode->title));
$PAGE->set_heading(format_string($podcast->name));
$PAGE->requires->css('/mod/learnplugpodcasts/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_learnplugpodcasts/public_episode', [
    'podcastname' => format_string($podcast->name),
    'title' => format_string($episode->title),
    'subtitle' => format_string((string)$episode->subtitle),
    'description' => format_text($episode->description, $episode->descriptionformat, ['context' => $context]),
    'publishtime' => userdate((int)$episode->publishtime),
    'audiourl' => $audiourl,
    'imageurl' => $imageurl,
    'transcripttext' => $transcript['text'],
    'transcriptfileurl' => $transcriptfileurl,
    'attachments' => $attachments,
    'hasattachments' => !empty($attachments),
]);
echo $OUTPUT->footer();
