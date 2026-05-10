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
 * Core callbacks and API integration for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib/gradelib.php');
require_once(__DIR__ . '/classes/local/util/slug.php');
require_once(__DIR__ . '/classes/local/service/episode_service.php');
require_once(__DIR__ . '/classes/local/service/public_access_service.php');

/**
 * Features supported by LearnPlug Podcasts.
 *
 * @param string $feature
 * @return mixed
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Creates a podcast activity instance.
 *
 * @param stdClass $data
 * @param mod_learnplugpodcasts_mod_form|null $mform
 * @return int
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_add_instance($data, $mform = null): int {
    global $DB, $USER;

    $now = time();
    $data->timecreated = $now;
    $data->timemodified = $now;
    $data->owneruserid = $USER->id;

    if (empty($data->publicslug)) {
        $data->publicslug = \mod_learnplugpodcasts\local\util\slug::make((string)$data->name);
    }

    $data->grade = isset($data->grade) ? (float)$data->grade : 0.0;
    $data->gradeenabled = $data->grade > 0 ? 1 : 0;

    $id = $DB->insert_record('learnplugpodcasts', $data);
    $data->id = $id;

    if (!empty($data->coursemodule)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files(
            (int)($data->coverimage ?? 0),
            $context->id,
            'mod_learnplugpodcasts',
            'coverimage',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
        );
    }

    learnplugpodcasts_grade_item_update($data);

    return $id;
}

/**
 * Updates an existing podcast activity instance.
 *
 * @param stdClass $data
 * @param mod_learnplugpodcasts_mod_form|null $mform
 * @return bool
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_update_instance($data, $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    if (empty($data->publicslug)) {
        $data->publicslug = \mod_learnplugpodcasts\local\util\slug::make((string)$data->name);
    }

    $data->grade = isset($data->grade) ? (float)$data->grade : 0.0;
    $data->gradeenabled = $data->grade > 0 ? 1 : 0;

    $result = $DB->update_record('learnplugpodcasts', $data);

    if (!empty($data->coursemodule)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files(
            (int)($data->coverimage ?? 0),
            $context->id,
            'mod_learnplugpodcasts',
            'coverimage',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
        );
    }

    learnplugpodcasts_grade_item_update($data);

    return (bool)$result;
}

/**
 * Deletes an activity instance and all related records.
 *
 * @param int $id
 * @return bool
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_delete_instance($id): bool {
    global $DB;

    $podcast = $DB->get_record('learnplugpodcasts', ['id' => $id]);
    if (!$podcast) {
        return false;
    }

    $cm = get_coursemodule_from_instance('learnplugpodcasts', $podcast->id, $podcast->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'coverimage');

        $episodes = $DB->get_records('learnplugpodcasts_eps', ['podcastid' => $podcast->id], '', 'id');
        foreach ($episodes as $episode) {
            $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodeaudio', $episode->id);
            $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodeimage', $episode->id);
            $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodetranscript', $episode->id);
            $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodecaption', $episode->id);
            $fs->delete_area_files($context->id, 'mod_learnplugpodcasts', 'episodeattachment', $episode->id);
        }
    }

    $DB->delete_records('learnplugpodcasts_prog', ['podcastid' => $podcast->id]);
    $DB->delete_records('learnplugpodcasts_like', ['podcastid' => $podcast->id]);
    $DB->delete_records('learnplugpodcasts_log', ['podcastid' => $podcast->id]);
    $DB->delete_records('learnplugpodcasts_eps', ['podcastid' => $podcast->id]);
    $DB->delete_records('learnplugpodcasts', ['id' => $podcast->id]);

    learnplugpodcasts_grade_item_delete($podcast);

    return true;
}

/**
 * Serves plugin files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context_module $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): bool {
    global $DB;

    if ($context->contextlevel !== CONTEXT_MODULE) {
        return false;
    }

    $allowedareas = ['coverimage', 'episodeaudio', 'episodeimage', 'episodetranscript', 'episodecaption',
        'episodeattachment'];
    if (!in_array($filearea, $allowedareas, true)) {
        return false;
    }

    $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);
    $canmanage = has_capability('mod/learnplugpodcasts:manageepisodes', $context);

    $itemid = (int)array_shift($args);
    if ($filearea === 'coverimage') {
        $itemid = 0;
    }

    $episode = null;
    if ($filearea !== 'coverimage') {
        $episode = $DB->get_record('learnplugpodcasts_eps', ['id' => $itemid, 'podcastid' => $podcast->id]);
        if (!$episode) {
            return false;
        }
    }

    if ($canmanage || (isloggedin() && !isguestuser())) {
        require_login($course, false, $cm);
        if (
            !$canmanage
            && !has_capability('mod/learnplugpodcasts:view', $context)
            && !has_capability('mod/learnplugpodcasts:downloadmedia', $context)
        ) {
            return false;
        }
    } else {
        $token = optional_param('token', '', PARAM_ALPHANUMEXT);
        $publicservice = new \mod_learnplugpodcasts\local\service\public_access_service();
        if (!$publicservice->can_access_public_media((int)$podcast->id, $token, (int)$itemid)) {
            return false;
        }
        if ($episode && $episode->draftstatus !== \mod_learnplugpodcasts\local\service\episode_service::STATUS_PUBLISHED) {
            return false;
        }
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_learnplugpodcasts', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    $alwaysdownloadareas = ['episodetranscript', 'episodecaption', 'episodeattachment'];
    $effectiveforcedownload = $forcedownload || in_array($filearea, $alwaysdownloadareas, true);
    send_stored_file($file, 3600, 0, $effectiveforcedownload, $options);
}

/**
 * Return custom completion state.
 *
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param int $userid
 * @param int $type
 * @return bool
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_get_completion_state($course, $cm, $userid, $type): bool {
    global $DB;

    $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);
    $mode = (int)$podcast->completionlistenmode;

    if ($mode === 0) {
        return true;
    }

    if ($mode === 1) {
        return $DB->record_exists_select(
            'learnplugpodcasts_prog',
            'podcastid = :podcastid AND userid = :userid AND listenedsecs > 0',
            ['podcastid' => $podcast->id, 'userid' => $userid]
        );
    }

    if ($mode === 2) {
        return $DB->record_exists_select(
            'learnplugpodcasts_prog',
            'podcastid = :podcastid AND userid = :userid AND listenedpercent >= :threshold',
            ['podcastid' => $podcast->id, 'userid' => $userid, 'threshold' => (float)$podcast->completionlistenpercent]
        );
    }

    if ($mode === 3) {
        $count = $DB->count_records_select(
            'learnplugpodcasts_prog',
            'podcastid = :podcastid AND userid = :userid AND completed = 1',
            ['podcastid' => $podcast->id, 'userid' => $userid]
        );
        return $count >= (int)$podcast->completionepisodecount;
    }

    return false;
}

/**
 * Active custom completion rule descriptions.
 *
 * @param cm_info $cm
 * @return array
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_get_completion_active_rule_descriptions($cm): array {
    global $DB;

    $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', MUST_EXIST);
    $descriptions = [];

    if ((int)$podcast->completionlistenmode === 1) {
        $descriptions[] = get_string('completionlistenmode_started', 'learnplugpodcasts');
    } else if ((int)$podcast->completionlistenmode === 2) {
        $descriptions[] = get_string('completionlistenmode_percent', 'learnplugpodcasts') . ': ' .
            (int)$podcast->completionlistenpercent . '%';
    } else if ((int)$podcast->completionlistenmode === 3) {
        $descriptions[] = get_string('completionrule_channelrecommended', 'learnplugpodcasts', (object)[
            'count' => (int)$podcast->completionepisodecount,
            'percent' => (int)$podcast->completionlistenpercent,
        ]);
    }

    return $descriptions;
}

/**
 * Returns grade records for one or all users.
 *
 * @param stdClass $podcast
 * @param int $userid
 * @return array
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_get_user_grades($podcast, $userid = 0): array {
    global $DB;

    if (!learnplugpodcasts_has_grading_enabled($podcast)) {
        return [];
    }

    $params = ['podcastid' => $podcast->id];
    $userfilter = '';
    if (!empty($userid)) {
        $userfilter = ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $users = $DB->get_records_sql(
        "SELECT DISTINCT userid FROM {learnplugpodcasts_prog} WHERE podcastid = :podcastid {$userfilter}",
        $params
    );

    $grades = [];
    foreach ($users as $record) {
        $grades[$record->userid] = (object)[
            'userid' => $record->userid,
            'rawgrade' => \mod_learnplugpodcasts\local\service\progress_service::calculate_grade(
                (int)$podcast->id,
                (int)$record->userid
            ),
        ];
    }

    return $grades;
}

/**
 * Create or update grade item.
 *
 * @param stdClass $podcast
 * @param array|null $grades
 * @return int
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_grade_item_update($podcast, $grades = null): int {
    $params = [
        'itemname' => clean_param($podcast->name, PARAM_NOTAGS),
        'idnumber' => $podcast->cmidnumber ?? null,
    ];

    if (learnplugpodcasts_has_grading_enabled($podcast)) {
        $grademax = (float)($podcast->grade ?? 100);
        if ($grademax <= 0) {
            $grademax = 100;
        }
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $grademax;
        $params['grademin'] = 0;
        if (isset($podcast->gradepass)) {
            $params['gradepass'] = max(0, (float)$podcast->gradepass);
        }
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update(
        'mod/learnplugpodcasts',
        $podcast->course,
        'mod',
        'learnplugpodcasts',
        $podcast->id,
        0,
        $grades,
        $params
    );
}

/**
 * Delete grade item.
 *
 * @param stdClass $podcast
 * @return int
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_grade_item_delete($podcast): int {
    return grade_update(
        'mod/learnplugpodcasts',
        $podcast->course,
        'mod',
        'learnplugpodcasts',
        $podcast->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Pushes grades to gradebook.
 *
 * @param stdClass $podcast
 * @param int $userid
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_update_grades($podcast, $userid = 0): void {
    if (!learnplugpodcasts_has_grading_enabled($podcast)) {
        learnplugpodcasts_grade_item_update($podcast);
        return;
    }

    $grades = learnplugpodcasts_get_user_grades($podcast, $userid);
    learnplugpodcasts_grade_item_update($podcast, $grades);
}

/**
 * Returns whether the activity has gradebook grading enabled.
 *
 * @param stdClass $podcast
 * @return bool
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_has_grading_enabled($podcast): bool {
    $grade = isset($podcast->grade) ? (float)$podcast->grade : 0.0;
    return $grade > 0 || !empty($podcast->gradeenabled);
}

/**
 * Supports course reset.
 *
 * @param stdClass $data
 * @return array
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function learnplugpodcasts_reset_userdata($data): array {
    global $DB;

    $status = [];
    if (!empty($data->reset_gradebook_grades)) {
        $instances = $DB->get_records('learnplugpodcasts', ['course' => $data->courseid]);
        foreach ($instances as $instance) {
            $DB->delete_records('learnplugpodcasts_prog', ['podcastid' => $instance->id]);
            $DB->delete_records('learnplugpodcasts_like', ['podcastid' => $instance->id]);
            learnplugpodcasts_update_grades($instance);
        }
        $status[] = [
            'component' => get_string('modulenameplural', 'learnplugpodcasts'),
            'item' => get_string('gradeheader', 'learnplugpodcasts'),
            'error' => false,
        ];
    }

    return $status;
}
