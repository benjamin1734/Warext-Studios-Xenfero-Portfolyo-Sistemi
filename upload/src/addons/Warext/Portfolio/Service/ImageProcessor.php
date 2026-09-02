<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use XF\Util\File;
use Warext\Portfolio\Entity\PortfolioFile;

class ImageProcessor extends AbstractService
{
    public function process(PortfolioFile $file): array
    {
        if (!in_array((string)$file->extension, ['jpg', 'jpeg', 'png', 'webp'], true))
        {
            throw new \InvalidArgumentException('image_processor_extension_invalid');
        }
        if (!$file->storage_name)
        {
            throw new \RuntimeException('processing_source_missing');
        }

        $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wrxtp_' . bin2hex(random_bytes(12));
        if (!@mkdir($workDir, 0700, true) && !is_dir($workDir))
        {
            throw new \RuntimeException('processing_workdir_failed');
        }

        $input = $workDir . DIRECTORY_SEPARATOR . 'input.bin';
        $display = $workDir . DIRECTORY_SEPARATOR . 'display.webp';
        $thumb = $workDir . DIRECTORY_SEPARATOR . 'thumb.webp';

        try
        {
            $this->materialize((string)$file->storage_name, $input);
            $beforeHash = hash_file('sha256', $input);
            if (!is_string($beforeHash) || !$file->sha256 || !hash_equals((string)$file->sha256, $beforeHash))
            {
                throw new \RuntimeException('processing_source_hash_mismatch');
            }

            $workerResult = $this->service('Warext\Portfolio:WorkerProcess')->runImageWorker($input, $display, $thumb);
            $displayMeta = $this->verifyWebp($display);
            $thumbMeta = $this->verifyWebp($thumb);

            $base = 'internal-data://wrxt_portfolio/staging/image/' . substr((string)$file->file_key, 0, 2) . '/' . (string)$file->file_key;
            $displayPath = $base . '/display.webp';
            $thumbPath = $base . '/thumb.webp';

            try
            {
                File::copyFileToAbstractedPath($display, $displayPath);
                File::copyFileToAbstractedPath($thumb, $thumbPath);
            }
            catch (\Throwable $e)
            {
                File::deleteFromAbstractedPath($displayPath);
                File::deleteFromAbstractedPath($thumbPath);
                throw $e;
            }

            $displayHash = hash_file('sha256', $display);
            if (!is_string($displayHash))
            {
                File::deleteFromAbstractedPath($displayPath);
                File::deleteFromAbstractedPath($thumbPath);
                throw new \RuntimeException('processed_hash_failed');
            }

            return [
                'processed_storage_name' => $displayPath,
                'thumbnail_storage_name' => $thumbPath,
                'processed_sha256' => $displayHash,
                'processed_size' => (int)filesize($display),
                'processed_mime' => 'image/webp',
                'processed_width' => $displayMeta['width'],
                'processed_height' => $displayMeta['height'],
                'thumbnail_width' => $thumbMeta['width'],
                'thumbnail_height' => $thumbMeta['height'],
                'worker' => $workerResult
            ];
        }
        finally
        {
            foreach ([$input, $display, $thumb] as $path)
            {
                if (is_file($path))
                {
                    @unlink($path);
                }
            }
            @rmdir($workDir);
        }
    }

    private function materialize(string $abstractPath, string $targetPath): void
    {
        $source = \XF::fs()->readStream($abstractPath);
        if (!is_resource($source))
        {
            throw new \RuntimeException('processing_source_open_failed');
        }
        $target = @fopen($targetPath, 'xb');
        if (!is_resource($target))
        {
            fclose($source);
            throw new \RuntimeException('processing_target_open_failed');
        }
        @chmod($targetPath, 0600);
        try
        {
            if (stream_copy_to_stream($source, $target) === false)
            {
                throw new \RuntimeException('processing_materialize_failed');
            }
        }
        finally
        {
            fclose($source);
            fclose($target);
        }
    }

    private function verifyWebp(string $path): array
    {
        if (!is_file($path) || (int)filesize($path) <= 12)
        {
            throw new \RuntimeException('processed_output_missing');
        }
        $fp = fopen($path, 'rb');
        $header = is_resource($fp) ? fread($fp, 12) : '';
        if (is_resource($fp))
        {
            fclose($fp);
        }
        if (strlen($header) !== 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP')
        {
            throw new \RuntimeException('processed_webp_magic_invalid');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        if (strtolower((string)$finfo->file($path)) !== 'image/webp')
        {
            throw new \RuntimeException('processed_webp_mime_invalid');
        }
        $size = @getimagesize($path);
        if (!is_array($size) || empty($size[0]) || empty($size[1]))
        {
            throw new \RuntimeException('processed_webp_dimensions_invalid');
        }
        return ['width' => (int)$size[0], 'height' => (int)$size[1]];
    }
}
