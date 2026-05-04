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
class duration {
    /**
     * Format seconds as HH:MM:SS.
     *
     * @param int $seconds
     * @return string
     */
    public static function format_hms(int $seconds): string {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Parses HH:MM:SS or MM:SS into seconds.
     *
     * @param string $value
     * @return int
     */
    public static function parse_to_seconds(string $value): int {
        $value = trim($value);
        if ($value === '' || is_numeric($value)) {
            return max(0, (int)$value);
        }

        $parts = array_map('intval', explode(':', $value));
        if (count($parts) === 2) {
            return max(0, ($parts[0] * 60) + $parts[1]);
        }
        if (count($parts) === 3) {
            return max(0, ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2]);
        }

        return 0;
    }

    /**
     * Tries to detect audio duration from a stored file using Moodle-bundled getID3.
     *
     * @param \stored_file|null $file
     * @return int
     */
    public static function detect_seconds_from_stored_file(?\stored_file $file): int {
        global $CFG;

        if (!$file || $file->is_directory()) {
            return 0;
        }

        $getid3lib = $CFG->libdir . '/getid3/getid3/getid3.php';
        if (!is_readable($getid3lib)) {
            $getid3lib = $CFG->libdir . '/getid3/getid3.php';
        }
        if (!is_readable($getid3lib)) {
            return 0;
        }
        require_once($getid3lib);
        if (!class_exists('\\getID3')) {
            return 0;
        }

        $tempdir = make_temp_directory('mod_learnplugpodcasts');
        $filename = clean_param($file->get_filename(), PARAM_FILE);
        if ($filename === '' || $filename === '.') {
            $filename = 'episode-audio.bin';
        }
        $tmppath = $tempdir . '/' . uniqid('lp-dur-', true) . '-' . $filename;

        try {
            $file->copy_content_to($tmppath);
            $wavaduration = self::detect_wav_seconds_from_path($tmppath);
            if ($wavaduration > 0) {
                return $wavaduration;
            }
            $analyser = new \getID3();
            $info = $analyser->analyze($tmppath);
        } catch (\Throwable $e) {
            $info = [];
        } finally {
            @unlink($tmppath);
        }

        if (!empty($info['playtime_seconds']) && is_numeric($info['playtime_seconds'])) {
            return max(0, (int)round((float)$info['playtime_seconds']));
        }

        if (!empty($info['playtime_string']) && is_string($info['playtime_string'])) {
            return self::parse_to_seconds($info['playtime_string']);
        }

        return 0;
    }

    /**
     * Detect WAV duration from RIFF header/chunks.
     *
     * @param string $path
     * @return int
     */
    private static function detect_wav_seconds_from_path(string $path): int {
        if (!is_readable($path)) {
            return 0;
        }

        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return 0;
        }

        $duration = 0;
        try {
            $head = (string)fread($fh, 12);
            if (strlen($head) < 12) {
                return 0;
            }
            if (substr($head, 0, 4) !== 'RIFF' || substr($head, 8, 4) !== 'WAVE') {
                return 0;
            }

            $byterate = 0;
            $datasize = 0;

            while (!feof($fh)) {
                $chunkhead = (string)fread($fh, 8);
                if (strlen($chunkhead) < 8) {
                    break;
                }

                $chunkid = substr($chunkhead, 0, 4);
                $chunksize = (int)unpack('V', substr($chunkhead, 4, 4))[1];

                if ($chunkid === 'fmt ') {
                    $fmt = (string)fread($fh, min($chunksize, 64));
                    if (strlen($fmt) >= 8) {
                        $byterate = (int)unpack('V', substr($fmt, 4, 4))[1];
                    }
                    $remaining = $chunksize - strlen($fmt);
                    if ($remaining > 0) {
                        fseek($fh, $remaining, SEEK_CUR);
                    }
                } else if ($chunkid === 'data') {
                    $datasize = $chunksize;
                    fseek($fh, $chunksize, SEEK_CUR);
                } else {
                    fseek($fh, $chunksize, SEEK_CUR);
                }

                if ($chunksize % 2 === 1) {
                    fseek($fh, 1, SEEK_CUR);
                }

                if ($byterate > 0 && $datasize > 0) {
                    $duration = (int)round($datasize / $byterate);
                    break;
                }
            }
        } finally {
            fclose($fh);
        }

        return max(0, $duration);
    }
}
