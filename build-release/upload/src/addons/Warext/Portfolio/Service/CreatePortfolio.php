<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;

class CreatePortfolio extends AbstractService
{
    protected Portfolio $portfolio;
    protected string $tags = '';

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        $this->portfolio = $this->em()->create('Warext\Portfolio:Portfolio');
        $visitor = \XF::visitor();
        $this->portfolio->portfolio_key = bin2hex(random_bytes(16));
        $this->portfolio->user_id = $visitor->user_id;
        $this->portfolio->username = $visitor->username;
        $this->portfolio->created_date = \XF::$time;
    }

    public function setContent(string $title, string $description, int $categoryId, string $type, string $programs = '', string $tags = ''): void
    {
        $this->portfolio->title = trim($title);
        $this->portfolio->description = trim($description);
        $this->portfolio->category_id = $categoryId;
        $this->portfolio->portfolio_type = $type;
        $this->portfolio->programs = mb_substr(trim($programs), 0, 255, 'UTF-8');
        $this->tags = $tags;
    }

    public function validate(?array &$errors = null): bool
    {
        $errors = [];
        if (!\XF::visitor()->hasPermission('wrxtPortfolio', 'create'))
        {
            $errors[] = \XF::phrase('do_not_have_permission');
        }

        $category = $this->em()->find('Warext\Portfolio:Category', $this->portfolio->category_id);
        if (!$category || !$category->is_active)
        {
            $errors[] = \XF::phrase('wrxt_portfolio_invalid_category');
        }
        elseif (!$category->allowsType($this->portfolio->portfolio_type))
        {
            $errors[] = \XF::phrase('wrxt_portfolio_type_not_allowed');
        }

        if (!$this->portfolio->preSave())
        {
            foreach ($this->portfolio->getErrors() as $error)
            {
                $errors[] = $error;
            }
        }

        return !$errors;
    }

    public function save(): Portfolio
    {
        $db = $this->db();
        $db->beginTransaction();
        try
        {
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
