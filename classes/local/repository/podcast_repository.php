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

namespace mod_learnplugpodcasts\local\repository;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class podcast_repository {
    /**
     * Fetch podcast by ID.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public function get_by_id(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('learnplugpodcasts', ['id' => $id]) ?: null;
    }

    /**
     * Fetch podcast by cm id.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    public function get_by_cmid(int $cmid): ?\stdClass {
        global $DB;

        $cm = get_coursemodule_from_id('learnplugpodcasts', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }

        return $this->get_by_id((int)$cm->instance);
    }

    /**
     * Fetch podcast by public slug.
     *
     * @param string $slug
     * @return \stdClass|null
     */
    public function get_by_slug(string $slug): ?\stdClass {
        global $DB;
        return $DB->get_record('learnplugpodcasts', ['publicslug' => $slug]) ?: null;
    }

    /**
     * Updates feed build timestamp.
     *
     * @param int $podcastid
     */
    public function touch_rss_build(int $podcastid): void {
        global $DB;
        $DB->set_field('learnplugpodcasts', 'rsslastbuild', time(), ['id' => $podcastid]);
    }
}
