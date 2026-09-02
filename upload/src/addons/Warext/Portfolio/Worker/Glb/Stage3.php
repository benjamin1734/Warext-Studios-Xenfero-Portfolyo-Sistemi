<?php
$materials = is_array($json['materials'] ?? null) ? $json['materials'] : [];
$textures = is_array($json['textures'] ?? null) ? $json['textures'] : [];
$images = is_array($json['images'] ?? null) ? $json['images'] : [];
$meshes = is_array($json['meshes'] ?? null) ? $json['meshes'] : [];
$nodes = is_array($json['nodes'] ?? null) ? $json['nodes'] : [];
$animations = is_array($json['animations'] ?? null) ? $json['animations'] : [];
$skins = is_array($json['skins'] ?? null) ? $json['skins'] : [];
$scenes = is_array($json['scenes'] ?? null) ? $json['scenes'] : [];

if (count($materials) > $limits['materials']) fail('glb_material_limit');
if (count($textures) > $limits['textures'] || count($images) > $limits['textures']) fail('glb_texture_limit');
if (count($meshes) > $limits['meshes']) fail('glb_mesh_limit');
if (count($nodes) > $limits['nodes']) fail('glb_node_limit');
if (count($animations) > $limits['animations']) fail('glb_animation_limit');
if (count($skins) > $limits['skins']) fail('glb_skin_limit');

$maxTextureDimension = 0;
$totalTextureBytes = 0;
foreach ($images as $image)
{
    if (!is_array($image) || array_key_exists('uri', $image)) fail('glb_external_image_uri');
    $viewIndex = assertIndex($image['bufferView'] ?? null, count($bufferViews), 'glb_image_buffer_view_invalid');
    $mime = (string)($image['mimeType'] ?? '');
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) fail('glb_image_mime_not_allowed');
    $view = $bufferViews[$viewIndex];
    $length = (int)$view['byteLength'];
    if ($length < 1 || $length > $limits['textureBytes']) fail('glb_texture_byte_limit');
    $totalTextureBytes += $length;
    if ($totalTextureBytes > $limits['textureBytes'] * max(1, $limits['textures'])) fail('glb_total_texture_byte_limit');
    $bytes = substr($bin, (int)($view['byteOffset'] ?? 0), $length);
    $meta = $mime === 'image/png' ? inspectPng($bytes, $limits['textureDimension']) : inspectJpeg($bytes, $limits['textureDimension']);
    $maxTextureDimension = max($maxTextureDimension, $meta['width'], $meta['height']);
}
foreach ($textures as $texture)
{
    if (!is_array($texture)) fail('glb_texture_invalid');
    assertIndex($texture['source'] ?? null, count($images), 'glb_texture_source_invalid');
}

