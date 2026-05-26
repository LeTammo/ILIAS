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
use ILIAS\Authentication\Login\Port\Account\AccountReactivationSettings;
use ILIAS\Data\Clock\ClockInterface;
use ILIAS\Data\Result;

final readonly class TimeLimitPolicy implements PostLoginPolicy
{
    public function __construct(
        private AccountReactivationSettings $reactivation,
        private ClockInterface $clock
    ) {
    }

    public function evaluate(LoginSubject $subject): Result
    {
        if ($this->isWithinTimeLimit($subject)) {
            return new Result\Ok(null);
        }

        if ($this->reactivation->isReactivationCodeEnabled()) {
            return new Result\Error('STATUS_CODE_ACTIVATION_REQUIRED');
        }

        return new Result\Error('auth_err_invalid_user_account');
    }

    private function isWithinTimeLimit(LoginSubject $subject): bool
    {
        if ($subject->isUnlimitedAccount()) {
            return true;
        }

        $now = $this->clock->now();

        return $subject->isValidFrom() < $now && $subject->expiresAt() > $now;
    }
}
