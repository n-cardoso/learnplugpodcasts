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
 * Public podcast landing page.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

use mod_learnplugpodcasts\local\repository\episode_repository;
use mod_learnplugpodcasts\local\repository\podcast_repository;
use mod_learnplugpodcasts\local\service\episode_service;
use mod_learnplugpodcasts\local\service\public_access_service;

$id = optional_param('id', 0, PARAM_INT);
$podcastid = optional_param('podcast', 0, PARAM_INT);
$slug = optional_param('slug', '', PARAM_ALPHANUMEXT);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);

$podcastrepo = new podcast_repository();
$episoderepo = new episode_repository();
$episodeservice = new episode_service();
$publicaccess = new public_access_service();

$cm = null;
if ($id) {
    $cm = get_coursemodule_from_id('learnplugpodcasts', $id, 0, false, MUST_EXIST);
    $podcast = $podcastrepo->get_by_id((int)$cm->instance);
} else if ($podcastid) {
    $podcast = $podcastrepo->get_by_id($podcastid);
    if ($podcast) {
        $cm = get_coursemodule_from_instance('learnplugpodcasts', $podcast->id, $podcast->course, false, MUST_EXIST);
    }
} else if ($slug !== '') {
    $podcast = $podcastrepo->get_by_slug($slug);
    if ($podcast) {
        $cm = get_coursemodule_from_instance('learnplugpodcasts', $podcast->id, $podcast->course, false, MUST_EXIST);
    }
} else {
    throw new moodle_exception('errornopodcast', 'learnplugpodcasts');
}

if (empty($podcast) || empty($cm)) {
    throw new moodle_exception('errornopodcast', 'learnplugpodcasts');
}

if (!$publicaccess->can_access_public_podcast((int)$podcast->id, $token)) {
    if ($publicaccess->is_token_mode()) {
        throw new moodle_exception('invalidpublictoken', 'learnplugpodcasts');
    }
    throw new moodle_exception('publicdisabled', 'learnplugpodcasts');
}

$context = context_module::instance($cm->id);
$course = get_course((int)$cm->course);
$PAGE->set_url('/mod/learnplugpodcasts/public.php', ['id' => $cm->id]);
$PAGE->set_course($course);
$PAGE->set_cm($cm, $course);
$PAGE->set_context($context);
$PAGE->set_pagelayout('base');
$PAGE->set_title(format_string($podcast->name));
$PAGE->set_heading(format_string($podcast->name));

$coverurl = '';
$fs = get_file_storage();
$coverfiles = $fs->get_area_files($context->id, 'mod_learnplugpodcasts', 'coverimage', 0, 'filename', false);
if ($coverfiles) {
    $cover = reset($coverfiles);
    $coverurlobj = moodle_url::make_pluginfile_url(
        $context->id,
        'mod_learnplugpodcasts',
        'coverimage',
        0,
        $cover->get_filepath(),
        $cover->get_filename()
    );
    $coverurlobj = $publicaccess->with_optional_token($coverurlobj, (int)$podcast->id);
    $coverurl = $coverurlobj->out(false);
}

$episodes = $episoderepo->get_by_podcast((int)$podcast->id, true, 'newest');
$episoderows = [];
foreach ($episodes as $episode) {
    $audio = $episodeservice->get_episode_audio_file($context, (int)$episode->id);
    if (!$audio) {
        continue;
    }

    $audiourl = moodle_url::make_pluginfile_url(
        $context->id,
        'mod_learnplugpodcasts',
        'episodeaudio',
        $episode->id,
        $audio->get_filepath(),
        $audio->get_filename()
    );
    $audiourl = $publicaccess->with_optional_token($audiourl, (int)$podcast->id, (int)$episode->id)->out(false);

    $episodeurl = new moodle_url('/mod/learnplugpodcasts/episode.php', ['episode' => $episode->id]);
    $episodeurl = $publicaccess->with_optional_token($episodeurl, (int)$podcast->id, (int)$episode->id)->out(false);

    $episoderows[] = [
        'id' => (int)$episode->id,
        'title' => format_string($episode->title),
        'subtitle' => format_string((string)$episode->subtitle),
        'description' => format_text($episode->description, $episode->descriptionformat, ['context' => $context]),
        'publishtime' => userdate((int)$episode->publishtime),
        'audiourl' => $audiourl,
        'episodeurl' => $episodeurl,
    ];
}

$rssurl = '';
if (!empty($podcast->rssenabled) && $publicaccess->is_site_rss_enabled()) {
    $rss = new moodle_url('/mod/learnplugpodcasts/rss.php', ['id' => $cm->id]);
    $rssurl = $publicaccess->with_optional_token($rss, (int)$podcast->id)->out(false);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_learnplugpodcasts/public_podcast', [
    'name' => format_string($podcast->name),
    'subtitle' => format_string((string)$podcast->subtitle),
    'authorname' => format_string((string)$podcast->authorname),
    'description' => !empty($podcast->intro) ? format_text($podcast->intro, $podcast->introformat, ['context' => $context]) :
        get_string('emptypublicdescription', 'learnplugpodcasts'),
    'coverimage' => $coverurl,
    'rssurl' => $rssurl,
    'episodes' => $episoderows,
    'hasepisodes' => !empty($episoderows),
]);
echo $OUTPUT->footer();
