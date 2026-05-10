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
 * Upgrade script for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade hook for mod_learnplugpodcasts.
 *
 * @param int $oldversion
 * @return bool
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_learnplugpodcasts_upgrade($oldversion): bool {
    global $DB;

    require_once(__DIR__ . '/../classes/local/util/mime.php');

    $cleanuplegacyautomation = static function () use ($DB): void {
        $now = time();
        $legacyprefix = 'open' . 'ai_';
        $legacytaskpattern = '%process_' . 'open' . 'ai' . '_job%';
        $legacycustomdatapattern = '%"actiontype":"' . $legacyprefix . '%';

        $legacyrunning = $DB->get_records_select(
            'learnplugpodcasts_log',
            'actiontype LIKE :prefix AND status IN (:queued, :running)',
            [
                'prefix' => $legacyprefix . '%',
                'queued' => 'queued',
                'running' => 'running',
            ],
            '',
            'id'
        );
        foreach ($legacyrunning as $job) {
            $DB->update_record('learnplugpodcasts_log', (object)[
                'id' => (int)$job->id,
                'status' => 'error',
                'message' => 'Legacy automation removed by plugin update.',
                'timecreated' => $now,
            ]);
        }

        $DB->delete_records_select(
            'task_adhoc',
            'component = :component AND customdata LIKE :prefix',
            [
                'component' => 'mod_learnplugpodcasts',
                'prefix' => $legacycustomdatapattern,
            ]
        );

        $dbman = $DB->get_manager();
        $taskrunning = new xmldb_table('task_running');
        if ($dbman->table_exists($taskrunning)) {
            $DB->delete_records_select(
                'task_running',
                'component = :component AND classname LIKE :classname',
                [
                    'component' => 'mod_learnplugpodcasts',
                    'classname' => $legacytaskpattern,
                ]
            );
        }

        $legacyconfigs = [
            'enable' . 'open' . 'ai' . 'transcription',
            'open' . 'ai' . 'apikey',
            'open' . 'ai' . 'transcriptionmodel',
            'open' . 'ai' . 'audioendpoint',
            'enable' . 'open' . 'ai' . 'captions',
            'open' . 'ai' . 'captionsmodel',
            'open' . 'ai' . 'captiontranslationmodel',
            'defaultcaptiontranslations',
            'open' . 'ai' . 'chatendpoint',
        ];
        foreach ($legacyconfigs as $configname) {
            unset_config($configname, 'mod_learnplugpodcasts');
        }
    };

    if ($oldversion < 2026042200) {
        upgrade_mod_savepoint(true, 2026042200, 'learnplugpodcasts');
    }

    if ($oldversion < 2026042202) {
        $default = \mod_learnplugpodcasts\local\util\mime::default_allowed_audio_types_string();
        $required = array_filter(array_map('trim', explode(',', $default)));

        $currentraw = trim((string)get_config('mod_learnplugpodcasts', 'allowedaudiomimetypes'));
        if ($currentraw === '') {
            set_config('allowedaudiomimetypes', $default, 'mod_learnplugpodcasts');
        } else {
            $current = array_filter(array_map('trim', explode(',', $currentraw)));
            $merged = array_values(array_unique(array_merge($current, $required)));
            set_config('allowedaudiomimetypes', implode(',', $merged), 'mod_learnplugpodcasts');
        }

        upgrade_mod_savepoint(true, 2026042202, 'learnplugpodcasts');
    }

    if ($oldversion < 2026042203) {
        upgrade_mod_savepoint(true, 2026042203, 'learnplugpodcasts');
    }

    if ($oldversion < 2026042204) {
        upgrade_mod_savepoint(true, 2026042204, 'learnplugpodcasts');
    }

    if ($oldversion < 2026042205) {
        upgrade_mod_savepoint(true, 2026042205, 'learnplugpodcasts');
    }

    if ($oldversion < 2026050105) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('learnplugpodcasts');
        $field = new xmldb_field(
            'notifynewepisodes',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'rssenabled'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        set_config('enableepisodenotifications', 1, 'mod_learnplugpodcasts');
        set_config('defaultnotifynewepisodes', 1, 'mod_learnplugpodcasts');

        upgrade_mod_savepoint(true, 2026050105, 'learnplugpodcasts');
    }

    if ($oldversion < 2026050218) {
        $cleanuplegacyautomation();
        upgrade_mod_savepoint(true, 2026050218, 'learnplugpodcasts');
    }

    if ($oldversion < 2026050220) {
        $cleanuplegacyautomation();
        upgrade_mod_savepoint(true, 2026050220, 'learnplugpodcasts');
    }

    if ($oldversion < 2026050233) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('learnplugpodcasts');
        $field = new xmldb_field(
            'grade',
            XMLDB_TYPE_NUMBER,
            '10, 5',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completionepisodecount'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->execute(
            'UPDATE {learnplugpodcasts}
                SET grade = :defaultgrade
              WHERE grade = :zero
                AND gradeenabled = :enabled',
            [
                'defaultgrade' => 100,
                'zero' => 0,
                'enabled' => 1,
            ]
        );

        $DB->execute(
            'UPDATE {learnplugpodcasts}
                SET gradeenabled = :enabled
              WHERE grade > :zero',
            [
                'enabled' => 1,
                'zero' => 0,
            ]
        );

        $DB->execute(
            'UPDATE {learnplugpodcasts}
                SET gradeenabled = :disabled
              WHERE grade <= :zero',
            [
                'disabled' => 0,
                'zero' => 0,
            ]
        );

        upgrade_mod_savepoint(true, 2026050233, 'learnplugpodcasts');
    }

    if ($oldversion < 2026050409) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('learnplugpodcasts_like');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('podcastid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('episodeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('podcast_fk', XMLDB_KEY_FOREIGN, ['podcastid'], 'learnplugpodcasts', ['id']);
            $table->add_key('episode_fk', XMLDB_KEY_FOREIGN, ['episodeid'], 'learnplugpodcasts_eps', ['id']);
            $table->add_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $table->add_index('episode_user_uix', XMLDB_INDEX_UNIQUE, ['episodeid', 'userid']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026050409, 'learnplugpodcasts');
    }

    if ($oldversion < 2026050507) {
        $mobileservice = $DB->get_record(
            'external_services',
            ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE],
            'id',
            IGNORE_MISSING
        );
        if ($mobileservice) {
            $functionnames = [
                'mod_learnplugpodcasts_save_progress',
                'mod_learnplugpodcasts_toggle_like',
            ];
            foreach ($functionnames as $functionname) {
                $function = $DB->get_record(
                    'external_functions',
                    ['name' => $functionname],
                    'id',
                    IGNORE_MISSING
                );
                if (!$function) {
                    continue;
                }
                $exists = $DB->record_exists(
                    'external_services_functions',
                    ['externalserviceid' => (int)$mobileservice->id, 'functionname' => $functionname]
                );
                if (!$exists) {
                    $DB->insert_record('external_services_functions', (object)[
                        'externalserviceid' => (int)$mobileservice->id,
                        'functionname' => $functionname,
                    ]);
                }
            }
        }

        upgrade_mod_savepoint(true, 2026050507, 'learnplugpodcasts');
    }

    return true;
}
