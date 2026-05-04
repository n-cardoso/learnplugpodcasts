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

use core_user;
use moodle_url;

/**
 * Sends learner notifications for newly published episodes.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_service {
    /** @var string */
    private const LOG_ACTION_NEW_EPISODE = 'episode_notify_published';

    /**
     * Returns whether site-level episode notifications are enabled.
     *
     * @return bool
     */
    public function is_site_enabled(): bool {
        return !empty(get_config('mod_learnplugpodcasts', 'enableepisodenotifications'));
    }

    /**
     * Returns whether notifications are enabled for this activity.
     *
     * @param \stdClass $podcast
     * @return bool
     */
    public function is_activity_enabled(\stdClass $podcast): bool {
        return $this->is_site_enabled() && !empty($podcast->notifynewepisodes);
    }

    /**
     * Sends notification to enrolled users for a newly published episode.
     *
     * @param \stdClass $podcast
     * @param \stdClass $episode
     * @param \context_module $context
     * @param int $actoruserid
     */
    public function notify_new_episode(\stdClass $podcast, \stdClass $episode, \context_module $context, int $actoruserid = 0): void {
        global $CFG, $DB;

        if (!$this->is_activity_enabled($podcast) || $this->already_notified((int)$podcast->id, (int)$episode->id)) {
            return;
        }

        require_once($CFG->dirroot . '/message/lib.php');

        $cm = get_coursemodule_from_instance(
            'learnplugpodcasts',
            (int)$podcast->id,
            (int)$podcast->course,
            false,
            IGNORE_MISSING
        );
        if (!$cm) {
            $this->write_log((int)$podcast->id, (int)$episode->id, 'error', 'Could not resolve course module.');
            return;
        }

        $users = get_enrolled_users($context, 'mod/learnplugpodcasts:view', 0, 'u.id,u.deleted,u.suspended');
        if (empty($users)) {
            $this->write_log((int)$podcast->id, (int)$episode->id, 'ok', 'No recipients enrolled.');
            return;
        }

        $url = new moodle_url('/mod/learnplugpodcasts/view.php', ['id' => $cm->id]);
        $contexturl = $url->out(false);

        $a = (object)[
            'podcast' => format_string($podcast->name),
            'episode' => format_string($episode->title),
        ];
        $subject = get_string('notificationnewepisodesubject', 'learnplugpodcasts', $a);
        $fullmessage = get_string('notificationnewepisodebody', 'learnplugpodcasts', $a) . PHP_EOL . $contexturl;
        $fullmessagehtml = '<p>' . s(get_string('notificationnewepisodebody', 'learnplugpodcasts', $a)) . '</p>' .
            '<p><a href="' . s($contexturl) . '">' . s($contexturl) . '</a></p>';

        $userfrom = core_user::get_noreply_user();
        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            $recipient = core_user::get_user((int)$user->id, '*', IGNORE_MISSING);
            if (!$recipient) {
                continue;
            }
            if (!empty($recipient->deleted) || !empty($recipient->suspended) || isguestuser($recipient)) {
                continue;
            }
            if ($actoruserid > 0 && (int)$recipient->id === $actoruserid) {
                continue;
            }

            $eventdata = new \core\message\message();
            $eventdata->component = 'mod_learnplugpodcasts';
            $eventdata->name = 'newepisode';
            $eventdata->userfrom = $userfrom;
            $eventdata->userto = $recipient;
            $eventdata->subject = $subject;
            $eventdata->fullmessage = $fullmessage;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = $fullmessagehtml;
            $eventdata->smallmessage = $subject;
            $eventdata->notification = 1;
            $eventdata->courseid = (int)$podcast->course;
            $eventdata->contexturl = $contexturl;
            $eventdata->contexturlname = format_string($podcast->name);

            try {
                $result = message_send($eventdata);
                if (!empty($result)) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $status = $sent > 0 ? 'ok' : 'error';
        $message = 'Sent=' . $sent . ', failed=' . $failed;
        $this->write_log((int)$podcast->id, (int)$episode->id, $status, $message);
    }

    /**
     * Checks whether a notification log exists for this episode.
     *
     * @param int $podcastid
     * @param int $episodeid
     * @return bool
     */
    private function already_notified(int $podcastid, int $episodeid): bool {
        global $DB;

        return $DB->record_exists('learnplugpodcasts_log', [
            'podcastid' => $podcastid,
            'episodeid' => $episodeid,
            'actiontype' => self::LOG_ACTION_NEW_EPISODE,
            'status' => 'ok',
        ]);
    }

    /**
     * Writes a notification action record.
     *
     * @param int $podcastid
     * @param int $episodeid
     * @param string $status
     * @param string $message
     */
    private function write_log(int $podcastid, int $episodeid, string $status, string $message): void {
        global $DB;

        $DB->insert_record('learnplugpodcasts_log', (object)[
            'podcastid' => $podcastid,
            'episodeid' => $episodeid,
            'actiontype' => self::LOG_ACTION_NEW_EPISODE,
            'status' => $status,
            'message' => $message,
            'timecreated' => time(),
        ]);
    }
}
