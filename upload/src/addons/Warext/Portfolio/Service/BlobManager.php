<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use XF\Util\File;
use Warext\Portfolio\Entity\Blob;
use Warext\Portfolio\Entity\PortfolioFile;

class BlobManager extends AbstractService
{
    public function acquire(string $sourcePath, string $sha256, string $mime, string $extension, int $size, string $assetType): Blob
    {
        $sha256 = strtolower($sha256);
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256))
        {
            throw new \RuntimeException('blob_hash_invalid');
        }
        $lock = 'wrxtp_blob_' . substr($sha256, 0, 48);
        if ((int)$this->db()->fetchOne('SELECT GET_LOCK(?, 10)', $lock) !== 1)
        {
            throw new \RuntimeException('blob_lock_timeout');
        }

        try
        {
            $storage = $this->storage();
            $storageName = $storage->ensureFromAbstractedPath($sourcePath, $sha256, $extension);
            $blob = $this->finder('Warext\Portfolio:Blob')->where('sha256', $sha256)->fetchOne();
            if ($blob)
            {
                if ((string)$blob->security_state !== 'clean')
                {
                    throw new \RuntimeException('blob_security_not_clean');
                }
                if ((string)$blob->storage_name !== $storageName)
                {
                    $blob->storage_name = $storageName;
                }
                $blob->ref_count = (int)$blob->ref_count + 1;
                $blob->state = 'ready';
                $blob->delete_after_date = 0;
                $blob->last_ref_date = \XF::$time;
                $blob->save();
                return $blob;
            }

            $blob = $this->em()->create('Warext\Portfolio:Blob');
            $blob->sha256 = $sha256;
            $blob->asset_type = $assetType;
            $blob->mime = $mime;
            $blob->extension = $extension;
            $blob->file_size = max(0, $size);
            $blob->storage_name = $storageName;
            $blob->ref_count = 1;
            $blob->state = 'ready';
            $blob->created_date = \XF::$time;
            $blob->last_ref_date = \XF::$time;
            $blob->save();
            return $blob;
        }
        finally
        {
            $this->db()->fetchOne('SELECT RELEASE_LOCK(?)', $lock);
        }
    }

    public function attachProcessedResult(PortfolioFile $file, array $result): void
    {
        $primarySource = (string)($result['processed_storage_name'] ?? '');
        $primaryHash = strtolower((string)($result['processed_sha256'] ?? ''));
        $primaryMime = (string)($result['processed_mime'] ?? 'application/octet-stream');
        if ($primarySource === '' || $primaryHash === '')
        {
            throw new \RuntimeException('blob_processed_result_invalid');
        }

        $isModel = (string)$file->extension === 'glb';
        $primaryExt = $isModel ? 'glb' : 'webp';
        $primaryType = $isModel ? 'model' : 'image';
        $oldPrimary = (int)$file->processed_blob_id;
        $oldThumb = (int)$file->thumbnail_blob_id;
        $primary = null;
        $thumb = null;

        try
        {
            if ($oldPrimary)
            {
                $existing = $this->em()->find('Warext\Portfolio:Blob', $oldPrimary);
                if ($existing && hash_equals((string)$existing->sha256, $primaryHash))
                {
                    $primary = $existing;
                }
            }
            if (!$primary)
            {
                $primary = $this->acquire($primarySource, $primaryHash, $primaryMime, $primaryExt, (int)($result['processed_size'] ?? 0), $primaryType);
            }

            $thumbSource = (string)($result['thumbnail_storage_name'] ?? '');
            if ($thumbSource !== '')
            {
                $thumbHash = $this->hashAbstractedPath($thumbSource);
                if ($oldThumb)
                {
                    $existingThumb = $this->em()->find('Warext\Portfolio:Blob', $oldThumb);
                    if ($existingThumb && hash_equals((string)$existingThumb->sha256, $thumbHash))
                    {
                        $thumb = $existingThumb;
                    }
                }
                if (!$thumb)
                {
                    $thumbSize = $this->abstractedSize($thumbSource);
                    $thumb = $this->acquire($thumbSource, $thumbHash, 'image/webp', 'webp', $thumbSize, 'thumbnail');
                }
            }

            $file->processed_blob_id = (int)$primary->blob_id;
            $file->processed_storage_name = (string)$primary->storage_name;
            $file->thumbnail_blob_id = $thumb ? (int)$thumb->blob_id : 0;
            $file->thumbnail_storage_name = $thumb ? (string)$thumb->storage_name : '';
            $file->save();
        }
        catch (\Throwable $e)
        {
            if ($primary && (int)$primary->blob_id !== $oldPrimary)
            {
                $this->release((int)$primary->blob_id);
            }
            if ($thumb && (int)$thumb->blob_id !== $oldThumb)
            {
                $this->release((int)$thumb->blob_id);
            }
            throw $e;
        }

        if ($oldPrimary && $oldPrimary !== (int)$file->processed_blob_id)
        {
            $this->release($oldPrimary);
        }
        if ($oldThumb && $oldThumb !== (int)$file->thumbnail_blob_id)
        {
            $this->release($oldThumb);
        }
        foreach ([$primarySource, (string)($result['thumbnail_storage_name'] ?? '')] as $staging)
        {
            if ($staging !== '' && $staging !== (string)$file->processed_storage_name && $staging !== (string)$file->thumbnail_storage_name)
            {
                File::deleteFromAbstractedPath($staging);
            }
        }
    }

    public function detachFile(PortfolioFile $file): void
    {
        $primary = (int)$file->processed_blob_id;
        $thumb = (int)$file->thumbnail_blob_id;
        $legacyPrimary = (string)$file->processed_storage_name;
        $legacyThumb = (string)$file->thumbnail_storage_name;
        $file->processed_blob_id = 0;
        $file->thumbnail_blob_id = 0;
        $file->processed_storage_name = '';
        $file->thumbnail_storage_name = '';
        $file->save();

        if ($primary)
        {
            $this->release($primary);
        }
        elseif ($legacyPrimary !== '')
        {
            File::deleteFromAbstractedPath($legacyPrimary);
        }
        if ($thumb)
        {
            $this->release($thumb);
        }
        elseif ($legacyThumb !== '')
        {
            File::deleteFromAbstractedPath($legacyThumb);
        }
    }

    public function release(int $blobId): void
    {
        if ($blobId <= 0)
        {
            return;
        }
        $db = $this->db();
        $db->beginTransaction();
        try
        {
            $row = $db->fetchRow('SELECT blob_id, ref_count FROM xf_wrxt_portfolio_blob WHERE blob_id = ? FOR UPDATE', $blobId);
            if ($row)
            {
                $next = max(0, (int)$row['ref_count'] - 1);
                $deleteAfter = 0;
                if ($next === 0)
                {
                    $graceHours = max(1, min(720, (int)($this->app->options()->wrxtPortfolioBlobGcGraceHours ?? 24)));
                    $deleteAfter = \XF::$time + ($graceHours * 3600);
                }
                $db->query(
                    'UPDATE xf_wrxt_portfolio_blob SET ref_count = ?, last_ref_date = ?, delete_after_date = ? WHERE blob_id = ?',
                    [$next, \XF::$time, $deleteAfter, $blobId]
                );
            }
            $db->commit();
        }
        catch (\Throwable $e)
        {
            $db->rollback();
            throw $e;
        }
    }

    public function migrateLegacyFile(PortfolioFile $file): bool
    {
        if ((int)$file->processed_blob_id || !$file->processed_storage_name || !$file->processed_sha256)
        {
            return false;
        }
        $result = [
            'processed_storage_name' => (string)$file->processed_storage_name,
            'processed_sha256' => (string)$file->processed_sha256,
            'processed_mime' => (string)$file->processed_mime,
            'processed_size' => (int)$file->processed_size,
            'thumbnail_storage_name' => (string)$file->thumbnail_storage_name
        ];
        $this->attachProcessedResult($file, $result);
        return true;
    }

    public function cleanupStaging(PortfolioFile $file): void
    {
        $key = (string)$file->file_key;
        if ($key === '')
        {
            return;
        }
        $prefix = substr($key, 0, 2);
        foreach ([
            'internal-data://wrxt_portfolio/staging/image/' . $prefix . '/' . $key . '/display.webp',
            'internal-data://wrxt_portfolio/staging/image/' . $prefix . '/' . $key . '/thumb.webp',
            'internal-data://wrxt_portfolio/staging/model/' . $prefix . '/' . $key . '.glb'
        ] as $path)
        {
            try
            {
                File::deleteFromAbstractedPath($path);
            }
            catch (\Throwable $e) {}
        }
    }

    public function primaryStorageName(PortfolioFile $file): string
    {
        if ($file->ProcessedBlob)
        {
            return (string)$file->ProcessedBlob->storage_name;
        }
        return (string)$file->processed_storage_name;
    }

    protected function storage(): \Warext\Portfolio\Storage\BlobStorageInterface
    {
        return $this->service('Warext\Portfolio:LocalBlobStorage');
    }

    protected function hashAbstractedPath(string $path): string
    {
        $stream = \XF::fs()->readStream($path);
        if (!is_resource($stream))
        {
            throw new \RuntimeException('blob_hash_open_failed');
        }
        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $stream);
        fclose($stream);
        return hash_final($ctx);
    }

    protected function abstractedSize(string $path): int
    {
        $stream = \XF::fs()->readStream($path);
        if (!is_resource($stream))
        {
            throw new \RuntimeException('blob_size_open_failed');
        }
        $size = 0;
        while (!feof($stream))
        {
            $chunk = fread($stream, 1048576);
            if ($chunk === false)
            {
                fclose($stream);
                throw new \RuntimeException('blob_size_read_failed');
            }
            $size += strlen($chunk);
        }
        fclose($stream);
        return $size;
    }
}
