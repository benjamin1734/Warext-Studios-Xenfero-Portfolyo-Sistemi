<?php

namespace Warext\Portfolio\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Portfolio extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_wrxt_portfolio';
        $structure->shortName = 'Warext\Portfolio:Portfolio';
        $structure->primaryKey = 'portfolio_id';
        $structure->contentType = 'wrxt_portfolio';

        $structure->columns = [
            'portfolio_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'portfolio_key' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'category_id' => ['type' => self::UINT, 'default' => 0],
            'title' => ['type' => self::STR, 'maxLength' => 150, 'required' => 'please_enter_valid_title'],
            'description' => ['type' => self::STR, 'required' => true],
            'programs' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'tags_cache' => ['type' => self::STR, 'maxLength' => 500, 'default' => ''],
            'tag_count' => ['type' => self::UINT, 'default' => 0],
            'portfolio_type' => ['type' => self::STR, 'allowedValues' => ['image', 'model3d'], 'default' => 'image'],
            'status' => ['type' => self::STR, 'allowedValues' => ['draft', 'awaiting_files', 'security_review', 'moderation', 'published', 'rejected', 'deleted'], 'default' => 'draft'],
            'security_status' => ['type' => self::STR, 'allowedValues' => ['none', 'pending', 'passed', 'blocked'], 'default' => 'none'],
            'pending_moderation' => ['type' => self::BOOL, 'default' => false],
            'pending_revision_json' => ['type' => self::STR, 'nullable' => true, 'default' => null],
            'pending_revision_date' => ['type' => self::UINT, 'default' => 0],
            'cover_file_id' => ['type' => self::UINT, 'default' => 0],
            'model_file_id' => ['type' => self::UINT, 'default' => 0],
            'gallery_count' => ['type' => self::UINT, 'default' => 0],
            'view_count' => ['type' => self::UINT, 'default' => 0],
            'like_count' => ['type' => self::UINT, 'default' => 0],
            'save_count' => ['type' => self::UINT, 'default' => 0],
            'comment_count' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'updated_date' => ['type' => self::UINT, 'default' => 0],
            'published_date' => ['type' => self::UINT, 'default' => 0],
            'deleted_date' => ['type' => self::UINT, 'default' => 0]
        ];

        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Category' => [
                'entity' => 'Warext\Portfolio:Category',
                'type' => self::TO_ONE,
                'conditions' => 'category_id',
                'primary' => true
            ],
            'CoverFile' => [
                'entity' => 'Warext\Portfolio:PortfolioFile',
                'type' => self::TO_ONE,
                'conditions' => [['file_id', '=', '$cover_file_id']]
            ],
            'ModelFile' => [
                'entity' => 'Warext\Portfolio:PortfolioFile',
                'type' => self::TO_ONE,
                'conditions' => [['file_id', '=', '$model_file_id']]
            ],
            'Files' => [
                'entity' => 'Warext\Portfolio:PortfolioFile',
                'type' => self::TO_MANY,
                'conditions' => 'portfolio_id',
                'order' => ['display_order', 'file_id']
            ],
            'TagLinks' => [
                'entity' => 'Warext\Portfolio:PortfolioTag',
                'type' => self::TO_MANY,
                'conditions' => 'portfolio_id',
                'order' => 'display_order'
            ],
            'ApprovalQueue' => [
                'entity' => 'XF:ApprovalQueue',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['content_type', '=', 'wrxt_portfolio'],
                    ['content_id', '=', '$portfolio_id']
                ],
                'primary' => true
            ]
        ];

        return $structure;
    }

    public function canView(?string &$error = null): bool
    {
        if (!\XF::visitor()->hasPermission('wrxtPortfolio', 'view'))
        {
            return false;
        }

        if ($this->status === 'published')
        {
            return true;
        }

        $visitor = \XF::visitor();
        return $visitor->user_id && ($visitor->user_id === $this->user_id || $visitor->hasPermission('wrxtPortfolio', 'manage'));
    }

    public function canEdit(?string &$error = null): bool
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || $this->status === 'deleted')
        {
            return false;
        }

        if ($visitor->hasPermission('wrxtPortfolio', 'manage'))
        {
            return true;
        }

        return $visitor->user_id === $this->user_id && $visitor->hasPermission('wrxtPortfolio', 'editOwn');
    }

    public function canDelete(?string &$error = null): bool
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || $this->status === 'deleted')
        {
            return false;
        }

        if ($visitor->hasPermission('wrxtPortfolio', 'manage'))
        {
            return true;
        }

        return $visitor->user_id === $this->user_id && $visitor->hasPermission('wrxtPortfolio', 'deleteOwn');
    }

    public function canApproveUnapprove(?string &$error = null): bool
    {
        return (bool)(\XF::visitor()->hasPermission('wrxtPortfolio', 'manage') || \XF::visitor()->hasPermission('wrxtPortfolio', 'moderate'));
    }

    public function canReport(?string &$error = null): bool
    {
        $visitor = \XF::visitor();
        return (bool)($visitor->user_id && $visitor->hasPermission('wrxtPortfolio', 'report') && $this->canView($error));
    }

    public function getPendingRevision(): array
    {
        if (!$this->pending_revision_json) { return []; }
        try { $data = json_decode((string)$this->pending_revision_json, true, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { return []; }
        return is_array($data) ? $data : [];
    }

    public function getModerationTitle(): string
    {
        $pending = $this->getPendingRevision();
        return (string)($pending['title'] ?? $this->title);
    }

    public function getModerationDescription(): string
    {
        $pending = $this->getPendingRevision();
        return (string)($pending['description'] ?? $this->description);
    }

    public function getTagList(): array
    {
        if (!$this->tags_cache)
        {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->tags_cache))));
    }
}
