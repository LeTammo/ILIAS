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

namespace ILIAS\Registration\Entity;

class RegistrationHash
{
    private int $usr_id;
    private string $reg_hash;
    private int $creation_date;

    public function __construct(int $usr_id, string $reg_hash, int $creation_date)
    {
        $this->usr_id = $usr_id;
        $this->reg_hash = $reg_hash;
        $this->creation_date = $creation_date;
    }

    public function getUserId(): int
    {
        return $this->usr_id;
    }

    public function getHash(): string
    {
        return $this->reg_hash;
    }

    public function getCreationDate(): int
    {
        return $this->creation_date;
    }
}
