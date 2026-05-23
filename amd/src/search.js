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
// @module     mod_learnplugpodcasts/search
// @copyright  2026 LearnPlug
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define([], function() {
    const toNumber = (value) => {
        const number = Number(value);
        return Number.isFinite(number) ? number : 0;
    };

    const sortCards = (cards, sortmode) => {
        const sorted = cards.slice();
        sorted.sort((a, b) => {
            const apublish = toNumber(a.dataset.publishtime);
            const bpublish = toNumber(b.dataset.publishtime);
            const aduration = toNumber(a.dataset.durationsecs);
            const bduration = toNumber(b.dataset.durationsecs);
            const atitle = String(a.dataset.title || '').toLowerCase();
            const btitle = String(b.dataset.title || '').toLowerCase();

            switch (sortmode) {
                case 'oldest':
                    return apublish - bpublish;
                case 'titleaz':
                    return atitle.localeCompare(btitle);
                case 'titleza':
                    return btitle.localeCompare(atitle);
                case 'durationlong':
                    return bduration - aduration;
                case 'durationshort':
                    return aduration - bduration;
                case 'newest':
                default:
                    return bpublish - apublish;
            }
        });
        return sorted;
    };

    const matchesTranscriptFilter = (filter, hastranscript) => {
        if (filter === 'all') {
            return true;
        }
        if (filter === 'with') {
            return hastranscript;
        }
        if (filter === 'without') {
            return !hastranscript;
        }
        return true;
    };

    const matchesMediaFilter = (filter, hasaudio, hasexternal) => {
        if (filter === 'all') {
            return true;
        }
        if (filter === 'audio') {
            return hasaudio;
        }
        if (filter === 'external') {
            return hasexternal;
        }
        return true;
    };

    const cardMatchesFilters = (card, filters) => {
        const text = card.textContent.toLowerCase();
        const cardstatus = String(card.dataset.status || '');
        const hastranscript = String(card.dataset.hasTranscript || '0') === '1';
        const hasaudio = String(card.dataset.hasAudio || '0') === '1';
        const hasexternal = String(card.dataset.hasExternal || '0') === '1';
        const cardseason = String(card.dataset.season || '0');

        const matchquery = !filters.query || text.includes(filters.query);
        const matchstatus = filters.status === 'all' || cardstatus === filters.status;
        const matchtranscript = matchesTranscriptFilter(filters.transcript, hastranscript);
        const matchmedia = matchesMediaFilter(filters.media, hasaudio, hasexternal);
        const matchseason = filters.season === '' || cardseason === filters.season;

        return matchquery && matchstatus && matchtranscript && matchmedia && matchseason;
    };

    const init = () => {
        const input = document.querySelector('[data-region="lp-podcast-search"]');
        const sortselect = document.querySelector('[data-region="lp-podcast-sort"]');
        const statusfilter = document.querySelector('[data-region="lp-filter-status"]');
        const transcriptfilter = document.querySelector('[data-region="lp-filter-transcript"]');
        const mediafilter = document.querySelector('[data-region="lp-filter-media"]');
        const seasonfilter = document.querySelector('[data-region="lp-filter-season"]');
        const list = document.querySelector('[data-region="lp-episode-list"]');
        const cards = Array.from(document.querySelectorAll('.lp-episode-card'));

        if (!list || !cards.length) {
            return;
        }

        const applyState = () => {
            const filters = {
                query: String(input?.value || '').trim().toLowerCase(),
                sortmode: String(sortselect?.value || 'newest'),
                status: String(statusfilter?.value || 'all'),
                transcript: String(transcriptfilter?.value || 'all'),
                media: String(mediafilter?.value || 'all'),
                season: String(seasonfilter?.value || '').trim(),
            };

            const sorted = sortCards(cards, filters.sortmode);
            sorted.forEach((card) => list.appendChild(card));

            cards.forEach((card) => {
                card.style.display = cardMatchesFilters(card, filters) ? '' : 'none';
            });
        };

        if (input) {
            input.addEventListener('input', applyState);
        }
        if (sortselect) {
            sortselect.addEventListener('change', applyState);
        }
        if (statusfilter) {
            statusfilter.addEventListener('change', applyState);
        }
        if (transcriptfilter) {
            transcriptfilter.addEventListener('change', applyState);
        }
        if (mediafilter) {
            mediafilter.addEventListener('change', applyState);
        }
        if (seasonfilter) {
            seasonfilter.addEventListener('input', applyState);
        }

        applyState();
    };

    return {init};
});
