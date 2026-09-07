<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;

class CommunityNotifier extends AbstractService
{
    public function notifyLike($portfolio, $actor): void { $this->alertPortfolioOwner($portfolio, $actor, 'wrxt_portfolio_like'); }
    public function notifyComment($portfolio, $actor, $comment): void
    {
        $this->alertPortfolioOwner($portfolio, $actor, 'wrxt_portfolio_comment', ['comment_id' => (int)$comment->comment_id, 'comment_preview' => mb_substr((string)$comment->message, 0, 120, 'UTF-8')]);
    }
    public function notifyFollow($target, $actor): void
    {
        if ((int)$target->user_id === (int)$actor->user_id) return;
        $this->send($target, $actor, 'wrxt_portfolio_follow', ['profile_url' => $this->app->router('public')->buildLink('canonical:portfolyo/kullanici', $target)]);
    }
    public function notifyNewPortfolioRecipient($portfolio, $recipient): void
    {
        $author = $portfolio->User ?: $this->em()->find('XF:User', (int)$portfolio->user_id);
        if (!$author || (int)$recipient->user_id === (int)$author->user_id) return;
        $this->send($recipient, $author, 'wrxt_portfolio_new', [
            'portfolio_id' => (int)$portfolio->portfolio_id,
            'portfolio_title' => (string)$portfolio->title,
            'portfolio_url' => $this->app->router('public')->buildLink('canonical:portfolyo/calisma', $portfolio)
        ]);
    }
    private function alertPortfolioOwner($portfolio, $actor, string $action, array $extra = []): void
    {
        if ((int)$portfolio->user_id === (int)$actor->user_id) return;
        $owner = $portfolio->User ?: $this->em()->find('XF:User', (int)$portfolio->user_id);
        if (!$owner) return;
        $extra += ['portfolio_id'=>(int)$portfolio->portfolio_id,'portfolio_title'=>(string)$portfolio->title,'portfolio_url'=>$this->app->router('public')->buildLink('canonical:portfolyo/calisma',$portfolio)];
        $this->send($owner, $actor, $action, $extra);
    }
    private function send($recipient, $actor, string $action, array $extra): void
    {
        try
        {
            $this->repository('XF:UserAlert')->alertFromUser($recipient, $actor, 'user', (int)$recipient->user_id, $action, $extra);
        }
        catch (\Throwable $e)
        {
            \XF::logException($e, false, 'Warext Portfolio alert: ');
        }
    }
}
