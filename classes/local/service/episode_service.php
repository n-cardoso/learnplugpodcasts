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

use mod_learnplugpodcasts\event\episode_created;
use mod_learnplugpodcasts\event\episode_deleted;
use mod_learnplugpodcasts\event\episode_published;
use mod_learnplugpodcasts\event\episode_updated;
use mod_learnplugpodcasts\local\repository\episode_repository;
use mod_learnplugpodcasts\local\util\duration;
use mod_learnplugpodcasts\local\util\mime;
use mod_learnplugpodcasts\local\util\slug;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class episode_service {
    /** @var string */
    public const STATUS_DRAFT = 'draft';
    /** @var string */
    public const STATUS_PUBLISHED = 'published';
    /** @var string */
    public const STATUS_UNPUBLISHED = 'unpublished';

    /** @var episode_repository */
    private episode_repository $episoderepo;
    /** @var notification_service */
    private notification_service $notificationservice;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->episoderepo = new episode_repository();
        $this->notificationservice = new notification_service();
    }

    /**
     * Returns one episode.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public function get_by_id(int $id): ?\stdClass {
        return $this->episoderepo->get_by_id($id);
    }

    /**
     * Returns episodes for display.
     *
     * @param int $podcastid
     * @param bool $onlypublished
     * @param string $sort
     * @param int $page
     * @param int $perpage
     * @param string $search
     * @return array
     */
    public function get_for_display(
        int $podcastid,
        bool $onlypublished,
        string $sort,
        int $page,
        int $perpage,
        string $search = ''
    ): array {
        $limitfrom = max(0, $page) * max(1, $perpage);
        return $this->episoderepo->get_paged($podcastid, $onlypublished, $sort, $limitfrom, $perpage, $search);
    }

    /**
     * Episode count.
     *
     * @param int $podcastid
     * @param bool $onlypublished
     * @param string $search
     * @return int
     */
    public function count(int $podcastid, bool $onlypublished = false, string $search = ''): int {
        return $this->episoderepo->count($podcastid, $onlypublished, $search);
    }

    /**
     * Create episode from submitted form data.
     *
     * @param \stdClass $podcast
     * @param \context_module $context
     * @param \stdClass $data
     * @return \stdClass
     */
    public function create_episode(\stdClass $podcast, \context_module $context, \stdClass $data): \stdClass {
        $now = time();

        $record = (object)[
            'podcastid' => $podcast->id,
            'title' => trim((string)$data->title),
            'subtitle' => trim((string)($data->subtitle ?? '')),
            'slug' => $this->build_unique_slug((int)$podcast->id, (string)$data->title),
            'description' => $data->description['text'] ?? '',
            'descriptionformat' => $data->description['format'] ?? FORMAT_HTML,
            'episodenumber' => empty($data->episodenumber) ? null : (int)$data->episodenumber,
            'seasonnumber' => empty($data->seasonnumber) ? null : (int)$data->seasonnumber,
            'publishtime' => empty($data->publishtime) ? $now : (int)$data->publishtime,
            'durationsecs' => 0,
            'draftstatus' => $this->normalise_status((string)($data->draftstatus ?? self::STATUS_DRAFT)),
            'explicitflag' => !empty($data->explicitflag) ? 1 : 0,
            'audiofileitemid' => (int)($data->audiofile ?? 0),
            'transcriptfileitemid' => (int)($data->transcriptfile ?? 0),
            'transcripttext' => $data->transcripttext['text'] ?? '',
            'transcriptformat' => $data->transcripttext['format'] ?? FORMAT_HTML,
            'episodeimageitemid' => (int)($data->episodeimage ?? 0),
            'externalurl' => trim((string)($data->externalurl ?? '')),
            'guid' => $this->generate_guid($podcast->id),
            'rssitemhash' => '',
            'sortorder' => $this->episoderepo->get_max_sort_order((int)$podcast->id) + 1,
            'timemodified' => $now,
            'timecreated' => $now,
        ];

        $record->id = $this->episoderepo->insert($record);

        $this->save_episode_files($context, (int)$record->id, $data);
        $record = $this->sync_duration_from_audio($context, $record);
        $this->update_rss_hash($record);

        episode_created::create([
            'context' => $context,
            'objectid' => $record->id,
            'relateduserid' => $podcast->owneruserid,
            'other' => ['podcastid' => $podcast->id],
        ])->trigger();

        if ($record->draftstatus === self::STATUS_PUBLISHED) {
            $this->notificationservice->notify_new_episode($podcast, $record, $context);
        }

        return $record;
    }

    /**
     * Update episode from form data.
     *
     * @param \stdClass $podcast
     * @param \context_module $context
     * @param \stdClass $episode
     * @param \stdClass $data
     * @return \stdClass
     */
    public function update_episode(\stdClass $podcast, \context_module $context, \stdClass $episode, \stdClass $data): \stdClass {
        $previousstatus = (string)$episode->draftstatus;
        $episode->title = trim((string)$data->title);
        $episode->subtitle = trim((string)($data->subtitle ?? ''));
        $episode->slug = $this->build_unique_slug((int)$podcast->id, (string)$data->title, (int)$episode->id);
        $episode->description = $data->description['text'] ?? '';
        $episode->descriptionformat = $data->description['format'] ?? FORMAT_HTML;
        $episode->episodenumber = empty($data->episodenumber) ? null : (int)$data->episodenumber;
        $episode->seasonnumber = empty($data->seasonnumber) ? null : (int)$data->seasonnumber;
        $episode->publishtime = empty($data->publishtime) ? time() : (int)$data->publishtime;
        $episode->draftstatus = $this->normalise_status((string)($data->draftstatus ?? $episode->draftstatus));
        $episode->explicitflag = !empty($data->explicitflag) ? 1 : 0;
        $episode->externalurl = trim((string)($data->externalurl ?? ''));
        if (!empty(get_config('mod_learnplugpodcasts', 'allowtranscripts'))) {
            $episode->transcripttext = $data->transcripttext['text'] ?? '';
            $episode->transcriptformat = $data->transcripttext['format'] ?? FORMAT_HTML;
        }
        $episode->timemodified = time();

        $this->episoderepo->update($episode);
        $this->save_episode_files($context, (int)$episode->id, $data);
        $episode = $this->sync_duration_from_audio($context, $episode);
        $this->update_rss_hash($episode);

        episode_updated::create([
            'context' => $context,
            'objectid' => $episode->id,
            'relateduserid' => $podcast->owneruserid,
            'other' => ['podcastid' => $podcast->id],
        ])->trigger();

        if ($previousstatus !== self::STATUS_PUBLISHED && $episode->draftstatus === self::STATUS_PUBLISHED) {
            $this->notificationservice->notify_new_episode($podcast, $episode, $context);
        }

        return $episode;
    }

    /**
     * Delete episode and associated files.
     *
     * @param \stdClass $podcast
     * @param \context_module $context
     * @param \stdClass $episode
     */
    public function delete_episode(\stdClass $podcast, \context_module $context, \stdClass $episode): void {
        global $DB;

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodeaudio', $episode->id);
        $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodeimage', $episode->id);
        $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodetranscript', $episode->id);
        $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodecaption', $episode->id);
        $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodeattachment', $episode->id);

        $DB->delete_records('learnplugpodcasts_prog', ['episodeid' => $episode->id]);
        $this->episoderepo->delete((int)$episode->id);

        episode_deleted::create([
            'context' => $context,
            'objectid' => $episode->id,
            'relateduserid' => $podcast->owneruserid,
            'other' => ['podcastid' => $podcast->id],
        ])->trigger();
    }

    /**
     * Publish or unpublish episode.
     *
     * @param \stdClass $podcast
     * @param \context_module $context
     * @param \stdClass $episode
     * @param bool $publish
     * @return \stdClass
     */
    public function set_published(\stdClass $podcast, \context_module $context, \stdClass $episode, bool $publish): \stdClass {
        global $USER;

        $previousstatus = (string)$episode->draftstatus;
        $episode->draftstatus = $publish ? self::STATUS_PUBLISHED : self::STATUS_UNPUBLISHED;
        $episode->timemodified = time();
        $this->episoderepo->update($episode);

        episode_published::create([
            'context' => $context,
            'objectid' => $episode->id,
            'relateduserid' => $podcast->owneruserid,
            'other' => ['podcastid' => $podcast->id, 'status' => $episode->draftstatus],
        ])->trigger();

        if ($publish && $previousstatus !== self::STATUS_PUBLISHED) {
            $actoruserid = !empty($USER->id) ? (int)$USER->id : 0;
            $this->notificationservice->notify_new_episode($podcast, $episode, $context, $actoruserid);
        }

        return $episode;
    }

    /**
     * Reorders episodes using ordered id list.
     *
     * @param int $podcastid
     * @param array $episodeids
     */
    public function reorder(int $podcastid, array $episodeids): void {
        $cleanids = array_values(array_filter(array_map('intval', $episodeids)));
        if ($cleanids) {
            $this->episoderepo->reorder($podcastid, $cleanids);
        }
    }

    /**
     * Public wrapper to refresh stored duration from uploaded audio.
     *
     * @param \context_module $context
     * @param \stdClass $episode
     * @return \stdClass
     */
    public function refresh_duration_from_audio(\context_module $context, \stdClass $episode): \stdClass {
        return $this->sync_duration_from_audio($context, $episode);
    }

    /**
     * Prepares draft data for edit form.
     *
     * @param \context_module $context
     * @param \stdClass $episode
     * @return \stdClass
     */
    public function prepare_form_data(\context_module $context, \stdClass $episode): \stdClass {
        $data = clone $episode;

        $data->description = [
            'text' => $episode->description,
            'format' => $episode->descriptionformat,
        ];
        $data->transcripttext = [
            'text' => $episode->transcripttext ?? '',
            'format' => $episode->transcriptformat ?? FORMAT_HTML,
        ];

        $data->audiofile = file_get_submitted_draft_itemid('audiofile');
        file_prepare_draft_area($data->audiofile, $context->id, 'mod_learnplugpodcasts', 'episodeaudio', $episode->id,
            ['subdirs' => 0, 'maxfiles' => 1]);

        $data->episodeimage = file_get_submitted_draft_itemid('episodeimage');
        file_prepare_draft_area($data->episodeimage, $context->id, 'mod_learnplugpodcasts', 'episodeimage', $episode->id,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]);

        $data->transcriptfile = file_get_submitted_draft_itemid('transcriptfile');
        file_prepare_draft_area($data->transcriptfile, $context->id, 'mod_learnplugpodcasts', 'episodetranscript', $episode->id,
            ['subdirs' => 0, 'maxfiles' => 1]);

        $data->episodecaption = file_get_submitted_draft_itemid('episodecaption');
        file_prepare_draft_area(
            $data->episodecaption,
            $context->id,
            'mod_learnplugpodcasts',
            'episodecaption',
            $episode->id,
            ['subdirs' => 0, 'maxfiles' => caption_service::MAX_TRACK_FILES]
        );

        $data->attachments = file_get_submitted_draft_itemid('attachments');
        file_prepare_draft_area($data->attachments, $context->id, 'mod_learnplugpodcasts', 'episodeattachment', $episode->id,
            ['subdirs' => 0, 'maxfiles' => 10]);

        return $data;
    }

    /**
     * Fetch first audio file for episode.
     *
     * @param \context_module $context
     * @param int $episodeid
     * @return \stored_file|null
     */
    public function get_episode_audio_file(\context_module $context, int $episodeid): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_learnplugpodcasts', 'episodeaudio', $episodeid, 'filename', false);
        return $files ? reset($files) : null;
    }

    /**
     * Fetch first image file for episode.
     *
     * @param \context_module $context
     * @param int $episodeid
     * @return \stored_file|null
     */
    public function get_episode_image_file(\context_module $context, int $episodeid): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_learnplugpodcasts', 'episodeimage', $episodeid, 'filename', false);
        return $files ? reset($files) : null;
    }

    /**
     * Save episode managed files from draft areas.
     *
     * @param \context_module $context
     * @param int $episodeid
     * @param \stdClass $data
     */
    private function save_episode_files(\context_module $context, int $episodeid, \stdClass $data): void {
        $audioopts = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['audio']];
        file_save_draft_area_files((int)($data->audiofile ?? 0), $context->id, 'mod_learnplugpodcasts', 'episodeaudio', $episodeid,
            $audioopts);

        $audiofile = $this->get_episode_audio_file($context, $episodeid);
        if ($audiofile && !mime::is_allowed_audio($audiofile->get_mimetype())) {
            get_file_storage()->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodeaudio', $episodeid);
            throw new \moodle_exception('invalidmimetype', 'learnplugpodcasts', '', $audiofile->get_mimetype());
        }

        file_save_draft_area_files(
            (int)($data->episodeimage ?? 0),
            $context->id,
            'mod_learnplugpodcasts',
            'episodeimage',
            $episodeid,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
        );

        if (!empty(get_config('mod_learnplugpodcasts', 'allowtranscripts'))) {
            file_save_draft_area_files(
                (int)($data->transcriptfile ?? 0),
                $context->id,
                'mod_learnplugpodcasts',
                'episodetranscript',
                $episodeid,
                ['subdirs' => 0, 'maxfiles' => 1]
            );

            file_save_draft_area_files(
                (int)($data->episodecaption ?? 0),
                $context->id,
                'mod_learnplugpodcasts',
                'episodecaption',
                $episodeid,
                ['subdirs' => 0, 'maxfiles' => caption_service::MAX_TRACK_FILES]
            );
            $captionfiles = get_file_storage()->get_area_files(
                $context->id,
                'mod_learnplugpodcasts',
                'episodecaption',
                $episodeid,
                'filename',
                false
            );
            foreach ($captionfiles as $captionfile) {
                $extension = strtolower(pathinfo($captionfile->get_filename(), PATHINFO_EXTENSION));
                if ($extension !== 'vtt') {
                    $captionfile->delete();
                    throw new \moodle_exception(
                        'invalidcaptionfile',
                        'learnplugpodcasts',
                        '',
                        $captionfile->get_filename()
                    );
                }
            }
        }

        file_save_draft_area_files((int)($data->attachments ?? 0), $context->id, 'mod_learnplugpodcasts', 'episodeattachment',
            $episodeid, ['subdirs' => 0, 'maxfiles' => 10]);
    }

    /**
     * Detects duration from stored audio and updates episode durationsecs.
     *
     * @param \context_module $context
     * @param \stdClass $episode
     * @return \stdClass
     */
    private function sync_duration_from_audio(\context_module $context, \stdClass $episode): \stdClass {
        $audiofile = $this->get_episode_audio_file($context, (int)$episode->id);
        if (!$audiofile) {
            return $episode;
        }

        $detected = duration::detect_seconds_from_stored_file($audiofile);
        if ($detected <= 0) {
            return $episode;
        }

        $current = max(0, (int)($episode->durationsecs ?? 0));
        if ($detected === $current) {
            return $episode;
        }

        $episode->durationsecs = $detected;
        $episode->timemodified = time();
        $this->episoderepo->update($episode);
        $this->update_rss_hash($episode);
        return $episode;
    }

    /**
     * Update RSS hash from stable fields.
     *
     * @param \stdClass $episode
     */
    private function update_rss_hash(\stdClass $episode): void {
        $episode->rssitemhash = sha1(
            implode('|', [
                $episode->title,
                $episode->slug,
                (string)$episode->publishtime,
                (string)$episode->timemodified,
                $episode->draftstatus,
            ])
        );
        $this->episoderepo->update($episode);
    }

    /**
     * Normalize publication status.
     *
     * @param string $status
     * @return string
     */
    private function normalise_status(string $status): string {
        $allowed = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_UNPUBLISHED];
        return in_array($status, $allowed, true) ? $status : self::STATUS_DRAFT;
    }

    /**
     * Create immutable GUID for episode.
     *
     * @param int $podcastid
     * @return string
     */
    private function generate_guid(int $podcastid): string {
        return $podcastid . '-' . bin2hex(random_bytes(16));
    }

    /**
     * Builds a podcast-unique slug.
     *
     * @param int $podcastid
     * @param string $title
     * @param int $ignoreid
     * @return string
     */
    private function build_unique_slug(int $podcastid, string $title, int $ignoreid = 0): string {
        global $DB;

        $base = slug::make($title);
        $candidate = $base;
        $suffix = 2;

        while (true) {
            $params = ['podcastid' => $podcastid, 'slug' => $candidate];
            $exists = $DB->record_exists('learnplugpodcasts_eps', $params);

            if ($exists && $ignoreid > 0) {
                $existing = $DB->get_record('learnplugpodcasts_eps', $params, 'id', IGNORE_MISSING);
                $exists = $existing && (int)$existing->id !== $ignoreid;
            }

            if (!$exists) {
                return $candidate;
            }

            $candidate = substr($base . '-' . $suffix, 0, 140);
            $suffix++;
        }
    }
}
