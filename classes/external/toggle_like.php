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
use mod_learnplugpodcasts\local\service\like_service;

/**
 * Toggle like for an episode.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_like extends external_api {
    /**
     * Input params.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id', VALUE_DEFAULT, 0),
            'episodeid' => new external_value(PARAM_INT, 'Episode id'),
        ]);
    }

    /**
     * Execute endpoint.
     *
     * @param int $cmid Optional course module id (0 will resolve from episode)
     * @param int $episodeid
     * @return array
     */
    public static function execute(int $cmid, int $episodeid): array {
        global $DB, $USER;

        $wstoken = optional_param('wstoken', '', PARAM_ALPHANUMEXT);
        if ($wstoken === '') {
            require_sesskey();
        }
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'episodeid' => $episodeid,
        ]);

        $episode = (new episode_service())->get_by_id($params['episodeid']);
        if (!$episode) {
            throw new \invalid_parameter_exception(get_string('errornoepisode', 'learnplugpodcasts'));
        }

        if ((int)$params['cmid'] > 0) {
            $cm = get_coursemodule_from_id('learnplugpodcasts', $params['cmid'], 0, false, MUST_EXIST);
        } else {
            $cm = get_coursemodule_from_instance(
                'learnplugpodcasts',
                (int)$episode->podcastid,
                0,
                false,
                MUST_EXIST
            );
        }

        $course = get_course($cm->course);
        require_login($course, false, $cm);

        if (isguestuser()) {
            throw new \invalid_parameter_exception(get_string('errorcapability', 'learnplugpodcasts'));
        }

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/learnplugpodcasts:view', $context);

        $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);
        if ((int)$episode->podcastid !== (int)$podcast->id) {
            throw new \invalid_parameter_exception(get_string('errornoepisode', 'learnplugpodcasts'));
        }

        $result = (new like_service())->toggle_like(
            (int)$podcast->id,
            (int)$episode->id,
            (int)$USER->id
        );

        return [
            'liked' => (int)$result['liked'],
            'likecount' => (int)$result['likecount'],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'liked' => new external_value(PARAM_INT, '1 when liked, else 0'),
            'likecount' => new external_value(PARAM_INT, 'Current like count'),
        ]);
    }
}
