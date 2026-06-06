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

namespace mod_learnplugpodcasts\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Metadata declaration.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('learnplugpodcasts', [
            'owneruserid' => 'privacy:metadata:learnplugpodcasts:owneruserid',
        ], 'privacy:metadata:learnplugpodcasts');

        $collection->add_database_table('learnplugpodcasts_prog', [
            'userid' => 'privacy:metadata:learnplugpodcasts_prog:userid',
            'podcastid' => 'privacy:metadata:learnplugpodcasts_prog:podcastid',
            'episodeid' => 'privacy:metadata:learnplugpodcasts_prog:episodeid',
            'lastpositionsecs' => 'privacy:metadata:learnplugpodcasts_prog:lastpositionsecs',
            'listenedsecs' => 'privacy:metadata:learnplugpodcasts_prog:listenedsecs',
            'listenedpercent' => 'privacy:metadata:learnplugpodcasts_prog:listenedpercent',
            'completed' => 'privacy:metadata:learnplugpodcasts_prog:completed',
            'lastplaystate' => 'privacy:metadata:learnplugpodcasts_prog:lastplaystate',
            'timemodified' => 'privacy:metadata:learnplugpodcasts_prog:timemodified',
            'timecreated' => 'privacy:metadata:learnplugpodcasts_prog:timecreated',
        ], 'privacy:metadata:learnplugpodcasts_prog');

        $collection->add_database_table('learnplugpodcasts_like', [
            'userid' => 'privacy:metadata:learnplugpodcasts_like:userid',
            'podcastid' => 'privacy:metadata:learnplugpodcasts_like:podcastid',
            'episodeid' => 'privacy:metadata:learnplugpodcasts_like:episodeid',
            'timemodified' => 'privacy:metadata:learnplugpodcasts_like:timemodified',
            'timecreated' => 'privacy:metadata:learnplugpodcasts_like:timecreated',
        ], 'privacy:metadata:learnplugpodcasts_like');

        $collection->add_database_table('learnplugpodcasts_zone', [
            'userid' => 'privacy:metadata:learnplugpodcasts_zone:userid',
            'podcastid' => 'privacy:metadata:learnplugpodcasts_zone:podcastid',
            'episodeid' => 'privacy:metadata:learnplugpodcasts_zone:episodeid',
            'bucketstart' => 'privacy:metadata:learnplugpodcasts_zone:bucketstart',
            'listenedsecs' => 'privacy:metadata:learnplugpodcasts_zone:listenedsecs',
            'timemodified' => 'privacy:metadata:learnplugpodcasts_zone:timemodified',
            'timecreated' => 'privacy:metadata:learnplugpodcasts_zone:timecreated',
        ], 'privacy:metadata:learnplugpodcasts_zone');

        $collection->add_subsystem_link(
            'core_message',
            [],
            'privacy:metadata:coremessage'
        );

        return $collection;
    }

    /**
     * Contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextmodule
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {learnplugpodcasts} lp ON lp.id = cm.instance
                  JOIN {learnplugpodcasts_prog} p ON p.podcastid = lp.id
                 WHERE p.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextmodule' => CONTEXT_MODULE,
            'modname' => 'learnplugpodcasts',
            'userid' => $userid,
        ]);

        $sqlowner = "SELECT DISTINCT ctx.id
                       FROM {context} ctx
                       JOIN {course_modules} cm
                         ON cm.id = ctx.instanceid
                        AND ctx.contextlevel = :contextmodule
                       JOIN {modules} m
                         ON m.id = cm.module
                        AND m.name = :modname
                       JOIN {learnplugpodcasts} lp
                         ON lp.id = cm.instance
                      WHERE lp.owneruserid = :userid";
        $contextlist->add_from_sql($sqlowner, [
            'contextmodule' => CONTEXT_MODULE,
            'modname' => 'learnplugpodcasts',
            'userid' => $userid,
        ]);

        $sqllikes = "SELECT DISTINCT ctx.id
                       FROM {context} ctx
                       JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextmodule
                       JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                       JOIN {learnplugpodcasts} lp ON lp.id = cm.instance
                       JOIN {learnplugpodcasts_like} l ON l.podcastid = lp.id
                      WHERE l.userid = :userid";
        $contextlist->add_from_sql($sqllikes, [
            'contextmodule' => CONTEXT_MODULE,
            'modname' => 'learnplugpodcasts',
            'userid' => $userid,
        ]);

        $sqlzones = "SELECT DISTINCT ctx.id
                       FROM {context} ctx
                       JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextmodule
                       JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                       JOIN {learnplugpodcasts} lp ON lp.id = cm.instance
                       JOIN {learnplugpodcasts_zone} z ON z.podcastid = lp.id
                      WHERE z.userid = :userid";
        $contextlist->add_from_sql($sqlzones, [
            'contextmodule' => CONTEXT_MODULE,
            'modname' => 'learnplugpodcasts',
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        foreach ($contextlist as $context) {
            $cm = get_coursemodule_from_id('learnplugpodcasts', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $exportdata = new \stdClass();
            $podcast = $DB->get_record('learnplugpodcasts', ['id' => $cm->instance], '*', IGNORE_MISSING);
            if ($podcast && (int)$podcast->owneruserid === (int)$contextlist->get_user()->id) {
                $exportdata->owner = get_string('yes');
            }

            $progressrows = $DB->get_records_sql(
                "SELECT p.*, e.title
                   FROM {learnplugpodcasts_prog} p
                   JOIN {learnplugpodcasts_eps} e ON e.id = p.episodeid
                  WHERE p.podcastid = :podcastid AND p.userid = :userid",
                ['podcastid' => $cm->instance, 'userid' => $contextlist->get_user()->id]
            );

            if ($progressrows) {
                $export = [];
                foreach ($progressrows as $row) {
                    $export[] = [
                        'episode' => $row->title,
                        'lastpositionsecs' => (int)$row->lastpositionsecs,
                        'listenedsecs' => (int)$row->listenedsecs,
                        'listenedpercent' => (float)$row->listenedpercent,
                        'completed' => transform::yesno((bool)$row->completed),
                        'lastplaystate' => $row->lastplaystate,
                        'timemodified' => transform::datetime($row->timemodified),
                    ];
                }
                $exportdata->progress = $export;
            }

            $likerows = $DB->get_records_sql(
                "SELECT l.*, e.title
                   FROM {learnplugpodcasts_like} l
                   JOIN {learnplugpodcasts_eps} e ON e.id = l.episodeid
                  WHERE l.podcastid = :podcastid AND l.userid = :userid",
                ['podcastid' => $cm->instance, 'userid' => $contextlist->get_user()->id]
            );

            if ($likerows) {
                $exportlikes = [];
                foreach ($likerows as $row) {
                    $exportlikes[] = [
                        'episode' => $row->title,
                        'timecreated' => transform::datetime($row->timecreated),
                        'timemodified' => transform::datetime($row->timemodified),
                    ];
                }
                $exportdata->likes = $exportlikes;
            }

            $zonerows = $DB->get_records_sql(
                "SELECT z.*, e.title
                   FROM {learnplugpodcasts_zone} z
                   JOIN {learnplugpodcasts_eps} e ON e.id = z.episodeid
                  WHERE z.podcastid = :podcastid AND z.userid = :userid
               ORDER BY z.episodeid ASC, z.bucketstart ASC",
                ['podcastid' => $cm->instance, 'userid' => $contextlist->get_user()->id]
            );

            if ($zonerows) {
                $exportzones = [];
                foreach ($zonerows as $row) {
                    $exportzones[] = [
                        'episode' => $row->title,
                        'bucketstart' => (int)$row->bucketstart,
                        'listenedsecs' => (float)$row->listenedsecs,
                        'timemodified' => transform::datetime($row->timemodified),
                    ];
                }
                $exportdata->listeningzones = $exportzones;
            }

            if (!empty((array)$exportdata)) {
                writer::with_context($context)->export_data([get_string('pluginname', 'learnplugpodcasts')], $exportdata);
            }
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('learnplugpodcasts', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $DB->delete_records('learnplugpodcasts_prog', ['podcastid' => $cm->instance]);
        $DB->delete_records('learnplugpodcasts_like', ['podcastid' => $cm->instance]);
        $DB->delete_records('learnplugpodcasts_zone', ['podcastid' => $cm->instance]);
        $DB->set_field('learnplugpodcasts', 'owneruserid', 0, ['id' => $cm->instance]);
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('learnplugpodcasts', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('learnplugpodcasts_prog', ['podcastid' => $cm->instance, 'userid' => $userid]);
            $DB->delete_records('learnplugpodcasts_like', ['podcastid' => $cm->instance, 'userid' => $userid]);
            $DB->delete_records('learnplugpodcasts_zone', ['podcastid' => $cm->instance, 'userid' => $userid]);
            $DB->set_field(
                'learnplugpodcasts',
                'owneruserid',
                0,
                ['id' => $cm->instance, 'owneruserid' => $userid]
            );
        }
    }

    /**
     * Get users in context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT p.userid
                  FROM {learnplugpodcasts_prog} p
                  JOIN {course_modules} cm ON cm.instance = p.podcastid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid AND m.name = :modname";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid, 'modname' => 'learnplugpodcasts']);

        $sqlowner = "SELECT lp.owneruserid AS userid
                       FROM {learnplugpodcasts} lp
                       JOIN {course_modules} cm ON cm.instance = lp.id
                       JOIN {modules} m ON m.id = cm.module
                      WHERE cm.id = :cmid
                        AND m.name = :modname
                        AND lp.owneruserid > 0";
        $userlist->add_from_sql('userid', $sqlowner, ['cmid' => $context->instanceid, 'modname' => 'learnplugpodcasts']);

        $sqllikes = "SELECT l.userid
                       FROM {learnplugpodcasts_like} l
                       JOIN {course_modules} cm ON cm.instance = l.podcastid
                       JOIN {modules} m ON m.id = cm.module
                      WHERE cm.id = :cmid
                        AND m.name = :modname";
        $userlist->add_from_sql('userid', $sqllikes, ['cmid' => $context->instanceid, 'modname' => 'learnplugpodcasts']);

        $sqlzones = "SELECT z.userid
                       FROM {learnplugpodcasts_zone} z
                       JOIN {course_modules} cm ON cm.instance = z.podcastid
                       JOIN {modules} m ON m.id = cm.module
                      WHERE cm.id = :cmid
                        AND m.name = :modname";
        $userlist->add_from_sql('userid', $sqlzones, ['cmid' => $context->instanceid, 'modname' => 'learnplugpodcasts']);
    }

    /**
     * Delete selected users in context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('learnplugpodcasts', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        [$in, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED, 'uid');
        $params['podcastid'] = $cm->instance;
        $DB->delete_records_select('learnplugpodcasts_prog', "podcastid = :podcastid AND userid {$in}", $params);
        $DB->delete_records_select('learnplugpodcasts_like', "podcastid = :podcastid AND userid {$in}", $params);
        $DB->delete_records_select('learnplugpodcasts_zone', "podcastid = :podcastid AND userid {$in}", $params);
        $DB->execute(
            "UPDATE {learnplugpodcasts}
                SET owneruserid = 0
              WHERE id = :podcastid
                AND owneruserid {$in}",
            $params
        );
    }
}