$totalVertices = 0;
$totalTriangles = 0;
$totalPrimitives = 0;
foreach ($meshes as $mesh)
{
    if (!is_array($mesh) || !is_array($mesh['primitives'] ?? null) || !$mesh['primitives']) fail('glb_mesh_primitives_missing');
    if (isset($mesh['weights'])) fail('glb_morph_weights_unsupported');
    foreach ($mesh['primitives'] as $primitive)
    {
        if (!is_array($primitive)) fail('glb_primitive_invalid');
        if (isset($primitive['targets'])) fail('glb_morph_targets_unsupported');
        if (!empty($primitive['extensions'])) fail('glb_primitive_extensions_not_allowed');
        $totalPrimitives++;
        if ($totalPrimitives > $limits['primitives']) fail('glb_primitive_limit');
        $mode = (int)($primitive['mode'] ?? 4);
        if (!in_array($mode, [4,5,6], true)) fail('glb_primitive_mode_not_allowed');
        $attributes = $primitive['attributes'] ?? null;
        if (!is_array($attributes) || !isset($attributes['POSITION'])) fail('glb_position_missing');
        $positionIndex = assertIndex($attributes['POSITION'], count($accessors), 'glb_position_accessor_invalid');
        $position = $accessors[$positionIndex];
        if ((string)$position['type'] !== 'VEC3' || (int)$position['componentType'] !== 5126) fail('glb_position_format_invalid');
        if (!is_array($position['min'] ?? null) || !is_array($position['max'] ?? null) || count($position['min']) !== 3 || count($position['max']) !== 3) fail('glb_position_bounds_missing');
        for ($boundIndex = 0; $boundIndex < 3; $boundIndex++)
        {
            $minValue = $position['min'][$boundIndex];
            $maxValue = $position['max'][$boundIndex];
            if (!is_numeric($minValue) || !is_numeric($maxValue) || !is_finite((float)$minValue) || !is_finite((float)$maxValue) || (float)$minValue > (float)$maxValue) fail('glb_position_bounds_invalid');
        }
        $vertexCount = (int)$position['count'];
        if ($vertexCount < 1) fail('glb_vertex_count_invalid');
        $totalVertices += $vertexCount;
        if ($totalVertices > $limits['vertices']) fail('glb_vertex_limit');
        foreach (['NORMAL','TANGENT','TEXCOORD_0','JOINTS_0','WEIGHTS_0'] as $semantic)
        {
            if (isset($attributes[$semantic]))
            {
                $accessor = $accessors[assertIndex($attributes[$semantic], count($accessors), 'glb_attribute_accessor_invalid')];
                if ((int)$accessor['count'] !== $vertexCount) fail('glb_attribute_count_mismatch');
                $accessorType = (string)$accessor['type'];
                $componentType = (int)$accessor['componentType'];
                $normalized = !empty($accessor['normalized']);
                if ($semantic === 'NORMAL' && ($accessorType !== 'VEC3' || $componentType !== 5126)) fail('glb_normal_format_invalid');
                if ($semantic === 'TANGENT' && ($accessorType !== 'VEC4' || $componentType !== 5126)) fail('glb_tangent_format_invalid');
                if ($semantic === 'TEXCOORD_0' && ($accessorType !== 'VEC2' || !($componentType === 5126 || (($componentType === 5121 || $componentType === 5123) && $normalized)))) fail('glb_texcoord_format_invalid');
                if ($semantic === 'JOINTS_0' && ($accessorType !== 'VEC4' || !in_array($componentType, [5121,5123], true) || $normalized)) fail('glb_joints_format_invalid');
                if ($semantic === 'WEIGHTS_0' && ($accessorType !== 'VEC4' || !($componentType === 5126 || (($componentType === 5121 || $componentType === 5123) && $normalized)))) fail('glb_weights_format_invalid');
            }
        }
        if (isset($attributes['JOINTS_0']) xor isset($attributes['WEIGHTS_0'])) fail('glb_skin_attributes_incomplete');
        $elementCount = $vertexCount;
        if (isset($primitive['indices']))
        {
            $indexAccessor = $accessors[assertIndex($primitive['indices'], count($accessors), 'glb_indices_accessor_invalid')];
            if ((string)$indexAccessor['type'] !== 'SCALAR' || !in_array((int)$indexAccessor['componentType'], [5121,5123,5125], true)) fail('glb_indices_format_invalid');
            $elementCount = (int)$indexAccessor['count'];
        }
        if ($mode === 4 && $elementCount % 3 !== 0) fail('glb_triangle_index_count_invalid');
        if (($mode === 5 || $mode === 6) && $elementCount < 3) fail('glb_triangle_strip_count_invalid');
        $triangles = $mode === 4 ? intdiv($elementCount, 3) : max(0, $elementCount - 2);
        $totalTriangles += $triangles;
        if ($totalTriangles > $limits['triangles']) fail('glb_triangle_limit');
        if (isset($primitive['material'])) assertIndex($primitive['material'], count($materials), 'glb_material_reference_invalid');
    }
}
