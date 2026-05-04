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

namespace mod_learnplugpodcasts\local\service;

/**
 * Transcript retrieval service.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transcript_service {
    /**
     * Returns transcript render data for an episode.
     *
     * @param \context_module $context
     * @param \stdClass $episode
     * @return array
     */
    public function get_transcript_data(\context_module $context, \stdClass $episode): array {
        $result = [
            'hastext' => !empty($episode->transcripttext),
            'text' => '',
            'fileurl' => '',
        ];

        if (!empty($episode->transcripttext)) {
            $result['text'] = format_text(
                $episode->transcripttext,
                $episode->transcriptformat ?? FORMAT_HTML,
                ['context' => $context]
            );
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_learnplugpodcasts',
            'episodetranscript',
            (int)$episode->id,
            'timemodified DESC',
            false
        );
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if ($extension === 'vtt') {
                // VTT belongs to timed captions and should not be exposed as transcript download.
                continue;
            }
            $result['fileurl'] = \moodle_url::make_pluginfile_url(
                $context->id,
                'mod_learnplugpodcasts',
                'episodetranscript',
                (int)$episode->id,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            break;
        }

        return $result;
    }

    /**
     * Checks whether episode already has a transcript version.
     *
     * @param \context_module $context
     * @param \stdClass $episode
     * @return bool
     */
    public function has_transcript(\context_module $context, \stdClass $episode): bool {
        if (!empty(trim((string)($episode->transcripttext ?? '')))) {
            return true;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_learnplugpodcasts',
            'episodetranscript',
            (int)$episode->id,
            'filename',
            false
        );
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if ($extension !== 'vtt') {
                return true;
            }
        }

        return false;
    }
}

