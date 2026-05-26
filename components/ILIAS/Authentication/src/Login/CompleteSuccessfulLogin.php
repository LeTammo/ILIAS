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

use ILIAS\Authentication\Login\Port\Lockout\LoginAttemptRepository;
use ILIAS\Authentication\Login\Port\Account\LoginTimestampsRepository;
use ILIAS\Authentication\Login\Port\Password\PasswordChangeTrackingRepository;
use ILIAS\Authentication\Login\Port\Password\PasswordChangeOnFirstLoginSettings;

final readonly class CompleteSuccessfulLogin
{
    public function __construct(
        private LoginAttemptRepository $attempts,
        private LoginTimestampsRepository $timestamps,
        private PasswordChangeTrackingRepository $password_changes,
        private PasswordChangeOnFirstLoginSettings $first_login_policy
    ) {
    }

    public function execute(LoginSubject $subject): void
    {
        if ($this->first_login_policy->isEnabled() && $subject->lastLogin() === '') {
            $this->password_changes->resetLastChange($subject->userId());
        }

        if ($subject->loginAttempts() > 0) {
            $this->attempts->reset($subject->userId());
        }

        $this->timestamps->refreshLogin($subject->userId());
    }
}
