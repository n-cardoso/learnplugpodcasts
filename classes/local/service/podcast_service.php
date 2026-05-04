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
class podcast_service {
    /** @var podcast_repository */
    private podcast_repository $podcastrepo;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->podcastrepo = new podcast_repository();
    }

    /**
     * Get podcast by course module id.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    public function get_by_cmid(int $cmid): ?\stdClass {
        return $this->podcastrepo->get_by_cmid($cmid);
    }

    /**
     * Get podcast by id.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public function get_by_id(int $id): ?\stdClass {
        return $this->podcastrepo->get_by_id($id);
    }

    /**
     * Update rss build timestamp.
     *
     * @param int $podcastid
     */
    public function touch_feed_build(int $podcastid): void {
        $this->podcastrepo->touch_rss_build($podcastid);
    }
}
