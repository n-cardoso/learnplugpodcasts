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

use mod_learnplugpodcasts\local\service\analytics_service;
use mod_learnplugpodcasts\local\repository\progress_repository;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

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
$analyticsservice = new analytics_service();
$canviewreports = has_capability('mod/learnplugpodcasts:viewreports', $context);
$canmanageprogress = $canviewreports && has_capability('mod/learnplugpodcasts:manageepisodes', $context);
$baseurl = new moodle_url('/mod/learnplugpodcasts/grade.php', ['id' => $cm->id]);

if (learnplugpodcasts_has_grading_enabled($podcast)) {
    learnplugpodcasts_update_grades($podcast);
}

$cleargrades = static function (array $userids) use ($podcast): void {
    if (!learnplugpodcasts_has_grading_enabled($podcast) || empty($userids)) {
        return;
    }

    $grades = [];
    foreach (array_unique($userids) as $useridtoclear) {
        $grades[(int)$useridtoclear] = (object)[
            'userid' => (int)$useridtoclear,
            'rawgrade' => null,
        ];
    }
    learnplugpodcasts_grade_item_update($podcast, $grades);
};

if ($action !== '') {
    require_sesskey();

    if (!$canmanageprogress) {
        throw new required_capability_exception(
            $context,
            'mod/learnplugpodcasts:manageepisodes',
            'nopermissions',
            ''
        );
    }

    if ($action === 'resetallprogress') {
        if (!$confirm) {
            $confirmurl = new moodle_url($baseurl, [
                'action' => 'resetallprogress',
                'confirm' => 1,
                'sesskey' => sesskey(),
            ]);
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('viewreports', 'learnplugpodcasts'));
            echo $OUTPUT->confirm(
                get_string('resetprogressconfirmall', 'learnplugpodcasts'),
                $confirmurl,
                $baseurl
            );
            echo $OUTPUT->footer();
            exit;
        }

        $userids = $progressrepo->get_podcast_userids((int)$podcast->id);
        $progressrepo->reset_podcast_progress((int)$podcast->id);
        $cleargrades($userids);
        redirect($baseurl, get_string('resetprogressdoneall', 'learnplugpodcasts'));
    }

    if ($action === 'resetuserprogress') {
        $targetuser = core_user::get_user($userid, '*', MUST_EXIST);
        if (!$confirm) {
            $confirmurl = new moodle_url($baseurl, [
                'action' => 'resetuserprogress',
                'userid' => $userid,
                'confirm' => 1,
                'sesskey' => sesskey(),
            ]);
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('viewreports', 'learnplugpodcasts'));
            echo $OUTPUT->confirm(
                get_string('resetprogressconfirmuser', 'learnplugpodcasts', fullname($targetuser)),
                $confirmurl,
                $baseurl
            );
            echo $OUTPUT->footer();
            exit;
        }

        $progressrepo->reset_user_progress((int)$podcast->id, $userid);
        $cleargrades([$userid]);
        redirect($baseurl, get_string('resetprogressdoneuser', 'learnplugpodcasts', fullname($targetuser)));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewreports', 'learnplugpodcasts'));

if (!learnplugpodcasts_has_grading_enabled($podcast)) {
    echo $OUTPUT->notification(get_string('gradebookdisabled', 'learnplugpodcasts'), 'info');
}

if ($canviewreports) {
    $enrolledcount = (int)count_enrolled_users($context, 'mod/learnplugpodcasts:view');
    $analytics = $analyticsservice->get_report_data($podcast, $enrolledcount);
    echo $OUTPUT->render_from_template('mod_learnplugpodcasts/analytics', [
        'analytics' => $analytics,
        'hasanalyticsrows' => !empty($analytics['hasrows']),
    ]);

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
                $numericgrade = null;
                if (property_exists($grade, 'finalgrade') && is_numeric($grade->finalgrade)) {
                    $numericgrade = (float)$grade->finalgrade;
                } else if (property_exists($grade, 'grade') && is_numeric($grade->grade)) {
                    $numericgrade = (float)$grade->grade;
                } else if (property_exists($grade, 'rawgrade') && is_numeric($grade->rawgrade)) {
                    $numericgrade = (float)$grade->rawgrade;
                }
                $gradebookgrades[(int)$userid] = $numericgrade;
            }
        }
    }

    if (!$rows) {
        echo $OUTPUT->notification(get_string('noprogress', 'learnplugpodcasts'), 'info');
    } else {
        if ($canmanageprogress) {
            echo $OUTPUT->single_button(
                new moodle_url($baseurl, [
                    'action' => 'resetallprogress',
                    'sesskey' => sesskey(),
                ]),
                get_string('resetprogressall', 'learnplugpodcasts'),
                'get'
            );
        }

        $table = new html_table();
        $table->head = [
            get_string('user'),
            get_string('completionlistenpercent', 'learnplugpodcasts'),
            get_string('durationsecs', 'learnplugpodcasts'),
            get_string('lastaccess'),
            get_string('gradeheader', 'learnplugpodcasts'),
        ];
        if ($canmanageprogress) {
            $table->head[] = get_string('actions');
        }
        foreach ($rows as $row) {
            $userid = (int)$row->userid;
            $grade = $gradebookgrades[$userid] ?? null;
            $tablerow = [
                fullname($row),
                round((float)$row->avgpercent, 2) . '%',
                (int)$row->totalsecs,
                userdate((int)$row->lastactivity),
                is_null($grade) ? '-' : round($grade, 2),
            ];
            if ($canmanageprogress) {
                $tablerow[] = $OUTPUT->single_button(
                    new moodle_url($baseurl, [
                        'action' => 'resetuserprogress',
                        'userid' => $userid,
                        'sesskey' => sesskey(),
                    ]),
                    get_string('resetprogressuser', 'learnplugpodcasts'),
                    'get'
                );
            }
            $table->data[] = $tablerow;
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

        $episodeids = [];
        foreach ($records as $record) {
            $episodeids[] = (int)$record->episodeid;
        }
        $episodes = [];
        if (!empty($episodeids)) {
            $episodes = $DB->get_records_list('learnplugpodcasts_eps', 'id', array_unique($episodeids), '', 'id,title');
        }

        foreach ($records as $record) {
            $episode = $episodes[(int)$record->episodeid] ?? null;
            $table->data[] = [
                format_string($episode->title ?? get_string('notfoundepisode', 'learnplugpodcasts')),
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
