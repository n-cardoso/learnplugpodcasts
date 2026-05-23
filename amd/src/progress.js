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
// @module     mod_learnplugpodcasts/progress
// @copyright  2026 LearnPlug
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    const states = new Map();

    const getState = (player) => {
        if (!states.has(player)) {
            states.set(player, {
                lastPos: Number(player.currentTime || 0),
                pending: 0,
                duration: Number(player.duration || 0),
                seeking: false,
                timer: null
            });
        }
        return states.get(player);
    };

    const callSave = (config, player, force) => {
        const state = getState(player);
        const episodeid = Number(player.dataset.episodeId || 0);
        if (!episodeid || (!force && state.pending < 1)) {
            return;
        }

        const delta = Math.max(0, Math.min(state.pending, 35));
        state.pending = 0;

        Ajax.call([{
            methodname: 'mod_learnplugpodcasts_save_progress',
            args: {
                cmid: config.cmid,
                episodeid: episodeid,
                positionsecs: Math.floor(player.currentTime || 0),
                advanceddelta: delta,
                durationsecs: Math.floor(player.duration || state.duration || 0),
                playstate: player.paused ? 'paused' : 'playing'
            }
        }])[0].then((response) => {
            const label = player.closest('.lp-player-wrap')?.querySelector('[data-region="lp-progress-label"]');
            if (label) {
                label.textContent = `Listened: ${Math.round(response.listenedpercent)}%`;
            }
            return response;
        }).catch(Notification.exception);
    };

    const bindPlayer = (config, player) => {
        const state = getState(player);

        player.addEventListener('loadedmetadata', () => {
            state.duration = Number(player.duration || 0);
            state.lastPos = Number(player.currentTime || 0);
        });

        player.addEventListener('seeking', () => {
            state.seeking = true;
        });

        player.addEventListener('seeked', () => {
            state.seeking = false;
            state.lastPos = Number(player.currentTime || 0);
        });

        player.addEventListener('timeupdate', () => {
            if (player.paused || state.seeking) {
                state.lastPos = Number(player.currentTime || 0);
                return;
            }

            const current = Number(player.currentTime || 0);
            const advance = current - state.lastPos;
            if (advance > 0 && advance < 3.5) {
                state.pending += advance;
            }
            state.lastPos = current;
        });

        player.addEventListener('play', () => {
            if (state.timer) {
                clearInterval(state.timer);
            }
            state.timer = setInterval(() => callSave(config, player, false), 15000);
        });

        player.addEventListener('pause', () => {
            if (state.timer) {
                clearInterval(state.timer);
                state.timer = null;
            }
            callSave(config, player, true);
        });

        player.addEventListener('ended', () => {
            if (state.timer) {
                clearInterval(state.timer);
                state.timer = null;
            }
            callSave(config, player, true);
        });

        window.addEventListener('beforeunload', () => {
            callSave(config, player, true);
        });
    };

    const init = (config) => {
        if (!config || !config.cmid) {
            return;
        }
        document.querySelectorAll('audio.lp-audio').forEach((player) => bindPlayer(config, player));
    };

    return {init};
});
