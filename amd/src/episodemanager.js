// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
//
// @module     mod_learnplugpodcasts/episodemanager
// @copyright  2026 LearnPlug
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    const setupDragAndDrop = (config) => {
        const list = document.querySelector('[data-region="lp-episode-list"]');
        if (!list) {
            return;
        }

        let dragSource = null;

        list.querySelectorAll('.lp-episode-card').forEach((card) => {
            card.setAttribute('draggable', 'true');

            card.addEventListener('dragstart', (e) => {
                dragSource = card;
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            card.addEventListener('drop', (e) => {
                e.preventDefault();
                if (!dragSource || dragSource === card) {
                    return;
                }
                const rect = card.getBoundingClientRect();
                const before = e.clientY < rect.top + rect.height / 2;
                if (before) {
                    list.insertBefore(dragSource, card);
                } else {
                    list.insertBefore(dragSource, card.nextSibling);
                }
                saveOrder(config);
            });
        });
    };

    const saveOrder = (config) => {
        const ids = Array.from(document.querySelectorAll('.lp-episode-card'))
            .map((el) => Number(el.dataset.episodeId || 0))
            .filter((id) => id > 0);

        Ajax.call([{
            methodname: 'mod_learnplugpodcasts_reorder_episodes',
            args: {
                cmid: config.cmid,
                episodeids: ids
            }
        }])[0].catch(Notification.exception);
    };

    const init = (config) => {
        if (!config || !config.cmid) {
            return;
        }
        setupDragAndDrop(config);
    };

    return {init};
});
