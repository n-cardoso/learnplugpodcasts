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
class xml {
    /**
     * Formats a timestamp for RSS.
     *
     * @param int $timestamp
     * @return string
     */
    public static function rss_date(int $timestamp): string {
        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }

    /**
     * Appends CDATA node to element.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $parent
     * @param string $name
     * @param string $value
     */
    public static function append_cdata(\DOMDocument $doc, \DOMElement $parent, string $name, string $value): void {
        $child = $doc->createElement($name);
        $child->appendChild($doc->createCDATASection($value));
        $parent->appendChild($child);
    }
}
