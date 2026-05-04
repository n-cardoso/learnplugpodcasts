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

/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_publish extends external_api {
    /**
     * Params.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'episodeid' => new external_value(PARAM_INT, 'Episode id'),
            'publish' => new external_value(PARAM_BOOL, 'Publish when true, unpublish when false'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $episodeid
     * @param bool $publish
     * @return array
     */
    public static function execute(int $cmid, int $episodeid, bool $publish): array {
        global $DB;

        require_sesskey();

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'episodeid' => $episodeid,
            'publish' => $publish,
        ]);

        $cm = get_coursemodule_from_id('learnplugpodcasts', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_login($course, false, $cm);

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/learnplugpodcasts:manageepisodes', $context);
        require_capability('mod/learnplugpodcasts:publish', $context);

        $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);

        $episodeservice = new episode_service();
        $episode = $episodeservice->get_by_id($params['episodeid']);
        if (!$episode || (int)$episode->podcastid !== (int)$podcast->id) {
            throw new \invalid_parameter_exception(get_string('errornoepisode', 'learnplugpodcasts'));
        }

        $episode = $episodeservice->set_published($podcast, $context, $episode, $params['publish']);

        return [
            'status' => $episode->draftstatus,
            'published' => (int)($episode->draftstatus === episode_service::STATUS_PUBLISHED),
        ];
    }

    /**
     * Return structure.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Current publication status'),
            'published' => new external_value(PARAM_INT, '1 if published, else 0'),
        ]);
    }
}
