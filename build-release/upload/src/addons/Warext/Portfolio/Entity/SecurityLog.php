<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class SecurityLog extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_security_log';
        $structure->shortName = 'Warext\Portfolio:SecurityLog';
        $structure->primaryKey = 'log_id';

        $structure->columns = [
            'log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'portfolio_id' => ['type' => self::UINT, 'default' => 0],
            'file_id' => ['type' => self::UINT, 'default' => 0],
            'user_id' => ['type' => self::UINT, 'default' => 0],
            'event' => ['type' => self::STR, 'maxLength' => 64, 'required' => true],
            'severity' => ['type' => self::STR, 'allowedValues' => ['info', 'warning', 'critical'], 'default' => 'info'],
            'reason_code' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'details_json' => ['type' => self::STR, 'nullable' => true, 'default' => null],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];

        return $structure;
    }
}
