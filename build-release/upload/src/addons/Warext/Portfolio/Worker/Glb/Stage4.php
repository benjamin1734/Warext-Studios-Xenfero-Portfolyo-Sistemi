<?php
$totalJoints = 0;
foreach ($skins as $skin)
{
    if (!is_array($skin) || !is_array($skin['joints'] ?? null) || !$skin['joints']) fail('glb_skin_joints_missing');
    $jointCount = count($skin['joints']);
    if ($jointCount > $limits['joints']) fail('glb_skin_joint_limit');
    $totalJoints += $jointCount;
    foreach ($skin['joints'] as $joint) assertIndex($joint, count($nodes), 'glb_joint_node_invalid');
    if (isset($skin['skeleton'])) assertIndex($skin['skeleton'], count($nodes), 'glb_skin_skeleton_invalid');
    if (isset($skin['inverseBindMatrices']))
    {
        $accessor = $accessors[assertIndex($skin['inverseBindMatrices'], count($accessors), 'glb_inverse_bind_accessor_invalid')];
        if ((string)$accessor['type'] !== 'MAT4' || (int)$accessor['componentType'] !== 5126 || (int)$accessor['count'] !== $jointCount) fail('glb_inverse_bind_format_invalid');
    }
}

$parents = array_fill(0, count($nodes), -1);
foreach ($nodes as $nodeIndex => $node)
{
    if (!is_array($node)) fail('glb_node_invalid');
    if (isset($node['weights'])) fail('glb_node_weights_unsupported');
    if (isset($node['mesh'])) assertIndex($node['mesh'], count($meshes), 'glb_node_mesh_invalid');
    if (isset($node['skin'])) assertIndex($node['skin'], count($skins), 'glb_node_skin_invalid');
    if (isset($node['matrix']) && (!is_array($node['matrix']) || count($node['matrix']) !== 16)) fail('glb_node_matrix_invalid');
    foreach (['translation' => 3, 'rotation' => 4, 'scale' => 3] as $field => $requiredCount)
    {
        if (isset($node[$field]) && (!is_array($node[$field]) || count($node[$field]) !== $requiredCount)) fail('glb_node_trs_invalid');
    }
    foreach (($node['children'] ?? []) as $child)
    {
        $child = assertIndex($child, count($nodes), 'glb_node_child_invalid');
        if ($parents[$child] !== -1) fail('glb_node_multiple_parents');
        $parents[$child] = $nodeIndex;
    }
}

$state = array_fill(0, count($nodes), 0);
$visit = function(int $nodeIndex, int $depth) use (&$visit, &$state, $nodes, $limits): void
{
    if ($depth > $limits['depth']) fail('glb_node_depth_limit');
    if ($state[$nodeIndex] === 1) fail('glb_node_cycle');
    if ($state[$nodeIndex] === 2) return;
    $state[$nodeIndex] = 1;
    foreach (($nodes[$nodeIndex]['children'] ?? []) as $child) $visit((int)$child, $depth + 1);
    $state[$nodeIndex] = 2;
};
for ($i = 0; $i < count($nodes); $i++) $visit($i, 1);

foreach ($scenes as $scene)
{
    if (!is_array($scene)) fail('glb_scene_invalid');
    foreach (($scene['nodes'] ?? []) as $node) assertIndex($node, count($nodes), 'glb_scene_node_invalid');
}
if (isset($json['scene'])) assertIndex($json['scene'], count($scenes), 'glb_default_scene_invalid');
