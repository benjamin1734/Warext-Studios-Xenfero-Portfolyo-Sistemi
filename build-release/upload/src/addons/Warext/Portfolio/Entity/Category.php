<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Category extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio_category';
        $structure->shortName = 'Warext\Portfolio:Category';
        $structure->primaryKey = 'category_id';

        $structure->columns = [
            'category_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'parent_category_id' => ['type' => self::UINT, 'default' => 0],
            'title' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
            'description' => ['type' => self::STR, 'nullable' => true, 'default' => null],
            'allowed_types' => ['type' => self::STR, 'maxLength' => 100, 'default' => 'image,model3d'],
            'display_order' => ['type' => self::UINT, 'default' => 10],
            'is_active' => ['type' => self::BOOL, 'default' => true],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];

        $structure->relations = [
            'Parent' => [
                'entity' => 'Warext\Portfolio:Category',
                'type' => self::TO_ONE,
                'conditions' => [['category_id', '=', '$parent_category_id']]
            ]
        ];

        return $structure;
    }

    public function allowsType(string $type): bool
    {
        return in_array($type, array_filter(array_map('trim', explode(',', $this->allowed_types))), true);
    }
}
