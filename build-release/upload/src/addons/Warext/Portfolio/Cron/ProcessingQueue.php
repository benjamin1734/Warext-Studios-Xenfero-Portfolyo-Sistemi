<?php

namespace Warext\Portfolio\Cron;

class ProcessingQueue
{
    public static function run(): void
    {
        $ids = \XF::db()->fetchAllColumn(
            "SELECT file_id FROM xf_wrxt_portfolio_file WHERE state = 'processing' AND (next_processing_date = 0 OR next_processing_date <= ?) ORDER BY file_id LIMIT 100",
            \XF::$time
        );
        $manager = \XF::app()->jobManager();
        foreach ($ids as $fileId)
        {
            $fileId = (int)$fileId;
            $manager->enqueueUnique(
                'wrxtPortfolioProcess_' . $fileId,
                'Warext\Portfolio:ProcessFile',
                ['file_id' => $fileId],
                false,
                110
            );
        }
    }
}
