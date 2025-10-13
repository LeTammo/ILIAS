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

namespace ILIAS\DualOptIn\Setup;

use ilDatabaseUpdateStepsExecutedObjective;
use ilDatabaseUpdateStepsMetricsCollectedObjective;
use ilFileSystemComponentDataDirectoryCreatedObjective;
use ILIAS\Refinery\Transformation;
use ILIAS\Setup\Agent;
use ILIAS\Setup\Agent\HasNoNamedObjective;
use ILIAS\Setup\Config;
use ILIAS\Setup\Metrics\Storage;
use ILIAS\Setup\Objective;
use ILIAS\Setup\Objective\NullObjective;
use ILIAS\Setup\ObjectiveCollection;
use LogicException;

class RegistrationHashSetupAgent implements Agent
{
    use HasNoNamedObjective;

    public function hasConfig(): bool
    {
        return false;
    }

    public function getArrayToConfigTransformation(): Transformation
    {
        throw new LogicException("Agent has no config.");
    }

    public function getInstallObjective(Config $config = null): Objective
    {
        return new ilFileSystemComponentDataDirectoryCreatedObjective(
            'registration_hash',
            ilFileSystemComponentDataDirectoryCreatedObjective::DATADIR
        );
    }

    public function getUpdateObjective(?Config $config = null): Objective
    {
        return new ObjectiveCollection(
            'RegistrationHash',
            true,
            new ilDatabaseUpdateStepsExecutedObjective(new RegistrationHashDatabaseUpdateSteps()),
        );
    }

    public function getBuildObjective(): Objective
    {
        return new NullObjective();
    }

    public function getStatusObjective(Storage $storage): Objective
    {
        return new ObjectiveCollection(
            'Component RegistrationHash',
            true,
            new ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new RegistrationHashDatabaseUpdateSteps()),
        );
    }

    public function getMigrations(): array
    {
        return [];
    }
}
