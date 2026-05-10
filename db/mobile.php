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

/**
 * Moodle App support for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_learnplugpodcasts' => [
        'handlers' => [
            'coursecontent' => [
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/learnplugpodcasts/pix/icon.svg',
                    'class' => '',
                ],
                'offlinefunctions' => [
                    'mobile_course_view' => [],
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'learnplugpodcasts'],
            ['episodes', 'learnplugpodcasts'],
            ['audiofile', 'learnplugpodcasts'],
            ['captiontrackselect', 'learnplugpodcasts'],
            ['learnerlistempty', 'learnplugpodcasts'],
            ['likeepisode', 'learnplugpodcasts'],
            ['likescount', 'learnplugpodcasts'],
            ['unlikeepisode', 'learnplugpodcasts'],
            ['offlineavailable', 'learnplugpodcasts'],
            ['downloadallepisodes', 'learnplugpodcasts'],
            ['downloadthisepisode', 'learnplugpodcasts'],
            ['offlinelisteninghint', 'learnplugpodcasts'],
        ],
    ],
];
