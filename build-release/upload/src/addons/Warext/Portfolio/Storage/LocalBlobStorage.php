<?php

namespace Warext\Portfolio\Storage;

use XF\Service\AbstractService;
use XF\Util\File;

class LocalBlobStorage extends AbstractService implements BlobStorageInterface
{
    public function ensureFromAbstractedPath(string $sourcePath, string $sha256, string $extension): string
    {
        $sha256 = strtolower($sha256);
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256))
        {
            throw new \RuntimeException('blob_hash_invalid');
        }
        $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin');
        $target = $this->buildStorageName($sha256, $extension);

        if ($this->verifyAbstractedPath($target, $sha256))
        {
            return $target;
        }

        $temp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wrxtb_' . bin2hex(random_bytes(12));
        $source = \XF::fs()->readStream($sourcePath);
        if (!is_resource($source))
        {
            throw new \RuntimeException('blob_source_open_failed');
        }
        $targetFile = @fopen($temp, 'xb');
        if (!is_resource($targetFile))
        {
            fclose($source);
            throw new \RuntimeException('blob_temp_open_failed');
        }
        @chmod($temp, 0600);
        try
        {
            if (stream_copy_to_stream($source, $targetFile) === false)
            {
                throw new \RuntimeException('blob_materialize_failed');
            }
        }
        finally
        {
            fclose($source);
            fclose($targetFile);
        }

        try
        {
            $localHash = hash_file('sha256', $temp);
            if (!is_string($localHash) || !hash_equals($sha256, $localHash))
            {
                throw new \RuntimeException('blob_source_hash_mismatch');
            }
            File::copyFileToAbstractedPath($temp, $target);
            if (!$this->verifyAbstractedPath($target, $sha256))
            {
                File::deleteFromAbstractedPath($target);
                throw new \RuntimeException('blob_publish_hash_mismatch');
            }
        }
        finally
        {
            @unlink($temp);
        }

        return $target;
    }

    public function readStream(string $storageName)
    {
        return \XF::fs()->readStream($storageName);
    }

    public function delete(string $storageName): void
    {
        if ($storageName !== '')
        {
            File::deleteFromAbstractedPath($storageName);
        }
    }

    public function verifyAbstractedPath(string $storageName, string $sha256): bool
    {
        $stream = \XF::fs()->readStream($storageName);
        if (!is_resource($stream))
        {
            return false;
        }
        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $stream);
        fclose($stream);
        return hash_equals(strtolower($sha256), hash_final($ctx));
    }

    protected function buildStorageName(string $sha256, string $extension): string
    {
        return 'internal-data://wrxt_portfolio/blobs/' . substr($sha256, 0, 2) . '/' . substr($sha256, 2, 2) . '/' . $sha256 . '.' . $extension;
    }
}
