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
 * Public RSS endpoint for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');

use mod_learnplugpodcasts\local\repository\podcast_repository;
use mod_learnplugpodcasts\local\service\feed_service;
use mod_learnplugpodcasts\local\service\public_access_service;

$id = optional_param('id', 0, PARAM_INT);
$podcastid = optional_param('podcast', 0, PARAM_INT);
$slug = optional_param('slug', '', PARAM_ALPHANUMEXT);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);

$podcastrepo = new podcast_repository();
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

if (!$publicaccess->can_access_rss((int)$podcast->id, $token)) {
    if ($publicaccess->is_token_mode()) {
        throw new moodle_exception('invalidpublictoken', 'learnplugpodcasts');
    }
    throw new moodle_exception('rssdisabled', 'learnplugpodcasts');
}

$context = context_module::instance($cm->id);
$feedservice = new feed_service();
$xml = $feedservice->build_feed($podcast, $cm, $context);

$DB->set_field('learnplugpodcasts', 'rsslastbuild', time(), ['id' => $podcast->id]);

header('Content-Type: application/rss+xml; charset=UTF-8');
header('X-Robots-Tag: noindex, follow');
echo $xml;
exit;
