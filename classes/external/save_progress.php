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
use core_external\external_single_structure;
use core_external\external_value;
use mod_learnplugpodcasts\local\service\episode_service;
use mod_learnplugpodcasts\local\service\progress_service;

/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_progress extends external_api {
    /**
     * Params.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'episodeid' => new external_value(PARAM_INT, 'Episode id'),
            'positionsecs' => new external_value(PARAM_INT, 'Playback position in seconds'),
            'advanceddelta' => new external_value(PARAM_FLOAT, 'Advancing playback delta in seconds'),
            'durationsecs' => new external_value(PARAM_INT, 'Episode duration in seconds'),
            'playstate' => new external_value(PARAM_ALPHA, 'Playback state', VALUE_DEFAULT, 'playing'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $episodeid
     * @param int $positionsecs
     * @param float $advanceddelta
     * @param int $durationsecs
     * @param string $playstate
     * @return array
     */
    public static function execute(
        int $cmid,
        int $episodeid,
        int $positionsecs,
        float $advanceddelta,
        int $durationsecs,
        string $playstate = 'playing'
    ): array {
        global $DB, $USER;

        require_sesskey();

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'episodeid' => $episodeid,
            'positionsecs' => $positionsecs,
            'advanceddelta' => $advanceddelta,
            'durationsecs' => $durationsecs,
            'playstate' => $playstate,
        ]);

        $cm = get_coursemodule_from_id('learnplugpodcasts', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_login($course, false, $cm);

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/learnplugpodcasts:view', $context);

        $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);
        $episodeservice = new episode_service();
        $episode = $episodeservice->get_by_id($params['episodeid']);
        if (!$episode || (int)$episode->podcastid !== (int)$podcast->id) {
            throw new \invalid_parameter_exception(get_string('errornoepisode', 'learnplugpodcasts'));
        }

        $progressservice = new progress_service();
        $saved = $progressservice->save_progress(
            $podcast,
            $episode,
            $context,
            (int)$USER->id,
            $params['positionsecs'],
            $params['advanceddelta'],
            $params['durationsecs'],
            $params['playstate']
        );

        return [
            'listenedpercent' => (float)$saved->listenedpercent,
            'lastpositionsecs' => (int)$saved->lastpositionsecs,
            'completed' => (int)$saved->completed,
        ];
    }

    /**
     * Return structure.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'listenedpercent' => new external_value(PARAM_FLOAT, 'Current listened percent'),
            'lastpositionsecs' => new external_value(PARAM_INT, 'Saved position'),
            'completed' => new external_value(PARAM_INT, 'Completed flag 0/1'),
        ]);
    }
}
