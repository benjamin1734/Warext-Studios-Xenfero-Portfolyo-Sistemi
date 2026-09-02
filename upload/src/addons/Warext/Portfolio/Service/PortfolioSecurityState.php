<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;

class PortfolioSecurityState extends AbstractService
{
    public function refresh(Portfolio $portfolio): string
    {
        $files = $this->finder('Warext\Portfolio:PortfolioFile')
            ->where('portfolio_id', $portfolio->portfolio_id)
            ->where('state', '<>', 'deleted')
            ->fetch();

        if ((string)$portfolio->status === 'published')
        {
            foreach ($files as $file)
            {
                if ((string)$file->state === 'blocked' && !(int)$file->published_date)
                {
                    try
                    {
                        $this->service('Warext\Portfolio:BlobManager')->cleanupStaging($file);
                        $this->service('Warext\Portfolio:BlobManager')->detachFile($file);
                        if ($file->storage_name)
                        {
                            \XF\Util\File::deleteFromAbstractedPath((string)$file->storage_name);
                            $file->storage_name = '';
                            $file->save();
                        }
                        (new StateMachine())->transitionFile($file, 'deleted', 'blocked_pending_candidate');
                    }
                    catch (\Throwable $e)
                    {
                        (new StateMachine())->logFileEvent($file, 'pending_candidate_cleanup_failed', 'warning', 'pending_candidate_cleanup_failed');
                    }
                }
            }
            $files = $this->finder('Warext\Portfolio:PortfolioFile')
                ->where('portfolio_id', $portfolio->portfolio_id)
                ->where('state', '<>', 'deleted')
                ->fetch();
        }

        if (!$files->count())
        {
            $status = 'none';
        }
        else
        {
            $status = 'passed';
            foreach ($files as $file)
            {
                if ((string)$file->state === 'blocked')
                {
                    $status = 'blocked';
                    break;
                }
                if (!in_array((string)$file->state, ['security_passed', 'moderation', 'published'], true))
                {
                    $status = 'pending';
                }
            }
        }

        if ((string)$portfolio->security_status !== $status)
        {
            $portfolio->security_status = $status;
            $portfolio->updated_date = \XF::$time;
            $portfolio->save();
        }

        $stateMachine = new StateMachine();
        if ($status === 'blocked' && (string)$portfolio->status === 'published')
        {
            $stateMachine->transitionPortfolio($portfolio, 'security_review');
        }
        elseif ($status === 'passed' && (string)$portfolio->status === 'published')
        {
            $hasPendingFile = false;
            foreach ($files as $file)
            {
                if ((string)$file->state === 'security_passed' && !(int)$file->published_date)
                {
                    $stateMachine->transitionFile($file, 'moderation');
                    $hasPendingFile = true;
                }
                elseif ((string)$file->state === 'moderation' && !(int)$file->published_date)
                {
                    $hasPendingFile = true;
                }
            }
            if ($hasPendingFile)
            {
                $portfolio->pending_moderation = true;
                $portfolio->updated_date = \XF::$time;
                $portfolio->save();
            }
            elseif (!$portfolio->pending_revision_json && (bool)$portfolio->pending_moderation)
            {
                $portfolio->pending_moderation = false;
                $portfolio->updated_date = \XF::$time;
                $portfolio->save();
            }
            $stateMachine->syncApprovalQueue($portfolio);
        }
        elseif ($status === 'passed' && in_array((string)$portfolio->status, ['awaiting_files', 'security_review'], true))
        {
            if ((string)$portfolio->status === 'awaiting_files')
            {
                $stateMachine->transitionPortfolio($portfolio, 'security_review');
            }
            foreach ($files as $file)
            {
                if ((string)$file->state === 'security_passed')
                {
                    $stateMachine->transitionFile($file, 'moderation');
                }
            }
            $stateMachine->transitionPortfolio($portfolio, 'moderation');
        }
        else
        {
            $stateMachine->syncApprovalQueue($portfolio);
        }
        return $status;
    }
}
