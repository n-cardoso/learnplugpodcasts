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

use mod_learnplugpodcasts\local\util\duration;

/**
 * Aggregates analytics for teacher reports.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_service {
    /**
     * Build activity and episode analytics.
     *
     * @param \stdClass $podcast
     * @param int $enrolledcount
     * @return array
     */
    public function get_report_data(\stdClass $podcast, int $enrolledcount): array {
        global $DB;

        $podcastid = (int)$podcast->id;
        $episodestats = $DB->get_records_sql(
            "SELECT e.id,
                    e.title,
                    e.draftstatus,
                    e.durationsecs,
                    e.publishtime,
                    COUNT(p.id) AS progressrows,
                    COUNT(DISTINCT p.userid) AS listeners,
                    COALESCE(AVG(p.listenedpercent), 0) AS avglistenedpercent,
                    COALESCE(SUM(p.listenedsecs), 0) AS totallistenedsecs,
                    COALESCE(SUM(CASE WHEN p.completed = 1 THEN 1 ELSE 0 END), 0) AS completions,
                    COALESCE(l.likes, 0) AS likes,
                    COALESCE(MAX(p.timemodified), 0) AS lastactivity
               FROM {learnplugpodcasts_eps} e
          LEFT JOIN {learnplugpodcasts_prog} p
                 ON p.episodeid = e.id
          LEFT JOIN (
                    SELECT episodeid, COUNT(1) AS likes
                      FROM {learnplugpodcasts_like}
                  GROUP BY episodeid
          ) l
                 ON l.episodeid = e.id
              WHERE e.podcastid = :podcastid
           GROUP BY e.id, e.title, e.draftstatus, e.durationsecs, e.publishtime, e.sortorder, l.likes
           ORDER BY e.sortorder ASC, e.publishtime DESC, e.id DESC",
            ['podcastid' => $podcastid]
        );

        $episodecount = count($episodestats);
        $publishedcount = 0;
        $overalllisteners = 0;
        $overallprogressrows = 0;
        $overallcompletions = 0;
        $overalltotalsecs = 0;
        $overalllikes = 0;
        $weightedpercentsum = 0.0;
        $reportrows = [];

        foreach ($episodestats as $row) {
            if ((string)$row->draftstatus === episode_service::STATUS_PUBLISHED) {
                $publishedcount++;
            }

            $listeners = (int)$row->listeners;
            $progressrows = (int)$row->progressrows;
            $completions = (int)$row->completions;
            $avgpercent = (float)$row->avglistenedpercent;
            $totalsecs = (int)$row->totallistenedsecs;
            $likes = (int)$row->likes;

            $overalllisteners += $listeners;
            $overallprogressrows += $progressrows;
            $overallcompletions += $completions;
            $overalltotalsecs += $totalsecs;
            $overalllikes += $likes;
            $weightedpercentsum += ($avgpercent * $progressrows);

            $completionrate = $listeners > 0 ? ($completions / $listeners) * 100 : 0;
            $listenerengagement = $enrolledcount > 0 ? ($listeners / $enrolledcount) * 100 : 0;

            $reportrows[] = [
                'title' => format_string((string)$row->title),
                'statuslabel' => $this->status_label((string)$row->draftstatus),
                'listeners' => $listeners,
                'listenerengagement' => $this->format_percent($listenerengagement),
                'avglistenedpercent' => $this->format_percent($avgpercent),
                'completionrate' => $this->format_percent($completionrate),
                'completions' => $completions,
                'likes' => $likes,
                'duration' => duration::format_hms((int)$row->durationsecs),
                'totallistened' => duration::format_hms($totalsecs),
                'lastactivity' => !empty($row->lastactivity) ? userdate((int)$row->lastactivity) : '-',
                'publishtime' => !empty($row->publishtime) ? userdate((int)$row->publishtime) : '-',
            ];
        }

        $avglistenedoverall = $overallprogressrows > 0 ? ($weightedpercentsum / $overallprogressrows) : 0;
        $activityengagement = $enrolledcount > 0 ? ($this->count_active_learners($podcastid) / $enrolledcount) * 100 : 0;
        $avgcompletionoverall = $overallprogressrows > 0 ? ($overallcompletions / $overallprogressrows) * 100 : 0;

        return [
            'cards' => [
                [
                    'label' => get_string('analyticscard_enrolledlearners', 'learnplugpodcasts'),
                    'value' => (string)$enrolledcount,
                    'sub' => get_string('analyticscard_enrolledlearners_sub', 'learnplugpodcasts'),
                ],
                [
                    'label' => get_string('analyticscard_activityengagement', 'learnplugpodcasts'),
                    'value' => $this->format_percent($activityengagement),
                    'sub' => get_string('analyticscard_activityengagement_sub', 'learnplugpodcasts'),
                ],
                [
                    'label' => get_string('analyticscard_avglistened', 'learnplugpodcasts'),
                    'value' => $this->format_percent($avglistenedoverall),
                    'sub' => get_string('analyticscard_avglistened_sub', 'learnplugpodcasts'),
                ],
                [
                    'label' => get_string('analyticscard_completionrate', 'learnplugpodcasts'),
                    'value' => $this->format_percent($avgcompletionoverall),
                    'sub' => get_string('analyticscard_completionrate_sub', 'learnplugpodcasts'),
                ],
                [
                    'label' => get_string('analyticscard_totallisteningtime', 'learnplugpodcasts'),
                    'value' => duration::format_hms($overalltotalsecs),
                    'sub' => get_string('analyticscard_totallisteningtime_sub', 'learnplugpodcasts'),
                ],
                [
                    'label' => get_string('analyticscard_episodecoverage', 'learnplugpodcasts'),
                    'value' => $publishedcount . '/' . $episodecount,
                    'sub' => get_string('analyticscard_episodecoverage_sub', 'learnplugpodcasts'),
                ],
                [
                    'label' => get_string('analyticscard_totallikes', 'learnplugpodcasts'),
                    'value' => (string)$overalllikes,
                    'sub' => get_string('analyticscard_totallikes_sub', 'learnplugpodcasts'),
                ],
            ],
            'rows' => $reportrows,
            'hasrows' => !empty($reportrows),
        ];
    }

    /**
     * Count active learners (any tracked progress row).
     *
     * @param int $podcastid
     * @return int
     */
    private function count_active_learners(int $podcastid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT userid)
                  FROM {learnplugpodcasts_prog}
                 WHERE podcastid = :podcastid";
        return (int)$DB->get_field_sql($sql, ['podcastid' => $podcastid]);
    }

    /**
     * Build status label.
     *
     * @param string $status
     * @return string
     */
    private function status_label(string $status): string {
        if ($status === episode_service::STATUS_PUBLISHED) {
            return get_string('publishedlabel', 'learnplugpodcasts');
        }
        if ($status === episode_service::STATUS_UNPUBLISHED) {
            return get_string('unpublishedlabel', 'learnplugpodcasts');
        }
        return get_string('draftlabel', 'learnplugpodcasts');
    }

    /**
     * Format a percent value for display.
     *
     * @param float $value
     * @return string
     */
    private function format_percent(float $value): string {
        return format_float(round($value, 1), 1) . '%';
    }
}
