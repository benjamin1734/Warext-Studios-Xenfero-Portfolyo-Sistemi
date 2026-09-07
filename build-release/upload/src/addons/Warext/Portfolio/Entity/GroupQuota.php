<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class GroupQuota extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_group_quota';
        $structure->shortName = 'Warext\Portfolio:GroupQuota';
        $structure->primaryKey = 'user_group_id';

        $structure->columns = [
            'user_group_id' => ['type' => self::UINT, 'required' => true],
            'max_file_bytes' => ['type' => self::UINT, 'default' => 52428800],
            'max_total_bytes' => ['type' => self::UINT, 'default' => 536870912],
            'hourly_uploads' => ['type' => self::UINT, 'default' => 10],
            'daily_uploads' => ['type' => self::UINT, 'default' => 30],
            'max_files_per_portfolio' => ['type' => self::UINT, 'default' => 15],
            'allow_model3d' => ['type' => self::BOOL, 'default' => true],
            'is_unlimited' => ['type' => self::BOOL, 'default' => false]
        ];

        $structure->relations = [
            'UserGroup' => [
                'entity' => 'XF:UserGroup',
                'type' => self::TO_ONE,
                'conditions' => 'user_group_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
