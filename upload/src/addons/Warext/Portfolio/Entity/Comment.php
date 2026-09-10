<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Comment extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_comment';
        $structure->shortName = 'Warext\Portfolio:Comment';
        $structure->contentType = 'wrxt_portfolio_comment';
        $structure->primaryKey = 'comment_id';
        $structure->columns = [
            'comment_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'portfolio_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'message' => ['type' => self::STR, 'required' => true],
            'attach_count' => ['type' => self::UINT, 'max' => 65535, 'forced' => true, 'default' => 0],
            'state' => ['type' => self::STR, 'allowedValues' => ['visible', 'deleted'], 'default' => 'visible'],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'updated_date' => ['type' => self::UINT, 'default' => 0],
            'deleted_date' => ['type' => self::UINT, 'default' => 0]
        ];
        $structure->relations = [
            'Portfolio' => ['entity' => 'Warext\Portfolio:Portfolio', 'type' => self::TO_ONE, 'conditions' => 'portfolio_id', 'primary' => true],
            'User' => ['entity' => 'XF:User', 'type' => self::TO_ONE, 'conditions' => 'user_id', 'primary' => true],
            'Attachments' => [
                'entity' => 'XF:Attachment',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['content_type', '=', 'wrxt_portfolio_comment'],
                    ['content_id', '=', '$comment_id']
                ],
                'with' => 'Data',
                'order' => 'attach_date'
            ]
        ];
        return $structure;
    }

    public function canDelete(): bool
    {
        $visitor = \XF::visitor();
        return (bool)($visitor->user_id && (
            $visitor->hasPermission('wrxtPortfolio', 'manage') ||
            $visitor->hasPermission('wrxtPortfolio', 'moderate') ||
            ($visitor->user_id === $this->user_id && $visitor->hasPermission('wrxtPortfolio', 'deleteOwnComment'))
        ));
    }

    public function canReport(): bool
    {
        $visitor = \XF::visitor();
        return (bool)(
            $this->state === 'visible'
            && $visitor->user_id
            && $visitor->hasPermission('wrxtPortfolio', 'report')
        );
    }
}
