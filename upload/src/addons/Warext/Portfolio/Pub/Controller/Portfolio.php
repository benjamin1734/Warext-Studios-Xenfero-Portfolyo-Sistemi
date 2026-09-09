<?php

namespace Warext\Portfolio\Pub\Controller;

use XF\ControllerPlugin\EditorPlugin;
use XF\Pub\Controller\AbstractController;

class Portfolio extends AbstractController
{
    public function actionIndex()
    {
        $page = $this->filterPage();
        $perPage = 24;
        $repo = $this->repository('Warext\Portfolio:Portfolio');
        $filters = $this->filter([
            'category_id' => 'uint',
            'portfolio_type' => 'str',
            'sort' => 'str'
        ]);

        $finder = $repo->applyPublishedFilters($repo->findPublished(), $filters);
        $total = $finder->total();
        $this->assertValidPage($page, $perPage, $total, 'portfolyo');
        $items = $finder->limitByPage($page, $perPage)->fetch();

        return $this->view('Warext\Portfolio:Portfolio\List', 'wrxt_portfolio_list', [
            'items' => $items,
            'categories' => $repo->getActiveCategories(),
            'filters' => $filters,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionSaved()
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'save'))
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 24;
        $finder = $this->repository('Warext\Portfolio:Portfolio')->findSavedForUser($visitor->user_id);
        $total = $finder->total();
        $items = $finder->limitByPage($page, $perPage)->fetch();

        return $this->view('Warext\Portfolio:Portfolio\Saved', 'wrxt_portfolio_saved', [
            'items' => $items,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionMine()
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id)
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 20;
        $finder = $this->repository('Warext\Portfolio:Portfolio')->findForUser($visitor->user_id);
        $total = $finder->total();
        $items = $finder->limitByPage($page, $perPage)->fetch();

        return $this->view('Warext\Portfolio:Portfolio\Mine', 'wrxt_portfolio_mine', [
            'items' => $items,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionAdd()
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'create'))
        {
            return $this->noPermission();
        }

        return $this->view('Warext\Portfolio:Portfolio\Add', 'wrxt_portfolio_add', [
            'categories' => $this->repository('Warext\Portfolio:Portfolio')->getActiveCategories(),
            'quota' => $this->service('Warext\Portfolio:QuotaPolicy')->getPolicy($visitor)
        ]);
    }

    public function actionSave()
    {
        $this->assertPostOnly();
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'create'))
        {
            return $this->noPermission();
        }

        $input = $this->filter([
            'title' => 'str',
            'category_id' => 'uint',
            'portfolio_type' => 'str',
            'programs' => 'str',
            'tags' => 'str'
        ]);
        $description = $this->plugin(EditorPlugin::class)->fromInput('description');

        $service = $this->service('Warext\Portfolio:CreatePortfolio');
        $service->setContent(
            $input['title'],
            $description,
            $input['category_id'],
            $input['portfolio_type'],
            $input['programs'],
            $input['tags']
        );

        if (!$service->validate($errors))
        {
            return $this->error($errors);
        }

        $portfolio = $service->save();

        try
        {
            $this->acceptInitialUploads($portfolio);
        }
        catch (\RuntimeException $e)
        {
            return $this->error($e->getMessage());
        }

        return $this->redirect($this->buildLink('portfolyo/calisma', $portfolio));
    }

    protected function acceptInitialUploads($portfolio): void
    {
        $quarantine = $this->service('Warext\Portfolio:Quarantine');

        $cover = $this->request->getFile('cover_file', false, false);
        if ($cover)
        {
            $quarantine->accept($portfolio, $cover, 'cover');
        }

        $gallery = $this->request->getFile('gallery_files', true, false);
        if ($gallery)
        {
            foreach (is_array($gallery) ? $gallery : [$gallery] as $upload)
            {
                if ($upload)
                {
                    $quarantine->accept($portfolio, $upload, 'gallery');
                }
            }
        }

        $model = $this->request->getFile('model_file', false, false);
        if ($model)
        {
            $quarantine->accept($portfolio, $model, 'model');
        }
    }
}
