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
 * Course activity index for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('mod/learnplugpodcasts:view', $context);

$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/learnplugpodcasts/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'learnplugpodcasts'));
$PAGE->set_heading(format_string($course->fullname));

$instances = get_all_instances_in_course('learnplugpodcasts', $course);

$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('episodes', 'learnplugpodcasts'),
    get_string('publicurl', 'learnplugpodcasts'),
];
$table->data = [];

foreach ($instances as $instance) {
    $episodes = $DB->count_records('learnplugpodcasts_eps', ['podcastid' => $instance->id]);
    $publicurl = '';
    if (!empty($instance->publicenabled) && !empty(get_config('mod_learnplugpodcasts', 'enablepublicpages'))) {
        $publicurl = html_writer::link(
            new moodle_url('/mod/learnplugpodcasts/public.php', ['id' => $instance->coursemodule]),
            get_string('publicurl', 'learnplugpodcasts')
        );
    }

    $table->data[] = [
        html_writer::link(new moodle_url('/mod/learnplugpodcasts/view.php', ['id' => $instance->coursemodule]),
            format_string($instance->name)),
        $episodes,
        $publicurl,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'learnplugpodcasts'));
if ($table->data) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nonewmodules', 'moodle'), 'info');
}
echo $OUTPUT->footer();
