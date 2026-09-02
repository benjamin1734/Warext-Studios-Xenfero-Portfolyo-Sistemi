<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class AuditLog extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_audit_log';
        $structure->shortName = 'Warext\\Portfolio:AuditLog';
        $structure->primaryKey = 'audit_id';
        $structure->columns = [
            'audit_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'actor_user_id' => ['type' => self::UINT, 'default' => 0],
            'actor_username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'action' => ['type' => self::STR, 'maxLength' => 64, 'required' => true],
            'target_type' => ['type' => self::STR, 'maxLength' => 32, 'default' => ''],
            'target_id' => ['type' => self::UINT, 'default' => 0],
            'portfolio_id' => ['type' => self::UINT, 'default' => 0],
            'file_id' => ['type' => self::UINT, 'default' => 0],
            'reason_code' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'details_json' => ['type' => self::STR, 'nullable' => true, 'default' => null],
            'ip_hash' => ['type' => self::STR, 'maxLength' => 64, 'default' => ''],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];
        return $structure;
    }
}
