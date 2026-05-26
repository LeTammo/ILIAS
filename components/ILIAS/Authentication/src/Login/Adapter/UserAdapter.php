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

namespace ILIAS\Authentication\Login\Adapter;

use ILIAS\Authentication\Login\Port\Lockout\LoginAttemptRepository;
use ILIAS\Authentication\Login\Port\Account\LoginTimestampsRepository;
use ILIAS\Authentication\Login\Port\Password\PasswordChangeTrackingRepository;
use ILIAS\Authentication\Login\Port\Lockout\AccountDeactivation;
use ILIAS\Authentication\Login\UserId;

final readonly class UserAdapter implements
    LoginAttemptRepository,
    LoginTimestampsRepository,
    PasswordChangeTrackingRepository,
    AccountDeactivation
{
    private \ilDBInterface $db;

    public function __construct(\ilDBInterface $db)
    {
        $this->db = $db;
    }

    public function getCount(UserId $user_id): int
    {
        $query = 'SELECT login_attempts FROM usr_data WHERE usr_id = %s';
        $result = $this->db->queryF($query, ['integer'], [$user_id->value()]);
        $record = $this->db->fetchAssoc($result);
        return (int) ($record['login_attempts'] ?? 0);
    }

    public function increment(UserId $user_id): void
    {
        $query = 'UPDATE usr_data SET login_attempts = (login_attempts + 1) WHERE usr_id = %s';
        $this->db->manipulateF($query, ['integer'], [$user_id->value()]);
    }

    public function reset(UserId $user_id): void
    {
        $query = 'UPDATE usr_data SET login_attempts = 0 WHERE usr_id = %s';
        $this->db->manipulateF($query, ['integer'], [$user_id->value()]);
    }

    public function refreshLogin(UserId $user_id): void
    {
        $now = \ilUtil::now();
        $query = 'UPDATE usr_data SET last_login = %s WHERE usr_id = %s';
        $this->db->manipulateF($query, ['timestamp', 'integer'], [$now, $user_id->value()]);
    }

    public function resetLastChange(UserId $user_id): void
    {
        $query = 'UPDATE usr_data SET last_password_change = 0 WHERE usr_id = %s';
        $this->db->manipulateF($query, ['integer'], [$user_id->value()]);
    }

    public function deactivate(UserId $user_id): void
    {
        $query = 'UPDATE usr_data SET active = 0, inactivation_date = %s WHERE usr_id = %s';
        $this->db->manipulateF($query, ['timestamp', 'integer'], [\ilUtil::now(), $user_id->value()]);
    }
}
