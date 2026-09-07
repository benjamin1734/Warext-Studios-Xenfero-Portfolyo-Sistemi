<?php

namespace Warext\Portfolio\Cron;

class SecurityQueue
{
    public static function run(): void
    {
        $db = \XF::db();
        $ids = $db->fetchAllColumn(
            "SELECT file_id FROM xf_wrxt_portfolio_file WHERE state IN ('quarantine','validating','scanning') AND (next_scan_date = 0 OR next_scan_date <= ?) ORDER BY file_id LIMIT 100",
            \XF::$time
        );

        $manager = \XF::app()->jobManager();
        foreach ($ids as $fileId)
        {
            $fileId = (int)$fileId;
            $manager->enqueueUnique(
                'wrxtPortfolioSecurity_' . $fileId,
                'Warext\Portfolio:SecurityScan',
                ['file_id' => $fileId],
                false,
                100
            );
        }
    }
}
