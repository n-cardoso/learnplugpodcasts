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

namespace mod_learnplugpodcasts\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_learnplugpodcasts\local\service\episode_service;

/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_episodes extends external_api {
    /**
     * Params.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search string', VALUE_DEFAULT, ''),
            'sort' => new external_value(PARAM_ALPHA, 'Sort mode', VALUE_DEFAULT, 'newest'),
            'page' => new external_value(PARAM_INT, 'Page index', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param string $query
     * @param string $sort
     * @param int $page
     * @return array
     */
    public static function execute(int $cmid, string $query = '', string $sort = 'newest', int $page = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'query' => $query,
            'sort' => $sort,
            'page' => $page,
        ]);

        $cm = get_coursemodule_from_id('learnplugpodcasts', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_login($course, false, $cm);

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/learnplugpodcasts:view', $context);

        $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);
        $episodeservice = new episode_service();
        $episodes = $episodeservice->get_for_display(
            (int)$podcast->id,
            true,
            $params['sort'] === 'oldest' ? 'oldest' : 'newest',
            max(0, $params['page']),
            max(1, (int)$podcast->episodesperpage),
            $params['query']
        );

        $rows = [];
        foreach ($episodes as $episode) {
            $audio = $episodeservice->get_episode_audio_file($context, (int)$episode->id);
            $audiourl = '';
            if ($audio) {
                $audiourl = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_learnplugpodcasts',
                    'episodeaudio',
                    $episode->id,
                    $audio->get_filepath(),
                    $audio->get_filename()
                )->out(false);
            }

            $progress = $DB->get_record('learnplugpodcasts_prog', ['episodeid' => $episode->id, 'userid' => $USER->id]);

            $rows[] = [
                'id' => (int)$episode->id,
                'title' => format_string($episode->title),
                'subtitle' => format_string((string)$episode->subtitle),
                'description' => format_text($episode->description, $episode->descriptionformat, ['context' => $context]),
                'audiourl' => $audiourl,
                'publishtime' => (int)$episode->publishtime,
                'durationsecs' => (int)$episode->durationsecs,
                'listenedpercent' => (float)($progress->listenedpercent ?? 0),
                'lastpositionsecs' => (int)($progress->lastpositionsecs ?? 0),
            ];
        }

        return ['episodes' => $rows];
    }

    /**
     * Return structure.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'episodes' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Episode ID'),
                    'title' => new external_value(PARAM_TEXT, 'Title'),
                    'subtitle' => new external_value(PARAM_TEXT, 'Subtitle'),
                    'description' => new external_value(PARAM_RAW, 'Description HTML'),
                    'audiourl' => new external_value(PARAM_URL, 'Audio URL', VALUE_DEFAULT, ''),
                    'publishtime' => new external_value(PARAM_INT, 'Publish timestamp'),
                    'durationsecs' => new external_value(PARAM_INT, 'Duration in seconds'),
                    'listenedpercent' => new external_value(PARAM_FLOAT, 'Listened percent'),
                    'lastpositionsecs' => new external_value(PARAM_INT, 'Last position in seconds'),
                ])
            ),
        ]);
    }
}
