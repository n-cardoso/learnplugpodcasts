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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/learnplugpodcasts/backup/moodle2/restore_learnplugpodcasts_stepslib.php');

/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_learnplugpodcasts_activity_task extends restore_activity_task {
    /**
     * No custom settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines restore steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_learnplugpodcasts_activity_structure_step('learnplugpodcasts_structure',
            'learnplugpodcasts.xml'));
    }

    /**
     * Content decode.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('learnplugpodcasts', ['intro'], 'learnplugpodcasts'),
            new restore_decode_content('learnplugpodcasts_eps', ['description', 'transcripttext'], 'learnplugpodcasts_eps'),
        ];
    }

    /**
     * Link decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('LEARNPLUGPODCASTSINDEX', '/mod/learnplugpodcasts/index.php?id=$1', 'course'),
            new restore_decode_rule('LEARNPLUGPODCASTSVIEWBYID', '/mod/learnplugpodcasts/view.php?id=$1', 'course_module'),
        ];
    }

    /**
     * Restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules(): array {
        return [
            new restore_log_rule('learnplugpodcasts', 'add', 'view.php?id={course_module}', '{learnplugpodcasts}'),
            new restore_log_rule('learnplugpodcasts', 'update', 'view.php?id={course_module}', '{learnplugpodcasts}'),
            new restore_log_rule('learnplugpodcasts', 'view', 'view.php?id={course_module}', '{learnplugpodcasts}'),
        ];
    }

    /**
     * Course log restore rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course(): array {
        return [
            new restore_log_rule('learnplugpodcasts', 'view all', 'index.php?id={course}', null),
        ];
    }
}
