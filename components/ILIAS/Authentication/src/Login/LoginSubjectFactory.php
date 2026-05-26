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

final readonly class LoginSubjectFactory
{
    public function __construct(
        private \ilDBInterface $db
    ){ }

    public function forUserId(UserId $user_id): LoginSubject
    {
        $query = 'SELECT active, login_attempts, client_ip, time_limit_unlimited, time_limit_from, time_limit_until, last_login 
                  FROM usr_data WHERE usr_id = %s';
        $result = $this->db->queryF($query, ['integer'], [$user_id->value()]);
        $record = $this->db->fetchAssoc($result);

        if (!$record) {
             throw new \RuntimeException("User not found: " . $user_id->value());
        }

        return new LoginSubject(
            $user_id,
            (bool) $record['active'],
            (int) $record['login_attempts'],
            (string) ($record['client_ip'] ?? ''),
            (bool) $record['time_limit_unlimited'],
            (int) $record['time_limit_from'],
            (int) $record['time_limit_until'],
            (string) ($record['last_login'] ?? ''),
            session_id()
        );
    }
}
