<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Tag extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_tag';
        $structure->shortName = 'Warext\Portfolio:Tag';
        $structure->primaryKey = 'tag_id';
        $structure->columns = [
            'tag_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'tag' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
            'tag_normalized' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
            'use_count' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];
        return $structure;
    }
}
