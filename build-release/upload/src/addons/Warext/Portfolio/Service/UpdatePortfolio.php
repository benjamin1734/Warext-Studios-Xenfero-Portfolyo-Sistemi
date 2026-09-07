<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;

class UpdatePortfolio extends AbstractService
{
    protected Portfolio $portfolio;
    protected string $tags = '';
    protected array $content = [];
    protected array $original = [];

    public function __construct(\XF\App $app, Portfolio $portfolio)
    {
        parent::__construct($app);
        $this->portfolio = $portfolio;
        foreach (['title', 'description', 'category_id', 'portfolio_type', 'programs', 'updated_date'] as $field)
        {
            $this->original[$field] = $portfolio->$field;
        }
    }

    public function setContent(string $title, string $description, int $categoryId, string $type, string $programs = '', string $tags = ''): void
    {
        $this->content = [
            'title' => trim($title),
            'description' => trim($description),
            'category_id' => $categoryId,
            'portfolio_type' => $type,
            'programs' => mb_substr(trim($programs), 0, 255, 'UTF-8')
        ];
        $this->tags = $tags;
    }

    public function validate(?array &$errors = null): bool
    {
        $errors = [];
        if (!$this->portfolio->canEdit())
        {
            $errors[] = \XF::phrase('do_not_have_permission');
            return false;
        }
        if (!$this->content)
        {
            $errors[] = \XF::phrase('wrxt_portfolio_invalid_category');
            return false;
        }

        $category = $this->em()->find('Warext\Portfolio:Category', (int)$this->content['category_id']);
        if (!$category || !$category->is_active)
        {
            $errors[] = \XF::phrase('wrxt_portfolio_invalid_category');
        }
        elseif (!$category->allowsType((string)$this->content['portfolio_type']))
        {
            $errors[] = \XF::phrase('wrxt_portfolio_type_not_allowed');
        }

        foreach ($this->content as $field => $value)
        {
            $this->portfolio->$field = $value;
        }
        $this->portfolio->updated_date = \XF::$time;
        if (!$this->portfolio->preSave())
        {
            foreach ($this->portfolio->getErrors() as $error)
            {
                $errors[] = $error;
            }
        }
        foreach ($this->original as $field => $value)
        {
            $this->portfolio->$field = $value;
        }
        return !$errors;
    }

    public function save(): Portfolio
    {
        if ((string)$this->portfolio->status === 'published')
        {
            $this->service('Warext\Portfolio:PendingRevision')->set($this->portfolio, $this->content, $this->tags);
            $this->service('Warext\Portfolio:AuditLogger')->log('published_revision_submitted', 'portfolio', (int)$this->portfolio->portfolio_id, (int)$this->portfolio->portfolio_id);
            return $this->portfolio;
        }

        $db = $this->db();
        $db->beginTransaction();
        try
        {
            foreach ($this->content as $field => $value)
            {
                $this->portfolio->$field = $value;
            }
            $this->portfolio->updated_date = \XF::$time;
            $this->portfolio->save();
            $this->repository('Warext\Portfolio:Portfolio')->syncTags($this->portfolio, $this->tags);
            $db->commit();
        }
        catch (\Throwable $e)
        {
            $db->rollback();
            throw $e;
        }
        return $this->portfolio;
    }
}
