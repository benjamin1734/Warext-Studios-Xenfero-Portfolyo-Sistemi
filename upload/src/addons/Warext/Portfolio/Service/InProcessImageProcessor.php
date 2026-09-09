<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Exception\ProcessingUnavailableException;

class InProcessImageProcessor extends AbstractService
{
    public function process(string $input, string $displayOutput, string $thumbOutput): array
    {
        if (!is_file($input) || (int)@filesize($input) <= 0)
        {
            throw new ProcessingUnavailableException('fallback_input_missing');
        }

        if (!class_exists('finfo'))
        {
            throw new ProcessingUnavailableException('fallback_fileinfo_unavailable');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($input));
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true))
        {
            throw new ProcessingUnavailableException('fallback_mime_not_allowed');
        }

        $size = @getimagesize($input);
        if (!is_array($size) || empty($size[0]) || empty($size[1]))
        {
            throw new ProcessingUnavailableException('fallback_decode_probe_failed');
        }

        $width = (int)$size[0];
        $height = (int)$size[1];
        $displayMax = max(512, min(8192, (int)($this->app->options()->wrxtPfDisplayMaxDim ?? 2560)));
        $thumbMax = max(128, min(2048, (int)($this->app->options()->wrxtPfThumbMaxDim ?? 480)));
        $quality = max(50, min(95, (int)($this->app->options()->wrxtPfWebpQuality ?? 86)));

        if (extension_loaded('imagick') && class_exists('Imagick'))
        {
            $engine = 'inprocess-imagick';
            $this->processImagick($input, $displayOutput, $thumbOutput, $width, $height, $displayMax, $thumbMax, $quality);
        }
        elseif (extension_loaded('gd') && function_exists('imagewebp'))
        {
            $engine = 'inprocess-gd';
            $this->processGd($input, $displayOutput, $thumbOutput, $mime, $width, $height, $displayMax, $thumbMax, $quality);
        }
        else
        {
            throw new ProcessingUnavailableException('image_library_unavailable');
        }

        foreach ([$displayOutput, $thumbOutput] as $output)
        {
            if (!is_file($output) || (int)@filesize($output) <= 12)
            {
                throw new ProcessingUnavailableException('fallback_output_missing');
            }
            @chmod($output, 0600);
        }

        $displayInfo = @getimagesize($displayOutput);
        $thumbInfo = @getimagesize($thumbOutput);
        if (!is_array($displayInfo) || !is_array($thumbInfo))
        {
            throw new ProcessingUnavailableException('fallback_output_verify_failed');
        }

        return [
            'ok' => true,
            'engine' => $engine,
            'source_width' => $width,
            'source_height' => $height,
            'display_width' => (int)$displayInfo[0],
            'display_height' => (int)$displayInfo[1],
            'thumb_width' => (int)$thumbInfo[0],
            'thumb_height' => (int)$thumbInfo[1]
        ];
    }

    private function processImagick(
        string $input,
        string $displayOutput,
        string $thumbOutput,
        int $width,
        int $height,
        int $displayMax,
        int $thumbMax,
        int $quality
    ): void
    {
        try
        {
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_THREAD, 1);
            $memoryMb = max(64, min(1024, (int)($this->app->options()->wrxtPfWorkerMemMb ?? 256)));
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, $memoryMb * 1024 * 1024);

            $image = new \Imagick();
            $image->readImage($input);
            if ($image->getNumberImages() !== 1)
            {
                $image->clear();
                throw new ProcessingUnavailableException('animated_image_not_allowed');
            }

            $image->setIteratorIndex(0);
            $image->stripImage();
            $image->setImagePage(0, 0, 0, 0);

            [$displayW, $displayH] = $this->fitSize($width, $height, $displayMax);
            $display = clone $image;
            if ($displayW !== $width || $displayH !== $height)
            {
                $display->resizeImage($displayW, $displayH, \Imagick::FILTER_LANCZOS, 1, true);
            }
            $display->stripImage();
            $display->setImageFormat('webp');
            $display->setImageCompressionQuality($quality);
            if (!$display->writeImage($displayOutput))
            {
                throw new ProcessingUnavailableException('fallback_imagick_display_write_failed');
            }

            [$thumbW, $thumbH] = $this->fitSize($width, $height, $thumbMax);
            $thumb = clone $image;
            if ($thumbW !== $width || $thumbH !== $height)
            {
                $thumb->resizeImage($thumbW, $thumbH, \Imagick::FILTER_LANCZOS, 1, true);
            }
            $thumb->stripImage();
            $thumb->setImageFormat('webp');
            $thumb->setImageCompressionQuality(min($quality, 84));
            if (!$thumb->writeImage($thumbOutput))
            {
                throw new ProcessingUnavailableException('fallback_imagick_thumb_write_failed');
            }

            $display->clear();
            $thumb->clear();
            $image->clear();
        }
        catch (ProcessingUnavailableException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new ProcessingUnavailableException('fallback_imagick_processing_failed', 0, $e);
        }
    }

    private function processGd(
        string $input,
        string $displayOutput,
        string $thumbOutput,
        string $mime,
        int $width,
        int $height,
        int $displayMax,
        int $thumbMax,
        int $quality
    ): void
    {
        $source = match ($mime)
        {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($input) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($input) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($input) : false,
            default => false
        };

        if (!$source)
        {
            throw new ProcessingUnavailableException('fallback_gd_decode_failed');
        }

        try
        {
            [$displayW, $displayH] = $this->fitSize($width, $height, $displayMax);
            $display = $this->gdResize($source, $width, $height, $displayW, $displayH);
            if (!@imagewebp($display, $displayOutput, $quality))
            {
                if ($display !== $source) { imagedestroy($display); }
                throw new ProcessingUnavailableException('fallback_gd_display_write_failed');
            }
            if ($display !== $source) { imagedestroy($display); }

            [$thumbW, $thumbH] = $this->fitSize($width, $height, $thumbMax);
            $thumb = $this->gdResize($source, $width, $height, $thumbW, $thumbH);
            if (!@imagewebp($thumb, $thumbOutput, min($quality, 84)))
            {
                if ($thumb !== $source) { imagedestroy($thumb); }
                throw new ProcessingUnavailableException('fallback_gd_thumb_write_failed');
            }
            if ($thumb !== $source) { imagedestroy($thumb); }
        }
        finally
        {
            imagedestroy($source);
        }
    }

    private function gdResize($source, int $width, int $height, int $targetWidth, int $targetHeight)
    {
        if ($width === $targetWidth && $height === $targetHeight)
        {
            return $source;
        }

        $target = @imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$target)
        {
            throw new ProcessingUnavailableException('fallback_gd_target_create_failed');
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);

        if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height))
        {
            imagedestroy($target);
            throw new ProcessingUnavailableException('fallback_gd_resize_failed');
        }

        return $target;
    }

    private function fitSize(int $width, int $height, int $max): array
    {
        if ($width <= 0 || $height <= 0)
        {
            throw new ProcessingUnavailableException('fallback_image_dimensions_invalid');
        }
        if ($width <= $max && $height <= $max)
        {
            return [$width, $height];
        }

        $ratio = min($max / $width, $max / $height);
        return [
            max(1, (int)floor($width * $ratio)),
            max(1, (int)floor($height * $ratio))
        ];
    }
}
