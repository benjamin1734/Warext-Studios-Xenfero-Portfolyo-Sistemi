<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;

class HashBlocklist extends AbstractService
{
    public function isBlocked(string $sha256): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256))
        {
            return false;
        }

        return (bool)$this->db()->fetchOne(
            'SELECT 1 FROM xf_wrxt_portfolio_blocked_hash WHERE sha256 = ? AND is_active = 1 LIMIT 1',
            $sha256
        );
    }

    public function getReason(string $sha256): string
    {
        return (string)$this->db()->fetchOne(
            'SELECT reason_code FROM xf_wrxt_portfolio_blocked_hash WHERE sha256 = ? AND is_active = 1 LIMIT 1',
            $sha256
        );
    }

    public function add(string $sha256, string $reasonCode, string $note = '', int $userId = 0): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256))
        {
            throw new \InvalidArgumentException('Invalid SHA-256 hash');
        }

        $now = \XF::$time;
        $this->db()->query(
            'INSERT INTO xf_wrxt_portfolio_blocked_hash (sha256, reason_code, note, is_active, created_by, created_date, updated_date) VALUES (?, ?, ?, 1, ?, ?, ?) ON DUPLICATE KEY UPDATE reason_code = VALUES(reason_code), note = VALUES(note), is_active = 1, updated_date = VALUES(updated_date)',
            [$sha256, mb_substr($reasonCode, 0, 100), mb_substr($note, 0, 255), $userId, $now, $now]
        );
    }
}
