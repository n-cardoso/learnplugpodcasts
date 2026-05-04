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

namespace mod_learnplugpodcasts\local\form;

defined('MOODLE_INTERNAL') || die();

use mod_learnplugpodcasts\local\service\caption_service;

require_once($CFG->libdir . '/formslib.php');

/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class episode_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'episodeid', $custom['episodeid'] ?? 0);
        $mform->setType('episodeid', PARAM_INT);

        $mform->addElement('hidden', 'action', $custom['action'] ?? 'add');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('text', 'title', get_string('episodetitle', 'learnplugpodcasts'), ['size' => 64]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('text', 'subtitle', get_string('episodesubtitle', 'learnplugpodcasts'), ['size' => 64]);
        $mform->setType('subtitle', PARAM_TEXT);

        $mform->addElement(
            'editor',
            'description',
            get_string('description', 'learnplugpodcasts'),
            null,
            ['maxfiles' => 0, 'maxbytes' => 0]
        );
        $mform->setType('description', PARAM_RAW);

        $mform->addElement('text', 'seasonnumber', get_string('seasonnumber', 'learnplugpodcasts'), ['size' => 6]);
        $mform->setType('seasonnumber', PARAM_INT);

        $mform->addElement('text', 'episodenumber', get_string('episodenumber', 'learnplugpodcasts'), ['size' => 6]);
        $mform->setType('episodenumber', PARAM_INT);

        $mform->addElement(
            'date_time_selector',
            'publishtime',
            get_string('publishtime', 'learnplugpodcasts'),
            ['optional' => false]
        );

        $statusoptions = [
            'draft' => get_string('status_draft', 'learnplugpodcasts'),
            'published' => get_string('status_published', 'learnplugpodcasts'),
            'unpublished' => get_string('status_unpublished', 'learnplugpodcasts'),
        ];
        $mform->addElement('select', 'draftstatus', get_string('draftstatus', 'learnplugpodcasts'), $statusoptions);

        $mform->addElement('advcheckbox', 'explicitflag', get_string('explicitflag', 'learnplugpodcasts'));

        $mform->addElement(
            'filemanager',
            'audiofile',
            get_string('audiofile', 'learnplugpodcasts'),
            null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['audio']]
        );

        $mform->addElement(
            'filemanager',
            'episodeimage',
            get_string('episodeimage', 'learnplugpodcasts'),
            null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
        );

        if (!empty(get_config('mod_learnplugpodcasts', 'allowtranscripts'))) {
            $mform->addElement(
                'filemanager',
                'transcriptfile',
                get_string('transcriptfile', 'learnplugpodcasts'),
                null,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $mform->addElement(
                'editor',
                'transcripttext',
                get_string('transcripttext', 'learnplugpodcasts'),
                null,
                ['maxfiles' => 0, 'maxbytes' => 0]
            );
            $mform->setType('transcripttext', PARAM_RAW);
            $mform->addElement(
                'filemanager',
                'episodecaption',
                get_string('captionfiles', 'learnplugpodcasts'),
                null,
                [
                    'subdirs' => 0,
                    'maxfiles' => caption_service::MAX_TRACK_FILES,
                    'accepted_types' => ['.vtt'],
                ]
            );
            $mform->addHelpButton('episodecaption', 'captionfiles', 'learnplugpodcasts');
            $mform->addElement('static', 'captionnamingguide', '', get_string('captionnamingguide', 'learnplugpodcasts'));
        }

        if (!empty(get_config('mod_learnplugpodcasts', 'allowepisodeattachments'))) {
            $mform->addElement(
                'filemanager',
                'attachments',
                get_string('attachments', 'learnplugpodcasts'),
                null,
                ['subdirs' => 0, 'maxfiles' => 10]
            );
        }

        $mform->addElement('url', 'externalurl', get_string('externalurl', 'learnplugpodcasts'), ['size' => 64]);
        $mform->setType('externalurl', PARAM_URL);

        $this->add_action_buttons(true, get_string('saveepisode', 'learnplugpodcasts'));
    }
}
