<?php

namespace Warext\Portfolio\Service;

use XF\Mvc\Entity\Entity;

class StateMachine
{
    private const FILE_TRANSITIONS = [
        'uploading' => ['quarantine', 'blocked', 'deleted'],
        'quarantine' => ['validating', 'blocked', 'deleted'],
        'validating' => ['scanning', 'blocked', 'deleted'],
        'scanning' => ['processing', 'blocked', 'deleted'],
        'processing' => ['security_passed', 'blocked', 'deleted'],
        'security_passed' => ['moderation', 'published', 'blocked', 'deleted'],
        'moderation' => ['published', 'rejected', 'blocked', 'deleted'],
        'published' => ['quarantine', 'blocked', 'deleted'],
        'blocked' => ['deleted'],
        'rejected' => ['deleted'],
        'deleted' => []
    ];

    private const PORTFOLIO_TRANSITIONS = [
        'draft' => ['awaiting_files', 'security_review', 'deleted'],
        'awaiting_files' => ['security_review', 'draft', 'deleted'],
        'security_review' => ['moderation', 'rejected', 'deleted'],
        'moderation' => ['published', 'rejected', 'deleted'],
        'published' => ['security_review', 'deleted'],
        'rejected' => ['draft', 'deleted'],
        'deleted' => []
    ];

    public function transitionFile(Entity $file, string $newState, string $reasonCode = ''): void
    {
        $current = (string)$file->state;
        if (!in_array($newState, self::FILE_TRANSITIONS[$current] ?? [], true))
        {
            throw new \LogicException("Invalid portfolio file state transition: {$current} -> {$newState}");
        }

        $file->state = $newState;
        $file->reason_code = $reasonCode;
        if (in_array($newState, ['security_passed', 'blocked'], true))
        {
            $file->checked_date = \XF::$time;
        }
        if ($newState === 'published')
        {
            $file->published_date = \XF::$time;
        }
        $file->save();

        $this->logFileEvent($file, 'state_transition', $newState === 'blocked' ? 'critical' : 'info', $reasonCode, [
            'from' => $current,
            'to' => $newState
        ]);
    }

    public function transitionPortfolio(Entity $portfolio, string $newState): void
    {
        $current = (string)$portfolio->status;
        if ($newState === 'published')
        {
            \XF::service('Warext\Portfolio:ModerationPolicy')->assertPublishable($portfolio);
        }
        if (!in_array($newState, self::PORTFOLIO_TRANSITIONS[$current] ?? [], true))
        {
            throw new \LogicException("Invalid portfolio state transition: {$current} -> {$newState}");
        }

        $portfolio->status = $newState;
        $portfolio->updated_date = \XF::$time;
        $firstPublish = $newState === 'published' && !$portfolio->published_date;
        if ($firstPublish)
        {
            $portfolio->published_date = \XF::$time;
        }
        if ($newState === 'deleted')
        {
            $portfolio->deleted_date = \XF::$time;
        }
        $portfolio->save();
        $this->syncApprovalQueue($portfolio);
        if ($firstPublish)
        {
            \XF::app()->jobManager()->enqueueUnique('wrxtPortfolioFollowers_' . (int)$portfolio->portfolio_id, 'Warext\Portfolio:NotifyFollowers', ['portfolio_id' => (int)$portfolio->portfolio_id, 'position' => 0], false, 100);
        }
    }

    public function syncApprovalQueue(Entity $portfolio): void
    {
        try
        {
            if (((string)$portfolio->status === 'moderation' || ((string)$portfolio->status === 'published' && (bool)$portfolio->pending_moderation)) && (string)$portfolio->security_status === 'passed')
            {
                $queue = $portfolio->getRelationOrDefault('ApprovalQueue', false);
                $queue->content_date = (int)($portfolio->updated_date ?: $portfolio->created_date);
                $queue->save();
            }
            elseif ($portfolio->ApprovalQueue)
            {
                $portfolio->ApprovalQueue->delete();
            }
        }
        catch (\Throwable $e)
        {
            \XF::logException($e, false, 'Warext Portfolio approval queue: ');
        }
    }

    public function blockFile(Entity $file, string $reasonCode, array $details = []): void
    {
        if ($file->state !== 'blocked')
        {
            $this->transitionFile($file, 'blocked', $reasonCode);
        }

        $this->logFileEvent($file, 'security_block', 'critical', $reasonCode, $details);
    }

    public function logFileEvent(Entity $file, string $event, string $severity = 'info', string $reasonCode = '', array $details = []): void
    {
        $log = \XF::em()->create('Warext\Portfolio:SecurityLog');
        $log->portfolio_id = (int)$file->portfolio_id;
        $log->file_id = (int)$file->file_id;
        $log->user_id = (int)$file->user_id;
        $log->event = $event;
        $log->severity = $severity;
        $log->reason_code = $reasonCode;
        $log->details_json = $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $log->created_date = \XF::$time;
        $log->save();
    }
}
