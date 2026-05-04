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

require_once($CFG->dirroot . '/mod/learnplugpodcasts/backup/moodle2/backup_learnplugpodcasts_stepslib.php');

/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_learnplugpodcasts_activity_task extends backup_activity_task {
    /**
     * No specific settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines backup steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_learnplugpodcasts_activity_structure_step('learnplugpodcasts_structure',
            'learnplugpodcasts.xml'));
    }

    /**
     * File annotations.
     *
     * @return array
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = "/({$base}\\/mod\\/learnplugpodcasts\\/index.php\\?id=)([0-9]+)/";
        $content = preg_replace($search, '$@LEARNPLUGPODCASTSINDEX*$2@$', $content);

        $search = "/({$base}\\/mod\\/learnplugpodcasts\\/view.php\\?id=)([0-9]+)/";
        $content = preg_replace($search, '$@LEARNPLUGPODCASTSVIEWBYID*$2@$', $content);

        return $content;
    }
}
