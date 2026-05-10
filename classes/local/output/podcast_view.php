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

namespace mod_learnplugpodcasts\local\output;

use mod_learnplugpodcasts\local\util\duration;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class podcast_view implements \renderable, \templatable {
    /** @var \stdClass */
    private \stdClass $podcast;
    /** @var array */
    private array $episodes;
    /** @var array */
    private array $options;

    /**
     * Constructor.
     *
     * @param \stdClass $podcast
     * @param array $episodes
     * @param array $options
     */
    public function __construct(\stdClass $podcast, array $episodes, array $options) {
        $this->podcast = $podcast;
        $this->episodes = $episodes;
        $this->options = $options;
    }

    /**
     * Export data for mustache.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $episodedata = [];
        $index = 1;
        foreach ($this->episodes as $episode) {
            $descriptionhtml = format_text(
                $episode->description,
                $episode->descriptionformat,
                ['context' => $this->options['context']]
            );
            $descriptionplain = trim(preg_replace('/\s+/', ' ', strip_tags($descriptionhtml)));
            $captiontracks = $episode->captiontracks ?? [];
            $captiontracksjson = json_encode(
                $captiontracks,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if ($captiontracksjson === false) {
                $captiontracksjson = '[]';
            }
            $episodedata[] = [
                'id' => (int)$episode->id,
                'episodeindex' => $index,
                'isfeatured' => $index === 1,
                'title' => format_string($episode->title),
                'subtitle' => format_string((string)$episode->subtitle),
                'description' => $descriptionhtml,
                'descriptionplain' => shorten_text($descriptionplain, 260),
                'duration' => duration::format_hms((int)$episode->durationsecs),
                'durationsecs' => (int)$episode->durationsecs,
                'publishtime' => userdate((int)$episode->publishtime),
                'publishtimeunix' => (int)$episode->publishtime,
                'audio' => $episode->audiourl ?? '',
                'audiotype' => $episode->audiomimetype ?? 'audio/mpeg',
                'image' => $episode->imageurl ?? '',
                'displayimage' => $episode->imageurl ?? ($this->options['coverimage'] ?? ''),
                'transcripttext' => $episode->transcripttextformatted ?? '',
                'transcriptfileurl' => $episode->transcriptfileurl ?? '',
                'captiontrackurl' => $episode->captiontrackurl ?? '',
                'captiontracklang' => $episode->captiontracklang ?? '',
                'hascaptiontrack' => !empty($episode->captiontrackurl),
                'captiontracks' => $captiontracks,
                'hascaptiontracks' => !empty($captiontracks),
                'captiontracksjson' => $captiontracksjson,
                'attachments' => $episode->attachments ?? [],
                'hasattachments' => !empty($episode->attachments),
                'externalurl' => !empty($episode->externalurl) ? (string)$episode->externalurl : '',
                'hasexternalurl' => !empty($episode->externalurl),
                'hasaudio' => !empty($episode->audiourl),
                'hastranscript' => !empty($episode->transcripttextformatted) || !empty($episode->transcriptfileurl),
                'seasonnumber' => (int)($episode->seasonnumber ?? 0),
                'episodenumber' => (int)($episode->episodenumber ?? 0),
                'status' => $episode->draftstatus,
                'statuslabel' => $this->status_label((string)$episode->draftstatus),
                'iscompleted' => !empty($episode->iscompleted),
                'listenedpercent' => (float)($episode->listenedpercent ?? 0),
                'lastpositionsecs' => (int)($episode->lastpositionsecs ?? 0),
                'likecount' => (int)($episode->likecount ?? 0),
                'userliked' => !empty($episode->userliked),
                'manageediturl' => $episode->manageediturl ?? '',
                'managedeleteurl' => $episode->managedeleteurl ?? '',
                'managetoggleurl' => $episode->managetoggleurl ?? '',
                'managetogglelabel' => $episode->managetogglelabel ?? get_string('publishepisode', 'learnplugpodcasts'),
                'ismarkedpublished' => $episode->draftstatus === 'published',
                'canmanage' => !empty($this->options['canmanage']),
                'canlike' => !empty($this->options['canlike']),
                'showdescriptions' => !empty($this->podcast->showdescriptions),
                'showtranscripts' => !empty($this->podcast->showtranscripts),
                'sesskey' => sesskey(),
            ];
            $index++;
        }

        $featuredepisode = $episodedata[0] ?? null;

        return [
            'podcastid' => (int)$this->podcast->id,
            'name' => format_string($this->podcast->name),
            'subtitle' => format_string((string)$this->podcast->subtitle),
            'authorname' => format_string((string)$this->podcast->authorname),
            'intro' => format_module_intro('learnplugpodcasts', $this->podcast, $this->options['cmid']),
            'coverimage' => $this->options['coverimage'] ?? '',
            'episodes' => $episodedata,
            'canmanage' => !empty($this->options['canmanage']),
            'canviewreports' => !empty($this->options['canviewreports']),
            'canlike' => !empty($this->options['canlike']),
            'showsearch' => !empty($this->podcast->showsearch),
            'showdescriptions' => !empty($this->podcast->showdescriptions),
            'showtranscripts' => !empty($this->podcast->showtranscripts),
            'showsubscribe' => !empty($this->podcast->showsubscribe),
            'hasfeaturedepisode' => !empty($featuredepisode),
            'featuredepisode' => $featuredepisode,
            'rssurl' => $this->options['rssurl'] ?? '',
            'publicurl' => $this->options['publicurl'] ?? '',
            'manageaddurl' => $this->options['manageaddurl'] ?? '',
            'managehelp' => get_string('manageepisodeshelp', 'learnplugpodcasts'),
            'searchplaceholder' => get_string('searchplaceholder', 'learnplugpodcasts'),
            'sort' => $this->options['sort'] ?? 'newest',
            'sortnewest' => $this->options['sort'] === 'newest',
            'sortoldest' => $this->options['sort'] === 'oldest',
            'page' => (int)$this->options['page'],
            'analytics' => $this->options['analytics'] ?? [],
            'hasanalyticsrows' => !empty($this->options['analytics']['hasrows']),
            'hasepisodes' => !empty($episodedata),
            'sesskey' => sesskey(),
            'cmid' => (int)$this->options['cmid'],
        ];
    }

    /**
     * Human-readable status label.
     *
     * @param string $status
     * @return string
     */
    private function status_label(string $status): string {
        if ($status === 'published') {
            return get_string('publishedlabel', 'learnplugpodcasts');
        }
        if ($status === 'unpublished') {
            return get_string('unpublishedlabel', 'learnplugpodcasts');
        }
        return get_string('draftlabel', 'learnplugpodcasts');
    }
}
