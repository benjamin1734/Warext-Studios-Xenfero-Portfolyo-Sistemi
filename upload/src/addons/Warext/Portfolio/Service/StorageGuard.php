<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;

class StorageGuard extends AbstractService
{
    public function assertCapacity(int $incomingBytes): void
    {
        $reserveMb = max(256, (int)$this->app->options()->wrxtPortfolioDiskReserveMb);
        $reserveBytes = $reserveMb * 1024 * 1024;
        $path = \XF::getRootDirectory() . '/internal_data';
        if (!is_dir($path))
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_storage_unavailable'));
        }
        $free = @disk_free_space($path);
        if ($free === false || $free < ($reserveBytes + $incomingBytes))
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_storage_reserve'));
        }
    }
}
