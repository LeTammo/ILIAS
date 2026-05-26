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
use ILIAS\Data\Result;

final readonly class PostLoginPolicyRunner
{
    /** @param list<PostLoginPolicy> $policies */
    public function __construct(
        private array $policies
    ) {
    }

    public function evaluate(LoginSubject $subject): Result
    {
        foreach ($this->policies as $policy) {
            $result = $policy->evaluate($subject);
            if ($result->isError()) {
                return $result;
            }
        }

        return new Result\Ok(null);
    }
}
