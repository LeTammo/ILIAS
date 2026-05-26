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

namespace ILIAS\Authentication\Login\Port\Lockout;

use ILIAS\Authentication\Login\Port\Lockout\LoginAttemptLimit;
use ILIAS\Authentication\Login\Port\Lockout\LoginAttemptRepository;
use ILIAS\Authentication\Login\Port\Lockout\AccountDeactivation;
use ILIAS\Authentication\Login\UserId;
use ILIAS\Data\Result;

final readonly class RecordFailedLoginAttempts
{
    public function __construct(
        private LoginAttemptLimit $limit,
        private LoginAttemptRepository $attempts,
        private AccountDeactivation $deactivation
    ) {
    }

    /**
     * @param list<UserId> $candidate_usr_ids
     * @return Result<int> Anzahl deaktivierter Konten
     */
    public function execute(array $candidate_usr_ids): Result
    {
        $max = $this->limit->maxAttempts();
        if ($max < 1) {
            return new Result\Ok(0);
        }

        $num_deactivated = 0;
        foreach ($candidate_usr_ids as $usr_id) {
            if ($usr_id->isAnonymous()) {
                continue;
            }

            $count = $this->attempts->getCount($usr_id);
            if ($count < $max) {
                $this->attempts->increment($usr_id);
                if ($this->attempts->getCount($usr_id) >= $max) {
                    $this->deactivation->deactivate($usr_id);
                    ++$num_deactivated;
                }
                continue;
            }

            $this->deactivation->deactivate($usr_id);
            ++$num_deactivated;
        }

        return new Result\Ok($num_deactivated);
    }
}
