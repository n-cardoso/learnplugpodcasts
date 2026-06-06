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
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_learnplugpodcasts_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $learnplugpodcasts = new backup_nested_element('learnplugpodcasts', ['id'], [
            'course', 'name', 'intro', 'introformat', 'subtitle', 'authorname', 'owneruserid', 'languagecode', 'category',
            'explicitflag', 'copyrightnotice', 'websiteurl', 'email', 'publicenabled', 'publicslug', 'rssenabled',
            'notifynewepisodes', 'rsslastbuild', 'defaultsort', 'episodesperpage', 'showsubscribe', 'showsearch',
            'showdescriptions',
            'showtranscripts', 'completionlistenmode', 'completionlistenpercent', 'completionepisodecount',
            'grade', 'gradeenabled', 'timemodified', 'timecreated',
        ]);

        $episodes = new backup_nested_element('episodes');
        $episode = new backup_nested_element('episode', ['id'], [
            'podcastid', 'title', 'subtitle', 'slug', 'description', 'descriptionformat', 'episodenumber',
            'seasonnumber', 'publishtime', 'durationsecs', 'draftstatus', 'explicitflag', 'audiofileitemid',
            'transcriptfileitemid', 'transcripttext', 'transcriptformat', 'episodeimageitemid', 'externalurl',
            'guid', 'rssitemhash', 'sortorder', 'timemodified', 'timecreated',
        ]);

        $progressrows = new backup_nested_element('progressrows');
        $progress = new backup_nested_element('progress', ['id'], [
            'podcastid', 'episodeid', 'userid', 'lastpositionsecs', 'listenedsecs', 'listenedpercent', 'completed',
            'lastplaystate', 'timemodified', 'timecreated',
        ]);
        $zonerows = new backup_nested_element('zonerows');
        $zone = new backup_nested_element('zone', ['id'], [
            'podcastid', 'episodeid', 'userid', 'bucketstart', 'listenedsecs', 'timemodified', 'timecreated',
        ]);

        $learnplugpodcasts->add_child($episodes);
        $episodes->add_child($episode);

        if ($userinfo) {
            $learnplugpodcasts->add_child($progressrows);
            $progressrows->add_child($progress);
            $learnplugpodcasts->add_child($zonerows);
            $zonerows->add_child($zone);
        }

        $learnplugpodcasts->set_source_table('learnplugpodcasts', ['id' => backup::VAR_ACTIVITYID]);
        $episode->set_source_table('learnplugpodcasts_eps', ['podcastid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $progress->set_source_table('learnplugpodcasts_prog', ['podcastid' => backup::VAR_PARENTID]);
            $progress->annotate_ids('user', 'userid');
            $zone->set_source_table('learnplugpodcasts_zone', ['podcastid' => backup::VAR_PARENTID]);
            $zone->annotate_ids('user', 'userid');
        }

        $learnplugpodcasts->annotate_files('mod_learnplugpodcasts', 'intro', null);
        $learnplugpodcasts->annotate_files('mod_learnplugpodcasts', 'coverimage', null);
        $episode->annotate_files('mod_learnplugpodcasts', 'episodeaudio', 'id');
        $episode->annotate_files('mod_learnplugpodcasts', 'episodeimage', 'id');
        $episode->annotate_files('mod_learnplugpodcasts', 'episodetranscript', 'id');
        $episode->annotate_files('mod_learnplugpodcasts', 'episodecaption', 'id');
        $episode->annotate_files('mod_learnplugpodcasts', 'episodeattachment', 'id');

        return $this->prepare_activity_structure($learnplugpodcasts);
    }
}
