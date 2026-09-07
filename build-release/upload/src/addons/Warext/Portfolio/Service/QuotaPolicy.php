<?php

namespace Warext\Portfolio\Service;

use XF\Entity\User;
use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;

class QuotaPolicy extends AbstractService
{
    public function getPolicy(User $user): array
    {
        $groupIds = [(int)$user->user_group_id];
        $secondary = $user->secondary_group_ids;
        if (is_array($secondary))
        {
            $groupIds = array_merge($groupIds, array_map('intval', $secondary));
        }
        elseif (is_string($secondary) && $secondary !== '')
        {
            $groupIds = array_merge($groupIds, array_map('intval', explode(',', $secondary)));
        }
        $groupIds = array_values(array_unique(array_filter($groupIds)));

        $policy = [
            'max_file_bytes' => 10485760,
            'max_total_bytes' => 104857600,
            'hourly_uploads' => 5,
            'daily_uploads' => 10,
            'max_files_per_portfolio' => 5,
            'allow_model3d' => false,
            'is_unlimited' => false
        ];

        if (!$groupIds)
        {
            return $policy;
        }

        $rows = $this->finder('Warext\Portfolio:GroupQuota')
            ->where('user_group_id', $groupIds)
            ->fetch();

        foreach ($rows as $row)
        {
            $policy['max_file_bytes'] = max($policy['max_file_bytes'], (int)$row->max_file_bytes);
            $policy['max_total_bytes'] = max($policy['max_total_bytes'], (int)$row->max_total_bytes);
            $policy['hourly_uploads'] = max($policy['hourly_uploads'], (int)$row->hourly_uploads);
            $policy['daily_uploads'] = max($policy['daily_uploads'], (int)$row->daily_uploads);
            $policy['max_files_per_portfolio'] = max($policy['max_files_per_portfolio'], (int)$row->max_files_per_portfolio);
            $policy['allow_model3d'] = $policy['allow_model3d'] || (bool)$row->allow_model3d;
            $policy['is_unlimited'] = $policy['is_unlimited'] || (bool)$row->is_unlimited;
        }

        return $policy;
    }

    public function assertCanAccept(Portfolio $portfolio, string $role, int $fileSize): array
    {
        $user = $portfolio->User ?: $this->em()->find('XF:User', (int)$portfolio->user_id);
        if (!$user)
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_upload_failed'));
        }
        $policy = $this->getPolicy($user);

        if ($fileSize <= 0)
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_upload_empty'));
        }

        if ($role === 'model' && (!$policy['allow_model3d'] || $portfolio->portfolio_type !== 'model3d'))
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_model_upload_not_allowed'));
        }

        if (!$policy['is_unlimited'] && $fileSize > $policy['max_file_bytes'])
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_file_too_large'));
        }

        $fileCount = (int)$this->db()->fetchOne(
            "SELECT COUNT(*) FROM xf_wrxt_portfolio_file WHERE portfolio_id = ? AND state <> 'deleted'",
            $portfolio->portfolio_id
        );
        if (!$policy['is_unlimited'] && $fileCount >= $policy['max_files_per_portfolio'])
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_file_count_limit'));
        }

        $usedBytes = (int)$this->db()->fetchOne(
            "SELECT COALESCE(SUM(file_size), 0) FROM xf_wrxt_portfolio_file WHERE user_id = ? AND state <> 'deleted'",
            $user->user_id
        );
        if (!$policy['is_unlimited'] && ($usedBytes + $fileSize) > $policy['max_total_bytes'])
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_storage_quota_exceeded'));
        }

        return $policy;
    }
}
