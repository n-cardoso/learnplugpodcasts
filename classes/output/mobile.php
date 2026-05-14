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
            $coverimage = self::to_mobile_media_url($coverimage);
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
                $imageurl = self::to_mobile_media_url($imageurl);
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
            $audiourl = self::to_mobile_media_url($audiourl);

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
            foreach ($captiontracks as $index => $track) {
                $captiontracks[$index]['url'] = self::to_mobile_media_url((string)($track['url'] ?? ''));
                $captiontracks[$index]['cuesb64'] = self::get_caption_track_cues_b64(
                    $context,
                    (int)$episode->id,
                    (string)($track['filename'] ?? '')
                );
            }
            $primarycaption = $captionservice->get_primary_caption_track($context, (int)$episode->id);
            $primarycaptionurl = self::to_mobile_media_url((string)($primarycaption['url'] ?? ''));
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
    document.addEventListener('play', (event) => {
        const target = event && event.target ? event.target : null;
        if (!(target instanceof HTMLAudioElement)) {
            return;
        }
        document.querySelectorAll('audio').forEach((audio) => {
            if (audio !== target && !audio.paused) {
                audio.pause();
            }
        });
    }, true);

    const asInt = (value, fallback = 0) => {
        const num = parseInt(String(value ?? ''), 10);
        return Number.isFinite(num) ? num : fallback;
    };

    const parseTimestampToSeconds = (raw) => {
        const text = String(raw || '').trim().replace(',', '.');
        const match = text.match(/^((\d+):)?(\d{1,2}):(\d{2})(\.\d+)?$/);
        if (!match) {
            return NaN;
        }
        const hours = Number(match[2] || 0);
        const minutes = Number(match[3] || 0);
        const seconds = Number(match[4] || 0);
        const fraction = Number(match[5] || 0);
        return (hours * 3600) + (minutes * 60) + seconds + fraction;
    };

        const parseVtt = (content) => {
        const text = String(content || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const blocks = text.split(/\n{2,}/);
        const cues = [];

        blocks.forEach((block) => {
            const lines = block.split('\n')
                .map((line) => line.trim())
                .filter((line) => line !== '');
            if (!lines.length) {
                return;
            }
            if (/^WEBVTT/i.test(lines[0]) || /^NOTE/i.test(lines[0])) {
                return;
            }

            let timinglineindex = 0;
            if (!lines[0].includes('-->')) {
                timinglineindex = 1;
            }
            const timingline = lines[timinglineindex] || '';
            if (!timingline.includes('-->')) {
                return;
            }

            const parts = timingline.split('-->');
            const starttext = String(parts[0] || '').trim().split(/\s+/)[0];
            const endtext = String(parts[1] || '').trim().split(/\s+/)[0];
            const start = parseTimestampToSeconds(starttext);
            const end = parseTimestampToSeconds(endtext);
            if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
                return;
            }

            const payload = lines.slice(timinglineindex + 1).join('\n').trim();
            if (!payload) {
                return;
            }

            cues.push({
                start: start,
                end: end,
                text: payload.replace(/<[^>]+>/g, ''),
            });
        });

        return cues;
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
            let selectedTrackElement = null;
            let selectedUrl = String((select && select.value) || audio.dataset.captionUrl || '');
            let captionCues = [];
            const optionCueMap = new Map();
            let captionLoadToken = 0;
            let captionRenderTimer = 0;
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
            setFormValue('cmid', asInt(audio.dataset.cmid, 0));
            setFormValue('episodeid', asInt(audio.dataset.episodeid, 0));
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

        const normalizeTrackUrl = (url) => {
            const raw = String(url || '').trim();
            if (!raw) {
                return '';
            }
            const value = raw.replace('/pluginfile.php/', '/webservice/pluginfile.php/');
            const withoutQuery = value.split('?')[0];
            return withoutQuery.replace(/\/+$/, '');
        };

        const urlsEquivalent = (a, b) => {
            const left = normalizeTrackUrl(a);
            const right = normalizeTrackUrl(b);
            return !!left && !!right && left === right;
        };

            const getTrackRuntimeUrl = (trackelement) => {
                if (!trackelement) {
                    return '';
                }
                return String(trackelement.src || trackelement.getAttribute('src') || '').trim();
            };

            const decodeBase64Json = (raw) => {
                const value = String(raw || '').trim();
                if (!value) {
                    return [];
                }
                try {
                    const decoded = atob(value);
                    const parsed = JSON.parse(decoded);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            };

            const registerOptionCues = () => {
                if (!select) {
                    return;
                }
                Array.from(select.options).forEach((option) => {
                    const value = String(option.value || '').trim();
                    if (!value) {
                        return;
                    }
                    const cues = decodeBase64Json(option.getAttribute('data-cues'));
                    if (cues.length) {
                        optionCueMap.set(normalizeTrackUrl(value), cues);
                    }
                });
            };

        const findTrackByUrl = (url) => {
            const matchurl = String(url || '').trim();
            if (!matchurl) {
                return null;
            }
            for (let i = 0; i < trackElements.length; i += 1) {
                const sourceattr = String(trackElements[i].getAttribute('src') || '');
                const sourceruntime = getTrackRuntimeUrl(trackElements[i]);
                if (urlsEquivalent(sourceattr, matchurl) || urlsEquivalent(sourceruntime, matchurl)) {
                    const textTrack = audio.textTracks && audio.textTracks[i] ? audio.textTracks[i] : null;
                    return {
                        textTrack: textTrack,
                        element: trackElements[i],
                    };
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
                selectedTrack = found.textTrack;
                selectedTrackElement = found.element;
                // "showing" is more reliable than "hidden" for activeCues in iOS webviews.
                selectedTrack.mode = 'showing';
            } else {
                selectedTrack = null;
                selectedTrackElement = null;
            }
        };

            const loadCaptionTrack = (url, trackelement = null) => {
                const trackurl = String(url || '').trim();
                captionLoadToken += 1;
                const token = captionLoadToken;
                const urlkey = normalizeTrackUrl(trackurl);
                if (urlkey && optionCueMap.has(urlkey)) {
                    captionCues = optionCueMap.get(urlkey) || [];
                    renderCue();
                    return;
                }

                captionCues = [];
                renderCue();

            const runtimeurl = getTrackRuntimeUrl(trackelement);
            const fetchurl = runtimeurl || trackurl;
            if (!fetchurl) {
                return;
            }

            fetch(fetchurl, {credentials: 'include'})
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Caption track fetch failed');
                    }
                    return response.text();
                })
                .then((content) => {
                    if (token !== captionLoadToken) {
                        return null;
                    }
                    captionCues = parseVtt(content);
                    renderCue();
                    return null;
                })
                .catch(() => {
                    if (token !== captionLoadToken) {
                        return null;
                    }
                    captionCues = [];
                    renderCue();
                    return null;
                });
        };

        const renderCue = () => {
            if (!live) {
                return;
            }
            if (captionCues.length > 0) {
                const now = Number(audio.currentTime || 0);
                const cue = captionCues.find((item) => now >= item.start && now <= item.end);
                if (!cue || !String(cue.text || '').trim()) {
                    live.textContent = '';
                    live.hidden = true;
                    return;
                }
                live.textContent = String(cue.text).trim();
                live.hidden = false;
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
            const match = findTrackByUrl(selectedUrl);
            selectedTrack = match ? match.textTrack : null;
            selectedTrackElement = match ? match.element : null;
            if (selectedTrack) {
                selectedTrack.mode = 'showing';
            }
            loadCaptionTrack(selectedUrl, selectedTrackElement);
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
        audio.addEventListener('play', () => {
            document.querySelectorAll('[data-region="lp-mobile-audio"]').forEach((otheraudio) => {
                if (otheraudio !== audio && !otheraudio.paused) {
                    otheraudio.pause();
                }
            });
            if (!captionRenderTimer) {
                captionRenderTimer = window.setInterval(renderCue, 250);
            }
            renderCue();
        });
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
            if (captionRenderTimer) {
                window.clearInterval(captionRenderTimer);
                captionRenderTimer = 0;
            }
            queueSync('paused');
        });
        audio.addEventListener('ended', () => {
            renderCue();
            if (captionRenderTimer) {
                window.clearInterval(captionRenderTimer);
                captionRenderTimer = 0;
            }
            pendingdelta += 1;
            queueSync('ended');
        });

        if (select && live) {
            registerOptionCues();
            if (!selectedUrl && trackElements.length) {
                const firsttrack = trackElements[0];
                selectedUrl = String(firsttrack.getAttribute('src') || firsttrack.src || '').trim();
            }
            setSelectedTrack(selectedUrl);
        }
    };

    document.querySelectorAll('[data-region="lp-mobile-episode-content"]').forEach((container) => {
        setupEpisodeCaptions(container);
    });
})();
JS;
    }

    /**
     * Builds a Moodle App-safe URL for protected plugin files.
     *
     * @param string $url
     * @return string
     */
    private static function to_mobile_media_url(string $url): string {
        if ($url === '') {
            return '';
        }
        return str_replace('/pluginfile.php/', '/webservice/pluginfile.php/', $url);
    }

    /**
     * Encodes parsed cues for a caption track so mobile view can render without extra fetch calls.
     *
     * @param context_module $context
     * @param int $episodeid
     * @param string $filename
     * @return string
     */
    private static function get_caption_track_cues_b64(
        context_module $context,
        int $episodeid,
        string $filename
    ): string {
        if ($filename === '') {
            return '';
        }
        $file = get_file_storage()->get_file(
            $context->id,
            'mod_learnplugpodcasts',
            caption_service::FILEAREA,
            $episodeid,
            '/',
            $filename
        );
        if (!$file) {
            return '';
        }
        $payload = json_encode(self::parse_vtt_cues((string)$file->get_content()));
        if ($payload === false || $payload === '[]') {
            return '';
        }
        return base64_encode($payload);
    }

    /**
     * Parses VTT text into cue records for mobile timed rendering.
     *
     * @param string $content
     * @return array
     */
    private static function parse_vtt_cues(string $content): array {
        $text = str_replace(["\r\n", "\r"], "\n", $content);
        $blocks = preg_split("/\n{2,}/", $text) ?: [];
        $cues = [];
        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static function ($line): bool {
                return $line !== '';
            }));
            if (!$lines) {
                continue;
            }
            if (preg_match('/^(WEBVTT|NOTE)/i', (string)$lines[0])) {
                continue;
            }

            $timinglineindex = str_contains((string)$lines[0], '-->') ? 0 : 1;
            if (!isset($lines[$timinglineindex]) || !str_contains((string)$lines[$timinglineindex], '-->')) {
                continue;
            }
            [$rawstart, $rawend] = array_pad(explode('-->', (string)$lines[$timinglineindex], 2), 2, '');
            $start = self::parse_vtt_timestamp_to_seconds(trim((string)preg_split('/\s+/', trim($rawstart))[0]));
            $end = self::parse_vtt_timestamp_to_seconds(trim((string)preg_split('/\s+/', trim($rawend))[0]));
            if ($start === null || $end === null || $end <= $start) {
                continue;
            }
            $payload = trim(strip_tags(implode("\n", array_slice($lines, $timinglineindex + 1))));
            if ($payload === '') {
                continue;
            }
            $cues[] = [
                'start' => $start,
                'end' => $end,
                'text' => $payload,
            ];
        }
        return $cues;
    }

    /**
     * Parses one VTT timestamp into seconds.
     *
     * @param string $raw
     * @return float|null
     */
    private static function parse_vtt_timestamp_to_seconds(string $raw): ?float {
        $text = str_replace(',', '.', trim($raw));
        if (!preg_match('/^((\d+):)?(\d{1,2}):(\d{2})(\.\d+)?$/', $text, $matches)) {
            return null;
        }
        $hours = (int)($matches[2] ?? 0);
        $minutes = (int)($matches[3] ?? 0);
        $seconds = (int)($matches[4] ?? 0);
        $fraction = isset($matches[5]) ? (float)$matches[5] : 0.0;
        return ($hours * 3600) + ($minutes * 60) + $seconds + $fraction;
    }
}
