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
 * CSV analytics exports for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use mod_learnplugpodcasts\local\repository\progress_repository;
use mod_learnplugpodcasts\local\service\analytics_service;

$id = required_param('id', PARAM_INT);
$dataset = optional_param('dataset', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('learnplugpodcasts', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/learnplugpodcasts:viewreports', $context);

$datasets = [
    'activity' => get_string('exportactivity', 'learnplugpodcasts'),
    'episodes' => get_string('exportepisodes', 'learnplugpodcasts'),
    'learners' => get_string('exportlearners', 'learnplugpodcasts'),
    'hotspots' => get_string('exporthotspots', 'learnplugpodcasts'),
    'all' => get_string('exportall', 'learnplugpodcasts'),
];

if ($dataset === '') {
    $PAGE->set_url('/mod/learnplugpodcasts/export.php', ['id' => $cm->id]);
    $PAGE->set_title(get_string('exportpagetitle', 'learnplugpodcasts'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_pagelayout('incourse');

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('exportpagetitle', 'learnplugpodcasts'));
    echo html_writer::tag('p', get_string('exportdatasetdescription', 'learnplugpodcasts'));
    foreach ($datasets as $datasetkey => $datasetlabel) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/learnplugpodcasts/export.php', [
                'id' => $cm->id,
                'dataset' => $datasetkey,
            ]),
            $datasetlabel,
            'get'
        );
    }
    echo $OUTPUT->single_button(
        new moodle_url('/mod/learnplugpodcasts/grade.php', ['id' => $cm->id]),
        get_string('back'),
        'get'
    );
    echo $OUTPUT->footer();
    exit;
}

if (!isset($datasets[$dataset])) {
    throw new moodle_exception('invalidparameter', 'error');
}

$enrolledcount = (int)count_enrolled_users($context, 'mod/learnplugpodcasts:view');
$analytics = (new analytics_service())->get_report_data($podcast, $enrolledcount);
$progressrows = (new progress_repository())->report_episode_rows((int)$podcast->id);
$summary = $analytics['summary'];

$csv = new csv_export_writer('comma');
$filename = clean_filename(
    format_string($podcast->name) . '-analytics-' . $dataset . '-' . userdate(time(), '%Y%m%d-%H%M')
);
$csv->set_filename($filename);

// Prevent user-authored values from becoming formulas in spreadsheet software.
$protectcell = static function ($value): string {
    $text = (string)$value;
    if ($text !== '' && preg_match('/^\s*[=+\-@]/u', $text)) {
        return "'" . $text;
    }
    return $text;
};
$firstrow = true;
$addrow = static function (array $row) use ($csv, $protectcell, &$firstrow): void {
    $cells = array_map($protectcell, $row);
    if ($firstrow && isset($cells[0])) {
        $cells[0] = "\xEF\xBB\xBF" . $cells[0];
        $firstrow = false;
    }
    $csv->add_data($cells);
};
$formatdate = static function (int $timestamp): string {
    return $timestamp > 0 ? userdate($timestamp, '%Y-%m-%d %H:%M:%S') : '';
};
$unitcount = get_string('exportunitcount', 'learnplugpodcasts');
$unitdatetime = get_string('exportunitdatetime', 'learnplugpodcasts');
$unitpercent = get_string('exportunitpercent', 'learnplugpodcasts');
$unitseconds = get_string('exportunitseconds', 'learnplugpodcasts');

$activityrows = [
    [$analytics['cards'][0]['label'], $summary['enrolledlearners'], $unitcount, $analytics['cards'][0]['sub']],
    [get_string('exportactivelearners', 'learnplugpodcasts'), $summary['activelearners'], $unitcount,
        $analytics['cards'][1]['sub']],
    [$analytics['cards'][1]['label'], $summary['activityengagement'], $unitpercent, $analytics['cards'][1]['sub']],
    [$analytics['cards'][2]['label'], $summary['avglistened'], $unitpercent, $analytics['cards'][2]['sub']],
    [$analytics['cards'][3]['label'], $summary['completionrate'], $unitpercent, $analytics['cards'][3]['sub']],
    [$analytics['cards'][4]['label'], $summary['totallisteningsecs'], $unitseconds, $analytics['cards'][4]['sub']],
    [get_string('analyticscard_episodecoverage', 'learnplugpodcasts'), $summary['publishedepisodes'], $unitcount,
        $analytics['cards'][5]['sub']],
    [get_string('exporttotalepisodes', 'learnplugpodcasts'), $summary['totalepisodes'], $unitcount,
        $analytics['cards'][5]['sub']],
    [$analytics['cards'][6]['label'], $summary['totallikes'], $unitcount, $analytics['cards'][6]['sub']],
];

