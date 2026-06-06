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

use mod_learnplugpodcasts\event\progress_updated;
use mod_learnplugpodcasts\local\repository\progress_repository;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_service {
    /** @var progress_repository */
    private progress_repository $progressrepo;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->progressrepo = new progress_repository();
    }

    /**
     * Persist progress heartbeat.
     *
     * @param \stdClass $podcast
     * @param \stdClass $episode
     * @param \context_module $context
     * @param int $userid
     * @param int $positionsecs
     * @param float $advanceddelta
     * @param int $durationsecs
     * @param string $state
     * @param array $playedranges
     * @return \stdClass
     */
    public function save_progress(
        \stdClass $podcast,
        \stdClass $episode,
        \context_module $context,
        int $userid,
        int $positionsecs,
        float $advanceddelta,
        int $durationsecs,
        string $state,
        array $playedranges = []
    ): \stdClass {
        $positionsecs = max(0, $positionsecs);
        $durationsecs = max(0, $durationsecs);
        $this->sync_episode_duration_from_player($episode, $durationsecs);

        // Anti-abuse: count only bounded positive delta from advancing playback.
        $delta = max(0, min((float)$advanceddelta, 35.0));

        $existing = $this->progressrepo->get_episode_user((int)$episode->id, $userid);
        $listenedsecs = (float)($existing->listenedsecs ?? 0) + $delta;

        // Use a resilient duration source to avoid resetting listened% to 0 on
        // browsers/webviews that temporarily report unknown/invalid duration.
        $storedduration = max(0, (int)($episode->durationsecs ?? 0));
        $effectiveduration = $durationsecs > 0 ? $durationsecs : $storedduration;
        if ($effectiveduration <= 0) {
            $effectiveduration = max($positionsecs, (int)ceil($listenedsecs));
        }

        if ($effectiveduration > 0) {
            $listenedpercent = min(100.0, ($listenedsecs / $effectiveduration) * 100.0);
        } else {
            $listenedpercent = 0.0;
        }

        $episodecompletedthreshold = max(1, (int)($podcast->completionlistenpercent ?: 70));
        $completed = $listenedpercent >= $episodecompletedthreshold ? 1 : 0;

        $record = (object)[
            'podcastid' => $podcast->id,
            'episodeid' => $episode->id,
            'userid' => $userid,
            'lastpositionsecs' => $positionsecs,
            'listenedsecs' => (int)round($listenedsecs),
            'listenedpercent' => round($listenedpercent, 2),
            'completed' => $completed,
            'lastplaystate' => clean_param($state, PARAM_ALPHA),
            'timemodified' => time(),
        ];

        $saved = $this->progressrepo->upsert($record);
        $this->record_heatmap_ranges(
            (int)$podcast->id,
            (int)$episode->id,
            $userid,
            $playedranges,
            $effectiveduration
        );

        $event = progress_updated::create([
            'context' => $context,
            'objectid' => $saved->id,
            'relateduserid' => $userid,
            'other' => [
                'podcastid' => $podcast->id,
                'episodeid' => $episode->id,
                'listenedpercent' => $saved->listenedpercent,
            ],
        ]);
        $event->trigger();

        $cm = get_coursemodule_from_instance('learnplugpodcasts', $podcast->id, $podcast->course, false, MUST_EXIST);
        $completion = new \completion_info(get_course($podcast->course));
        $completion->update_state($cm, \COMPLETION_UNKNOWN, $userid);

        global $CFG;
        require_once($CFG->dirroot . '/mod/learnplugpodcasts/lib.php');
        learnplugpodcasts_update_grades($podcast, $userid);

        return $saved;
    }

    /**
     * Validate and persist listened ranges into fixed heatmap buckets.
     *
     * @param int $podcastid
     * @param int $episodeid
     * @param int $userid
     * @param array $playedranges
     * @param int $durationsecs
     * @return void
     */
    private function record_heatmap_ranges(
        int $podcastid,
        int $episodeid,
        int $userid,
        array $playedranges,
        int $durationsecs
    ): void {
        if (empty($playedranges)) {
            return;
        }

        $bucketsize = progress_repository::ZONE_BUCKET_SIZE;
        $maxduration = max(0, $durationsecs);
        foreach ($playedranges as $range) {
            if (!is_array($range) || count($range) !== 2) {
                continue;
            }

            $start = isset($range[0]) ? (float)$range[0] : 0.0;
            $end = isset($range[1]) ? (float)$range[1] : 0.0;
            if ($maxduration > 0) {
                $start = min($start, (float)$maxduration);
                $end = min($end, (float)$maxduration);
            }
            $start = max(0.0, $start);
            $end = max(0.0, $end);

            if ($end <= $start) {
                continue;
            }

            $rangeend = $end - 0.0001;
            $firstbucket = (int)floor($start / $bucketsize) * $bucketsize;
            $lastbucket = (int)floor($rangeend / $bucketsize) * $bucketsize;

            for ($bucketstart = $firstbucket; $bucketstart <= $lastbucket; $bucketstart += $bucketsize) {
                $bucketend = $bucketstart + $bucketsize;
                $overlapstart = max($start, (float)$bucketstart);
                $overlapend = min($end, (float)$bucketend);
                $overlap = round($overlapend - $overlapstart, 2);
                if ($overlap <= 0) {
                    continue;
                }
                $this->progressrepo->add_zone_listening(
                    $podcastid,
                    $episodeid,
                    $userid,
                    $bucketstart,
                    $overlap
                );
            }
        }
    }

    /**
     * Persist detected browser audio duration into episode record when missing/outdated.
     *
     * @param \stdClass $episode
     * @param int $durationsecs
     * @return void
     */
    private function sync_episode_duration_from_player(\stdClass $episode, int $durationsecs): void {
        global $DB;

        if ($durationsecs <= 0 || empty($episode->id)) {
            return;
        }

        $current = max(0, (int)($episode->durationsecs ?? 0));
        // Do not overwrite trusted metadata-derived duration with browser-reported values
        // when episode duration is already known.
        if ($current > 0 || $durationsecs <= 0) {
            return;
        }

        $DB->update_record('learnplugpodcasts_eps', (object)[
            'id' => (int)$episode->id,
            'durationsecs' => $durationsecs,
            'timemodified' => time(),
        ]);
        $episode->durationsecs = $durationsecs;
    }

    /**
     * Returns saved position.
     *
     * @param int $episodeid
     * @param int $userid
     * @return int
     */
    public function get_last_position(int $episodeid, int $userid): int {
        $existing = $this->progressrepo->get_episode_user($episodeid, $userid);
        return (int)($existing->lastpositionsecs ?? 0);
    }

    /**
     * Calculate deterministic grade 0-100 for a user.
     *
     * @param int $podcastid
     * @param int $userid
     * @return float
     */
    public static function calculate_grade(int $podcastid, int $userid): float {
        global $DB;

        $podcast = $DB->get_record('learnplugpodcasts', ['id' => $podcastid], '*', MUST_EXIST);
        $mode = (int)$podcast->completionlistenmode;

        if ($mode === 0) {
            return 0.0;
        }

        if ($mode === 1) {
            $started = $DB->record_exists_select(
                'learnplugpodcasts_prog',
                'podcastid = :podcastid AND userid = :userid AND listenedsecs > 0',
                ['podcastid' => $podcastid, 'userid' => $userid]
            );
            return $started ? 100.0 : 0.0;
        }

        if ($mode === 2) {
            $sql = 'SELECT COALESCE(MAX(listenedpercent), 0)
                      FROM {learnplugpodcasts_prog}
                     WHERE podcastid = :podcastid
                       AND userid = :userid';
            $maxpercent = (float)$DB->get_field_sql(
                $sql,
                ['podcastid' => $podcastid, 'userid' => $userid]
            );
            return min(100.0, max(0.0, $maxpercent));
        }

        if ($mode === 3) {
            $completed = (int)$DB->count_records(
                'learnplugpodcasts_prog',
                ['podcastid' => $podcastid, 'userid' => $userid, 'completed' => 1]
            );
            $target = max(1, (int)$podcast->completionepisodecount);
            return min(100.0, round(($completed / $target) * 100.0, 2));
        }

        return 0.0;
    }
}
