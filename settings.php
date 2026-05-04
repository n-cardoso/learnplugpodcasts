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
 * Site admin settings for LearnPlug Podcasts.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/local/util/mime.php');

if (!isset($settings) || !($settings instanceof admin_settingpage)) {
    return;
}

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'mod_learnplugpodcasts/enablepublicpages',
        get_string('enablepublicpages', 'learnplugpodcasts'),
        get_string('enablepublicpages_desc', 'learnplugpodcasts'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_learnplugpodcasts/enablepublicrss',
        get_string('enablepublicrss', 'learnplugpodcasts'),
        get_string('enablepublicrss_desc', 'learnplugpodcasts'),
        1
    ));

    $settings->add(new admin_setting_configselect(
        'mod_learnplugpodcasts/defaultpublicaccessmode',
        get_string('defaultpublicaccessmode', 'learnplugpodcasts'),
        get_string('defaultpublicaccessmode_desc', 'learnplugpodcasts'),
        'public',
        [
            'public' => get_string('accessmode_public', 'learnplugpodcasts'),
            'token' => get_string('accessmode_token', 'learnplugpodcasts'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_learnplugpodcasts/allowpublictokenmode',
        get_string('allowpublictokenmode', 'learnplugpodcasts'),
        get_string('allowpublictokenmode_desc', 'learnplugpodcasts'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'mod_learnplugpodcasts/allowedaudiomimetypes',
        get_string('allowedaudiomimetypes', 'learnplugpodcasts'),
        get_string('allowedaudiomimetypes_desc', 'learnplugpodcasts'),
        \mod_learnplugpodcasts\local\util\mime::default_allowed_audio_types_string(),
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'mod_learnplugpodcasts/maxuploadnote',
        get_string('maxuploadnote', 'learnplugpodcasts'),
        get_string('maxuploadnote_desc', 'learnplugpodcasts'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_learnplugpodcasts/defaultepisodesperpage',
        get_string('defaultepisodesperpage', 'learnplugpodcasts'),
        get_string('defaultepisodesperpage_desc', 'learnplugpodcasts'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'mod_learnplugpodcasts/defaultcompletionmode',
        get_string('defaultcompletionmode', 'learnplugpodcasts'),
        get_string('defaultcompletionmode_desc', 'learnplugpodcasts'),
        3,
        [
            0 => get_string('completionlistenmode_none', 'learnplugpodcasts'),
            1 => get_string('completionlistenmode_started', 'learnplugpodcasts'),
            2 => get_string('completionlistenmode_percent', 'learnplugpodcasts'),
            3 => get_string('completionlistenmode_channelrecommended', 'learnplugpodcasts'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'mod_learnplugpodcasts/defaultcompletionpercent',
        get_string('defaultcompletionpercent', 'learnplugpodcasts'),
        get_string('defaultcompletionpercent_desc', 'learnplugpodcasts'),
        70,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_learnplugpodcasts/defaultcompletionepisodecount',
        get_string('defaultcompletionepisodecount', 'learnplugpodcasts'),
        get_string('defaultcompletionepisodecount_desc', 'learnplugpodcasts'),
        3,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_learnplugpodcasts/allowtranscripts',
        get_string('allowtranscripts', 'learnplugpodcasts'),
        get_string('allowtranscripts_desc', 'learnplugpodcasts'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_learnplugpodcasts/enableepisodenotifications',
        get_string('enableepisodenotifications', 'learnplugpodcasts'),
        get_string('enableepisodenotifications_desc', 'learnplugpodcasts'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_learnplugpodcasts/defaultnotifynewepisodes',
        get_string('defaultnotifynewepisodes', 'learnplugpodcasts'),
        get_string('defaultnotifynewepisodes_desc', 'learnplugpodcasts'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_learnplugpodcasts/allowepisodeattachments',
        get_string('allowepisodeattachments', 'learnplugpodcasts'),
        get_string('allowepisodeattachments_desc', 'learnplugpodcasts'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'mod_learnplugpodcasts/publicbasepath',
        get_string('publicbasepath', 'learnplugpodcasts'),
        get_string('publicbasepath_desc', 'learnplugpodcasts'),
        '/mod/learnplugpodcasts/public.php?id={cmid}',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_learnplugpodcasts/brandingsupportemail',
        get_string('brandingsupportemail', 'learnplugpodcasts'),
        get_string('brandingsupportemail_desc', 'learnplugpodcasts'),
        '',
        PARAM_EMAIL
    ));
}
