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

namespace mod_learnplugpodcasts\output;

use context_module;
use mod_learnplugpodcasts\local\service\caption_service;
use mod_learnplugpodcasts\local\service\episode_service;
use mod_learnplugpodcasts\local\service\like_service;
use mod_learnplugpodcasts\local\util\duration;

/**
 * Moodle App output callbacks.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the course module view for the Moodle app.
     *
     * @param array $args Arguments from tool_mobile_get_content WS.
     * @return array
     */
    public static function mobile_course_view(array $args): array {
        global $DB, $OUTPUT, $USER;

        $args = (object)$args;
        $cm = get_coursemodule_from_id('learnplugpodcasts', $args->cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);

        require_login($course, false, $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability('mod/learnplugpodcasts:view', $context);

        $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);
        $canmanage = has_capability('mod/learnplugpodcasts:manageepisodes', $context);
        $canlike = isloggedin() && !isguestuser();
        $episodeservice = new episode_service();
        $captionservice = new caption_service();
        $likeservice = new like_service();
        $prefetchfiles = [];
        $downloadallfiles = [];

        $episodes = $episodeservice->get_for_display(
            (int)$podcast->id,
            !$canmanage,
            (string)$podcast->defaultsort ?: 'newest',
            0,
            200
        );
        $episodeids = array_map(static function ($episode): int {
            return (int)$episode->id;
        }, $episodes);
        $progressmap = [];
        if (!empty($episodeids) && isloggedin() && !isguestuser()) {
            [$insql, $params] = $DB->get_in_or_equal($episodeids, SQL_PARAMS_NAMED);
            $params['userid'] = (int)$USER->id;
            $sql = "SELECT episodeid, lastpositionsecs, listenedpercent
                      FROM {learnplugpodcasts_prog}
                     WHERE episodeid {$insql}
                       AND userid = :userid";
            $rows = $DB->get_records_sql($sql, $params);
            foreach ($rows as $row) {
                $progressmap[(int)$row->episodeid] = $row;
            }
        }
        $likecounts = $likeservice->get_episode_like_counts($episodeids);
        $userlikedmap = [];
        if ($canlike) {
            $userlikedmap = $likeservice->get_user_liked_episode_map((int)$USER->id, $episodeids);
        }

        $fs = get_file_storage();
        $coverimage = '';
        $coverfiles = $fs->get_area_files($context->id, 'mod_learnplugpodcasts', 'coverimage', 0, 'filename', false);
        if ($coverfiles) {
            /** @var \stored_file $file */
            $file = reset($coverfiles);
            $coverimage = \moodle_url::make_pluginfile_url(
                $context->id,
                'mod_learnplugpodcasts',
                'coverimage',
                0,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $prefetchfiles[] = [
                'fileurl' => $coverimage,
                'filename' => $file->get_filename(),
                'filesize' => (int)$file->get_filesize(),
                'timemodified' => (int)$file->get_timemodified(),
                'mimetype' => (string)$file->get_mimetype(),
            ];
        }

        $episodeitems = [];
        foreach ($episodes as $episode) {
            $audiofile = $episodeservice->get_episode_audio_file($context, (int)$episode->id);
            if (!$audiofile) {
                continue;
            }

            $imagefile = $episodeservice->get_episode_image_file($context, (int)$episode->id);
            $imageurl = '';
            if ($imagefile) {
                $imageurl = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_learnplugpodcasts',
                    'episodeimage',
                    (int)$episode->id,
                    $imagefile->get_filepath(),
                    $imagefile->get_filename()
                )->out(false);
                $prefetchfiles[] = [
                    'fileurl' => $imageurl,
                    'filename' => $imagefile->get_filename(),
                    'filesize' => (int)$imagefile->get_filesize(),
                    'timemodified' => (int)$imagefile->get_timemodified(),
                    'mimetype' => (string)$imagefile->get_mimetype(),
                ];
            } else if ($coverimage !== '') {
                $imageurl = $coverimage;
            }

            $audiourl = \moodle_url::make_pluginfile_url(
                $context->id,
                'mod_learnplugpodcasts',
                'episodeaudio',
                (int)$episode->id,
                $audiofile->get_filepath(),
                $audiofile->get_filename()
            )->out(false);

            $downloadfile = [
                'fileurl' => $audiourl,
                'filename' => $audiofile->get_filename(),
                'filesize' => (int)$audiofile->get_filesize(),
                'timemodified' => (int)$audiofile->get_timemodified(),
                'mimetype' => (string)$audiofile->get_mimetype(),
            ];
            $audiodownloadjson = json_encode($downloadfile);
            if ($audiodownloadjson === false) {
                $audiodownloadjson = '{}';
            }

            $captiontracks = $captionservice->get_caption_tracks($context, (int)$episode->id);
            $primarycaption = $captionservice->get_primary_caption_track($context, (int)$episode->id);
            $primarycaptionurl = (string)($primarycaption['url'] ?? '');
            $hascaptiontracks = !empty($captiontracks);
            if ($hascaptiontracks) {
                $first = true;
                foreach ($captiontracks as $index => $track) {
                    $captiontracks[$index]['isdefault'] = $first;
                    $captiontracks[$index]['isselected'] = ((string)($track['url'] ?? '') === $primarycaptionurl);
                    $first = false;
                    $prefetchfiles[] = [
                        'fileurl' => (string)$track['url'],
                        'filename' => (string)$track['filename'],
                    ];
                }
            }

            $episodeitems[] = [
                'id' => (int)$episode->id,
                'cmid' => (int)$cm->id,
                'title' => format_string($episode->title),
                'subtitle' => format_string((string)$episode->subtitle),
                'description' => trim(strip_tags(format_text(
                    (string)$episode->description,
                    (int)$episode->descriptionformat,
                    ['context' => $context]
                ))),
                'durationlabel' => duration::format_hms((int)$episode->durationsecs),
                'audiourl' => $audiourl,
                'audiofilename' => $downloadfile['filename'],
                'audiosize' => $downloadfile['filesize'],
                'audiotimemodified' => $downloadfile['timemodified'],
                'audiomimetype' => $downloadfile['mimetype'],
                'audiodownloadjson' => $audiodownloadjson,
                'imageurl' => $imageurl,
                'captiontracks' => $captiontracks,
                'hascaptiontracks' => $hascaptiontracks,
                'captiontrackurl' => $primarycaptionurl,
                'hasmultiplecaptiontracks' => count($captiontracks) > 1,
                'likecount' => (int)($likecounts[(int)$episode->id] ?? 0),
                'userliked' => !empty($userlikedmap[(int)$episode->id]),
                'lastpositionsecs' => (int)($progressmap[(int)$episode->id]->lastpositionsecs ?? 0),
            ];
            $prefetchfiles[] = $downloadfile;
            $downloadallfiles[] = $downloadfile;
        }

        $downloadfilesjson = json_encode(array_values($downloadallfiles));
        if ($downloadfilesjson === false) {
            $downloadfilesjson = '[]';
        }

        $data = [
            'cmid' => (int)$cm->id,
            'courseid' => (int)$course->id,
            'podcastname' => format_string($podcast->name),
            'podcastintrotext' => trim(strip_tags((string)format_module_intro('learnplugpodcasts', $podcast, $cm->id))),
            'episodeslabel' => get_string('episodes', 'learnplugpodcasts'),
            'captionlabel' => get_string('captiontrackselect', 'learnplugpodcasts'),
            'captionofflabel' => get_string('captiontrackoff', 'learnplugpodcasts'),
            'likeonlabel' => get_string('unlikeepisode', 'learnplugpodcasts'),
            'likeofflabel' => get_string('likeepisode', 'learnplugpodcasts'),
            'emptylabel' => get_string('learnerlistempty', 'learnplugpodcasts'),
            'coverimage' => $coverimage,
            'episodes' => $episodeitems,
            'hasepisodes' => !empty($episodeitems),
            'canlike' => $canlike,
            'downloadfiles' => array_values($prefetchfiles),
            'downloadfilesjson' => $downloadfilesjson,
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_learnplugpodcasts/mobileapp/mobile_view_page', $data),
                ],
            ],
            'files' => array_values($prefetchfiles),
            'javascript' => self::mobile_caption_javascript(),
        ];
    }

    /**
     * Caption rendering helper for Moodle App mobile view.
     *
     * @return string
     */
    private static function mobile_caption_javascript(): string {
        return <<<'JS'
(function() {
    const asInt = (value, fallback = 0) => {
        const num = parseInt(String(value ?? ''), 10);
        return Number.isFinite(num) ? num : fallback;
    };

    const setupEpisodeCaptions = (container) => {
        const audio = container.querySelector('[data-region="lp-mobile-audio"]');
        const live = container.querySelector('[data-region="lp-mobile-caption-live"]');
        const select = container.querySelector('[data-region="lp-mobile-caption-select"]');
        const syncBtn = container.querySelector('[data-region="lp-mobile-progress-sync"]');
        const form = syncBtn ? document.getElementById(String(syncBtn.getAttribute('form') || '')) : null;
        if (!audio) {
            return;
        }

        const trackElements = Array.from(audio.querySelectorAll('track[kind="subtitles"]'));
        let selectedTrack = null;
        let selectedUrl = String((select && select.value) || audio.dataset.captionUrl || '');
        let lasttime = 0;
        let lasteditted = 0;
        let pendingdelta = 0;
        let syncinflight = false;
        const lastposition = asInt(audio.dataset.lastposition, 0);
        const durationinitial = Number.isFinite(audio.duration) ? Math.floor(audio.duration) : 0;

        const formField = (name) => form ? form.querySelector('input[name="' + name + '"]') : null;
        const setFormValue = (name, value) => {
            const field = formField(name);
            if (field) {
                field.value = String(value);
            }
        };
        const getDuration = () => {
            const val = Number(audio.duration || 0);
            if (!Number.isFinite(val) || val <= 0) {
                return 0;
            }
            return Math.floor(val);
        };
        const queueSync = (state = 'playing') => {
            if (!form || !syncBtn || syncinflight) {
                return;
            }
            const position = Math.max(0, Math.floor(Number(audio.currentTime || 0)));
            setFormValue('positionsecs', position);
            setFormValue('advanceddelta', pendingdelta > 0 ? pendingdelta.toFixed(2) : '0');
            setFormValue('durationsecs', getDuration());
            setFormValue('playstate', state);

            pendingdelta = 0;
            syncinflight = true;
            try {
                syncBtn.click();
            } catch (e) {
                // Ignore click failures; keep queue for next chance.
            } finally {
                window.setTimeout(() => {
                    syncinflight = false;
                }, 800);
            }
        };

        const disableAllTracks = () => {
            const textTracks = audio.textTracks || [];
            for (let i = 0; i < textTracks.length; i += 1) {
                textTracks[i].mode = 'disabled';
            }
        };

        const findTrackByUrl = (url) => {
            const matchurl = String(url || '').trim();
            if (!matchurl) {
                return null;
            }
            for (let i = 0; i < trackElements.length; i += 1) {
                const source = String(trackElements[i].getAttribute('src') || '');
                if (source === matchurl) {
                    const textTrack = audio.textTracks && audio.textTracks[i] ? audio.textTracks[i] : null;
                    return textTrack;
                }
            }
            return null;
        };

        const syncSelectedTrack = () => {
            if (!selectedUrl) {
                selectedTrack = null;
                disableAllTracks();
                return;
            }
            const found = findTrackByUrl(selectedUrl);
            if (found) {
                disableAllTracks();
                selectedTrack = found;
                // "showing" is more reliable than "hidden" for activeCues in iOS webviews.
                selectedTrack.mode = 'showing';
            }
        };

        const renderCue = () => {
            if (!live) {
                return;
            }
            if (!selectedTrack || selectedTrack.mode === 'disabled') {
                syncSelectedTrack();
            }
            if (!selectedTrack || selectedTrack.mode === 'disabled') {
                live.textContent = '';
                live.hidden = true;
                return;
            }
            const active = selectedTrack.activeCues;
            if (!active || !active.length) {
                live.textContent = '';
                live.hidden = true;
                return;
            }
            const cue = active[0];
            const text = String((cue && cue.text) || '').trim();
            if (!text) {
                live.textContent = '';
                live.hidden = true;
                return;
            }
            live.textContent = text;
            live.hidden = false;
        };

        const setSelectedTrack = (url) => {
            selectedUrl = String(url || '').trim();
            disableAllTracks();
            selectedTrack = findTrackByUrl(selectedUrl);
            if (selectedTrack) {
                selectedTrack.mode = 'showing';
            }
            renderCue();
        };

        if (select) {
            select.addEventListener('change', () => {
                setSelectedTrack(select.value);
            });
        }

        // Restore last saved position once metadata is available.
        if (lastposition > 0) {
            const applyResume = () => {
                const duration = getDuration();
                if (duration > 0) {
                    audio.currentTime = Math.min(lastposition, Math.max(0, duration - 1));
                    lasttime = Number(audio.currentTime || 0);
                }
            };
            if (durationinitial > 0) {
                applyResume();
            } else {
                audio.addEventListener('loadedmetadata', applyResume, {once: true});
            }
        }

        audio.addEventListener('timeupdate', renderCue);
        audio.addEventListener('play', renderCue);
        audio.addEventListener('loadedmetadata', () => {
            if (select && live) {
                syncSelectedTrack();
            }
            renderCue();
            lasttime = Number(audio.currentTime || 0);
        });
        audio.addEventListener('canplay', () => {
            if (select && live) {
                syncSelectedTrack();
            }
            renderCue();
        });
        audio.addEventListener('timeupdate', () => {
            const now = Number(audio.currentTime || 0);
            const delta = now - lasttime;
            if (delta > 0 && delta < 12) {
                pendingdelta += delta;
            }
            lasttime = now;

            const ts = Date.now();
            if (pendingdelta >= 8 || (ts - lasteditted) > 15000) {
                queueSync('playing');
                lasteditted = ts;
            }
        });
        audio.addEventListener('seeked', () => {
            lasttime = Number(audio.currentTime || 0);
            renderCue();
        });
        audio.addEventListener('pause', () => {
            renderCue();
            queueSync('paused');
        });
        audio.addEventListener('ended', () => {
            renderCue();
            pendingdelta += 1;
            queueSync('ended');
        });

        if (select && live) {
            setSelectedTrack(selectedUrl);
        }
    };

    document.querySelectorAll('[data-region="lp-mobile-episode-content"]').forEach((container) => {
        setupEpisodeCaptions(container);
    });
})();
JS;
    }
}
