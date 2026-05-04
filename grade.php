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
 * Grade report page for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_learnplugpodcasts\local\repository\progress_repository;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('learnplugpodcasts', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/learnplugpodcasts:view', $context);

$PAGE->set_url('/mod/learnplugpodcasts/grade.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($podcast->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$progressrepo = new progress_repository();
$canviewreports = has_capability('mod/learnplugpodcasts:viewreports', $context);

if (learnplugpodcasts_has_grading_enabled($podcast)) {
    learnplugpodcasts_update_grades($podcast);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewreports', 'learnplugpodcasts'));

if (!learnplugpodcasts_has_grading_enabled($podcast)) {
    echo $OUTPUT->notification(get_string('gradebookdisabled', 'learnplugpodcasts'), 'info');
}

if ($canviewreports) {
    $rows = $progressrepo->report_rows((int)$podcast->id);
    $gradebookgrades = [];
    if ($rows && learnplugpodcasts_has_grading_enabled($podcast)) {
        $userids = array_values(array_map(static fn($row) => (int)$row->userid, $rows));
        $gradeinfo = grade_get_grades(
            (int)$course->id,
            'mod',
            'learnplugpodcasts',
            (int)$podcast->id,
            $userids
        );
        $item = $gradeinfo->items[0] ?? null;
        if (!empty($item->grades)) {
            foreach ($item->grades as $userid => $grade) {
                $gradebookgrades[(int)$userid] = is_null($grade->finalgrade) ? null : (float)$grade->finalgrade;
            }
        }
    }

    if (!$rows) {
        echo $OUTPUT->notification(get_string('noprogress', 'learnplugpodcasts'), 'info');
    } else {
        $table = new html_table();
        $table->head = [
            get_string('user'),
            get_string('completionlistenpercent', 'learnplugpodcasts'),
            get_string('durationsecs', 'learnplugpodcasts'),
            get_string('lastaccess'),
            get_string('gradeheader', 'learnplugpodcasts'),
        ];
        foreach ($rows as $row) {
            $userid = (int)$row->userid;
            $grade = $gradebookgrades[$userid] ?? null;
            $table->data[] = [
                fullname($row),
                round((float)$row->avgpercent, 2) . '%',
                (int)$row->totalsecs,
                userdate((int)$row->lastactivity),
                is_null($grade) ? '-' : round($grade, 2),
            ];
        }
        echo html_writer::table($table);
    }
} else {
    $records = $DB->get_records('learnplugpodcasts_prog', ['podcastid' => $podcast->id, 'userid' => $USER->id]);
    if (!$records) {
        echo $OUTPUT->notification(get_string('noprogress', 'learnplugpodcasts'), 'info');
    } else {
        $table = new html_table();
        $table->head = [
            get_string('episode', 'learnplugpodcasts'),
            get_string('completionlistenpercent', 'learnplugpodcasts'),
            get_string('completionstatus', 'learnplugpodcasts'),
            get_string('lastaccess'),
        ];
        foreach ($records as $record) {
            $episode = $DB->get_record('learnplugpodcasts_eps', ['id' => $record->episodeid], 'id,title', MUST_EXIST);
            $table->data[] = [
                format_string($episode->title),
                round((float)$record->listenedpercent, 2) . '%',
                !empty($record->completed) ?
                    get_string('completionstatus_complete', 'learnplugpodcasts') :
                    get_string('completionstatus_incomplete', 'learnplugpodcasts'),
                userdate((int)$record->timemodified),
            ];
        }
        echo html_writer::table($table);
    }
}

echo $OUTPUT->single_button(new moodle_url('/mod/learnplugpodcasts/view.php', ['id' => $cm->id]), get_string('back'));
echo $OUTPUT->footer();