if ($dataset === 'activity') {
    $addrow([
        get_string('exportmetric', 'learnplugpodcasts'),
        get_string('exportvalue', 'learnplugpodcasts'),
        get_string('exportunit', 'learnplugpodcasts'),
        get_string('description', 'learnplugpodcasts'),
    ]);
    foreach ($activityrows as $row) {
        $addrow($row);
    }
}

if ($dataset === 'episodes') {
    $addrow([
        get_string('analytics_episode', 'learnplugpodcasts'),
        get_string('analytics_status', 'learnplugpodcasts'),
        get_string('analytics_listeners', 'learnplugpodcasts'),
        get_string('analytics_engagement', 'learnplugpodcasts') . ' (%)',
        get_string('analytics_avglistened', 'learnplugpodcasts') . ' (%)',
        get_string('analytics_completionrate', 'learnplugpodcasts') . ' (%)',
        get_string('exportcompletions', 'learnplugpodcasts'),
        get_string('analytics_likes', 'learnplugpodcasts'),
        get_string('analytics_totallistened', 'learnplugpodcasts') . ' (s)',
        get_string('analytics_duration', 'learnplugpodcasts') . ' (s)',
        get_string('analytics_lastactivity', 'learnplugpodcasts'),
    ]);
    foreach ($analytics['rows'] as $row) {
        $addrow([
            $row['title'],
            $row['statuslabel'],
            $row['listeners'],
            $row['listenerengagementvalue'],
            $row['avglistenedvalue'],
            $row['completionratevalue'],
            $row['completions'],
            $row['likes'],
            $row['totallistenedsecs'],
            $row['durationsecs'],
            $formatdate($row['lastactivitytimestamp']),
        ]);
    }
}

if ($dataset === 'learners') {
    $addrow([
        get_string('user'),
        get_string('episode', 'learnplugpodcasts'),
        get_string('completionlistenpercent', 'learnplugpodcasts') . ' (%)',
        get_string('analytics_listenedtime', 'learnplugpodcasts') . ' (s)',
        get_string('lastposition', 'learnplugpodcasts') . ' (s)',
        get_string('completionstatus', 'learnplugpodcasts'),
        get_string('exportliked', 'learnplugpodcasts'),
        get_string('lastaccess'),
    ]);
    foreach ($progressrows as $row) {
        $addrow([
            fullname($row),
            format_string((string)$row->episodetitle),
            round((float)$row->listenedpercent, 2),
            (int)$row->listenedsecs,
            (int)$row->lastpositionsecs,
            !empty($row->completed) ?
                get_string('completionstatus_complete', 'learnplugpodcasts') :
                get_string('completionstatus_incomplete', 'learnplugpodcasts'),
            !empty($row->liked) ? get_string('yes') : get_string('no'),
            $formatdate((int)$row->timemodified),
        ]);
    }
}

if ($dataset === 'hotspots') {
    $addrow([
        get_string('exportscope', 'learnplugpodcasts'),
        get_string('analytics_learner', 'learnplugpodcasts'),
        get_string('analytics_episode', 'learnplugpodcasts'),
        get_string('exportzonestart', 'learnplugpodcasts'),
        get_string('exportzoneend', 'learnplugpodcasts'),
        get_string('analytics_listenedtime', 'learnplugpodcasts') . ' (s)',
        get_string('analytics_listeners', 'learnplugpodcasts'),
    ]);
    foreach ($analytics['episodezones'] as $episode) {
        foreach ($episode['zones'] as $zone) {
            $addrow([
                get_string('exportscopeepisode', 'learnplugpodcasts'),
                '',
                $episode['title'],
                $zone['bucketstart'],
                $zone['bucketend'],
                $zone['listenedsecs'],
                $zone['listeners'],
            ]);
        }
    }
    foreach ($analytics['learnerzones'] as $zone) {
        $addrow([
            get_string('exportscopelearner', 'learnplugpodcasts'),
            $zone['learner'],
            $zone['title'],
            $zone['bucketstart'],
            $zone['bucketend'],
            $zone['listenedsecs'],
            '',
        ]);
    }
}

