<?php
$input = $argv[1] ?? '';
$configRaw = $argv[2] ?? '';
if ($input === '' || !is_file($input))
{
    fail('glb_worker_input_missing');
}
$config = json_decode(base64_decode($configRaw, true) ?: '', true);
if (!is_array($config))
{
    fail('glb_worker_config_invalid');
}

$limits = [
    'vertices' => max(1000, (int)($config['vertices'] ?? 1000000)),
    'triangles' => max(1000, (int)($config['triangles'] ?? 1500000)),
    'meshes' => max(1, (int)($config['meshes'] ?? 500)),
    'primitives' => max(1, (int)($config['primitives'] ?? 1000)),
    'nodes' => max(1, (int)($config['nodes'] ?? 2000)),
    'materials' => max(1, (int)($config['materials'] ?? 250)),
    'textures' => max(0, (int)($config['textures'] ?? 50)),
    'animations' => max(0, (int)($config['animations'] ?? 50)),
    'skins' => max(0, (int)($config['skins'] ?? 20)),
    'joints' => max(0, min(48, (int)($config['joints'] ?? 64))),
    'accessors' => max(1, (int)($config['accessors'] ?? 5000)),
    'bufferViews' => max(1, (int)($config['bufferViews'] ?? 5000)),
    'accessorElements' => max(1, (int)($config['accessorElements'] ?? 2000000)),
    'textureDimension' => max(128, (int)($config['textureDimension'] ?? 4096)),
    'textureBytes' => max(1024, (int)($config['textureBytes'] ?? 16777216)),
    'animationKeyframes' => max(1, (int)($config['animationKeyframes'] ?? 100000)),
    'depth' => max(8, min(256, (int)($config['depth'] ?? 64)))
];

$data = file_get_contents($input);
if (!is_string($data) || strlen($data) < 20)
{
    fail('glb_worker_read_failed');
}
$fileLength = strlen($data);
if (substr($data, 0, 4) !== 'glTF' || u32($data, 4) !== 2 || u32($data, 8) !== $fileLength)
{
    fail('glb_header_invalid');
}

$offset = 12;
$jsonBytes = null;
$bin = '';
$chunkIndex = 0;
while ($offset < $fileLength)
{
    if ($offset + 8 > $fileLength)
    {
        fail('glb_chunk_header_invalid');
    }
    $chunkLength = u32($data, $offset);
    $chunkType = u32($data, $offset + 4);
    $offset += 8;
    if ($chunkLength % 4 !== 0 || $offset + $chunkLength > $fileLength)
    {
        fail('glb_chunk_length_invalid');
    }
    $chunk = substr($data, $offset, $chunkLength);
    $offset += $chunkLength;
    if ($chunkIndex === 0 && $chunkType === 0x4E4F534A)
    {
        $jsonBytes = $chunk;
    }
    elseif ($chunkIndex === 1 && $chunkType === 0x004E4942)
    {
        $bin = $chunk;
    }
    else
    {
        fail('glb_chunk_layout_invalid');
    }
    $chunkIndex++;
}
if (!is_string($jsonBytes) || $chunkIndex < 1 || $chunkIndex > 2)
{
    fail('glb_json_chunk_missing');
}

try
{
    $json = json_decode(rtrim($jsonBytes, " \t\r\n\0"), true, 128, JSON_THROW_ON_ERROR);
}
catch (Throwable $e)
{
    fail('glb_json_invalid');
}
if (!is_array($json) || !isset($json['asset']) || !is_array($json['asset']) || (string)($json['asset']['version'] ?? '') !== '2.0')
{
    fail('glb_asset_version_invalid');
}

$allowedExtensions = ['KHR_materials_unlit'];
foreach (($json['extensionsUsed'] ?? []) as $ext)
{
    if (!is_string($ext) || !in_array($ext, $allowedExtensions, true))
    {
        fail('glb_extension_not_allowed');
    }
}
foreach (($json['extensionsRequired'] ?? []) as $ext)
{
    if (!is_string($ext) || !in_array($ext, $allowedExtensions, true))
    {
        fail('glb_required_extension_not_allowed');
    }
}
