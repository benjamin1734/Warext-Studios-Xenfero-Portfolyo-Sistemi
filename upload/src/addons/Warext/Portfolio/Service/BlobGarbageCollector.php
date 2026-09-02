<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;

class BlobGarbageCollector extends AbstractService
{
    public function run(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $repaired = $this->reconcile(max(25, min(200, $limit * 2)));
        $rows = $this->db()->fetchAll(
            'SELECT blob_id, storage_name FROM xf_wrxt_portfolio_blob WHERE ref_count = 0 AND delete_after_date > 0 AND delete_after_date <= ? ORDER BY blob_id LIMIT ' . $limit,
            \XF::$time
        );
        $deleted = 0;
        foreach ($rows as $row)
        {
            $blobId = (int)$row['blob_id'];
            $actual = $this->actualReferences($blobId);
            if ($actual > 0)
            {
                $this->db()->query(
                    'UPDATE xf_wrxt_portfolio_blob SET ref_count = ?, delete_after_date = 0, last_ref_date = ?, last_verify_date = ? WHERE blob_id = ?',
                    [$actual, \XF::$time, \XF::$time, $blobId]
                );
                $repaired++;
                continue;
            }
            $this->service('Warext\Portfolio:LocalBlobStorage')->delete((string)$row['storage_name']);
            $this->db()->delete('xf_wrxt_portfolio_blob', 'blob_id = ?', $blobId);
            $deleted++;
        }
        return ['deleted' => $deleted, 'repaired' => $repaired];
    }

    protected function reconcile(int $limit): int
    {
        $rows = $this->db()->fetchAll(
            'SELECT blob_id, ref_count FROM xf_wrxt_portfolio_blob ORDER BY last_verify_date ASC, blob_id ASC LIMIT ' . max(1, $limit)
        );
        $repaired = 0;
        foreach ($rows as $row)
        {
            $blobId = (int)$row['blob_id'];
            $current = (int)$row['ref_count'];
            $actual = $this->actualReferences($blobId);
            $deleteAfter = 0;
            if ($actual === 0)
            {
                $graceHours = max(1, min(720, (int)($this->app->options()->wrxtPortfolioBlobGcGraceHours ?? 24)));
                $deleteAfter = \XF::$time + ($graceHours * 3600);
            }
            $this->db()->query(
                'UPDATE xf_wrxt_portfolio_blob SET ref_count = ?, delete_after_date = ?, last_verify_date = ? WHERE blob_id = ?',
                [$actual, $deleteAfter, \XF::$time, $blobId]
            );
            if ($actual !== $current)
            {
                $repaired++;
            }
        }
        return $repaired;
    }

    protected function actualReferences(int $blobId): int
    {
        return (int)$this->db()->fetchOne(
            'SELECT COUNT(*) FROM xf_wrxt_portfolio_file WHERE processed_blob_id = ? OR thumbnail_blob_id = ?',
            [$blobId, $blobId]
        );
    }
}
