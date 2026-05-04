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

use mod_learnplugpodcasts\local\repository\podcast_repository;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class public_access_service {
    /** @var podcast_repository */
    private podcast_repository $podcastrepo;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->podcastrepo = new podcast_repository();
    }

    /**
     * Check site-level public page setting.
     *
     * @return bool
     */
    public function is_site_public_enabled(): bool {
        return !empty(get_config('mod_learnplugpodcasts', 'enablepublicpages'));
    }

    /**
     * Check site-level RSS setting.
     *
     * @return bool
     */
    public function is_site_rss_enabled(): bool {
        return !empty(get_config('mod_learnplugpodcasts', 'enablepublicrss'));
    }

    /**
     * Whether tokenized access is required.
     *
     * @return bool
     */
    public function is_token_mode(): bool {
        return !empty(get_config('mod_learnplugpodcasts', 'allowpublictokenmode'));
    }

    /**
     * Returns true when public podcast page can be accessed.
     *
     * @param int $podcastid
     * @param string $token
     * @param int $episodeid
     * @return bool
     */
    public function can_access_public_podcast(int $podcastid, string $token = '', int $episodeid = 0): bool {
        if (!$this->viewer_allowed()) {
            return false;
        }

        $podcast = $this->podcastrepo->get_by_id($podcastid);
        if (!$podcast || !$this->is_site_public_enabled() || empty($podcast->publicenabled)) {
            return false;
        }

        if (!$this->is_token_mode()) {
            return true;
        }

        return hash_equals($this->build_token($podcastid, $episodeid), $token);
    }

    /**
     * Returns true when rss is publicly accessible.
     *
     * @param int $podcastid
     * @param string $token
     * @return bool
     */
    public function can_access_rss(int $podcastid, string $token = ''): bool {
        if (!$this->viewer_allowed()) {
            return false;
        }

        $podcast = $this->podcastrepo->get_by_id($podcastid);
        if (!$podcast || !$this->is_site_rss_enabled() || empty($podcast->rssenabled) || empty($podcast->publicenabled)) {
            return false;
        }

        if (!$this->is_token_mode()) {
            return true;
        }

        return hash_equals($this->build_token($podcastid, 0), $token);
    }

    /**
     * Returns true when public media access should be granted for pluginfile.
     *
     * @param int $podcastid
     * @param string $token
     * @param int $episodeid
     * @return bool
     */
    public function can_access_public_media(int $podcastid, string $token = '', int $episodeid = 0): bool {
        if (!$this->viewer_allowed()) {
            return false;
        }

        $podcast = $this->podcastrepo->get_by_id($podcastid);
        if (!$podcast || empty($podcast->publicenabled)) {
            return false;
        }

        $canpage = $this->is_site_public_enabled();
        $canrss = $this->is_site_rss_enabled() && !empty($podcast->rssenabled);
        if (!$canpage && !$canrss) {
            return false;
        }

        if (!$this->is_token_mode()) {
            return true;
        }

        if (hash_equals($this->build_token($podcastid, $episodeid), $token)) {
            return true;
        }

        // Allow feed media access token on enclosure URLs.
        return $canrss && hash_equals($this->build_token($podcastid, 0), $token);
    }

    /**
     * Adds token to URL params when needed.
     *
     * @param \moodle_url $url
     * @param int $podcastid
     * @param int $episodeid
     * @return \moodle_url
     */
    public function with_optional_token(\moodle_url $url, int $podcastid, int $episodeid = 0): \moodle_url {
        if ($this->is_token_mode()) {
            $url->param('token', $this->build_token($podcastid, $episodeid));
        }
        return $url;
    }

    /**
     * Build a deterministic public token.
     *
     * @param int $podcastid
     * @param int $episodeid
     * @return string
     */
    public function build_token(int $podcastid, int $episodeid = 0): string {
        $payload = $podcastid . ':' . $episodeid . ':' . get_site_identifier();
        return hash('sha256', $payload);
    }

    /**
     * Checks capability for public viewers.
     *
     * @return bool
     */
    private function viewer_allowed(): bool {
        if (!isloggedin() || isguestuser()) {
            return true;
        }
        return has_capability('mod/learnplugpodcasts:viewpublic', \context_system::instance());
    }
}
