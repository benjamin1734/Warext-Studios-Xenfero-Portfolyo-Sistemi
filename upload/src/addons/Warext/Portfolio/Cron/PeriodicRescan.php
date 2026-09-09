<?php

namespace Warext\Portfolio\Cron;

class PeriodicRescan
{
    public static function run(): void
    {
        $days = max(1, (int)(\XF::options()->wrxtPortfolioRescanDays ?? 30));
        $cutoff = \XF::$time - ($days * 86400);
        $files = \XF::finder('Warext\\Portfolio:PortfolioFile')
            ->where('state', ['security_passed', 'moderation', 'published'])
            ->where('last_scan_date', '<', $cutoff)
            ->order('last_scan_date', 'ASC')
            ->limit(25)
            ->fetch();
        foreach ($files as $file)
        {
            \XF::app()->jobManager()->enqueueUnique('wrxtPortfolioRescan_' . (int)$file->file_id, 'Warext\\Portfolio:RescanFile', ['file_id' => (int)$file->file_id, 'reason' => 'periodic'], false, 115);
        }
    }
}
