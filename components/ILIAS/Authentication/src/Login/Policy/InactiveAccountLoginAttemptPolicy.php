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

namespace ILIAS\Authentication\Login\Policy;

use ILIAS\Authentication\Login\LoginSubject;
use ILIAS\Authentication\Login\Port\Lockout\LoginAttemptLimit;
use ILIAS\Data\Result;

final readonly class InactiveAccountLoginAttemptPolicy implements PostLoginPolicy
{
    public function __construct(
        private LoginAttemptLimit $limit
    ) {
    }

    public function evaluate(LoginSubject $subject): Result
    {
        if ($subject->isActive() || $subject->userId()->isAnonymous()) {
            return new Result\Ok(null);
        }

        $max = $this->limit->maxAttempts();
        if ($max < 1) {
            return new Result\Ok(null);
        }

        if ($subject->loginAttempts() < $max) {
            return new Result\Ok(null);
        }

        return new Result\Error('auth_err_invalid_user_account');
    }
}
