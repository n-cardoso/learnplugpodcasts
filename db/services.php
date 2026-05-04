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
 * External service definitions for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_learnplugpodcasts_search_episodes' => [
        'classname' => 'mod_learnplugpodcasts\\external\\search_episodes',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Searches published episodes in a podcast activity.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/learnplugpodcasts:view',
    ],
    'mod_learnplugpodcasts_save_progress' => [
        'classname' => 'mod_learnplugpodcasts\\external\\save_progress',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Saves learner listening progress.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/learnplugpodcasts:view',
    ],
    'mod_learnplugpodcasts_toggle_publish' => [
        'classname' => 'mod_learnplugpodcasts\\external\\toggle_publish',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Publishes or unpublishes an episode.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/learnplugpodcasts:publish',
    ],
    'mod_learnplugpodcasts_reorder_episodes' => [
        'classname' => 'mod_learnplugpodcasts\\external\\reorder_episodes',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Reorders episodes in a podcast.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/learnplugpodcasts:manageepisodes',
    ],
];

$services = [];
