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

namespace mod_learnplugpodcasts\task;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_metadata extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_refresh_metadata', 'learnplugpodcasts');
    }

    /**
     * Task execution.
     */
    public function execute(): void {
        global $DB;

        $fields = 'id,title,slug,publishtime,timemodified,draftstatus,rssitemhash';
        $episodes = $DB->get_records('learnplugpodcasts_eps', null, '', $fields);
        foreach ($episodes as $episode) {
            $hash = sha1(implode('|', [
                $episode->title,
                $episode->slug,
                $episode->publishtime,
                $episode->timemodified,
                $episode->draftstatus,
            ]));
            if ($episode->rssitemhash !== $hash) {
                $DB->set_field('learnplugpodcasts_eps', 'rssitemhash', $hash, ['id' => $episode->id]);
            }
        }
    }
}
