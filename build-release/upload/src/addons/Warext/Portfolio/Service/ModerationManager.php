<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;
use Warext\Portfolio\Entity\ModerationReport;

class ModerationManager extends AbstractService
{
    private const REASONS = ['stolen_work', 'copyright', 'inappropriate', 'suspected_malicious', 'spam', 'wrong_category', 'other'];

    public function createReport(Portfolio $portfolio, string $reasonCode, string $message = '', int $fileId = 0): ModerationReport
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'report') || !$portfolio->canView())
        {
            throw new \RuntimeException('wrxt_portfolio_report_not_allowed');
        }
        if (!in_array($reasonCode, self::REASONS, true))
        {
            throw new \RuntimeException('wrxt_portfolio_report_reason_invalid');
        }
        $message = mb_substr(trim(preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', '', $message) ?? ''), 0, 1500, 'UTF-8');
        $lock = 'wrxtp_report_' . (int)$visitor->user_id . '_' . (int)$portfolio->portfolio_id;
        if ((int)$this->db()->fetchOne('SELECT GET_LOCK(?, 5)', $lock) !== 1)
        {
            throw new \RuntimeException('wrxt_portfolio_report_duplicate');
        }
        try
        {
            $daily = (int)$this->db()->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_moderation_report WHERE reporter_user_id = ? AND created_date >= ?', [$visitor->user_id, \XF::$time - 86400]);
            if ($daily >= 20)
            {
                throw new \RuntimeException('wrxt_portfolio_report_daily_limit');
            }
            $duplicate = (bool)$this->db()->fetchOne("SELECT 1 FROM xf_wrxt_portfolio_moderation_report WHERE reporter_user_id = ? AND portfolio_id = ? AND reason_code = ? AND state IN ('open','reviewing') AND created_date >= ?", [$visitor->user_id, $portfolio->portfolio_id, $reasonCode, \XF::$time - 86400]);
            if ($duplicate)
            {
                throw new \RuntimeException('wrxt_portfolio_report_duplicate');
            }
            if ($fileId)
            {
                $file = $this->em()->find('Warext\\Portfolio:PortfolioFile', $fileId);
                if (!$file || (int)$file->portfolio_id !== (int)$portfolio->portfolio_id) { $fileId = 0; }
            }
            $report = $this->em()->create('Warext\\Portfolio:ModerationReport');
            $report->portfolio_id = (int)$portfolio->portfolio_id;
            $report->file_id = $fileId;
            $report->reporter_user_id = (int)$visitor->user_id;
            $report->reporter_username = (string)$visitor->username;
            $report->reason_code = $reasonCode;
            $report->message = $message;
            $report->state = 'open';
            $report->security_rescan_requested = ($reasonCode === 'suspected_malicious');
            $report->created_date = \XF::$time;
            $report->save();
        }
        finally
        {
            $this->db()->fetchOne('SELECT RELEASE_LOCK(?)', $lock);
        }
        if ($report->security_rescan_requested)
        {
            $this->enqueuePortfolioRescan($portfolio, 'user_security_report');
        }
        $this->service('Warext\\Portfolio:AuditLogger')->log('report_created', 'moderation_report', (int)$report->report_id, (int)$portfolio->portfolio_id, $fileId, $reasonCode);
        return $report;
    }

    public function approve(Portfolio $portfolio, string $note = ''): void
    {
        if (!$this->canModerate()) { throw new \RuntimeException('wrxt_portfolio_moderation_not_allowed'); }
        $db = $this->db();
        $db->beginTransaction();
        try
        {
            $row = $db->fetchRow('SELECT status, security_status, pending_moderation FROM xf_wrxt_portfolio WHERE portfolio_id = ? FOR UPDATE', (int)$portfolio->portfolio_id);
            if (!$row || (string)$row['security_status'] !== 'passed')
            {
                throw new \RuntimeException('wrxt_portfolio_security_not_passed');
            }
            $publishedRevision = (string)$row['status'] === 'published' && (bool)$row['pending_moderation'];
            if ((string)$row['status'] !== 'moderation' && !$publishedRevision)
            {
                throw new \RuntimeException('wrxt_portfolio_not_in_moderation');
            }
            $portfolio->status = (string)$row['status'];
            $portfolio->security_status = (string)$row['security_status'];
            $portfolio->pending_moderation = (bool)$row['pending_moderation'];
            $this->lockAndAssertTechnicalSafety($portfolio);
            $this->service('Warext\\Portfolio:ModerationPolicy')->assertPublishable($portfolio);
            $this->service('Warext\\Portfolio:PendingRevision')->validate($portfolio);

            if ($publishedRevision && $portfolio->pending_revision_json)
            {
                $this->service('Warext\\Portfolio:PendingRevision')->apply($portfolio);
            }

            $pending = $this->finder('Warext\\Portfolio:PortfolioFile')
                ->where('portfolio_id', (int)$portfolio->portfolio_id)
                ->where('published_date', 0)
                ->where('state', ['security_passed', 'moderation'])
                ->order('file_id', 'ASC')
                ->fetch();
            $latest = [];
            foreach ($pending as $file)
            {
                if (in_array((string)$file->file_role, ['cover', 'model'], true))
                {
                    $latest[(string)$file->file_role] = (int)$file->file_id;
                }
            }
            $stateMachine = new StateMachine();
            $publish = [];
            foreach ($pending as $file)
            {
                $role = (string)$file->file_role;
                if (in_array($role, ['cover', 'model'], true) && ($latest[$role] ?? 0) !== (int)$file->file_id)
                {
                    $this->service('Warext\\Portfolio:BlobManager')->detachFile($file);
                    $stateMachine->transitionFile($file, 'deleted', 'superseded_pending_candidate');
                    continue;
                }
                $publish[] = $file;
            }
            if ($publish)
            {
                $this->service('Warext\\Portfolio:PublicationManager')->publishCandidates($publish);
            }

            $portfolio->pending_moderation = false;
            $portfolio->pending_revision_json = null;
            $portfolio->pending_revision_date = 0;
            $portfolio->save();
            if ((string)$portfolio->status === 'moderation')
            {
                $stateMachine->transitionPortfolio($portfolio, 'published');
            }
            else
            {
                $stateMachine->syncApprovalQueue($portfolio);
            }
            $db->commit();
        }
        catch (\Throwable $e)
        {
            $db->rollback();
            throw $e;
        }

        $this->service('Warext\\Portfolio:AuditLogger')->log('portfolio_approved', 'portfolio', (int)$portfolio->portfolio_id, (int)$portfolio->portfolio_id, 0, '', ['note' => mb_substr($note, 0, 500)]);
    }

    public function reject(Portfolio $portfolio, string $reasonCode, string $note = ''): void
    {
        if (!$this->canModerate()) { throw new \RuntimeException('wrxt_portfolio_moderation_not_allowed'); }
        if ((string)$portfolio->status === 'published' && (bool)$portfolio->pending_moderation)
        {
            $pending = $this->finder('Warext\\Portfolio:PortfolioFile')
                ->where('portfolio_id', (int)$portfolio->portfolio_id)
                ->where('published_date', 0)
                ->where('state', ['security_passed', 'moderation'])
                ->fetch();
            foreach ($pending as $file)
            {
                $this->service('Warext\\Portfolio:BlobManager')->cleanupStaging($file);
                $this->service('Warext\\Portfolio:BlobManager')->detachFile($file);
                (new StateMachine())->transitionFile($file, 'deleted', 'moderation_rejected');
            }
            $this->service('Warext\\Portfolio:PendingRevision')->discard($portfolio);
            $portfolio->pending_moderation = false;
            $portfolio->save();
            $this->service('Warext\\Portfolio:PortfolioSecurityState')->refresh($portfolio);
            (new StateMachine())->syncApprovalQueue($portfolio);
        }
        else
        {
            if ((string)$portfolio->status !== 'moderation') { throw new \RuntimeException('wrxt_portfolio_not_in_moderation'); }
            (new StateMachine())->transitionPortfolio($portfolio, 'rejected');
        }
        $this->service('Warext\\Portfolio:AuditLogger')->log('portfolio_rejected', 'portfolio', (int)$portfolio->portfolio_id, (int)$portfolio->portfolio_id, 0, mb_substr($reasonCode,0,100), ['note' => mb_substr($note, 0, 500)]);
    }

    public function resolveReport(ModerationReport $report, string $state, string $note = ''): void
    {
        if (!$this->canModerate()) { throw new \RuntimeException('wrxt_portfolio_moderation_not_allowed'); }
        if (!in_array($state, ['resolved', 'rejected'], true)) { throw new \RuntimeException('wrxt_portfolio_report_state_invalid'); }
        $report->state = $state;
        $report->assigned_user_id = (int)\XF::visitor()->user_id;
        $report->resolution_note = mb_substr(trim($note), 0, 1000, 'UTF-8');
        $report->updated_date = \XF::$time;
        $report->resolved_date = \XF::$time;
        $report->save();
        $this->service('Warext\\Portfolio:AuditLogger')->log('report_' . $state, 'moderation_report', (int)$report->report_id, (int)$report->portfolio_id, (int)$report->file_id, (string)$report->reason_code);
    }

    public function enqueuePortfolioRescan(Portfolio $portfolio, string $reason = 'manual'): int
    {
        $count = 0;
        $files = $this->finder('Warext\\Portfolio:PortfolioFile')->where('portfolio_id', $portfolio->portfolio_id)->where('state', ['security_passed', 'moderation', 'published'])->fetch();
        foreach ($files as $file)
        {
            \XF::app()->jobManager()->enqueueUnique('wrxtPortfolioRescan_' . (int)$file->file_id, 'Warext\\Portfolio:RescanFile', ['file_id' => (int)$file->file_id, 'reason' => $reason], false, 115);
            $count++;
        }
        $this->service('Warext\\Portfolio:AuditLogger')->log('portfolio_rescan_enqueued', 'portfolio', (int)$portfolio->portfolio_id, (int)$portfolio->portfolio_id, 0, $reason, ['file_count' => $count]);
        return $count;
    }

    private function lockAndAssertTechnicalSafety(Portfolio $portfolio): void
    {
        $rows = $this->db()->fetchAll("SELECT file_id, state, processing_status, processed_mime, processed_blob_id, thumbnail_blob_id FROM xf_wrxt_portfolio_file WHERE portfolio_id = ? AND state <> 'deleted' FOR UPDATE", (int)$portfolio->portfolio_id);
        if (!$rows)
        {
            throw new \RuntimeException('wrxt_portfolio_no_safe_files');
        }
        foreach ($rows as $row)
        {
            if (!in_array((string)$row['state'], ['security_passed', 'moderation', 'published'], true) || (string)$row['processing_status'] !== 'passed' || !(int)$row['processed_blob_id'])
            {
                throw new \RuntimeException('wrxt_portfolio_file_not_security_passed');
            }
            foreach ([(int)$row['processed_blob_id'], (int)$row['thumbnail_blob_id']] as $blobId)
            {
                if (!$blobId) { continue; }
                $blob = $this->db()->fetchRow('SELECT state, security_state FROM xf_wrxt_portfolio_blob WHERE blob_id = ? FOR UPDATE', $blobId);
                if (!$blob || (string)$blob['state'] !== 'ready' || (string)$blob['security_state'] !== 'clean')
                {
                    throw new \RuntimeException('wrxt_portfolio_blob_security_blocked');
                }
            }
        }
    }

    private function canModerate(): bool
    {
        $visitor = \XF::visitor();
        return (bool)($visitor->user_id && ($visitor->hasPermission('wrxtPortfolio', 'manage') || $visitor->hasPermission('wrxtPortfolio', 'moderate') || ($visitor->is_admin && $visitor->hasAdminPermission('wrxtPortfolioModerate'))));
    }
}
