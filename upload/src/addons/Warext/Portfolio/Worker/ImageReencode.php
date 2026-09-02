<?php

declare(strict_types=1);

function fail(string $reason): never
{
    echo json_encode(['ok' => false, 'error' => $reason], JSON_UNESCAPED_SLASHES);
    exit(2);
}

function fitSize(int $width, int $height, int $max): array
{
    if ($width <= 0 || $height <= 0)
    {
        fail('image_dimensions_invalid');
    }
    if ($width <= $max && $height <= $max)
    {
        return [$width, $height];
    }
    $ratio = min($max / $width, $max / $height);
    return [max(1, (int)floor($width * $ratio)), max(1, (int)floor($height * $ratio))];
}

function gdLoad(string $path, string $mime)
{
    return match ($mime)
    {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false
    };
}

function gdResize($source, int $width, int $height, int $targetWidth, int $targetHeight)
{
    if ($width === $targetWidth && $height === $targetHeight)
    {
        return $source;
    }
    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$target)
    {
        fail('gd_target_create_failed');
    }
    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
    if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height))
    {
        imagedestroy($target);
        fail('gd_resize_failed');
    }
    return $target;
}

if (PHP_SAPI !== 'cli' || count($argv) !== 7)
{
    fail('worker_arguments_invalid');
}

[$script, $input, $displayOutput, $thumbOutput, $displayMaxRaw, $thumbMaxRaw, $qualityRaw] = $argv;
$displayMax = max(512, min(8192, (int)$displayMaxRaw));
$thumbMax = max(128, min(2048, (int)$thumbMaxRaw));
$quality = max(50, min(95, (int)$qualityRaw));

if (!is_file($input) || filesize($input) <= 0)
{
    fail('worker_input_missing');
}

$finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
$mime = $finfo ? strtolower((string)$finfo->file($input)) : '';
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true))
{
    fail('worker_mime_not_allowed');
}

$size = @getimagesize($input);
if (!is_array($size) || empty($size[0]) || empty($size[1]))
{
    fail('worker_decode_probe_failed');
}
$width = (int)$size[0];
$height = (int)$size[1];

if (extension_loaded('imagick') && class_exists('Imagick'))
{
    try
    {
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_THREAD, 1);
        $image = new \Imagick();
        $image->readImage($input);
        if ($image->getNumberImages() !== 1)
        {
            fail('animated_image_not_allowed');
        }
        $image->setIteratorIndex(0);
        $image->stripImage();
        $image->setImagePage(0, 0, 0, 0);
        [$displayW, $displayH] = fitSize($width, $height, $displayMax);
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
            fail('imagick_display_write_failed');
        }

        [$thumbW, $thumbH] = fitSize($width, $height, $thumbMax);
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
            fail('imagick_thumb_write_failed');
        }
        $display->clear();
        $thumb->clear();
        $image->clear();
    }
    catch (\Throwable $e)
    {
        fail('imagick_processing_failed');
    }
}
elseif (extension_loaded('gd') && function_exists('imagewebp'))
{
    $source = gdLoad($input, $mime);
    if (!$source)
    {
        fail('gd_decode_failed');
    }
    [$displayW, $displayH] = fitSize($width, $height, $displayMax);
    $display = gdResize($source, $width, $height, $displayW, $displayH);
    if (!imagewebp($display, $displayOutput, $quality))
    {
        fail('gd_display_write_failed');
    }
    if ($display !== $source)
    {
        imagedestroy($display);
    }

    [$thumbW, $thumbH] = fitSize($width, $height, $thumbMax);
    $thumb = gdResize($source, $width, $height, $thumbW, $thumbH);
    if (!imagewebp($thumb, $thumbOutput, min($quality, 84)))
    {
        fail('gd_thumb_write_failed');
    }
    if ($thumb !== $source)
    {
        imagedestroy($thumb);
    }
    imagedestroy($source);
}
else
{
    fail('image_library_unavailable');
}

foreach ([$displayOutput, $thumbOutput] as $output)
{
    if (!is_file($output) || filesize($output) <= 0)
    {
        fail('worker_output_missing');
    }
    @chmod($output, 0600);
}

$displayInfo = @getimagesize($displayOutput);
$thumbInfo = @getimagesize($thumbOutput);
if (!is_array($displayInfo) || !is_array($thumbInfo))
{
    fail('worker_output_verify_failed');
}

echo json_encode([
    'ok' => true,
    'engine' => extension_loaded('imagick') ? 'imagick' : 'gd',
    'source_width' => $width,
    'source_height' => $height,
    'display_width' => (int)$displayInfo[0],
    'display_height' => (int)$displayInfo[1],
    'thumb_width' => (int)$thumbInfo[0],
    'thumb_height' => (int)$thumbInfo[1]
], JSON_UNESCAPED_SLASHES);
