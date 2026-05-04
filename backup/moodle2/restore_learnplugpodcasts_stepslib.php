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
class restore_learnplugpodcasts_activity_structure_step extends restore_activity_structure_step {
    /**
     * Path definitions.
     *
     * @return array
     */
    protected function define_structure(): array {
        $paths = [
            new restore_path_element('learnplugpodcasts', '/activity/learnplugpodcasts'),
            new restore_path_element('learnplugpodcasts_episode', '/activity/learnplugpodcasts/episodes/episode'),
        ];

        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('learnplugpodcasts_progress',
                '/activity/learnplugpodcasts/progressrows/progress');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process main activity.
     *
     * @param array $data
     */
    protected function process_learnplugpodcasts(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();
        $newitemid = $DB->insert_record('learnplugpodcasts', $data);

        $this->apply_activity_instance($newitemid);
        $this->set_mapping('learnplugpodcasts', $oldid, $newitemid);
    }

    /**
     * Process episode.
     *
     * @param array $data
     */
    protected function process_learnplugpodcasts_episode(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->podcastid = $this->get_new_parentid('learnplugpodcasts');
        $newitemid = $DB->insert_record('learnplugpodcasts_eps', $data);

        $this->set_mapping('learnplugpodcasts_eps', $oldid, $newitemid, true);
    }

    /**
     * Process progress.
     *
     * @param array $data
     */
    protected function process_learnplugpodcasts_progress(array $data): void {
        global $DB;

        $data = (object)$data;
        $data->podcastid = $this->get_new_parentid('learnplugpodcasts');
        $data->episodeid = $this->get_mappingid('learnplugpodcasts_eps', $data->episodeid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (empty($data->userid) || empty($data->episodeid)) {
            return;
        }
        $DB->insert_record('learnplugpodcasts_prog', $data);
    }

    /**
     * Restore files.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_learnplugpodcasts', 'intro', null);
        $this->add_related_files('mod_learnplugpodcasts', 'coverimage', null);
        $this->add_related_files('mod_learnplugpodcasts', 'episodeaudio', 'learnplugpodcasts_eps');
        $this->add_related_files('mod_learnplugpodcasts', 'episodeimage', 'learnplugpodcasts_eps');
        $this->add_related_files('mod_learnplugpodcasts', 'episodetranscript', 'learnplugpodcasts_eps');
        $this->add_related_files('mod_learnplugpodcasts', 'episodecaption', 'learnplugpodcasts_eps');
        $this->add_related_files('mod_learnplugpodcasts', 'episodeattachment', 'learnplugpodcasts_eps');
    }
}
