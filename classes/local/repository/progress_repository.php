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
     * Heatmap bucket size in seconds.
     */
    public const ZONE_BUCKET_SIZE = 15;

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
                       u.lastnamephonetic,
                       u.firstnamephonetic,
                       u.lastname,
                       u.middlename,
                       u.alternatename,
                       AVG(p.listenedpercent) AS avgpercent,
                       SUM(p.listenedsecs) AS totalsecs,
                       MAX(p.timemodified) AS lastactivity
                  FROM {learnplugpodcasts_prog} p
                  JOIN {user} u ON u.id = p.userid
                 WHERE p.podcastid = :podcastid
              GROUP BY p.userid,
                       u.firstname,
                       u.firstnamephonetic,
                       u.lastname,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename
              ORDER BY lastactivity DESC";

        return $DB->get_records_sql($sql, ['podcastid' => $podcastid]);
    }

    /**
     * Return learner IDs with progress records in one podcast.
     *
     * @param int $podcastid
     * @return int[]
     */
    public function get_podcast_userids(int $podcastid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT DISTINCT userid
               FROM {learnplugpodcasts_prog}
              WHERE podcastid = :podcastid",
            ['podcastid' => $podcastid]
        );

        return array_map(static fn($record) => (int)$record->userid, array_values($records));
    }

    /**
     * Delete all learner listening progress and heatmap rows for one podcast.
     *
     * @param int $podcastid
     * @return void
     */
    public function reset_podcast_progress(int $podcastid): void {
        global $DB;

        $DB->delete_records('learnplugpodcasts_prog', ['podcastid' => $podcastid]);
        $DB->delete_records('learnplugpodcasts_zone', ['podcastid' => $podcastid]);
    }

    /**
     * Delete one learner listening progress and heatmap rows for one podcast.
     *
     * @param int $podcastid
     * @param int $userid
     * @return void
     */
    public function reset_user_progress(int $podcastid, int $userid): void {
        global $DB;

        $DB->delete_records('learnplugpodcasts_prog', ['podcastid' => $podcastid, 'userid' => $userid]);
        $DB->delete_records('learnplugpodcasts_zone', ['podcastid' => $podcastid, 'userid' => $userid]);
    }

    /**
     * Add listened time to one heatmap bucket.
     *
     * @param int $podcastid
     * @param int $episodeid
     * @param int $userid
     * @param int $bucketstart
     * @param float $listenedsecs
     * @return void
     */
    public function add_zone_listening(
        int $podcastid,
        int $episodeid,
        int $userid,
        int $bucketstart,
        float $listenedsecs
    ): void {
        global $DB;

        if ($listenedsecs <= 0) {
            return;
        }

        $existing = $DB->get_record('learnplugpodcasts_zone', [
            'episodeid' => $episodeid,
            'userid' => $userid,
            'bucketstart' => $bucketstart,
        ]);

        $now = time();
        if ($existing) {
            $existing->listenedsecs = round((float)$existing->listenedsecs + $listenedsecs, 2);
            $existing->timemodified = $now;
            $DB->update_record('learnplugpodcasts_zone', $existing);
            return;
        }

        $record = (object)[
            'podcastid' => $podcastid,
            'episodeid' => $episodeid,
            'userid' => $userid,
            'bucketstart' => $bucketstart,
            'listenedsecs' => round($listenedsecs, 2),
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        $DB->insert_record('learnplugpodcasts_zone', $record);
    }

    /**
     * Return top aggregated zones per episode.
     *
     * @param int $podcastid
     * @param int $limitperepisode
     * @return array
     */
    public function get_top_episode_zones(int $podcastid, int $limitperepisode = 3): array {
        global $DB;

        $sql = "SELECT z.episodeid,
                       z.bucketstart,
                       SUM(z.listenedsecs) AS listenedsecs,
                       COUNT(DISTINCT z.userid) AS listeners
                  FROM {learnplugpodcasts_zone} z
                 WHERE z.podcastid = :podcastid
              GROUP BY z.episodeid, z.bucketstart
              ORDER BY z.episodeid ASC, listenedsecs DESC, z.bucketstart ASC";

        $rows = $DB->get_records_sql($sql, ['podcastid' => $podcastid]);
        $grouped = [];
        foreach ($rows as $row) {
            $episodeid = (int)$row->episodeid;
            if (!isset($grouped[$episodeid])) {
                $grouped[$episodeid] = [];
            }
            if (count($grouped[$episodeid]) < $limitperepisode) {
                $grouped[$episodeid][] = $row;
            }
        }
        return $grouped;
    }

    /**
     * Return strongest zone per learner and episode.
     *
     * @param int $podcastid
     * @return array
     */
    public function get_top_learner_zones(int $podcastid): array {
        global $DB;

        $sql = "SELECT z.userid,
                       z.episodeid,
                       z.bucketstart,
                       z.listenedsecs,
                       u.firstname,
                       u.lastname
                  FROM {learnplugpodcasts_zone} z
                  JOIN {user} u
                    ON u.id = z.userid
                 WHERE z.podcastid = :podcastid
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {learnplugpodcasts_zone} z2
                        WHERE z2.episodeid = z.episodeid
                          AND z2.userid = z.userid
                          AND (
                              z2.listenedsecs > z.listenedsecs OR
                              (z2.listenedsecs = z.listenedsecs AND z2.bucketstart < z.bucketstart)
                          )
                   )
              ORDER BY u.lastname ASC, u.firstname ASC, z.episodeid ASC";

        return $DB->get_records_sql($sql, ['podcastid' => $podcastid]);
    }
}
