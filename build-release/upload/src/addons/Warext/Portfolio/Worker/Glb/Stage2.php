<?php
$buffers = is_array($json['buffers'] ?? null) ? $json['buffers'] : [];
if (count($buffers) > 1)
{
    fail('glb_multiple_buffers_not_allowed');
}
if ($buffers)
{
    $buffer = $buffers[0];
    if (!is_array($buffer) || array_key_exists('uri', $buffer))
    {
        fail('glb_external_buffer_uri');
    }
    $declared = (int)($buffer['byteLength'] ?? -1);
    if ($declared < 0 || $declared > strlen($bin) || strlen($bin) - $declared > 3)
    {
        fail('glb_buffer_length_invalid');
    }
}
elseif ($bin !== '')
{
    fail('glb_unreferenced_bin_chunk');
}

$bufferViews = is_array($json['bufferViews'] ?? null) ? $json['bufferViews'] : [];
$accessors = is_array($json['accessors'] ?? null) ? $json['accessors'] : [];
if (count($bufferViews) > $limits['bufferViews']) fail('glb_buffer_view_limit');
if (count($accessors) > $limits['accessors']) fail('glb_accessor_limit');

foreach ($bufferViews as $i => $view)
{
    if (!is_array($view)) fail('glb_buffer_view_invalid');
    if ((int)($view['buffer'] ?? -1) !== 0) fail('glb_buffer_view_buffer_invalid');
    $start = (int)($view['byteOffset'] ?? 0);
    $length = (int)($view['byteLength'] ?? -1);
    $stride = isset($view['byteStride']) ? (int)$view['byteStride'] : 0;
    if ($start < 0 || $length < 0 || $start % 4 !== 0 || $start + $length > strlen($bin)) fail('glb_buffer_view_bounds');
    if ($stride !== 0 && ($stride < 4 || $stride > 252 || $stride % 4 !== 0)) fail('glb_buffer_view_stride_invalid');
    if (isset($view['target']) && !in_array((int)$view['target'], [34962, 34963], true)) fail('glb_buffer_view_target_invalid');
}

$totalAccessorElements = 0;
foreach ($accessors as $i => $accessor)
{
    if (!is_array($accessor)) fail('glb_accessor_invalid');
    if (isset($accessor['sparse'])) fail('glb_sparse_accessor_not_allowed');
    $componentType = (int)($accessor['componentType'] ?? 0);
    $type = (string)($accessor['type'] ?? '');
    $count = (int)($accessor['count'] ?? -1);
    if ($count < 0) fail('glb_accessor_count_invalid');
    $totalAccessorElements += $count;
    if ($totalAccessorElements > $limits['accessorElements']) fail('glb_accessor_element_limit');
    $elementSize = accessorElementSize($componentType, $type);
    if (!isset($accessor['bufferView']))
    {
        if ($count > 0) fail('glb_accessor_without_buffer_view');
        continue;
    }
    $viewIndex = assertIndex($accessor['bufferView'], count($bufferViews), 'glb_accessor_buffer_view_invalid');
    $view = $bufferViews[$viewIndex];
    $offsetInView = (int)($accessor['byteOffset'] ?? 0);
    $stride = (int)($view['byteStride'] ?? 0);
    if ($stride === 0) $stride = $elementSize;
    if ($stride < $elementSize || $offsetInView < 0 || $offsetInView % componentSize($componentType) !== 0)
    {
        fail('glb_accessor_layout_invalid');
    }
    $needed = $count === 0 ? 0 : (($count - 1) * $stride + $elementSize);
    if ($offsetInView + $needed > (int)$view['byteLength']) fail('glb_accessor_bounds');
}

$materials = is_array($json['materials'] ?? null) ? $json['materials'] : [];
$textures = is_array($json['textures'] ?? null) ? $json['textures'] : [];
