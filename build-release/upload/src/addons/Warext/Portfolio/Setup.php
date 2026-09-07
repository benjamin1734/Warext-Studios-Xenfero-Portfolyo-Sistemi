<?php

namespace Warext\Portfolio;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use Warext\Portfolio\Setup\AuxSchemaTrait;
use Warext\Portfolio\Setup\CoreSchemaTrait;
use Warext\Portfolio\Setup\DefaultsTrait;
use Warext\Portfolio\Setup\UpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;
    use CoreSchemaTrait;
    use AuxSchemaTrait;
    use UpgradeTrait;
    use DefaultsTrait;

    public function installStep1(): void
    {
        $this->createCoreTables();
        $this->createTagTables();
        $this->createUploadPolicyTables();
        $this->createSecurityTables();
        $this->createBlobTable();
        $this->createCommunityTables();
        $this->createModerationTables();
    }
}
