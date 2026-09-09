<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Blob;
use Warext\Portfolio\Entity\PortfolioFile;

class DirectBlobReference extends AbstractService
{
    public function attach(PortfolioFile $file, array $result): void
    {
        $primaryPath = (string)($result['processed_storage_name'] ?? '');
        $primaryHash = strtolower((string)($result['processed_sha256'] ?? ''));
        $primaryMime = (string)($result['processed_mime'] ?? '');
        if ($primaryPath === '' || !preg_match('/^[a-f0-9]{64}$/', $primaryHash))
        {
            throw new \RuntimeException('direct_blob_primary_invalid');
        }

        $isModel = (string)$file->extension === 'glb';
        $primary = $this->acquireReference(
            $primaryPath,
            $primaryHash,
            $primaryMime,
            $isModel ? 'glb' : 'webp',
            (int)($result['processed_size'] ?? 0),
            $isModel ? 'model' : 'image'
        );

        $thumb = null;
        $thumbPath = (string)($result['thumbnail_storage_name'] ?? '');
        if ($thumbPath !== '')
        {
            $thumbHash = $this->hashPath($thumbPath);
            $thumb = $this->acquireReference(
                $thumbPath,
                $thumbHash,
                'image/webp',
                'webp',
                $this->sizePath($thumbPath),
                'thumbnail'
            );
        }

        try
        {
            $file->processed_blob_id = (int)$primary->blob_id;
            $file->processed_storage_name = (string)$primary->storage_name;
            $file->thumbnail_blob_id = $thumb ? (int)$thumb->blob_id : 0;
            $file->thumbnail_storage_name = $thumb ? (string)$thumb->storage_name : '';
            $file->save();
        }
        catch (\Throwable $e)
        {
            $this->service('Warext\Portfolio:BlobManager')->release((int)$primary->blob_id);
            if ($thumb)
            {
                $this->service('Warext\Portfolio:BlobManager')->release((int)$thumb->blob_id);
            }
            throw $e;
        }
    }

    private function acquireReference(string $path, string $sha256, string $mime, string $extension, int $size, string $assetType): Blob
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256) || !hash_equals($sha256, $this->hashPath($path)))
        {
            throw new \RuntimeException('direct_blob_hash_mismatch');
        }

        $lock = 'wrxtp_blob_' . substr($sha256, 0, 48);
        if ((int)$this->db()->fetchOne('SELECT GET_LOCK(?, 10)', $lock) !== 1)
        {
            throw new \RuntimeException('direct_blob_lock_timeout');
        }

        try
        {
            $blob = $this->finder('Warext\Portfolio:Blob')->where('sha256', $sha256)->fetchOne();
            if ($blob)
            {
                if ((string)$blob->security_state !== 'clean')
                {
                    throw new \RuntimeException('direct_blob_security_not_clean');
                }
                $blob->storage_name = $path;
                $blob->mime = $mime;
                $blob->extension = $extension;
                $blob->file_size = max(0, $size);
                $blob->state = 'ready';
                $blob->ref_count = (int)$blob->ref_count + 1;
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
            $blob->storage_name = $path;
            $blob->ref_count = 1;
            $blob->state = 'ready';
            $blob->security_state = 'clean';
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

    private function hashPath(string $path): string
    {
        $stream = \XF::fs()->readStream($path);
        if (!is_resource($stream))
        {
            throw new \RuntimeException('direct_blob_open_failed');
        }
        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $stream);
        fclose($stream);
        return hash_final($ctx);
    }

    private function sizePath(string $path): int
    {
        $stream = \XF::fs()->readStream($path);
        if (!is_resource($stream))
        {
            throw new \RuntimeException('direct_blob_size_open_failed');
        }
        $size = 0;
        while (!feof($stream))
        {
            $chunk = fread($stream, 1048576);
            if ($chunk === false)
            {
                fclose($stream);
                throw new \RuntimeException('direct_blob_size_read_failed');
            }
            $size += strlen($chunk);
        }
        fclose($stream);
        return $size;
    }
}
