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
 * Persistence for episode likes.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class like_repository {
    /**
     * Get one like row for episode/user.
     *
     * @param int $episodeid
     * @param int $userid
     * @return \stdClass|null
     */
    public function get_episode_user(int $episodeid, int $userid): ?\stdClass {
        global $DB;

        $record = $DB->get_record('learnplugpodcasts_like', [
            'episodeid' => $episodeid,
            'userid' => $userid,
        ]);
        return $record ?: null;
    }

    /**
     * Insert like row.
     *
     * @param int $podcastid
     * @param int $episodeid
     * @param int $userid
     * @return int inserted id
     */
    public function create(int $podcastid, int $episodeid, int $userid): int {
        global $DB;

        $now = time();
        $record = (object)[
            'podcastid' => $podcastid,
            'episodeid' => $episodeid,
            'userid' => $userid,
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        return (int)$DB->insert_record('learnplugpodcasts_like', $record);
    }

    /**
     * Delete like row by id.
     *
     * @param int $id
     */
    public function delete(int $id): void {
        global $DB;
        $DB->delete_records('learnplugpodcasts_like', ['id' => $id]);
    }

    /**
     * Count likes by episode.
     *
     * @param int $episodeid
     * @return int
     */
    public function count_episode(int $episodeid): int {
        global $DB;
        return (int)$DB->count_records('learnplugpodcasts_like', ['episodeid' => $episodeid]);
    }

    /**
     * Count likes for many episodes.
     *
     * @param int[] $episodeids
     * @return array<int,int> map episodeid => count
     */
    public function count_episode_ids(array $episodeids): array {
        global $DB;

        $episodeids = array_values(array_unique(array_map('intval', $episodeids)));
        if (empty($episodeids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($episodeids, SQL_PARAMS_NAMED, 'eid');
        $sql = "SELECT episodeid, COUNT(1) AS likecount
                  FROM {learnplugpodcasts_like}
                 WHERE episodeid {$insql}
              GROUP BY episodeid";

        $rows = $DB->get_records_sql($sql, $params);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row->episodeid] = (int)$row->likecount;
        }
        return $result;
    }

    /**
     * Get liked episode ids for a user within a set.
     *
     * @param int $userid
     * @param int[] $episodeids
     * @return array<int,bool> map episodeid => true
     */
    public function get_user_episode_map(int $userid, array $episodeids): array {
        global $DB;

        $episodeids = array_values(array_unique(array_map('intval', $episodeids)));
        if (empty($episodeids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($episodeids, SQL_PARAMS_NAMED, 'eid');
        $params['userid'] = $userid;

        $sql = "SELECT episodeid
                  FROM {learnplugpodcasts_like}
                 WHERE userid = :userid
                   AND episodeid {$insql}";

        $rows = $DB->get_records_sql($sql, $params);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row->episodeid] = true;
        }
        return $result;
    }
}
