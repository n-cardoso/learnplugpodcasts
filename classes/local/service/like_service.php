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

use mod_learnplugpodcasts\local\repository\like_repository;

/**
 * Business logic for likes.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class like_service {
    /** @var like_repository */
    private like_repository $repository;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->repository = new like_repository();
    }

    /**
     * Toggle one learner like on/off for an episode.
     *
     * @param int $podcastid
     * @param int $episodeid
     * @param int $userid
     * @return array{liked:bool, likecount:int}
     */
    public function toggle_like(int $podcastid, int $episodeid, int $userid): array {
        $existing = $this->repository->get_episode_user($episodeid, $userid);
        if ($existing) {
            $this->repository->delete((int)$existing->id);
            return [
                'liked' => false,
                'likecount' => $this->repository->count_episode($episodeid),
            ];
        }

        $this->repository->create($podcastid, $episodeid, $userid);
        return [
            'liked' => true,
            'likecount' => $this->repository->count_episode($episodeid),
        ];
    }

    /**
     * Get episode like counts.
     *
     * @param int[] $episodeids
     * @return array<int,int> map episodeid => count
     */
    public function get_episode_like_counts(array $episodeids): array {
        return $this->repository->count_episode_ids($episodeids);
    }

    /**
     * Get liked episode map for user.
     *
     * @param int $userid
     * @param int[] $episodeids
     * @return array<int,bool> map episodeid => true
     */
    public function get_user_liked_episode_map(int $userid, array $episodeids): array {
        return $this->repository->get_user_episode_map($userid, $episodeids);
    }
}
