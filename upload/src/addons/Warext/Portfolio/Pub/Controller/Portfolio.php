<?php
namespace Warext\Portfolio\Pub\Controller;
use XF\Pub\Controller\AbstractController;
class Portfolio extends AbstractController
{
    public function actionIndex()
    {
        if (!\XF::visitor()->hasPermission('wrxtPortfolio','view')) return $this->noPermission();
        $page=$this->filterPage(); $perPage=24; $repo=$this->repository('Warext\Portfolio:Portfolio');
        $filters=$this->filter(['category_id'=>'uint','portfolio_type'=>'str','sort'=>'str']);
        $finder=$repo->applyPublishedFilters($repo->findPublished(),$filters); $total=$finder->total();
        $this->assertValidPage($page,$perPage,$total,'portfolyo'); $items=$finder->limitByPage($page,$perPage)->fetch();
        return $this->view('Warext\Portfolio:Portfolio\List','wrxt_portfolio_list',['items'=>$items,'categories'=>$repo->getActiveCategories(),'filters'=>$filters,'page'=>$page,'perPage'=>$perPage,'total'=>$total]);
    }
    public function actionSaved()
    {
        $visitor=\XF::visitor(); if(!$visitor->user_id||!$visitor->hasPermission('wrxtPortfolio','save')) return $this->noPermission();
        $page=$this->filterPage(); $perPage=24; $finder=$this->repository('Warext\Portfolio:Portfolio')->findSavedForUser($visitor->user_id); $total=$finder->total(); $items=$finder->limitByPage($page,$perPage)->fetch();
        return $this->view('Warext\Portfolio:Portfolio\Saved','wrxt_portfolio_saved',['items'=>$items,'page'=>$page,'perPage'=>$perPage,'total'=>$total]);
    }
    public function actionMine()
    {
        $visitor=\XF::visitor(); if(!$visitor->user_id||!$visitor->hasPermission('wrxtPortfolio','view')) return $this->noPermission();
        $page=$this->filterPage(); $perPage=20; $finder=$this->repository('Warext\Portfolio:Portfolio')->findForUser($visitor->user_id); $total=$finder->total(); $items=$finder->limitByPage($page,$perPage)->fetch();
        return $this->view('Warext\Portfolio:Portfolio\Mine','wrxt_portfolio_mine',['items'=>$items,'page'=>$page,'perPage'=>$perPage,'total'=>$total]);
    }
    public function actionAdd()
    {
        if(!\XF::visitor()->user_id||!\XF::visitor()->hasPermission('wrxtPortfolio','create')) return $this->noPermission();
        return $this->view('Warext\Portfolio:Portfolio\Add','wrxt_portfolio_add',['categories'=>$this->repository('Warext\Portfolio:Portfolio')->getActiveCategories()]);
    }
    public function actionSave()
    {
        $this->assertPostOnly(); if(!\XF::visitor()->user_id||!\XF::visitor()->hasPermission('wrxtPortfolio','create')) return $this->noPermission();
        $input=$this->filter(['title'=>'str','description'=>'str','category_id'=>'uint','portfolio_type'=>'str','programs'=>'str','tags'=>'str']);
        $service=$this->service('Warext\Portfolio:CreatePortfolio'); $service->setContent($input['title'],$input['description'],$input['category_id'],$input['portfolio_type'],$input['programs'],$input['tags']);
        if(!$service->validate($errors)) return $this->error($errors); $portfolio=$service->save(); return $this->redirect($this->buildLink('portfolyo/calisma',$portfolio));
    }
}
