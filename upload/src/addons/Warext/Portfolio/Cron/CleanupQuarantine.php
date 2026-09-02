<?php

namespace Warext\Portfolio\Cron;

use XF\Util\File;

class CleanupQuarantine
{
    public static function run(): void
    {
        $app = \XF::app();
        $ttlHours = max(1, (int)$app->options()->wrxtPortfolioQuarantineHours);
        $cutoff = \XF::$time - ($ttlHours * 3600);
        $files = \XF::finder('Warext\Portfolio:PortfolioFile')
            ->where('state', ['uploading', 'quarantine'])
            ->where('created_date', '<', $cutoff)
            ->order('file_id')
            ->limit(250)
            ->fetch();
        $stateMachine = new \Warext\Portfolio\Service\StateMachine();
        foreach ($files as $file)
        {
            if ($file->storage_name)
            {
                File::deleteFromAbstractedPath($file->storage_name);
            }
            $stateMachine->transitionFile($file, 'deleted', 'quarantine_expired');
        }
        $app->db()->query("UPDATE xf_wrxt_portfolio_upload_session SET state = 'expired' WHERE state = 'open' AND expires_date < ?", \XF::$time);
        $app->db()->delete('xf_wrxt_portfolio_upload_rate', 'updated_date < ?', \XF::$time - 172800);
    }
}
