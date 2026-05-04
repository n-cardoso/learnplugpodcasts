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
 * Timed captions service (.vtt management).
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class caption_service {
    /** @var string */
    public const FILEAREA = 'episodecaption';
    /** @var int */
    public const MAX_TRACK_FILES = 50;

    /**
     * Returns caption track list for one episode.
     *
     * @param \context_module $context
     * @param int $episodeid
     * @return array
     */
    public function get_caption_tracks(\context_module $context, int $episodeid): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_learnplugpodcasts',
            self::FILEAREA,
            $episodeid,
            'filename',
            false
        );

        $tracks = [];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'vtt') {
                continue;
            }
            $lang = $this->extract_lang_from_filename($filename);
            $tracks[] = [
                'lang' => $lang,
                'label' => $this->language_label($lang, $filename),
                'filename' => $filename,
                'url' => \moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_learnplugpodcasts',
                    self::FILEAREA,
                    $episodeid,
                    $file->get_filepath(),
                    $filename
                )->out(false),
            ];
        }

        usort($tracks, static function (array $a, array $b): int {
            if ($a['lang'] === $b['lang']) {
                return strcmp($a['filename'], $b['filename']);
            }
            return strcmp($a['lang'], $b['lang']);
        });

        return $tracks;
    }

    /**
     * Returns first matching track for preferred language or first available track.
     *
     * @param \context_module $context
     * @param int $episodeid
     * @param string $preferredlang
     * @return array|null
     */
    public function get_primary_caption_track(
        \context_module $context,
        int $episodeid,
        string $preferredlang = ''
    ): ?array {
        $tracks = $this->get_caption_tracks($context, $episodeid);
        if (!$tracks) {
            return null;
        }

        $preferred = $this->normalise_lang_tag($preferredlang);
        if ($preferred !== '') {
            foreach ($tracks as $track) {
                if ($track['lang'] === $preferred) {
                    return $track;
                }
            }
            $shortpreferred = $this->short_lang($preferred);
            foreach ($tracks as $track) {
                if ($this->short_lang($track['lang']) === $shortpreferred) {
                    return $track;
                }
            }
        }

        return $tracks[0];
    }

    /**
     * Saves one .vtt track in the episode caption file area.
     *
     * @param \context_module $context
     * @param int $episodeid
     * @param string $language
     * @param string $vtt
     */
    public function save_vtt_track(
        \context_module $context,
        int $episodeid,
        string $language,
        string $vtt
    ): void {
        $lang = $this->normalise_lang_tag($language);
        if ($lang === '') {
            $lang = 'und';
        }

        $content = $this->ensure_vtt_format($vtt);
        $filename = 'captions.' . $lang . '.vtt';

        $fs = get_file_storage();
        $existing = $fs->get_file(
            $context->id,
            'mod_learnplugpodcasts',
            self::FILEAREA,
            $episodeid,
            '/',
            $filename
        );
        if ($existing) {
            $existing->delete();
        }

        $record = [
            'contextid' => $context->id,
            'component' => 'mod_learnplugpodcasts',
            'filearea' => self::FILEAREA,
            'itemid' => $episodeid,
            'filepath' => '/',
            'filename' => $filename,
        ];
        $fs->create_file_from_string($record, $content);
    }

    /**
     * Extract language tag from caption filename.
     *
     * @param string $filename
     * @return string
     */
    private function extract_lang_from_filename(string $filename): string {
        $matches = [];
        if (preg_match('/(?:^|[._-])([a-z]{2,3}(?:[-_][a-z0-9]{2,8})?)\.vtt$/i', $filename, $matches)) {
            $normalised = $this->normalise_lang_tag((string)$matches[1]);
            if ($normalised !== '') {
                return $normalised;
            }
        }
        return 'und';
    }

    /**
     * Human-readable label for a language tag.
     *
     * @param string $lang
     * @param string $filename
     * @return string
     */
    private function language_label(string $lang, string $filename = ''): string {
        $lang = $this->normalise_lang_tag($lang);
        if ($lang === 'und' || $lang === '') {
            $basename = trim((string)pathinfo($filename, PATHINFO_FILENAME));
            if ($basename !== '') {
                return $basename;
            }
            return get_string('captionlang_unknown', 'learnplugpodcasts');
        }

        $displaycode = strtoupper($lang);
        $translations = get_string_manager()->get_list_of_translations();
        foreach ($translations as $code => $name) {
            $normalised = $this->normalise_lang_tag((string)$code);
            if ($normalised === $lang) {
                return $this->build_clean_language_label((string)$name, $displaycode);
            }
            if ($this->short_lang($normalised) === $this->short_lang($lang)) {
                return $this->build_clean_language_label((string)$name, $displaycode);
            }
        }

        return $displaycode;
    }

    /**
     * Builds a clean language label without duplicated language codes.
     *
     * @param string $name
     * @param string $displaycode
     * @return string
     */
    private function build_clean_language_label(string $name, string $displaycode): string {
        $label = trim(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Remove hidden directional marks that can break suffix matching.
        $label = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $label);
        $label = preg_replace('/\s+/u', ' ', $label);

        // Moodle translation names can already end in language-code suffixes, e.g. "English (en)".
        do {
            $previous = $label;
            $label = preg_replace(
                '/\s*[\(\[]\s*[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?\s*[\)\]]\s*$/iu',
                '',
                $label
            );
        } while ($label !== $previous);

        $label = trim((string)$label);
        if ($label === '') {
            return $displaycode;
        }

        return $label . ' (' . $displaycode . ')';
    }

    /**
     * Normalise language tag for storage.
     *
     * @param string $lang
     * @return string
     */
    private function normalise_lang_tag(string $lang): string {
        $lang = trim(\core_text::strtolower($lang));
        $lang = str_replace('_', '-', $lang);
        if ($lang === '') {
            return '';
        }
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $lang)) {
            return '';
        }
        return $lang;
    }

    /**
     * ISO-639-1/2 short code portion.
     *
     * @param string $lang
     * @return string
     */
    private function short_lang(string $lang): string {
        $lang = $this->normalise_lang_tag($lang);
        if ($lang === '') {
            return '';
        }
        $parts = explode('-', $lang);
        return (string)($parts[0] ?? '');
    }

    /**
     * Ensures text starts with a WEBVTT header.
     *
     * @param string $vtt
     * @return string
     */
    private function ensure_vtt_format(string $vtt): string {
        $clean = str_replace(["\r\n", "\r"], "\n", trim($vtt));
        if ($clean === '') {
            return "WEBVTT\n\n";
        }
        if (stripos($clean, 'WEBVTT') !== 0) {
            $clean = "WEBVTT\n\n" . $clean;
        }
        return $clean . "\n";
    }
}
