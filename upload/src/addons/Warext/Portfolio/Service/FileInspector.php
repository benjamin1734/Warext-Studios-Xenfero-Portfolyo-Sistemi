<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\PortfolioFile;

class FileInspector extends AbstractService
{
    private const MIME_MAP = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'glb' => ['model/gltf-binary', 'application/octet-stream']
    ];

    private const DANGEROUS_NAME_PARTS = [
        'php', 'phtml', 'phar', 'html', 'htm', 'js', 'mjs', 'exe', 'dll', 'bat', 'cmd', 'ps1', 'sh', 'jar', 'apk', 'msi', 'com', 'scr', 'htaccess', 'zip', 'rar', '7z', 'tar', 'gz'
    ];

    public function inspect(PortfolioFile $file, string $localPath): array
    {
        if (!is_file($localPath))
        {
            throw new \RuntimeException('security_file_missing');
        }

        $size = (int)filesize($localPath);
        if ($size <= 0 || $size !== (int)$file->file_size)
        {
            throw new \RuntimeException('file_size_mismatch');
        }

        $this->assertOriginalNameSafe((string)$file->original_name);
        $sha256 = hash_file('sha256', $localPath);
        if (!is_string($sha256) || !preg_match('/^[a-f0-9]{64}$/', $sha256))
        {
            throw new \RuntimeException('hash_failed');
        }

        if ($file->sha256 && !hash_equals((string)$file->sha256, $sha256))
        {
            throw new \RuntimeException('hash_changed');
        }

        $extension = strtolower((string)$file->extension);
        $mime = $this->detectMime($localPath);
        if (!isset(self::MIME_MAP[$extension]) || !in_array($mime, self::MIME_MAP[$extension], true))
        {
            throw new \RuntimeException('mime_mismatch');
        }

        $details = match ($extension)
        {
            'jpg', 'jpeg' => $this->inspectJpeg($localPath),
            'png' => $this->inspectPng($localPath),
            'webp' => $this->inspectWebp($localPath),
            'glb' => $this->inspectGlb($localPath),
            default => throw new \RuntimeException('extension_not_allowed')
        };

        return [
            'sha256' => $sha256,
            'detected_mime' => $mime,
            'magic_type' => $details['magic_type'],
            'details' => $details
        ];
    }

    private function detectMime(string $path): string
    {
        if (!class_exists('finfo'))
        {
            throw new \RuntimeException('fileinfo_unavailable');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) ? strtolower(trim($mime)) : '';
    }

    private function assertOriginalNameSafe(string $name): void
    {
        $normalized = str_replace('\\', '/', $name);
        $base = strtolower(basename($normalized));
        if ($base === '' || str_contains($name, "\0") || $normalized !== basename($normalized) || str_contains($normalized, '../'))
        {
            throw new \RuntimeException('unsafe_original_name');
        }

        $parts = preg_split('/[.\s]+/', $base) ?: [];
        array_pop($parts);
        foreach ($parts as $part)
        {
            if (in_array($part, self::DANGEROUS_NAME_PARTS, true))
            {
                throw new \RuntimeException('dangerous_double_extension');
            }
        }
    }

    private function inspectPng(string $path): array
    {
        $fp = fopen($path, 'rb');
        if (!is_resource($fp)) { throw new \RuntimeException('png_open_failed'); }
        try
        {
            if (fread($fp, 8) !== "\x89PNG\r\n\x1a\n") { throw new \RuntimeException('png_magic_invalid'); }
            $width = 0; $height = 0; $seenIhdr = false; $seenIend = false; $chunkCount = 0; $fileSize = (int)filesize($path);
            while (ftell($fp) < $fileSize)
            {
                $header = fread($fp, 8);
                if (strlen($header) !== 8) { throw new \RuntimeException('png_chunk_header_invalid'); }
                $length = unpack('N', substr($header, 0, 4))[1];
                $type = substr($header, 4, 4);
                $chunkCount++;
                $remaining = $fileSize - ftell($fp);
                if ($chunkCount > 10000 || $length > $remaining - 4) { throw new \RuntimeException('png_chunk_limit'); }
                $ctx = hash_init('crc32b');
                hash_update($ctx, $type);
                $ihdr = '';
                $left = $length;
                while ($left > 0)
                {
                    $take = min($left, 1048576);
                    $chunk = fread($fp, $take);
                    if (!is_string($chunk) || strlen($chunk) !== $take) { throw new \RuntimeException('png_truncated'); }
                    hash_update($ctx, $chunk);
                    if (!$seenIhdr && $type === 'IHDR') { $ihdr .= $chunk; }
                    $left -= $take;
                }
                $crc = fread($fp, 4);
                if (strlen($crc) !== 4) { throw new \RuntimeException('png_truncated'); }
                if (!hash_equals(strtolower(hash_final($ctx)), strtolower(bin2hex($crc)))) { throw new \RuntimeException('png_crc_invalid'); }
                if (!$seenIhdr)
                {
                    if ($type !== 'IHDR' || $length !== 13 || strlen($ihdr) !== 13) { throw new \RuntimeException('png_ihdr_invalid'); }
                    $width = unpack('N', substr($ihdr, 0, 4))[1];
                    $height = unpack('N', substr($ihdr, 4, 4))[1];
                    $seenIhdr = true;
                }
                if ($type === 'IEND')
                {
                    if ($length !== 0) { throw new \RuntimeException('png_iend_invalid'); }
                    $seenIend = true;
                    if (ftell($fp) !== $fileSize) { throw new \RuntimeException('png_trailing_data'); }
                    break;
                }
            }
            if (!$seenIhdr || !$seenIend) { throw new \RuntimeException('png_structure_invalid'); }
            $this->assertImageDimensions($width, $height);
            return ['magic_type' => 'png', 'width' => $width, 'height' => $height, 'chunks' => $chunkCount];
        }
        finally { fclose($fp); }
    }

    private function inspectJpeg(string $path): array
    {
        $fp = fopen($path, 'rb');
        if (!is_resource($fp))
        {
            throw new \RuntimeException('jpeg_open_failed');
        }

        try
        {
            if (fread($fp, 2) !== "\xFF\xD8")
            {
                throw new \RuntimeException('jpeg_magic_invalid');
            }

            $fileSize = (int)filesize($path);
            if ($fileSize < 4 || fseek($fp, -2, SEEK_END) !== 0 || fread($fp, 2) !== "\xFF\xD9")
            {
                throw new \RuntimeException('jpeg_trailing_or_missing_eoi');
            }

            rewind($fp);
            fread($fp, 2);
            $width = 0;
            $height = 0;
            $segments = 0;

            while (!feof($fp))
            {
                $byte = fread($fp, 1);
                if ($byte === '')
                {
                    break;
                }
                if (ord($byte) !== 0xFF)
                {
                    continue;
                }

                do
                {
                    $markerByte = fread($fp, 1);
                    if ($markerByte === '')
                    {
                        throw new \RuntimeException('jpeg_marker_truncated');
                    }
                } while (ord($markerByte) === 0xFF);

                $marker = ord($markerByte);
                if ($marker === 0xD9 || $marker === 0xDA)
                {
                    break;
                }
                if (($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01)
                {
                    continue;
                }

                $lenBytes = fread($fp, 2);
                if (strlen($lenBytes) !== 2)
                {
                    throw new \RuntimeException('jpeg_segment_truncated');
                }
                $segmentLength = unpack('n', $lenBytes)[1];
                if ($segmentLength < 2)
                {
                    throw new \RuntimeException('jpeg_segment_invalid');
                }
                $payloadLength = $segmentLength - 2;
                $segments++;
                if ($segments > 4096 || $payloadLength > $fileSize)
                {
                    throw new \RuntimeException('jpeg_segment_limit');
                }

                if (in_array($marker, [0xC0,0xC1,0xC2,0xC3,0xC5,0xC6,0xC7,0xC9,0xCA,0xCB,0xCD,0xCE,0xCF], true))
                {
                    $sof = fread($fp, $payloadLength);
                    if (strlen($sof) !== $payloadLength || $payloadLength < 5)
                    {
                        throw new \RuntimeException('jpeg_sof_invalid');
                    }
                    $height = unpack('n', substr($sof, 1, 2))[1];
                    $width = unpack('n', substr($sof, 3, 2))[1];
                }
                else
                {
                    if (fseek($fp, $payloadLength, SEEK_CUR) !== 0)
                    {
                        throw new \RuntimeException('jpeg_segment_seek_failed');
                    }
                }
            }

            if ($width <= 0 || $height <= 0)
            {
                throw new \RuntimeException('jpeg_dimensions_missing');
            }

            $this->assertImageDimensions($width, $height);
            return ['magic_type' => 'jpeg', 'width' => $width, 'height' => $height, 'segments' => $segments];
        }
        finally
        {
            fclose($fp);
        }
    }

    private function inspectWebp(string $path): array
    {
        $fp = fopen($path, 'rb');
        if (!is_resource($fp)) { throw new \RuntimeException('webp_open_failed'); }
        try
        {
            $header = fread($fp, 12);
            if (strlen($header) !== 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') { throw new \RuntimeException('webp_magic_invalid'); }
            $declared = unpack('V', substr($header, 4, 4))[1] + 8;
            $fileSize = (int)filesize($path);
            if ($declared !== $fileSize) { throw new \RuntimeException('webp_length_mismatch'); }
            $width = 0; $height = 0; $chunks = 0;
            while (ftell($fp) + 8 <= $fileSize)
            {
                $chunkHeader = fread($fp, 8);
                if (strlen($chunkHeader) !== 8) { throw new \RuntimeException('webp_chunk_header_invalid'); }
                $type = substr($chunkHeader, 0, 4);
                $length = unpack('V', substr($chunkHeader, 4, 4))[1];
                $chunks++;
                $padded = $length + ($length & 1);
                if ($chunks > 10000 || $padded > ($fileSize - ftell($fp))) { throw new \RuntimeException('webp_chunk_limit'); }
                if ($type === 'ANIM' || $type === 'ANMF') { throw new \RuntimeException('webp_animation_not_allowed'); }
                $need = $type === 'VP8X' ? min($length, 10) : (($type === 'VP8L') ? min($length, 5) : (($type === 'VP8 ') ? min($length, 10) : 0));
                $data = $need ? fread($fp, $need) : '';
                if (strlen($data) !== $need) { throw new \RuntimeException('webp_truncated'); }
                $skip = $length - $need;
                if ($skip && fseek($fp, $skip, SEEK_CUR) !== 0) { throw new \RuntimeException('webp_truncated'); }
                if ($length & 1)
                {
                    if (fread($fp, 1) === '') { throw new \RuntimeException('webp_padding_invalid'); }
                }
                if ($type === 'VP8X' && $length >= 10)
                {
                    $width = 1 + $this->u24le(substr($data, 4, 3)); $height = 1 + $this->u24le(substr($data, 7, 3));
                }
                elseif ($type === 'VP8L' && $length >= 5 && ord($data[0]) === 0x2F)
                {
                    $b1=ord($data[1]); $b2=ord($data[2]); $b3=ord($data[3]); $b4=ord($data[4]);
                    $width = 1 + ($b1 | (($b2 & 0x3F) << 8)); $height = 1 + (($b2 >> 6) | ($b3 << 2) | (($b4 & 0x0F) << 10));
                }
                elseif ($type === 'VP8 ' && $length >= 10 && substr($data, 3, 3) === "\x9D\x01\x2A")
                {
                    $width = unpack('v', substr($data, 6, 2))[1] & 0x3FFF; $height = unpack('v', substr($data, 8, 2))[1] & 0x3FFF;
                }
            }
            if (ftell($fp) !== $fileSize || $width <= 0 || $height <= 0) { throw new \RuntimeException('webp_structure_invalid'); }
            $this->assertImageDimensions($width, $height);
            return ['magic_type' => 'webp', 'width' => $width, 'height' => $height, 'chunks' => $chunks];
        }
        finally { fclose($fp); }
    }

    private function inspectGlb(string $path): array
    {
        $fp = fopen($path, 'rb');
        if (!is_resource($fp)) { throw new \RuntimeException('glb_open_failed'); }
        try
        {
            $header = fread($fp, 12);
            if (strlen($header) !== 12 || substr($header, 0, 4) !== 'glTF') { throw new \RuntimeException('glb_magic_invalid'); }
            $version = unpack('V', substr($header, 4, 4))[1];
            $declaredLength = unpack('V', substr($header, 8, 4))[1];
            $fileSize = (int)filesize($path);
            if ($version !== 2 || $declaredLength !== $fileSize) { throw new \RuntimeException('glb_header_invalid'); }
            $jsonText = null; $chunkCount = 0;
            while (ftell($fp) < $fileSize)
            {
                $chunkHeader = fread($fp, 8);
                if (strlen($chunkHeader) !== 8) { throw new \RuntimeException('glb_chunk_header_invalid'); }
                $length = unpack('V', substr($chunkHeader, 0, 4))[1];
                $type = unpack('V', substr($chunkHeader, 4, 4))[1];
                $chunkCount++;
                if ($chunkCount > 2 || $length % 4 !== 0 || $length > ($fileSize - ftell($fp))) { throw new \RuntimeException('glb_chunk_length_invalid'); }
                if ($chunkCount === 1)
                {
                    if ($type !== 0x4E4F534A || $length === 0 || $length > 8388608) { throw new \RuntimeException($length > 8388608 ? 'glb_json_chunk_limit' : 'glb_json_chunk_missing'); }
                    $jsonText = fread($fp, $length);
                    if (!is_string($jsonText) || strlen($jsonText) !== $length) { throw new \RuntimeException('glb_truncated'); }
                }
                else
                {
                    if ($type !== 0x004E4942) { throw new \RuntimeException('glb_unknown_chunk'); }
                    if ($length && fseek($fp, $length, SEEK_CUR) !== 0) { throw new \RuntimeException('glb_truncated'); }
                }
            }
            if (ftell($fp) !== $fileSize || $jsonText === null) { throw new \RuntimeException('glb_json_chunk_missing'); }
            $jsonText = rtrim($jsonText, " \t\r\n\0");
            try { $json = json_decode($jsonText, true, 64, JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { throw new \RuntimeException('glb_json_invalid'); }
            if (!is_array($json) || !isset($json['asset']) || !is_array($json['asset'])) { throw new \RuntimeException('glb_asset_missing'); }
            if (!str_starts_with((string)($json['asset']['version'] ?? ''), '2')) { throw new \RuntimeException('glb_asset_version_invalid'); }
            foreach (($json['buffers'] ?? []) as $buffer) { if (is_array($buffer) && array_key_exists('uri', $buffer)) { throw new \RuntimeException('glb_external_buffer_uri'); } }
            foreach (($json['images'] ?? []) as $image) { if (is_array($image) && array_key_exists('uri', $image)) { throw new \RuntimeException('glb_external_image_uri'); } }
            return ['magic_type'=>'glb2','version'=>$version,'chunks'=>$chunkCount,'nodes'=>is_array($json['nodes']??null)?count($json['nodes']):0,'meshes'=>is_array($json['meshes']??null)?count($json['meshes']):0,'materials'=>is_array($json['materials']??null)?count($json['materials']):0,'images'=>is_array($json['images']??null)?count($json['images']):0,'animations'=>is_array($json['animations']??null)?count($json['animations']):0];
        }
        finally { fclose($fp); }
    }

    private function assertImageDimensions(int $width, int $height): void
    {
        $maxWidth = max(512, (int)($this->app->options()->wrxtPortfolioMaxImageWidth ?? 12000));
        $maxHeight = max(512, (int)($this->app->options()->wrxtPortfolioMaxImageHeight ?? 12000));
        $maxMegapixels = max(1, (int)($this->app->options()->wrxtPortfolioMaxImageMegapixels ?? 50));

        if ($width <= 0 || $height <= 0 || $width > $maxWidth || $height > $maxHeight)
        {
            throw new \RuntimeException('image_dimensions_limit');
        }
        if (($width * $height) > ($maxMegapixels * 1000000))
        {
            throw new \RuntimeException('image_pixel_limit');
        }
    }

    private function u24le(string $bytes): int
    {
        if (strlen($bytes) !== 3)
        {
            return 0;
        }
        return ord($bytes[0]) | (ord($bytes[1]) << 8) | (ord($bytes[2]) << 16);
    }
}
