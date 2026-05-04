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
 * Activity settings form for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../course/moodleform_mod.php');

/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_learnplugpodcasts_mod_form extends moodleform_mod {
    /**
     * Defines the activity form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $config = get_config('mod_learnplugpodcasts');

        $mform->addElement('text', 'name', get_string('learnplugpodcastsname', 'learnplugpodcasts'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'seriesmetadata', get_string('seriesmetadata', 'learnplugpodcasts'));

        $mform->addElement('text', 'subtitle', get_string('subtitle', 'learnplugpodcasts'), ['size' => 64]);
        $mform->setType('subtitle', PARAM_TEXT);

        $mform->addElement('text', 'authorname', get_string('authorname', 'learnplugpodcasts'), ['size' => 64]);
        $mform->setType('authorname', PARAM_TEXT);

        $languages = get_string_manager()->get_list_of_translations(true);
        $mform->addElement('select', 'languagecode', get_string('languagecode', 'learnplugpodcasts'), $languages);
        $mform->setDefault('languagecode', current_language());

        $mform->addElement('text', 'category', get_string('category', 'learnplugpodcasts'), ['size' => 32]);
        $mform->setType('category', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'explicitflag', get_string('explicitflag', 'learnplugpodcasts'));

        $mform->addElement('text', 'copyrightnotice', get_string('copyrightnotice', 'learnplugpodcasts'), ['size' => 64]);
        $mform->setType('copyrightnotice', PARAM_TEXT);

        $mform->addElement('url', 'websiteurl', get_string('websiteurl', 'learnplugpodcasts'), ['size' => 64]);
        $mform->setType('websiteurl', PARAM_URL);

        $mform->addElement('text', 'email', get_string('email', 'learnplugpodcasts'), ['size' => 64]);
        $mform->setType('email', PARAM_EMAIL);

        $coveroptions = [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ];
        $mform->addElement('filemanager', 'coverimage', get_string('coverimage', 'learnplugpodcasts'), null, $coveroptions);

        $mform->addElement('header', 'publishing', get_string('publishing', 'learnplugpodcasts'));
        $mform->addElement('advcheckbox', 'publicenabled', get_string('publicenabled', 'learnplugpodcasts'));
        $mform->addElement('advcheckbox', 'rssenabled', get_string('rssenabled', 'learnplugpodcasts'));
        $mform->addElement('advcheckbox', 'notifynewepisodes', get_string('notifynewepisodes', 'learnplugpodcasts'));
        $mform->setDefault('notifynewepisodes', (int)($config->defaultnotifynewepisodes ?? 1));
        $mform->addElement('text', 'publicslug', get_string('publicslug', 'learnplugpodcasts'), ['size' => 32]);
        $mform->setType('publicslug', PARAM_ALPHANUMEXT);

        $sortoptions = [
            'newest' => get_string('sort_newest', 'learnplugpodcasts'),
            'oldest' => get_string('sort_oldest', 'learnplugpodcasts'),
        ];
        $mform->addElement('select', 'defaultsort', get_string('defaultsort', 'learnplugpodcasts'), $sortoptions);
        $mform->setDefault('defaultsort', 'newest');

        $mform->addElement('text', 'episodesperpage', get_string('episodesperpage', 'learnplugpodcasts'), ['size' => 4]);
        $mform->setType('episodesperpage', PARAM_INT);
        $mform->setDefault('episodesperpage', (int)($config->defaultepisodesperpage ?? 10));

        $mform->addElement('advcheckbox', 'showsearch', get_string('showsearch', 'learnplugpodcasts'));
        $mform->setDefault('showsearch', 1);
        $mform->addElement('advcheckbox', 'showdescriptions', get_string('showdescriptions', 'learnplugpodcasts'));
        $mform->setDefault('showdescriptions', 1);
        $mform->addElement('advcheckbox', 'showtranscripts', get_string('showtranscripts', 'learnplugpodcasts'));
        $mform->setDefault('showtranscripts', 1);
        $mform->addElement('advcheckbox', 'showsubscribe', get_string('showsubscribe', 'learnplugpodcasts'));
        $mform->setDefault('showsubscribe', 1);

        $mform->addElement('header', 'completionheader', get_string('completion', 'completion'));
        $completionmodes = [
            0 => get_string('completionlistenmode_none', 'learnplugpodcasts'),
            1 => get_string('completionlistenmode_started', 'learnplugpodcasts'),
            2 => get_string('completionlistenmode_percent', 'learnplugpodcasts'),
            3 => get_string('completionlistenmode_channelrecommended', 'learnplugpodcasts'),
        ];
        $mform->addElement(
            'select',
            'completionlistenmode',
            get_string('completionlistenmode', 'learnplugpodcasts'),
            $completionmodes
        );
        $mform->setDefault('completionlistenmode', (int)($config->defaultcompletionmode ?? 3));

        $mform->addElement(
            'text',
            'completionlistenpercent',
            get_string('completionlistenpercent', 'learnplugpodcasts'),
            ['size' => 4]
        );
        $mform->setType('completionlistenpercent', PARAM_INT);
        $mform->setDefault('completionlistenpercent', (int)($config->defaultcompletionpercent ?? 70));
        $mform->disabledIf('completionlistenpercent', 'completionlistenmode', 'eq', 0);
        $mform->disabledIf('completionlistenpercent', 'completionlistenmode', 'eq', 1);

        $mform->addElement(
            'text',
            'completionepisodecount',
            get_string('completionepisodecount', 'learnplugpodcasts'),
            ['size' => 4]
        );
        $mform->setType('completionepisodecount', PARAM_INT);
        $mform->setDefault('completionepisodecount', (int)($config->defaultcompletionepisodecount ?? 3));
        $mform->disabledIf('completionepisodecount', 'completionlistenmode', 'neq', 3);

        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Preprocessing for cover image draft area.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues): void {
        if (!empty($defaultvalues['gradeenabled']) && empty($defaultvalues['grade'])) {
            $defaultvalues['grade'] = 100;
        }

        if (!empty($this->current->coursemodule)) {
            $context = context_module::instance($this->current->coursemodule);
            $draftitemid = file_get_submitted_draft_itemid('coverimage');
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                'mod_learnplugpodcasts',
                'coverimage',
                0,
                ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
            );
            $defaultvalues['coverimage'] = $draftitemid;
        }
    }

    /**
     * Adds custom completion rule fields.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        return ['completionlistenmode', 'completionlistenpercent', 'completionepisodecount'];
    }

    /**
     * Evaluates whether custom completion is enabled.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        $mode = is_array($data) ? ($data['completionlistenmode'] ?? 0) : ($data->completionlistenmode ?? 0);
        return !empty($mode) && (int)$mode > 0;
    }

    /**
     * Validation rules.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if ((int)$data['episodesperpage'] < 1) {
            $errors['episodesperpage'] = get_string('required');
        }

        if (in_array((int)$data['completionlistenmode'], [2, 3], true)) {
            if ((int)$data['completionlistenpercent'] < 1 || (int)$data['completionlistenpercent'] > 100) {
                $errors['completionlistenpercent'] = get_string('errorvalidation', 'learnplugpodcasts');
            }
        }

        if ((int)$data['completionlistenmode'] === 3 && (int)$data['completionepisodecount'] < 1) {
            $errors['completionepisodecount'] = get_string('errorvalidation', 'learnplugpodcasts');
        }

        if (!empty($data['email']) && !validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        }

        return $errors;
    }
}
