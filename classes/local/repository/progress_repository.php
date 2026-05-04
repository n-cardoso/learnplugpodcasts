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
class progress_repository {
    /**
     * Fetches one progress record.
     *
     * @param int $episodeid
     * @param int $userid
     * @return \stdClass|null
     */
    public function get_episode_user(int $episodeid, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('learnplugpodcasts_prog', ['episodeid' => $episodeid, 'userid' => $userid]) ?: null;
    }

    /**
     * Upserts progress record.
     *
     * @param \stdClass $record
     * @return \stdClass
     */
    public function upsert(\stdClass $record): \stdClass {
        global $DB;

        $existing = $this->get_episode_user((int)$record->episodeid, (int)$record->userid);
        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('learnplugpodcasts_prog', $record);
            return (object)array_merge((array)$existing, (array)$record);
        }

        $record->timecreated = $record->timemodified;
        $record->id = $DB->insert_record('learnplugpodcasts_prog', $record);
        return $record;
    }

    /**
     * Counts completed episodes by user.
     *
     * @param int $podcastid
     * @param int $userid
     * @return int
     */
    public function count_completed_episodes(int $podcastid, int $userid): int {
        global $DB;
        return $DB->count_records('learnplugpodcasts_prog', ['podcastid' => $podcastid, 'userid' => $userid, 'completed' => 1]);
    }

    /**
     * Returns report rows.
     *
     * @param int $podcastid
     * @return array
     */
    public function report_rows(int $podcastid): array {
        global $DB;

        $sql = "SELECT p.userid,
                       u.firstname,
                       u.lastname,
                       AVG(p.listenedpercent) AS avgpercent,
                       SUM(p.listenedsecs) AS totalsecs,
                       MAX(p.timemodified) AS lastactivity
                  FROM {learnplugpodcasts_prog} p
                  JOIN {user} u ON u.id = p.userid
                 WHERE p.podcastid = :podcastid
              GROUP BY p.userid, u.firstname, u.lastname
              ORDER BY lastactivity DESC";

        return $DB->get_records_sql($sql, ['podcastid' => $podcastid]);
    }
}