if ($dataset === 'all') {
    $addrow([
        get_string('exportreport', 'learnplugpodcasts'),
        get_string('analytics_learner', 'learnplugpodcasts'),
        get_string('analytics_episode', 'learnplugpodcasts'),
        get_string('exportmetric', 'learnplugpodcasts'),
        get_string('exportvalue', 'learnplugpodcasts'),
        get_string('exportunit', 'learnplugpodcasts'),
        get_string('analytics_status', 'learnplugpodcasts'),
        get_string('description', 'learnplugpodcasts'),
    ]);
    foreach ($activityrows as $row) {
        $addrow([get_string('exportsectionactivity', 'learnplugpodcasts'), '', '', $row[0], $row[1], $row[2], '', $row[3]]);
    }
    foreach ($analytics['rows'] as $episode) {
        $episodemetrics = [
            [get_string('analytics_listeners', 'learnplugpodcasts'), $episode['listeners'], $unitcount],
            [get_string('analytics_engagement', 'learnplugpodcasts'), $episode['listenerengagementvalue'], $unitpercent],
            [get_string('analytics_avglistened', 'learnplugpodcasts'), $episode['avglistenedvalue'], $unitpercent],
            [get_string('analytics_completionrate', 'learnplugpodcasts'), $episode['completionratevalue'], $unitpercent],
            [get_string('exportcompletions', 'learnplugpodcasts'), $episode['completions'], $unitcount],
            [get_string('analytics_likes', 'learnplugpodcasts'), $episode['likes'], $unitcount],
            [get_string('analytics_totallistened', 'learnplugpodcasts'), $episode['totallistenedsecs'], $unitseconds],
            [get_string('analytics_duration', 'learnplugpodcasts'), $episode['durationsecs'], $unitseconds],
            [get_string('analytics_lastactivity', 'learnplugpodcasts'),
                $formatdate($episode['lastactivitytimestamp']), $unitdatetime],
        ];
        foreach ($episodemetrics as $metric) {
            $addrow([
                get_string('exportsectionepisodes', 'learnplugpodcasts'), '', $episode['title'],
                $metric[0], $metric[1], $metric[2], $episode['statuslabel'], '',
            ]);
        }
    }
    foreach ($progressrows as $progress) {
        $progressmetrics = [
            [get_string('completionlistenpercent', 'learnplugpodcasts'),
                round((float)$progress->listenedpercent, 2), $unitpercent],
            [get_string('analytics_listenedtime', 'learnplugpodcasts'), (int)$progress->listenedsecs, $unitseconds],
            [get_string('lastposition', 'learnplugpodcasts'), (int)$progress->lastpositionsecs, $unitseconds],
            [get_string('completionstatus', 'learnplugpodcasts'),
                !empty($progress->completed) ? get_string('completionstatus_complete', 'learnplugpodcasts') :
                    get_string('completionstatus_incomplete', 'learnplugpodcasts'), ''],
            [get_string('exportliked', 'learnplugpodcasts'),
                !empty($progress->liked) ? get_string('yes') : get_string('no'), ''],
            [get_string('lastaccess'), $formatdate((int)$progress->timemodified), $unitdatetime],
        ];
        foreach ($progressmetrics as $metric) {
            $addrow([
                get_string('exportsectionlearners', 'learnplugpodcasts'), fullname($progress),
                format_string((string)$progress->episodetitle), $metric[0], $metric[1], $metric[2], '', '',
            ]);
        }
    }
    foreach ($analytics['episodezones'] as $episode) {
        foreach ($episode['zones'] as $zone) {
            $description = $zone['bucketstart'] . '-' . $zone['bucketend'] . ' seconds';
            $addrow([
                get_string('exportsectionhotspots', 'learnplugpodcasts'), '', $episode['title'],
                get_string('analytics_listenedtime', 'learnplugpodcasts'), $zone['listenedsecs'], $unitseconds,
                get_string('exportscopeepisode', 'learnplugpodcasts'), $description,
            ]);
            $addrow([
                get_string('exportsectionhotspots', 'learnplugpodcasts'), '', $episode['title'],
                get_string('analytics_listeners', 'learnplugpodcasts'), $zone['listeners'], $unitcount,
                get_string('exportscopeepisode', 'learnplugpodcasts'), $description,
            ]);
        }
    }
    foreach ($analytics['learnerzones'] as $zone) {
        $addrow([
            get_string('exportsectionhotspots', 'learnplugpodcasts'), $zone['learner'], $zone['title'],
            get_string('analytics_listenedtime', 'learnplugpodcasts'), $zone['listenedsecs'], $unitseconds,
            get_string('exportscopelearner', 'learnplugpodcasts'),
            $zone['bucketstart'] . '-' . $zone['bucketend'] . ' seconds',
        ]);
    }
}

$csv->download_file();
exit;
