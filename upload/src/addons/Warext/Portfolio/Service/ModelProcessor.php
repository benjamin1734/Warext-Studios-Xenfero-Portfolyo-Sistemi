<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use XF\Util\File;
use Warext\Portfolio\Entity\PortfolioFile;

class ModelProcessor extends AbstractService
{
    public function process(PortfolioFile $file): array
    {
        if ((string)$file->extension !== 'glb')
        {
            throw new \InvalidArgumentException('model_processor_extension_invalid');
        }
        if (!$file->storage_name)
        {
            throw new \RuntimeException('processing_source_missing');
        }

        $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wrxtm_' . bin2hex(random_bytes(12));
        if (!@mkdir($workDir, 0700, true) && !is_dir($workDir))
        {
            throw new \RuntimeException('model_workdir_failed');
        }
        $input = $workDir . DIRECTORY_SEPARATOR . 'model.glb';

        try
        {
            $this->materialize((string)$file->storage_name, $input);
            $beforeHash = hash_file('sha256', $input);
            if (!is_string($beforeHash) || !$file->sha256 || !hash_equals((string)$file->sha256, $beforeHash))
            {
                throw new \RuntimeException('model_source_hash_mismatch');
            }

            $worker = $this->service('Warext\Portfolio:WorkerProcess')->runGlbWorker($input);
            $afterHash = hash_file('sha256', $input);
            if (!is_string($afterHash) || !hash_equals($beforeHash, $afterHash))
            {
                throw new \RuntimeException('model_hash_changed_during_processing');
            }

            $stats = is_array($worker['stats'] ?? null) ? $worker['stats'] : [];
            $target = 'internal-data://wrxt_portfolio/staging/model/' . substr((string)$file->file_key, 0, 2) . '/' . (string)$file->file_key . '.glb';
            File::copyFileToAbstractedPath($input, $target);

            $stream = \XF::fs()->readStream($target);
            if (!is_resource($stream))
            {
                File::deleteFromAbstractedPath($target);
                throw new \RuntimeException('model_publish_verify_open_failed');
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $stream);
            fclose($stream);
            $storedHash = hash_final($ctx);
            if (!hash_equals($beforeHash, $storedHash))
            {
                File::deleteFromAbstractedPath($target);
                throw new \RuntimeException('model_publish_hash_mismatch');
            }

            return [
                'processed_storage_name' => $target,
                'processed_sha256' => $beforeHash,
                'processed_size' => (int)filesize($input),
                'processed_mime' => 'model/gltf-binary',
                'stats' => $stats,
                'worker' => $worker
            ];
        }
        finally
        {
            if (is_file($input))
            {
                @unlink($input);
            }
            @rmdir($workDir);
        }
    }

    private function materialize(string $abstractPath, string $targetPath): void
    {
        $source = \XF::fs()->readStream($abstractPath);
        if (!is_resource($source))
        {
            throw new \RuntimeException('model_source_open_failed');
        }
        $target = @fopen($targetPath, 'xb');
        if (!is_resource($target))
        {
            fclose($source);
            throw new \RuntimeException('model_target_open_failed');
        }
        @chmod($targetPath, 0600);
        try
        {
            if (stream_copy_to_stream($source, $target) === false)
            {
                throw new \RuntimeException('model_materialize_failed');
            }
        }
        finally
        {
            fclose($source);
            fclose($target);
        }
    }
}
