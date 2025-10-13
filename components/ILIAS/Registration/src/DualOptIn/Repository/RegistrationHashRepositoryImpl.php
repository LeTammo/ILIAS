<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\DualOptIn\Repository;

use ilDBConstants;
use ilDBInterface;
use ILIAS\Data\Clock\ClockFactory;
use ILIAS\DualOptIn\Entity\RegistrationHash;

readonly class RegistrationHashRepositoryImpl implements RegistrationHashRepository
{
    public function __construct(
        protected ilDBInterface $db,
        protected ClockFactory $clock_factory
    ) {
    }

    public function create(int $usr_id): string
    {
        do {
            $unique_id = uniqid((string) mt_rand(), true);
            $hash = substr(md5($unique_id), 0, 16);

            $res = $this->db->queryf(
                'SELECT COUNT(usr_id) cnt FROM reg_dual_opt_in WHERE reg_hash = %s',
                [ilDBConstants::T_TEXT],
                [$hash]
            );
            $row = $this->db->fetchObject($res);
            if ($row->cnt > 0) {
                continue;
            }
            break;
        } while (true);

        $this->db->manipulateF(
            'INSERT INTO reg_dual_opt_in (usr_id, reg_hash, creation_date) VALUES (%s, %s, %s)',
            [ilDBConstants::T_INTEGER, ilDBConstants::T_TEXT, ilDBConstants::T_INTEGER],
            [$usr_id, $hash, $this->clock_factory->utc()->now()->getTimestamp() ]
        );

        return $hash;
    }

    public function store(int $usr_id, string $hash, string $creation_ts): void
    {
        $this->db->manipulateF(
            'REPLACE INTO reg_dual_opt_in (usr_id, reg_hash, creation_date) VALUES (%s, %s, %s)',
            [ilDBConstants::T_INTEGER, ilDBConstants::T_TEXT, ilDBConstants::T_INTEGER],
            [$usr_id, $hash, $creation_ts]
        );
    }

    public function findByHash(string $hash): ?RegistrationHash
    {
        $res = $this->db->queryf(
            'SELECT usr_id, reg_hash, creation_date FROM reg_dual_opt_in WHERE reg_hash = %s',
            [ilDBConstants::T_TEXT],
            [$hash]
        );

        $row = $this->db->fetchAssoc($res);
        if (!$row) {
            return null;
        }

        return new RegistrationHash($row['usr_id'], $row['reg_hash'], $row['creation_date']);
    }

    public function deleteByUserId(int $usr_id): void
    {
        $this->db->manipulateF(
            'DELETE FROM reg_dual_opt_in WHERE usr_id = %s',
            [ilDBConstants::T_INTEGER],
            [$usr_id]
        );
    }

    /**
     * @return list<RegistrationHash>
     */
    public function deleteExpired(int $cutoff_ts, ?int $prioritized_usr_id = null): array
    {
        $except_anon_and_sys = $this->db->in(
            'h.usr_id',
            [ANONYMOUS_USER_ID, SYSTEM_USER_ID],
            true,
            ilDBConstants::T_INTEGER
        );

        $query = '
            SELECT h.*
              FROM reg_dual_opt_in h
              JOIN usr_data u ON u.usr_id = h.usr_id
             WHERE u.active = 0
               AND h.creation_date < %s
               AND ' . $except_anon_and_sys . '
             ORDER BY (CASE WHEN h.usr_id = %s THEN 0 ELSE 1 END), h.creation_date
         ';

        $res = $this->db->queryF(
            $query,
            [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER],
            [$cutoff_ts, $prioritized_usr_id ?? 0]
        );

        $expired_hashes = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $this->deleteByUserId($row['usr_id']);
            $expired_hashes[] = new RegistrationHash($row['usr_id'], $row['reg_hash'], $row['creation_date']);
        }

        return $expired_hashes;
    }
}
