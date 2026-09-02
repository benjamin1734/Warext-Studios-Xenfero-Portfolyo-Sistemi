<?php

namespace Warext\Portfolio\Job;

use XF\Job\AbstractJob;

class RescanFile extends AbstractJob
{
    protected $defaultData = ['file_id' => 0, 'reason' => 'manual'];
    public function run($maxRunTime)
    {
        $file = $this->app->em()->find('Warext\\Portfolio:PortfolioFile', (int)$this->data['file_id'], ['ProcessedBlob', 'Portfolio']);
        if ($file) { $this->app->service('Warext\\Portfolio:PublishedRescan')->scan($file, (string)$this->data['reason']); }
        return $this->complete();
    }
    public function getStatusMessage() { return 'Portfolyo dosyası yeniden güvenlik taramasından geçiriliyor'; }
    public function canCancel() { return true; }
    public function canTriggerByChoice() { return false; }
}
