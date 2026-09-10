<?php

namespace Warext\Portfolio\Setup;

use XF\Db\Schema\Alter;

trait CommentAttachmentUpgradeTrait
{
    public function upgrade1010107Step1(): void
    {
        $db = $this->db();
        if (!$db->fetchOne("SHOW TABLES LIKE 'xf_wrxt_portfolio_comment'"))
        {
            return;
        }

        if (!$db->fetchRow("SHOW COLUMNS FROM xf_wrxt_portfolio_comment LIKE 'attach_count'"))
        {
            $this->schemaManager()->alterTable('xf_wrxt_portfolio_comment', function(Alter $table)
            {
                $table->addColumn('attach_count', 'smallint')->unsigned()->setDefault(0)->after('message');
            });
        }
    }
}
