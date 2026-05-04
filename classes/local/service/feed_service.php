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

namespace mod_learnplugpodcasts\local\service;

use mod_learnplugpodcasts\local\repository\episode_repository;
use mod_learnplugpodcasts\local\util\mime;
use mod_learnplugpodcasts\local\util\xml;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feed_service {
    /** @var episode_repository */
    private episode_repository $episoderepo;
    /** @var public_access_service */
    private public_access_service $publicaccess;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->episoderepo = new episode_repository();
        $this->publicaccess = new public_access_service();
    }

    /**
     * Generate RSS XML.
     *
     * @param \stdClass $podcast
     * @param \stdClass $cm
     * @param \context_module $context
     * @return string
     */
    public function build_feed(\stdClass $podcast, \stdClass $cm, \context_module $context): string {
        $episodes = $this->episoderepo->get_by_podcast((int)$podcast->id, true, 'newest');
        $token = $this->publicaccess->is_token_mode() ? $this->publicaccess->build_token((int)$podcast->id) : '';

        $feedurl = new \moodle_url('/mod/learnplugpodcasts/rss.php', ['id' => $cm->id]);
        if ($token !== '') {
            $feedurl->param('token', $token);
        }

        $publicurl = new \moodle_url('/mod/learnplugpodcasts/public.php', ['id' => $cm->id]);
        if ($token !== '') {
            $publicurl->param('token', $token);
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $rss = $doc->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $rss->setAttribute('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
        $rss->setAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $doc->appendChild($rss);

        $channel = $doc->createElement('channel');
        $rss->appendChild($channel);

        $channel->appendChild($doc->createElement('title', $podcast->name));
        $channel->appendChild($doc->createElement('link', $publicurl->out(false)));
        $channel->appendChild($doc->createElement('description', strip_tags((string)$podcast->intro)));
        $channel->appendChild($doc->createElement('language', $podcast->languagecode ?: 'en'));
        $channel->appendChild($doc->createElement('lastBuildDate', xml::rss_date(max(time(), (int)$podcast->rsslastbuild))));
        if (!empty($podcast->subtitle)) {
            $channel->appendChild($doc->createElement('itunes:subtitle', $podcast->subtitle));
        }
        $channel->appendChild($doc->createElement('itunes:summary', strip_tags((string)$podcast->intro)));
        if (!empty($podcast->copyrightnotice)) {
            $channel->appendChild($doc->createElement('copyright', $podcast->copyrightnotice));
        }

        if (!empty($podcast->authorname)) {
            $channel->appendChild($doc->createElement('itunes:author', $podcast->authorname));
        }

        $channel->appendChild($doc->createElement('itunes:explicit', !empty($podcast->explicitflag) ? 'yes' : 'no'));

        if (!empty($podcast->category)) {
            $itunescategory = $doc->createElement('itunes:category');
            $itunescategory->setAttribute('text', $podcast->category);
            $channel->appendChild($itunescategory);
        }

        $atomlink = $doc->createElement('atom:link');
        $atomlink->setAttribute('href', $feedurl->out(false));
        $atomlink->setAttribute('rel', 'self');
        $atomlink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomlink);

        if (!empty($podcast->email)) {
            $owner = $doc->createElement('itunes:owner');
            $owner->appendChild($doc->createElement('itunes:email', $podcast->email));
            if (!empty($podcast->authorname)) {
                $owner->appendChild($doc->createElement('itunes:name', $podcast->authorname));
            }
            $channel->appendChild($owner);
        }

        $cover = $this->get_cover_image_url($context, (int)$podcast->id);
        if ($cover !== '') {
            $itcover = $doc->createElement('itunes:image');
            $itcover->setAttribute('href', $cover);
            $channel->appendChild($itcover);
            $image = $doc->createElement('image');
            $image->appendChild($doc->createElement('url', $cover));
            $image->appendChild($doc->createElement('title', $podcast->name));
            $image->appendChild($doc->createElement('link', $publicurl->out(false)));
            $channel->appendChild($image);
        }

        $seenenclosures = [];
        foreach ($episodes as $episode) {
            $audio = $this->get_episode_audio($context, (int)$podcast->id, (int)$episode->id);
            if (!$audio) {
                continue;
            }

            $item = $doc->createElement('item');
            $item->appendChild($doc->createElement('title', $episode->title));
            if (!empty($episode->subtitle)) {
                $item->appendChild($doc->createElement('itunes:subtitle', $episode->subtitle));
            }

            $episodeurl = new \moodle_url('/mod/learnplugpodcasts/episode.php', ['episode' => $episode->id]);
            if ($token !== '') {
                $episodeurl->param('token', $this->publicaccess->build_token((int)$podcast->id, (int)$episode->id));
            }

            $item->appendChild($doc->createElement('link', $episodeurl->out(false)));
            $item->appendChild($doc->createElement('guid', $episode->guid));
            $item->appendChild($doc->createElement('pubDate', xml::rss_date((int)$episode->publishtime)));

            xml::append_cdata($doc, $item, 'description', strip_tags((string)$episode->description));
            xml::append_cdata($doc, $item, 'content:encoded', (string)$episode->description);

            $enclosureurl = $audio['url'];
            if (isset($seenenclosures[$enclosureurl])) {
                continue;
            }
            $seenenclosures[$enclosureurl] = true;

            $enclosure = $doc->createElement('enclosure');
            $enclosure->setAttribute('url', $enclosureurl);
            $enclosure->setAttribute('length', (string)$audio['size']);
            $enclosure->setAttribute('type', $audio['mimetype']);
            $item->appendChild($enclosure);

            $item->appendChild($doc->createElement('itunes:duration', gmdate('H:i:s', max((int)$episode->durationsecs, 0))));
            $item->appendChild($doc->createElement('itunes:explicit', !empty($episode->explicitflag) ? 'yes' : 'no'));
            if (!empty($episode->episodenumber)) {
                $item->appendChild($doc->createElement('itunes:episode', (string)$episode->episodenumber));
            }
            if (!empty($episode->seasonnumber)) {
                $item->appendChild($doc->createElement('itunes:season', (string)$episode->seasonnumber));
            }

            $episodeimage = $this->get_episode_image_url($context, (int)$podcast->id, (int)$episode->id);
            if ($episodeimage !== '') {
                $itimage = $doc->createElement('itunes:image');
                $itimage->setAttribute('href', $episodeimage);
                $item->appendChild($itimage);
            }

            $channel->appendChild($item);
        }

        return $doc->saveXML();
    }

    /**
     * Gets cover image URL.
     *
     * @param \context_module $context
     * @param int $podcastid
     * @return string
     */
    private function get_cover_image_url(\context_module $context, int $podcastid): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_learnplugpodcasts', 'coverimage', 0, 'filename', false);
        if (!$files) {
            return '';
        }

        $file = reset($files);
        $url = \moodle_url::make_pluginfile_url(
            $context->id,
            'mod_learnplugpodcasts',
            'coverimage',
            0,
            $file->get_filepath(),
            $file->get_filename()
        );
        $url = $this->publicaccess->with_optional_token($url, $podcastid);
        return $url->out(false);
    }

    /**
     * Gets episode image URL.
     *
     * @param \context_module $context
     * @param int $podcastid
     * @param int $episodeid
     * @return string
     */
    private function get_episode_image_url(\context_module $context, int $podcastid, int $episodeid): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_learnplugpodcasts', 'episodeimage', $episodeid, 'filename', false);
        if (!$files) {
            return '';
        }
        $file = reset($files);
        $url = \moodle_url::make_pluginfile_url(
            $context->id,
            'mod_learnplugpodcasts',
            'episodeimage',
            $episodeid,
            $file->get_filepath(),
            $file->get_filename()
        );
        $url = $this->publicaccess->with_optional_token($url, $podcastid, $episodeid);
        return $url->out(false);
    }

    /**
     * Gets episode audio metadata.
     *
     * @param \context_module $context
     * @param int $podcastid
     * @param int $episodeid
     * @return array|null
     */
    private function get_episode_audio(\context_module $context, int $podcastid, int $episodeid): ?array {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_learnplugpodcasts', 'episodeaudio', $episodeid, 'filename', false);
        if (!$files) {
            return null;
        }

        $file = reset($files);
        $url = \moodle_url::make_pluginfile_url(
            $context->id,
            'mod_learnplugpodcasts',
            'episodeaudio',
            $episodeid,
            $file->get_filepath(),
            $file->get_filename()
        );
        $url = $this->publicaccess->with_optional_token($url, $podcastid, $episodeid);

        $mimetype = $file->get_mimetype();
        if (!mime::is_allowed_audio($mimetype)) {
            $mimetype = 'audio/mpeg';
        }

        return [
            'url' => $url->out(false),
            'size' => $file->get_filesize(),
            'mimetype' => $mimetype,
        ];
    }
}
