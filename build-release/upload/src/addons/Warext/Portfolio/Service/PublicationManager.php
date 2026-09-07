<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\PortfolioFile;

class PublicationManager extends AbstractService
{
    public function activate(PortfolioFile $file): void
    {
        $this->publishCandidates([$file]);
    }

    public function publishCandidates(iterable $files): void
    {
        $candidates = [];
        foreach ($files as $file)
        {
            if ($file instanceof PortfolioFile) { $candidates[(int)$file->file_id] = $file; }
        }
        if (!$candidates) { return; }

        $first = reset($candidates);
        $portfolio = $first->Portfolio;
        if (!$portfolio) { throw new \RuntimeException('wrxt_portfolio_publish_file_missing_portfolio'); }
        foreach ($candidates as $file)
        {
            if ((int)$file->portfolio_id !== (int)$portfolio->portfolio_id) { throw new \RuntimeException('wrxt_portfolio_publish_mixed_portfolio'); }
        }

        $oldPointers = [];
        $db = $this->db();
        $db->beginTransaction();
        try
        {
            $db->fetchOne('SELECT portfolio_id FROM xf_wrxt_portfolio WHERE portfolio_id = ? FOR UPDATE', (int)$portfolio->portfolio_id);
            foreach ($candidates as $file)
            {
                $row = $db->fetchRow('SELECT file_id, state, processing_status, processed_mime, processed_blob_id, thumbnail_blob_id FROM xf_wrxt_portfolio_file WHERE file_id = ? FOR UPDATE', (int)$file->file_id);
                if (!$row || !in_array((string)$row['state'], ['security_passed', 'moderation'], true) || (string)$row['processing_status'] !== 'passed')
                {
                    throw new \RuntimeException('wrxt_portfolio_publish_file_not_ready');
                }
                $expectedMime = (string)$file->file_role === 'model' ? 'model/gltf-binary' : 'image/webp';
                if ((string)$row['processed_mime'] !== $expectedMime || !(int)$row['processed_blob_id'])
                {
                    throw new \RuntimeException('wrxt_portfolio_publish_file_mime_invalid');
                }
                foreach ([(int)$row['processed_blob_id'], (int)$row['thumbnail_blob_id']] as $blobId)
                {
                    if (!$blobId) { continue; }
                    $blob = $db->fetchRow('SELECT blob_id, state, security_state FROM xf_wrxt_portfolio_blob WHERE blob_id = ? FOR UPDATE', $blobId);
                    if (!$blob || (string)$blob['state'] !== 'ready' || (string)$blob['security_state'] !== 'clean')
                    {
                        throw new \RuntimeException('wrxt_portfolio_publish_blob_blocked');
                    }
                }
            }

            foreach ($candidates as $file)
            {
                $role = (string)$file->file_role;
                $file->state = 'published';
                $file->published_date = \XF::$time;
                $file->reason_code = '';
                $file->save();
                if ($role === 'cover')
                {
                    $oldPointers['cover'] = (int)$portfolio->cover_file_id;
                    $portfolio->cover_file_id = (int)$file->file_id;
                }
                elseif ($role === 'model')
                {
                    $oldPointers['model'] = (int)$portfolio->model_file_id;
                    $portfolio->model_file_id = (int)$file->file_id;
                }
                elseif ($role !== 'gallery')
                {
                    throw new \RuntimeException('wrxt_portfolio_publish_role_invalid');
                }
            }
            $portfolio->gallery_count = (int)$this->finder('Warext\Portfolio:PortfolioFile')
                ->where('portfolio_id', (int)$portfolio->portfolio_id)
                ->where('file_role', 'gallery')
                ->where('state', 'published')
                ->total();
            $portfolio->updated_date = \XF::$time;
            $portfolio->save();
            $db->commit();
        }
        catch (\Throwable $e)
        {
            $db->rollback();
            throw $e;
        }

        $stateMachine = new StateMachine();
        foreach ($candidates as $file)
        {
            $stateMachine->logFileEvent($file, 'state_transition', 'info', '', ['to' => 'published', 'approved' => true]);
        }
        foreach ($oldPointers as $oldFileId)
        {
            if (!$oldFileId || isset($candidates[$oldFileId])) { continue; }
            $old = $this->em()->find('Warext\Portfolio:PortfolioFile', $oldFileId);
            if ($old && (int)$old->portfolio_id === (int)$portfolio->portfolio_id && (string)$old->state !== 'deleted')
            {
                try
                {
                    $this->service('Warext\Portfolio:BlobManager')->detachFile($old);
                    $stateMachine->transitionFile($old, 'deleted', 'replaced_after_moderation');
                }
                catch (\Throwable $e)
                {
                    $stateMachine->logFileEvent($old, 'old_file_release_failed', 'warning', 'old_file_release_failed');
                }
            }
        }
    }
}
