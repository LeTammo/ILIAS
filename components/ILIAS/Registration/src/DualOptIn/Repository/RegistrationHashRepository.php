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

namespace ILIAS\DualOptIn\Repository;

use ILIAS\DualOptIn\Entity\RegistrationHash;

interface RegistrationHashRepository
{
    public function create(int $usr_id): string;
    public function store(int $usr_id, string $hash, string $creation_ts): void;
    public function findByHash(string $hash): ?RegistrationHash;
    public function deleteByUserId(int $usr_id): void;

    /** @return list<RegistrationHash> */
    public function deleteExpired(int $cutoff_ts, ?int $prioritized_usr_id = null): array;
}
