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
use mod_learnplugpodcasts\local\service\episode_service;
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
        global $DB, $OUTPUT;

        $args = (object)$args;
        $cm = get_coursemodule_from_id('learnplugpodcasts', $args->cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);

        require_login($course, false, $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability('mod/learnplugpodcasts:view', $context);

        $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);
        $canmanage = has_capability('mod/learnplugpodcasts:manageepisodes', $context);
        $episodeservice = new episode_service();
        $prefetchfiles = [];
        $downloadallfiles = [];

        $episodes = $episodeservice->get_for_display(
            (int)$podcast->id,
            !$canmanage,
            (string)$podcast->defaultsort ?: 'newest',
            0,
            200
        );

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

            $episodeitems[] = [
                'id' => (int)$episode->id,
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
                'audiodownloadjson' => json_encode($downloadfile),
                'imageurl' => $imageurl,
            ];
            $prefetchfiles[] = $downloadfile;
            $downloadallfiles[] = $downloadfile;
        }

        $data = [
            'cmid' => (int)$cm->id,
            'courseid' => (int)$course->id,
            'podcastname' => format_string($podcast->name),
            'podcastintro' => format_module_intro('learnplugpodcasts', $podcast, $cm->id),
            'coverimage' => $coverimage,
            'episodes' => $episodeitems,
            'hasepisodes' => !empty($episodeitems),
            'downloadfiles' => array_values($prefetchfiles),
            'downloadfilesjson' => json_encode(array_values($downloadallfiles)),
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_learnplugpodcasts/mobileapp/mobile_view_page', $data),
                ],
            ],
            'files' => array_values($prefetchfiles),
        ];
    }
}
