<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class PortfolioFile extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_file';
        $structure->shortName = 'Warext\Portfolio:PortfolioFile';
        $structure->primaryKey = 'file_id';

        $structure->columns = [
            'file_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'file_key' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
            'portfolio_id' => ['type' => self::UINT, 'default' => 0],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'file_role' => ['type' => self::STR, 'allowedValues' => ['cover', 'gallery', 'model', 'source'], 'default' => 'gallery'],
            'display_order' => ['type' => self::UINT, 'default' => 10],
            'original_name' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'extension' => ['type' => self::STR, 'maxLength' => 16, 'default' => ''],
            'declared_mime' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'detected_mime' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'magic_type' => ['type' => self::STR, 'maxLength' => 32, 'default' => ''],
            'validation_details_json' => ['type' => self::STR, 'default' => null, 'nullable' => true],
            'file_size' => ['type' => self::UINT, 'default' => 0],
            'sha256' => ['type' => self::STR, 'maxLength' => 64, 'default' => ''],
            'storage_name' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'state' => ['type' => self::STR, 'allowedValues' => ['uploading', 'quarantine', 'validating', 'scanning', 'processing', 'security_passed', 'moderation', 'published', 'blocked', 'rejected', 'deleted'], 'default' => 'uploading'],
            'scan_status' => ['type' => self::STR, 'allowedValues' => ['pending', 'clean', 'infected', 'error'], 'default' => 'pending'],
            'scan_signature' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'scan_attempts' => ['type' => self::UINT, 'default' => 0],
            'last_scan_date' => ['type' => self::UINT, 'default' => 0],
            'next_scan_date' => ['type' => self::UINT, 'default' => 0],
            'validation_status' => ['type' => self::STR, 'allowedValues' => ['pending', 'passed', 'failed'], 'default' => 'pending'],
            'processing_status' => ['type' => self::STR, 'allowedValues' => ['pending', 'running', 'passed', 'error'], 'default' => 'pending'],
            'processing_attempts' => ['type' => self::UINT, 'default' => 0],
            'last_processing_date' => ['type' => self::UINT, 'default' => 0],
            'next_processing_date' => ['type' => self::UINT, 'default' => 0],
            'processed_storage_name' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'processed_blob_id' => ['type' => self::UINT, 'default' => 0],
            'thumbnail_storage_name' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'thumbnail_blob_id' => ['type' => self::UINT, 'default' => 0],
            'processed_sha256' => ['type' => self::STR, 'maxLength' => 64, 'default' => ''],
            'processed_size' => ['type' => self::UINT, 'default' => 0],
            'processed_mime' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'processed_width' => ['type' => self::UINT, 'default' => 0],
            'processed_height' => ['type' => self::UINT, 'default' => 0],
            'thumbnail_width' => ['type' => self::UINT, 'default' => 0],
            'thumbnail_height' => ['type' => self::UINT, 'default' => 0],
            'model_stats_json' => ['type' => self::STR, 'default' => null, 'nullable' => true],
            'model_vertex_count' => ['type' => self::UINT, 'default' => 0],
            'model_triangle_count' => ['type' => self::UINT, 'default' => 0],
            'model_mesh_count' => ['type' => self::UINT, 'default' => 0],
            'model_node_count' => ['type' => self::UINT, 'default' => 0],
            'model_material_count' => ['type' => self::UINT, 'default' => 0],
            'model_texture_count' => ['type' => self::UINT, 'default' => 0],
            'model_animation_count' => ['type' => self::UINT, 'default' => 0],
            'model_skin_count' => ['type' => self::UINT, 'default' => 0],
            'model_joint_count' => ['type' => self::UINT, 'default' => 0],
            'model_max_texture_dimension' => ['type' => self::UINT, 'default' => 0],
            'reason_code' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'checked_date' => ['type' => self::UINT, 'default' => 0],
            'published_date' => ['type' => self::UINT, 'default' => 0]
        ];

        $structure->relations = [
            'Portfolio' => [
                'entity' => 'Warext\Portfolio:Portfolio',
                'type' => self::TO_ONE,
                'conditions' => 'portfolio_id',
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'ProcessedBlob' => [
                'entity' => 'Warext\Portfolio:Blob',
                'type' => self::TO_ONE,
                'conditions' => [['blob_id', '=', '$processed_blob_id']]
            ],
            'ThumbnailBlob' => [
                'entity' => 'Warext\Portfolio:Blob',
                'type' => self::TO_ONE,
                'conditions' => [['blob_id', '=', '$thumbnail_blob_id']]
            ]
        ];

        return $structure;
    }
}
