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

namespace mod_learnplugpodcasts\output;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render podcast view.
     *
     * @param \mod_learnplugpodcasts\local\output\podcast_view $view
     * @return string
     */
    protected function render_podcast_view(\mod_learnplugpodcasts\local\output\podcast_view $view): string {
        return $this->render_from_template('mod_learnplugpodcasts/view', $view->export_for_template($this));
    }

    /**
     * Render episode card template.
     *
     * @param \mod_learnplugpodcasts\local\output\episode_card $card
     * @return string
     */
    protected function render_episode_card(\mod_learnplugpodcasts\local\output\episode_card $card): string {
        return $this->render_from_template('mod_learnplugpodcasts/episode_card', $card->export_for_template($this));
    }
}
