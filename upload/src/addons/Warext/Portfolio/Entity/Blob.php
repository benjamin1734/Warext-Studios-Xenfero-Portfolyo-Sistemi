<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Blob extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_blob';
        $structure->shortName = 'Warext\Portfolio:Blob';
        $structure->primaryKey = 'blob_id';
        $structure->columns = [
            'blob_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'sha256' => ['type' => self::STR, 'maxLength' => 64, 'required' => true],
            'asset_type' => ['type' => self::STR, 'allowedValues' => ['image', 'thumbnail', 'model'], 'default' => 'image'],
            'mime' => ['type' => self::STR, 'maxLength' => 100, 'default' => 'application/octet-stream'],
            'extension' => ['type' => self::STR, 'maxLength' => 16, 'default' => 'bin'],
            'file_size' => ['type' => self::UINT, 'default' => 0],
            'storage_name' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'ref_count' => ['type' => self::UINT, 'default' => 0],
            'state' => ['type' => self::STR, 'allowedValues' => ['ready', 'deleting'], 'default' => 'ready'],
            'security_state' => ['type' => self::STR, 'allowedValues' => ['clean', 'pending', 'blocked'], 'default' => 'clean'],
            'blocked_reason' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'last_security_scan_date' => ['type' => self::UINT, 'default' => 0],
            'next_security_scan_date' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'last_ref_date' => ['type' => self::UINT, 'default' => 0],
            'delete_after_date' => ['type' => self::UINT, 'default' => 0],
            'last_verify_date' => ['type' => self::UINT, 'default' => 0]
        ];
        return $structure;
    }
}
