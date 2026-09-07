<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\PortfolioFile;

class PublishedRescan extends AbstractService
{
    public function scan(PortfolioFile $file, string $reason = 'periodic'): string
    {
        if (!in_array((string)$file->state, ['security_passed', 'moderation', 'published'], true))
        {
            return 'skipped';
        }

        $blobs = [];
        if ($file->ProcessedBlob) { $blobs[(int)$file->ProcessedBlob->blob_id] = $file->ProcessedBlob; }
        if ($file->ThumbnailBlob) { $blobs[(int)$file->ThumbnailBlob->blob_id] = $file->ThumbnailBlob; }
        if (!$blobs)
        {
            return 'missing_blob';
        }

        $finalStatus = 'clean';
        foreach ($blobs as $blob)
        {
            $status = $this->scanBlob($file, $blob, $reason);
            if ($status === 'blocked')
            {
                return 'blocked';
            }
            if ($status === 'pending')
            {
                $finalStatus = 'pending';
            }
        }

        if ($finalStatus === 'clean')
        {
            $file->last_scan_date = \XF::$time;
            $file->scan_status = 'clean';
            $file->save();
            (new StateMachine())->logFileEvent($file, 'published_rescan_clean', 'info', '', ['reason' => $reason]);
        }
        return $finalStatus;
    }

    private function scanBlob(PortfolioFile $file, $blob, string $reason): string
    {
        if (!$blob->storage_name || (string)$blob->state !== 'ready')
        {
            return 'missing_blob';
        }

        $tmp = $this->service('Warext\\Portfolio:LocalFileMaterializer')->materialize((string)$blob->storage_name);
        try
        {
            $hash = hash_file('sha256', $tmp);
            if (!is_string($hash) || !hash_equals((string)$blob->sha256, $hash))
            {
                return $this->blockSharedBlob($file, $blob, 'published_blob_hash_mismatch', $reason);
            }
            if ($this->service('Warext\\Portfolio:HashBlocklist')->isBlocked($hash))
            {
                return $this->blockSharedBlob($file, $blob, 'blocked_hash_rescan', $reason);
            }
            try
            {
                $scan = $this->service('Warext\\Portfolio:ClamAvScanner')->scan($tmp);
            }
            catch (\Warext\Portfolio\Exception\ScanUnavailableException $e)
            {
                $blob->security_state = 'pending';
                $blob->next_security_scan_date = \XF::$time + 3600;
                $blob->save();
                (new StateMachine())->logFileEvent($file, 'published_rescan_unavailable', 'warning', 'scan_unavailable', [
                    'reason' => $reason,
                    'blob_id' => (int)$blob->blob_id
                ]);
                return 'pending';
            }
            if (($scan['status'] ?? '') === 'infected')
            {
                $this->service('Warext\\Portfolio:HashBlocklist')->add($hash, 'malware_detected_rescan', (string)($scan['signature'] ?? ''), (int)\XF::visitor()->user_id);
                return $this->blockSharedBlob($file, $blob, 'malware_detected_rescan', $reason, ['signature' => (string)($scan['signature'] ?? '')]);
            }

            $blob->security_state = 'clean';
            $blob->blocked_reason = '';
            $blob->last_security_scan_date = \XF::$time;
            $blob->next_security_scan_date = 0;
            $blob->save();
            return 'clean';
        }
        finally
        {
            @unlink($tmp);
        }
    }

    private function blockSharedBlob(PortfolioFile $origin, $blob, string $reasonCode, string $reason, array $details = []): string
    {
        $blob->security_state = 'blocked';
        $blob->blocked_reason = $reasonCode;
        $blob->last_security_scan_date = \XF::$time;
        $blob->next_security_scan_date = 0;
        $blob->save();

        $ids = array_map('intval', $this->db()->fetchAllColumn(
            "SELECT file_id FROM xf_wrxt_portfolio_file WHERE (processed_blob_id = ? OR thumbnail_blob_id = ?) AND state IN ('security_passed','moderation','published')",
            [(int)$blob->blob_id, (int)$blob->blob_id]
        ));
        $files = $ids ? $this->finder('Warext\\Portfolio:PortfolioFile')->where('file_id', $ids)->fetch() : [];
        foreach ($files as $file)
        {
            (new StateMachine())->blockFile($file, $reasonCode, $details + ['rescan_reason' => $reason, 'blob_id' => (int)$blob->blob_id]);
            if ($file->Portfolio)
            {
                $this->service('Warext\\Portfolio:PortfolioSecurityState')->refresh($file->Portfolio);
            }
        }
        $this->service('Warext\\Portfolio:AuditLogger')->log(
            'shared_blob_security_block',
            'blob',
            (int)$blob->blob_id,
            (int)$origin->portfolio_id,
            (int)$origin->file_id,
            $reasonCode,
            $details + ['affected_files' => is_countable($files) ? count($files) : 0]
        );
        return 'blocked';
    }
}
