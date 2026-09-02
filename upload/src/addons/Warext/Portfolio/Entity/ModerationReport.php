<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ModerationReport extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_moderation_report';
        $structure->shortName = 'Warext\\Portfolio:ModerationReport';
        $structure->primaryKey = 'report_id';
        $structure->columns = [
            'report_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'portfolio_id' => ['type' => self::UINT, 'required' => true],
            'file_id' => ['type' => self::UINT, 'default' => 0],
            'reporter_user_id' => ['type' => self::UINT, 'required' => true],
            'reporter_username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'reason_code' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
            'message' => ['type' => self::STR, 'default' => ''],
            'state' => ['type' => self::STR, 'allowedValues' => ['open', 'reviewing', 'resolved', 'rejected'], 'default' => 'open'],
            'security_rescan_requested' => ['type' => self::BOOL, 'default' => false],
            'assigned_user_id' => ['type' => self::UINT, 'default' => 0],
            'resolution_note' => ['type' => self::STR, 'default' => ''],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'updated_date' => ['type' => self::UINT, 'default' => 0],
            'resolved_date' => ['type' => self::UINT, 'default' => 0]
        ];
        $structure->relations = [
            'Portfolio' => ['entity' => 'Warext\\Portfolio:Portfolio', 'type' => self::TO_ONE, 'conditions' => 'portfolio_id', 'primary' => true],
            'File' => ['entity' => 'Warext\\Portfolio:PortfolioFile', 'type' => self::TO_ONE, 'conditions' => [['file_id', '=', '$file_id']]],
            'Reporter' => ['entity' => 'XF:User', 'type' => self::TO_ONE, 'conditions' => [['user_id', '=', '$reporter_user_id']]],
            'Assignee' => ['entity' => 'XF:User', 'type' => self::TO_ONE, 'conditions' => [['user_id', '=', '$assigned_user_id']]]
        ];
        return $structure;
    }
}
