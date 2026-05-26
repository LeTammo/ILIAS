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

final readonly class LoginSubject
{
    public function __construct(
        private UserId $user_id,
        private bool $active,
        private int $login_attempts,
        private string $client_ip_pattern,
        private bool $unlimited_account,
        private int $valid_from,
        private int $expires_at,
        private string $last_login,
        private string $current_session_id
    ) {
    }

    public function userId(): UserId
    {
        return $this->user_id;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function loginAttempts(): int
    {
        return $this->login_attempts;
    }

    public function clientIpPattern(): string
    {
        return $this->client_ip_pattern;
    }

    public function isUnlimitedAccount(): bool
    {
        return $this->unlimited_account;
    }

    public function isValidFrom(): int
    {
        return $this->valid_from;
    }

    public function expiresAt(): int
    {
        return $this->expires_at;
    }

    public function lastLogin(): string
    {
        return $this->last_login;
    }

    public function currentSessionId(): string
    {
        return $this->current_session_id;
    }
}
