<?php

namespace Warext\Portfolio\Job;

use XF\Job\AbstractJob;

class SecurityScan extends AbstractJob
{
    protected $defaultData = [
        'file_id' => 0
    ];

    public function run($maxRunTime)
    {
        $fileId = (int)($this->data['file_id'] ?? 0);
        if (!$fileId)
        {
            return $this->complete();
        }

        $file = $this->app->em()->find('Warext\Portfolio:PortfolioFile', $fileId);
        if (!$file)
        {
            return $this->complete();
        }

        try
        {
            $this->app->service('Warext\Portfolio:SecurityPipeline')->process($file);
        }
        catch (\Throwable $e)
        {
            $file->reason_code = 'security_pipeline_error';
            if ($file->state === 'scanning')
            {
                $file->scan_status = 'error';
            }
            $file->next_scan_date = \XF::$time + 300;
            $file->save();
            (new \Warext\Portfolio\Service\StateMachine())->logFileEvent($file, 'security_pipeline_error', 'critical', 'security_pipeline_error', [
                'exception' => get_class($e)
            ]);
        }
        return $this->complete();
    }

    public function getStatusMessage()
    {
        return 'Portfolyo dosyası güvenlik kontrolünden geçiriliyor';
    }

    public function canCancel()
    {
        return false;
    }

    public function canTriggerByChoice()
    {
        return false;
    }
}
