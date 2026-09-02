<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;

class Community extends AbstractService
{
    public function visitorState(Portfolio $portfolio): array
    {
        $userId = (int)\XF::visitor()->user_id;
        if (!$userId)
        {
            return ['liked' => false, 'saved' => false];
        }
        $db = $this->db();
        return [
            'liked' => (bool)$db->fetchOne('SELECT 1 FROM xf_wrxt_portfolio_like WHERE portfolio_id = ? AND user_id = ?', [$portfolio->portfolio_id, $userId]),
            'saved' => (bool)$db->fetchOne('SELECT 1 FROM xf_wrxt_portfolio_save WHERE portfolio_id = ? AND user_id = ?', [$portfolio->portfolio_id, $userId])
        ];
    }

    public function toggleLike(Portfolio $portfolio): bool
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'like') || $portfolio->status !== 'published')
        {
            throw new \RuntimeException('wrxt_portfolio_like_not_allowed');
        }
        $db = $this->db();
        $db->beginTransaction();
        try
        {
            $exists = (bool)$db->fetchOne('SELECT 1 FROM xf_wrxt_portfolio_like WHERE portfolio_id = ? AND user_id = ? FOR UPDATE', [$portfolio->portfolio_id, $visitor->user_id]);
            if ($exists)
            {
                $db->delete('xf_wrxt_portfolio_like', 'portfolio_id = ? AND user_id = ?', [$portfolio->portfolio_id, $visitor->user_id]);
            }
            else
            {
                $db->query('INSERT IGNORE INTO xf_wrxt_portfolio_like (portfolio_id, user_id, like_date) VALUES (?, ?, ?)', [$portfolio->portfolio_id, $visitor->user_id, \XF::$time]);
            }
            $count = (int)$db->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_like WHERE portfolio_id = ?', $portfolio->portfolio_id);
            $db->update('xf_wrxt_portfolio', ['like_count' => $count], 'portfolio_id = ?', $portfolio->portfolio_id);
            $db->commit();
            $portfolio->like_count = $count;
            if (!$exists)
            {
                $this->service('Warext\Portfolio:CommunityNotifier')->notifyLike($portfolio, $visitor);
            }
            return !$exists;
        }
        catch (\Throwable $e)
        {
            $db->rollback();
            throw $e;
        }
    }

    public function toggleSave(Portfolio $portfolio): bool
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'save') || $portfolio->status !== 'published')
        {
            throw new \RuntimeException('wrxt_portfolio_save_not_allowed');
        }
        $db = $this->db();
        $db->beginTransaction();
        try
        {
            $exists = (bool)$db->fetchOne('SELECT 1 FROM xf_wrxt_portfolio_save WHERE portfolio_id = ? AND user_id = ? FOR UPDATE', [$portfolio->portfolio_id, $visitor->user_id]);
            if ($exists)
            {
                $db->delete('xf_wrxt_portfolio_save', 'portfolio_id = ? AND user_id = ?', [$portfolio->portfolio_id, $visitor->user_id]);
            }
            else
            {
                $db->query('INSERT IGNORE INTO xf_wrxt_portfolio_save (portfolio_id, user_id, save_date) VALUES (?, ?, ?)', [$portfolio->portfolio_id, $visitor->user_id, \XF::$time]);
            }
            $count = (int)$db->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_save WHERE portfolio_id = ?', $portfolio->portfolio_id);
            $db->update('xf_wrxt_portfolio', ['save_count' => $count], 'portfolio_id = ?', $portfolio->portfolio_id);
            $db->commit();
            $portfolio->save_count = $count;
            return !$exists;
        }
        catch (\Throwable $e)
        {
            $db->rollback();
            throw $e;
        }
    }

    public function toggleFollow($targetUser): bool
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'follow') || (int)$targetUser->user_id === (int)$visitor->user_id)
        {
            throw new \RuntimeException('wrxt_portfolio_follow_not_allowed');
        }
        $db = $this->db();
        $exists = (bool)$db->fetchOne('SELECT 1 FROM xf_wrxt_portfolio_follow WHERE follower_user_id = ? AND followed_user_id = ?', [$visitor->user_id, $targetUser->user_id]);
        if ($exists)
        {
            $db->delete('xf_wrxt_portfolio_follow', 'follower_user_id = ? AND followed_user_id = ?', [$visitor->user_id, $targetUser->user_id]);
            return false;
        }
        try
        {
            $db->query('INSERT IGNORE INTO xf_wrxt_portfolio_follow (follower_user_id, followed_user_id, follow_date) VALUES (?, ?, ?)', [$visitor->user_id, $targetUser->user_id, \XF::$time]);
        }
        catch (\Throwable $e)
        {
            if (!(bool)$db->fetchOne('SELECT 1 FROM xf_wrxt_portfolio_follow WHERE follower_user_id = ? AND followed_user_id = ?', [$visitor->user_id, $targetUser->user_id]))
            {
                throw $e;
            }
        }
        $this->service('Warext\Portfolio:CommunityNotifier')->notifyFollow($targetUser, $visitor);
        return true;
    }

    public function addComment(Portfolio $portfolio, string $message)
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'comment') || $portfolio->status !== 'published')
        {
            throw new \RuntimeException('wrxt_portfolio_comment_not_allowed');
        }
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim($message)) ?? '';
        $message = mb_substr($message, 0, 1000, 'UTF-8');
        if ($message === '')
        {
            throw new \RuntimeException('wrxt_portfolio_comment_empty');
        }
        $last = (int)$this->db()->fetchOne('SELECT MAX(created_date) FROM xf_wrxt_portfolio_comment WHERE user_id = ?', $visitor->user_id);
        if ($last && $last > \XF::$time - 15)
        {
            throw new \RuntimeException('wrxt_portfolio_comment_flood');
        }
        $daily = (int)$this->db()->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_comment WHERE user_id = ? AND created_date >= ?', [$visitor->user_id, \XF::$time - 86400]);
        if ($daily >= 100)
        {
            throw new \RuntimeException('wrxt_portfolio_comment_daily_limit');
        }
        $comment = $this->em()->create('Warext\Portfolio:Comment');
        $comment->portfolio_id = $portfolio->portfolio_id;
        $comment->user_id = $visitor->user_id;
        $comment->username = $visitor->username;
        $comment->message = $message;
        $comment->state = 'visible';
        $comment->created_date = \XF::$time;
        $comment->save();
        $count = (int)$this->db()->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_comment WHERE portfolio_id = ? AND state = 'visible'", $portfolio->portfolio_id);
        $this->db()->update('xf_wrxt_portfolio', ['comment_count' => $count], 'portfolio_id = ?', $portfolio->portfolio_id);
        $portfolio->comment_count = $count;
        $this->service('Warext\Portfolio:CommunityNotifier')->notifyComment($portfolio, $visitor, $comment);
        return $comment;
    }

    public function deleteComment($comment): void
    {
        if (!$comment->canDelete())
        {
            throw new \RuntimeException('wrxt_portfolio_comment_delete_not_allowed');
        }
        if ($comment->state === 'deleted') return;
        $comment->state = 'deleted';
        $comment->deleted_date = \XF::$time;
        $comment->save();
        $count = (int)$this->db()->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_comment WHERE portfolio_id = ? AND state = 'visible'", $comment->portfolio_id);
        $this->db()->update('xf_wrxt_portfolio', ['comment_count' => $count], 'portfolio_id = ?', $comment->portfolio_id);
    }

    public function recordView(Portfolio $portfolio): void
    {
        if ($portfolio->status !== 'published') return;
        $request = $this->app->request();
        $visitor = \XF::visitor();
        $identity = $visitor->user_id ? 'u:' . (int)$visitor->user_id : 'i:' . (string)$request->getIp();
        $bucket = intdiv(\XF::$time, 21600);
        $key = hash_hmac('sha256', $portfolio->portfolio_id . '|' . $identity . '|' . $bucket, (string)$this->app->config('globalSalt'));
        $result = $this->db()->query(
            'INSERT IGNORE INTO xf_wrxt_portfolio_view (view_key, portfolio_id, user_id, view_date) VALUES (?, ?, ?, ?)',
            [$key, $portfolio->portfolio_id, (int)$visitor->user_id, \XF::$time]
        );
        if (!$result->rowsAffected()) { return; }
        $this->db()->query('UPDATE xf_wrxt_portfolio SET view_count = view_count + 1 WHERE portfolio_id = ?', $portfolio->portfolio_id);
        $portfolio->view_count = (int)$portfolio->view_count + 1;
    }

    public function isFollowing(int $targetUserId): bool
    {
        $visitorId = (int)\XF::visitor()->user_id;
        return $visitorId && (bool)$this->db()->fetchOne('SELECT 1 FROM xf_wrxt_portfolio_follow WHERE follower_user_id = ? AND followed_user_id = ?', [$visitorId, $targetUserId]);
    }

    public function followStats(int $targetUserId): array
    {
        return [
            'followers' => (int)$this->db()->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_follow WHERE followed_user_id = ?', $targetUserId),
            'following' => (int)$this->db()->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_follow WHERE follower_user_id = ?', $targetUserId)
        ];
    }
}
