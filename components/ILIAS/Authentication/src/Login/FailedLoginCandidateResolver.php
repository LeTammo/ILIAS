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

namespace ILIAS\Authentication\Login;

final readonly class FailedLoginCandidateResolver
{
    private \ilDBInterface $db;

    public function __construct(\ilDBInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @param \ilAuthCredentials $credentials
     * @return list<UserId>
     */
    public function resolve(\ilAuthCredentials $credentials): array
    {
        $login = $credentials->getUsername();
        if ($login === '') {
            return [];
        }

        $query = 'SELECT usr_id FROM usr_data WHERE login = %s';
        $result = $this->db->queryF($query, ['text'], [$login]);

        $ids = [];
        while ($record = $this->db->fetchAssoc($result)) {
            $ids[] = new UserId((int) $record['usr_id']);
        }

        return $ids;
    }
}
