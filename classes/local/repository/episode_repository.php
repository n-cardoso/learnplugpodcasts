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

namespace mod_learnplugpodcasts\local\repository;


/**
 * Class definition.
 *
 * @package mod_learnplugpodcasts
 * @copyright 2026 LearnPlug
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class episode_repository {
    /** @var string */
    private const TABLE = 'learnplugpodcasts_eps';

    /**
     * Gets a single episode by id.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public function get_by_id(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Gets episodes by podcast id.
     *
     * @param int $podcastid
     * @param bool $onlypublished
     * @param string $sort
     * @return array
     */
    public function get_by_podcast(int $podcastid, bool $onlypublished = false, string $sort = 'newest'): array {
        global $DB;

        $params = ['podcastid' => $podcastid];
        $where = 'podcastid = :podcastid';
        if ($onlypublished) {
            $where .= ' AND draftstatus = :status';
            $params['status'] = 'published';
        }

        $ordersql = $this->get_order_sql($sort);

        return $DB->get_records_select(self::TABLE, $where, $params, $ordersql);
    }

    /**
     * Gets paged episode list.
     *
     * @param int $podcastid
     * @param bool $onlypublished
     * @param string $sort
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $search
     * @return array
     */
    public function get_paged(
        int $podcastid,
        bool $onlypublished,
        string $sort,
        int $limitfrom,
        int $limitnum,
        string $search = ''
    ): array {
        global $DB;

        $params = ['podcastid' => $podcastid];
        $where = 'podcastid = :podcastid';

        if ($onlypublished) {
            $where .= ' AND draftstatus = :status';
            $params['status'] = 'published';
        }

        if ($search !== '') {
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
            $where .= ' AND (' . $DB->sql_like('title', ':search', false, false) . ' OR ' .
                $DB->sql_like('description', ':search', false, false) . ')';
        }

        $ordersql = $this->get_order_sql($sort);

        return $DB->get_records_select(self::TABLE, $where, $params, $ordersql, '*', $limitfrom, $limitnum);
    }

    /**
     * Counts episodes with optional filters.
     *
     * @param int $podcastid
     * @param bool $onlypublished
     * @param string $search
     * @return int
     */
    public function count(int $podcastid, bool $onlypublished = false, string $search = ''): int {
        global $DB;

        $params = ['podcastid' => $podcastid];
        $where = 'podcastid = :podcastid';

        if ($onlypublished) {
            $where .= ' AND draftstatus = :status';
            $params['status'] = 'published';
        }

        if ($search !== '') {
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
            $where .= ' AND (' . $DB->sql_like('title', ':search', false, false) . ' OR ' .
                $DB->sql_like('description', ':search', false, false) . ')';
        }

        return $DB->count_records_select(self::TABLE, $where, $params);
    }

    /**
     * Inserts episode record.
     *
     * @param \stdClass $record
     * @return int
     */
    public function insert(\stdClass $record): int {
        global $DB;
        return (int)$DB->insert_record(self::TABLE, $record);
    }

    /**
     * Updates episode record.
     *
     * @param \stdClass $record
     * @return bool
     */
    public function update(\stdClass $record): bool {
        global $DB;
        return (bool)$DB->update_record(self::TABLE, $record);
    }

    /**
     * Deletes an episode.
     *
     * @param int $id
     */
    public function delete(int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Returns max sort order for a podcast.
     *
     * @param int $podcastid
     * @return int
     */
    public function get_max_sort_order(int $podcastid): int {
        global $DB;
        $max = $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {learnplugpodcasts_eps} WHERE podcastid = :podcastid',
            ['podcastid' => $podcastid]);
        return (int)$max;
    }

    /**
     * Reorders episodes.
     *
     * @param int $podcastid
     * @param array $orderedids
     */
    public function reorder(int $podcastid, array $orderedids): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $position = 1;
        foreach ($orderedids as $episodeid) {
            $DB->set_field(self::TABLE, 'sortorder', $position, ['id' => (int)$episodeid, 'podcastid' => $podcastid]);
            $position++;
        }
        $transaction->allow_commit();
    }

    /**
     * Returns SQL ORDER BY clause for list presentation.
     *
     * @param string $sort
     * @return string
     */
    private function get_order_sql(string $sort): string {
        if ($sort === 'manual') {
            return 'sortorder ASC, id ASC';
        }
        if ($sort === 'oldest') {
            return 'publishtime ASC, id ASC';
        }
        return 'publishtime DESC, id DESC';
    }
}
