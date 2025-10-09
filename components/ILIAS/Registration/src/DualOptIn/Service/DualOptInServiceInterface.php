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

namespace ILIAS\Registration\Service;

use ilObjUser;
use ilRegConfirmationLinkExpiredException;
use ilRegistrationHashNotFoundException;
use ilRegistrationSettings;

interface DualOptInServiceInterface
{
    /**
     * @throws ilRegistrationHashNotFoundException
     * @throws ilRegConfirmationLinkExpiredException
     */
    public function verifyAndActivateUser(string $hash): ilObjUser;
    public function distributeMailsOnRegistration(ilObjUser $user, ilRegistrationSettings $settings): void;
    public function deleteExpiredUserObjects(int $cutoff_ts, int $usr_id): void;

}
