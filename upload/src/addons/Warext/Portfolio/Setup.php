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

    public function installStep2(): void
    {
        if (!$this->db()->fetchRow("SHOW COLUMNS FROM xf_wrxt_portfolio_moderation_report LIKE 'comment_id'"))
        {
            $this->schemaManager()->alterTable('xf_wrxt_portfolio_moderation_report', function(\XF\Db\Schema\Alter $table)
            {
                $table->addColumn('comment_id', 'int')->unsigned()->setDefault(0)->after('file_id');
                $table->addKey(['comment_id', 'state']);
            });
        }
    }
}
