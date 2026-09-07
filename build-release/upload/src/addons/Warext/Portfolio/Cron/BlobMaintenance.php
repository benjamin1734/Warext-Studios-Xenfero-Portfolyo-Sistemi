<?php

namespace Warext\Portfolio\Cron;

class BlobMaintenance
{
    public static function run(): void
    {
        $app = \XF::app();
        $legacy = $app->finder('Warext\Portfolio:PortfolioFile')
            ->where('processed_blob_id', 0)
            ->where('processed_storage_name', '<>', '')
            ->where('processed_sha256', '<>', '')
            ->where('state', ['security_passed', 'moderation', 'published'])
            ->order('file_id')
            ->limit(25)
            ->fetch();
        foreach ($legacy as $file)
        {
            try
            {
                $app->service('Warext\Portfolio:BlobManager')->migrateLegacyFile($file);
            }
            catch (\Throwable $e)
            {
                (new \Warext\Portfolio\Service\StateMachine())->logFileEvent($file, 'blob_migration_failed', 'warning', 'blob_migration_failed');
            }
        }
        $app->service('Warext\Portfolio:BlobGarbageCollector')->run(100);
    }
}
