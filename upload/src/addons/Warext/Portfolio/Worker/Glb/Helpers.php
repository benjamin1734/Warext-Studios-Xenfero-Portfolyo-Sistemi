<?php

if (PHP_SAPI !== 'cli')
{
    exit(70);
}

function fail(string $code, int $exit = 2): void
{
    fwrite(STDOUT, json_encode(['ok' => false, 'error' => $code], JSON_UNESCAPED_SLASHES));
    exit($exit);
}

function u32(string $data, int $offset): int
{
    if ($offset < 0 || $offset + 4 > strlen($data))
    {
        fail('glb_u32_bounds');
    }
    return unpack('V', substr($data, $offset, 4))[1];
}

function aligned(int $value, int $alignment): int
{
    return (int)(ceil($value / $alignment) * $alignment);
}

function componentSize(int $type): int
{
    return match ($type) {
        5120, 5121 => 1,
        5122, 5123 => 2,
        5125, 5126 => 4,
        default => fail('glb_accessor_component_type_invalid')
    };
}

function typeComponents(string $type): array
{
    return match ($type) {
        'SCALAR' => [1, 1],
        'VEC2' => [1, 2],
        'VEC3' => [1, 3],
        'VEC4' => [1, 4],
        'MAT2' => [2, 2],
        'MAT3' => [3, 3],
        'MAT4' => [4, 4],
        default => fail('glb_accessor_type_invalid')
    };
}

function accessorElementSize(int $componentType, string $type): int
{
    $component = componentSize($componentType);
    [$cols, $rows] = typeComponents($type);
    if ($cols === 1)
    {
        return $rows * $component;
    }
    return $cols * aligned($rows * $component, 4);
}

function assertIndex($value, int $count, string $error): int
{
    if (!is_int($value) || $value < 0 || $value >= $count)
    {
        fail($error);
    }
    return $value;
}

function inspectPng(string $bytes, int $maxDim): array
{
    if (strlen($bytes) < 24 || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1A\n" || substr($bytes, 12, 4) !== 'IHDR')
    {
        fail('glb_texture_png_invalid');
    }
    $width = unpack('N', substr($bytes, 16, 4))[1];
    $height = unpack('N', substr($bytes, 20, 4))[1];
    if ($width < 1 || $height < 1 || $width > $maxDim || $height > $maxDim)
    {
        fail('glb_texture_dimensions_exceeded');
    }
    return ['width' => $width, 'height' => $height];
}

function inspectJpeg(string $bytes, int $maxDim): array
{
    $length = strlen($bytes);
    if ($length < 4 || substr($bytes, 0, 2) !== "\xFF\xD8")
    {
        fail('glb_texture_jpeg_invalid');
    }
    $offset = 2;
    $width = 0;
    $height = 0;
    $iterations = 0;
    while ($offset + 4 <= $length && $iterations++ < 4096)
    {
        while ($offset < $length && ord($bytes[$offset]) === 0xFF)
        {
            $offset++;
        }
        if ($offset >= $length)
        {
            break;
        }
        $marker = ord($bytes[$offset++]);
        if ($marker === 0xD9)
        {
            break;
        }
        if ($marker === 0xDA)
        {
            break;
        }
        if (in_array($marker, [0x01, 0xD0, 0xD1, 0xD2, 0xD3, 0xD4, 0xD5, 0xD6, 0xD7], true))
        {
            continue;
        }
        if ($offset + 2 > $length)
        {
            fail('glb_texture_jpeg_segment_invalid');
        }
        $segLen = unpack('n', substr($bytes, $offset, 2))[1];
        if ($segLen < 2 || $offset + $segLen > $length)
        {
            fail('glb_texture_jpeg_segment_invalid');
        }
        if (in_array($marker, [0xC0,0xC1,0xC2,0xC3,0xC5,0xC6,0xC7,0xC9,0xCA,0xCB,0xCD,0xCE,0xCF], true))
        {
            if ($segLen < 7)
            {
                fail('glb_texture_jpeg_sof_invalid');
            }
            $height = unpack('n', substr($bytes, $offset + 3, 2))[1];
            $width = unpack('n', substr($bytes, $offset + 5, 2))[1];
            break;
        }
        $offset += $segLen;
    }
    if ($width < 1 || $height < 1 || $width > $maxDim || $height > $maxDim)
    {
        fail('glb_texture_dimensions_exceeded');
    }
    return ['width' => $width, 'height' => $height];
}
