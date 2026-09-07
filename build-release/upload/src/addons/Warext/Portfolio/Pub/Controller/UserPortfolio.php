<?php
namespace Warext\Portfolio\Pub\Controller;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
class UserPortfolio extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        if(!\XF::visitor()->hasPermission('wrxtPortfolio','view')) return $this->noPermission();
        $user=$this->assertRecordExists('XF:User',$params->user_id,[],'requested_user_not_found'); $page=$this->filterPage(); $perPage=24;
        $finder=$this->repository('Warext\Portfolio:Portfolio')->findPublishedForUser($user->user_id); $total=$finder->total(); $items=$finder->limitByPage($page,$perPage)->fetch();
        $community=$this->service('Warext\Portfolio:Community'); $stats=$community->followStats((int)$user->user_id);
        return $this->view('Warext\Portfolio:User\Portfolio','wrxt_portfolio_user',['user'=>$user,'items'=>$items,'page'=>$page,'perPage'=>$perPage,'total'=>$total,'isFollowing'=>$community->isFollowing((int)$user->user_id),'followStats'=>$stats]);
    }
    public function actionFollow(ParameterBag $params)
    {
        $this->assertPostOnly(); $user=$this->assertRecordExists('XF:User',$params->user_id,[],'requested_user_not_found');
        try{$this->service('Warext\Portfolio:Community')->toggleFollow($user);}catch(\RuntimeException $e){return $this->error(\XF::phrase($e->getMessage()));}
        return $this->redirect($this->buildLink('portfolyo/kullanici',$user));
    }
}
