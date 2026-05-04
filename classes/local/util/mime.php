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

namespace mod_learnplugpodcasts\local\util;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mime {
    /** @var string */
    private const DEFAULT_ALLOWED_AUDIO_TYPES =
        'audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/aac,' .
        'audio/ogg,audio/wav,audio/x-wav,audio/wave';

    /**
     * Returns configured allowed audio MIME types.
     *
     * @return array
     */
    public static function allowed_audio_types(): array {
        $raw = trim((string)get_config('mod_learnplugpodcasts', 'allowedaudiomimetypes'));
        if ($raw === '') {
            $raw = self::DEFAULT_ALLOWED_AUDIO_TYPES;
        }

        $types = array_filter(array_map('trim', explode(',', $raw)));
        return array_values(array_unique(array_map([self::class, 'normalise'], $types)));
    }

    /**
     * Checks if mime type is allowed.
     *
     * @param string $mimetype
     * @return bool
     */
    public static function is_allowed_audio(string $mimetype): bool {
        $needle = self::normalise($mimetype);
        return in_array($needle, self::allowed_audio_types(), true);
    }

    /**
     * Return canonical MIME type for browser source tags.
     *
     * @param string $mimetype
     * @return string
     */
    public static function canonical_audio_type(string $mimetype): string {
        return self::normalise($mimetype);
    }

    /**
     * Returns default value for settings/install/upgrade.
     *
     * @return string
     */
    public static function default_allowed_audio_types_string(): string {
        return self::DEFAULT_ALLOWED_AUDIO_TYPES;
    }

    /**
     * Canonicalise MIME aliases.
     *
     * @param string $mimetype
     * @return string
     */
    private static function normalise(string $mimetype): string {
        $value = \core_text::strtolower(trim($mimetype));
        $aliases = [
            'audio/x-mp3' => 'audio/mp3',
            'audio/x-m4a' => 'audio/mp4',
            'audio/x-wav' => 'audio/wav',
            'audio/wave' => 'audio/wav',
            'audio/vnd.wave' => 'audio/wav',
        ];
        return $aliases[$value] ?? $value;
    }
}
