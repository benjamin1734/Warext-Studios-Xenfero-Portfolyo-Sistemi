<?php

namespace Warext\Portfolio\Job;

use XF\Job\AbstractJob;

class ProcessFile extends AbstractJob
{
    protected $defaultData = ['file_id' => 0];

    public function run($maxRunTime)
    {
        $fileId = (int)($this->data['file_id'] ?? 0);
        if (!$fileId)
        {
            return $this->complete();
        }
        $file = $this->app->em()->find('Warext\Portfolio:PortfolioFile', $fileId);
        if (!$file || $file->state !== 'processing')
        {
            return $this->complete();
        }

        try
        {
            $this->app->service('Warext\Portfolio:ProcessingPipeline')->process($file);
        }
        catch (\Throwable $e)
        {
            $file->processing_status = 'error';
            $file->reason_code = 'processing_pipeline_error';
            $file->next_processing_date = \XF::$time + 300;
            $file->save();
            (new \Warext\Portfolio\Service\StateMachine())->logFileEvent($file, 'processing_pipeline_error', 'critical', 'processing_pipeline_error', [
                'exception' => get_class($e)
            ]);
        }
        return $this->complete();
    }

    public function getStatusMessage()
    {
        return 'Portfolyo dosyası izole işlem sürecinden geçiriliyor';
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
