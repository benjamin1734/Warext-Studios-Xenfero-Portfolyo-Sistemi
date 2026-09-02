<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;
use Warext\Portfolio\Entity\UploadSession;

class UploadSessionManager extends AbstractService
{
    public function getOrCreate(Portfolio $portfolio): UploadSession
    {
        $visitor = \XF::visitor();
        $session = $this->finder('Warext\Portfolio:UploadSession')
            ->where('portfolio_id', $portfolio->portfolio_id)
            ->where('user_id', $visitor->user_id)
            ->where('state', 'open')
            ->where('expires_date', '>', \XF::$time)
            ->order('session_id', 'DESC')
            ->fetchOne();
        if ($session)
        {
            return $session;
        }
        $ip = trim((string)$this->app->request()->getIp());
        $salt = (string)$this->app->config('globalSalt');
        $session = $this->em()->create('Warext\Portfolio:UploadSession');
        $session->session_key = bin2hex(random_bytes(16));
        $session->portfolio_id = $portfolio->portfolio_id;
        $session->user_id = $visitor->user_id;
        $session->ip_hash = ($ip !== '' && $salt !== '') ? hash_hmac('sha256', $ip, $salt) : '';
        $session->created_date = \XF::$time;
        $session->last_activity_date = \XF::$time;
        $session->expires_date = \XF::$time + max(900, (int)$this->app->options()->wrxtPortfolioUploadSessionMinutes * 60);
        $session->save();
        return $session;
    }

    public function recordAccepted(UploadSession $session, int $bytes): void
    {
        $session->accepted_count++;
        $session->uploaded_bytes += $bytes;
        $session->last_activity_date = \XF::$time;
        $session->expires_date = \XF::$time + max(900, (int)$this->app->options()->wrxtPortfolioUploadSessionMinutes * 60);
        $session->save();
    }
}
