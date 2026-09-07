<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;

class AuditLogger extends AbstractService
{
    public function log(string $action, string $targetType = '', int $targetId = 0, int $portfolioId = 0, int $fileId = 0, string $reasonCode = '', array $details = []): void
    {
        $visitor = \XF::visitor();
        $ip = '';
        try { $ip = (string)$this->app->request()->getIp(); } catch (\Throwable $e) {}
        $salt = (string)$this->app->config('globalSalt');
        $log = $this->em()->create('Warext\\Portfolio:AuditLog');
        $log->actor_user_id = (int)$visitor->user_id;
        $log->actor_username = (string)$visitor->username;
        $log->action = mb_substr($action, 0, 64);
        $log->target_type = mb_substr($targetType, 0, 32);
        $log->target_id = $targetId;
        $log->portfolio_id = $portfolioId;
        $log->file_id = $fileId;
        $log->reason_code = mb_substr($reasonCode, 0, 100);
        $log->details_json = $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $log->ip_hash = $ip !== '' ? hash_hmac('sha256', $ip, $salt) : '';
        $log->created_date = \XF::$time;
        $log->save();
    }
}
