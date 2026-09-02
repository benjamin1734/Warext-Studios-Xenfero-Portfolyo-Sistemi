<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class PortfolioTag extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_tag_map';
        $structure->shortName = 'Warext\Portfolio:PortfolioTag';
        $structure->primaryKey = ['portfolio_id', 'tag_id'];
        $structure->columns = [
            'portfolio_id' => ['type' => self::UINT, 'required' => true],
            'tag_id' => ['type' => self::UINT, 'required' => true],
            'display_order' => ['type' => self::UINT, 'default' => 0]
        ];
        $structure->relations = [
            'Portfolio' => [
                'entity' => 'Warext\Portfolio:Portfolio',
                'type' => self::TO_ONE,
                'conditions' => 'portfolio_id',
                'primary' => true
            ],
            'Tag' => [
                'entity' => 'Warext\Portfolio:Tag',
                'type' => self::TO_ONE,
                'conditions' => 'tag_id',
                'primary' => true
            ]
        ];
        return $structure;
    }
}
