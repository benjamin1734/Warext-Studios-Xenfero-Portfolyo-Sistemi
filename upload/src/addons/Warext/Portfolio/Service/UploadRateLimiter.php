<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;

class UploadRateLimiter extends AbstractService
{
    public function consume(int $userId, int $fileSize, array $policy): void
    {
        if (!empty($policy['is_unlimited']))
        {
            return;
        }
        $now = \XF::$time;
        $ip = trim((string)$this->app->request()->getIp());
        $salt = (string)$this->app->config('globalSalt');
        $ipHash = ($ip !== '' && $salt !== '') ? hash_hmac('sha256', $ip, $salt) : '';
        $hourStart = (int)(floor($now / 3600) * 3600);
        $dayStart = (int)(floor($now / 86400) * 86400);
        $checks = [
            ['user_hour', (string)$userId, $hourStart, max(1, (int)$policy['hourly_uploads'])],
            ['user_day', (string)$userId, $dayStart, max(1, (int)$policy['daily_uploads'])]
        ];
        if ($ipHash !== '')
        {
            $checks[] = ['ip_hour', $ipHash, $hourStart, max(5, (int)$policy['hourly_uploads'] * 4)];
            $checks[] = ['ip_day', $ipHash, $dayStart, max(20, (int)$policy['daily_uploads'] * 4)];
        }
        $db = $this->db();
        $db->beginTransaction();
        try
        {
            foreach ($checks as [$type, $subject, $windowStart, $limit])
            {
                $rateKey = hash('sha256', $type . '|' . $subject . '|' . $windowStart);
                $db->query('INSERT IGNORE INTO xf_wrxt_portfolio_upload_rate (rate_key, user_id, window_start, upload_count, uploaded_bytes, updated_date) VALUES (?, ?, ?, 0, 0, ?)', [$rateKey, $userId, $windowStart, $now]);
                $row = $db->fetchRow('SELECT upload_count FROM xf_wrxt_portfolio_upload_rate WHERE rate_key = ? FOR UPDATE', $rateKey);
                if ($row && (int)$row['upload_count'] >= $limit)
                {
                    throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_upload_rate_limit'));
                }
                $db->query('UPDATE xf_wrxt_portfolio_upload_rate SET upload_count = upload_count + 1, uploaded_bytes = uploaded_bytes + ?, updated_date = ? WHERE rate_key = ?', [$fileSize, $now, $rateKey]);
            }
            $db->commit();
        }
        catch (\Throwable $e)
        {
            $db->rollback();
            throw $e;
        }
    }
}
