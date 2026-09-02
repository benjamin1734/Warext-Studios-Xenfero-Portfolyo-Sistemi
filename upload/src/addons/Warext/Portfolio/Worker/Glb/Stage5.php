<?php
$totalAnimationKeyframes = 0;
foreach ($animations as $animation)
{
    if (!is_array($animation)) fail('glb_animation_invalid');
    $samplers = is_array($animation['samplers'] ?? null) ? $animation['samplers'] : [];
    $channels = is_array($animation['channels'] ?? null) ? $animation['channels'] : [];
    if (!$samplers || !$channels) fail('glb_animation_empty');
    foreach ($samplers as $sampler)
    {
        if (!is_array($sampler)) fail('glb_animation_sampler_invalid');
        $input = $accessors[assertIndex($sampler['input'] ?? null, count($accessors), 'glb_animation_input_invalid')];
        $output = $accessors[assertIndex($sampler['output'] ?? null, count($accessors), 'glb_animation_output_invalid')];
        if ((string)$input['type'] !== 'SCALAR' || (int)$input['componentType'] !== 5126) fail('glb_animation_input_format_invalid');
        $interpolation = (string)($sampler['interpolation'] ?? 'LINEAR');
        if (!in_array($interpolation, ['LINEAR','STEP'], true)) fail('glb_animation_interpolation_not_allowed');
        $totalAnimationKeyframes += (int)$input['count'];
        if ($totalAnimationKeyframes > $limits['animationKeyframes']) fail('glb_animation_keyframe_limit');
        if ((int)$output['count'] !== (int)$input['count']) fail('glb_animation_count_mismatch');
    }
    foreach ($channels as $channel)
    {
        if (!is_array($channel)) fail('glb_animation_channel_invalid');
        assertIndex($channel['sampler'] ?? null, count($samplers), 'glb_animation_sampler_reference_invalid');
        $target = $channel['target'] ?? null;
        if (!is_array($target)) fail('glb_animation_target_invalid');
        $targetNode = assertIndex($target['node'] ?? null, count($nodes), 'glb_animation_target_node_invalid');
        if (isset($nodes[$targetNode]['matrix'])) fail('glb_animation_matrix_node_unsupported');
        $targetPath = (string)($target['path'] ?? '');
        if (!in_array($targetPath, ['translation','rotation','scale'], true)) fail('glb_animation_target_path_not_allowed');
        $samplerIndex = assertIndex($channel['sampler'] ?? null, count($samplers), 'glb_animation_sampler_reference_invalid');
        $outputAccessorIndex = assertIndex($samplers[$samplerIndex]['output'] ?? null, count($accessors), 'glb_animation_output_invalid');
        $outputAccessor = $accessors[$outputAccessorIndex];
        $requiredType = $targetPath === 'rotation' ? 'VEC4' : 'VEC3';
        if ((string)$outputAccessor['type'] !== $requiredType || (int)$outputAccessor['componentType'] !== 5126) fail('glb_animation_output_format_invalid');
    }
}

foreach ($materials as $material)
{
    if (!is_array($material)) fail('glb_material_invalid');
    $alphaMode = (string)($material['alphaMode'] ?? 'OPAQUE');
    if (!in_array($alphaMode, ['OPAQUE','MASK'], true)) fail('glb_alpha_blend_not_allowed');
    $extensions = array_keys(is_array($material['extensions'] ?? null) ? $material['extensions'] : []);
    foreach ($extensions as $ext)
    {
        if (!in_array($ext, $allowedExtensions, true)) fail('glb_material_extension_not_allowed');
    }
    $pbr = is_array($material['pbrMetallicRoughness'] ?? null) ? $material['pbrMetallicRoughness'] : [];
    if (isset($pbr['baseColorTexture']['index'])) assertIndex($pbr['baseColorTexture']['index'], count($textures), 'glb_base_color_texture_invalid');
    if (isset($material['normalTexture']) || isset($material['occlusionTexture']) || isset($material['emissiveTexture']))
    {
        fail('glb_material_texture_type_unsupported');
    }
}

fwrite(STDOUT, json_encode([
    'ok' => true,
    'stats' => [
        'vertices' => $totalVertices,
        'triangles' => $totalTriangles,
        'meshes' => count($meshes),
        'primitives' => $totalPrimitives,
        'nodes' => count($nodes),
        'materials' => count($materials),
        'textures' => count($textures),
        'images' => count($images),
        'animations' => count($animations),
        'animation_keyframes' => $totalAnimationKeyframes,
        'skins' => count($skins),
        'joints' => $totalJoints,
        'accessors' => count($accessors),
        'buffer_views' => count($bufferViews),
        'max_texture_dimension' => $maxTextureDimension,
        'texture_bytes' => $totalTextureBytes,
        'extensions' => array_values(array_unique(array_filter(array_map('strval', $json['extensionsUsed'] ?? []))))
    ]
], JSON_UNESCAPED_SLASHES));
