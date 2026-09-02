<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class UploadSession extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_upload_session';
        $structure->shortName = 'Warext\Portfolio:UploadSession';
        $structure->primaryKey = 'session_id';

        $structure->columns = [
            'session_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'session_key' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
            'portfolio_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'ip_hash' => ['type' => self::STR, 'maxLength' => 64, 'default' => ''],
            'state' => ['type' => self::STR, 'allowedValues' => ['open', 'closed', 'expired'], 'default' => 'open'],
            'accepted_count' => ['type' => self::UINT, 'default' => 0],
            'uploaded_bytes' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'last_activity_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'expires_date' => ['type' => self::UINT, 'required' => true]
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
            ]
        ];

        return $structure;
    }
}
