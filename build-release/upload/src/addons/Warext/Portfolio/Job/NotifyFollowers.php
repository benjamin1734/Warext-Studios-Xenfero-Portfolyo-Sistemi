<?php

namespace Warext\Portfolio\Job;

use XF\Job\AbstractJob;

class NotifyFollowers extends AbstractJob
{
    protected $defaultData = ['portfolio_id' => 0, 'position' => 0];
    public function run($maxRunTime)
    {
        $portfolioId = (int)($this->data['portfolio_id'] ?? 0);
        if (!$portfolioId) return $this->complete();
        $portfolio = $this->app->em()->find('Warext\Portfolio:Portfolio', $portfolioId, ['User']);
        if (!$portfolio || (string)$portfolio->status !== 'published') return $this->complete();
        $started = microtime(true); $position = (int)($this->data['position'] ?? 0);
        do
        {
            $ids = $this->app->db()->fetchAllColumn('SELECT follower_user_id FROM xf_wrxt_portfolio_follow WHERE followed_user_id = ? AND follower_user_id > ? ORDER BY follower_user_id LIMIT 100', [(int)$portfolio->user_id, $position]);
            if (!$ids) return $this->complete();
            $users = \XF::finder('XF:User')->where('user_id', array_map('intval', $ids))->fetch();
            foreach ($ids as $id)
            {
                $position = max($position, (int)$id);
                $recipient = $users[(int)$id] ?? null;
                if ($recipient) $this->app->service('Warext\Portfolio:CommunityNotifier')->notifyNewPortfolioRecipient($portfolio, $recipient);
            }
            $this->data['position'] = $position;
        }
        while ((microtime(true) - $started) < max(0.1, (float)$maxRunTime));
        return $this->resume();
    }
    public function getStatusMessage(){ return 'Portfolyo takipçilerine yeni çalışma bildirimi gönderiliyor'; }
    public function canCancel(){ return true; }
    public function canTriggerByChoice(){ return false; }
}
