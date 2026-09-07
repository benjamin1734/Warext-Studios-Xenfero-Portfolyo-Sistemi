<?php

namespace Warext\Portfolio\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Portfolio extends Repository
{
    public function findPublished(): Finder
    {
        return $this->finder('Warext\Portfolio:Portfolio')
            ->where('status', 'published')
            ->where('deleted_date', 0)
            ->with(['User', 'Category', 'CoverFile']);
    }

    public function findPublishedForUser(int $userId): Finder
    {
        return $this->findPublished()->where('user_id', $userId)->order('published_date', 'DESC');
    }

    public function findForUser(int $userId): Finder
    {
        return $this->finder('Warext\Portfolio:Portfolio')
            ->where('user_id', $userId)
            ->where('status', '<>', 'deleted')
            ->with(['Category', 'CoverFile'])
            ->order('created_date', 'DESC');
    }

    public function findDraftsForUser(int $userId): Finder
    {
        return $this->findForUser($userId)->where('status', ['draft', 'awaiting_files', 'security_review', 'moderation', 'rejected']);
    }

    public function getActiveCategories()
    {
        return $this->finder('Warext\Portfolio:Category')
            ->where('is_active', 1)
            ->order('display_order')
            ->fetch();
    }

    public function getGalleryFiles(int $portfolioId, bool $includePending = false)
    {
        return $this->finder('Warext\Portfolio:PortfolioFile')
            ->where('portfolio_id', $portfolioId)
            ->where('file_role', 'gallery')
            ->where('state', $includePending ? ['security_passed', 'moderation', 'published'] : 'published')
            ->where('processing_status', 'passed')
            ->order(['display_order', 'file_id'])
            ->fetch();
    }


    public function getEditableFiles(int $portfolioId)
    {
        return $this->finder('Warext\Portfolio:PortfolioFile')
            ->where('portfolio_id', $portfolioId)
            ->where('state', '<>', 'deleted')
            ->order(['file_role', 'display_order', 'file_id'])
            ->fetch();
    }


    public function applyPublishedFilters(Finder $finder, array $filters): Finder
    {
        if (!empty($filters['category_id'])) { $finder->where('category_id', (int)$filters['category_id']); }
        if (!empty($filters['portfolio_type']) && in_array($filters['portfolio_type'], ['image', 'model3d'], true)) { $finder->where('portfolio_type', $filters['portfolio_type']); }
        switch ((string)($filters['sort'] ?? 'latest'))
        {
            case 'popular': $finder->order('like_count', 'DESC')->order('published_date', 'DESC'); break;
            case 'viewed': $finder->order('view_count', 'DESC')->order('published_date', 'DESC'); break;
            case 'discussed': $finder->order('comment_count', 'DESC')->order('published_date', 'DESC'); break;
            case 'oldest': $finder->order('published_date', 'ASC'); break;
            default: $finder->order('published_date', 'DESC');
        }
        return $finder;
    }

    public function findSavedForUser(int $userId): Finder
    {
        $ids = array_map('intval', $this->db()->fetchAllColumn('SELECT portfolio_id FROM xf_wrxt_portfolio_save WHERE user_id = ? ORDER BY save_date DESC', $userId));
        $finder = $this->findPublished();
        return $ids ? $finder->where('portfolio_id', $ids)->order('published_date', 'DESC') : $finder->where('portfolio_id', 0);
    }

    public function getComments(int $portfolioId, int $limit = 50)
    {
        return $this->finder('Warext\Portfolio:Comment')
            ->where('portfolio_id', $portfolioId)
            ->where('state', 'visible')
            ->with('User')
            ->order('created_date', 'ASC')
            ->limit(max(1, min(100, $limit)))
            ->fetch();
    }

    public function normalizeTags(string $tags): array
    {
        $parts = preg_split('/[,;]+/u', $tags, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = [];
        foreach ($parts as $part)
        {
            $tag = preg_replace('/\s+/u', ' ', trim($part));
            $tag = preg_replace('/[\x00-\x1F\x7F]/u', '', $tag ?? '');
            if ($tag === '')
            {
                continue;
            }
            $tag = mb_substr($tag, 0, 50, 'UTF-8');
            $normalized = mb_strtolower($tag, 'UTF-8');
            if (!isset($result[$normalized]))
            {
                $result[$normalized] = $tag;
            }
            if (count($result) >= 10)
            {
                break;
            }
        }
        return $result;
    }

    public function syncTags(\Warext\Portfolio\Entity\Portfolio $portfolio, string $rawTags): void
    {
        $tags = $this->normalizeTags($rawTags);
        $db = $this->db();
        $existingIds = array_map('intval', $db->fetchAllColumn(
            'SELECT tag_id FROM xf_wrxt_portfolio_tag_map WHERE portfolio_id = ?',
            $portfolio->portfolio_id
        ));
        $newIds = [];
        $order = 0;

        foreach ($tags as $normalized => $display)
        {
            $tagId = (int)$db->fetchOne('SELECT tag_id FROM xf_wrxt_portfolio_tag WHERE tag_normalized = ?', $normalized);
            if (!$tagId)
            {
                $db->query(
                    'INSERT IGNORE INTO xf_wrxt_portfolio_tag (tag, tag_normalized, use_count, created_date) VALUES (?, ?, 0, ?)',
                    [$display, $normalized, \XF::$time]
                );
                $tagId = (int)$db->fetchOne('SELECT tag_id FROM xf_wrxt_portfolio_tag WHERE tag_normalized = ?', $normalized);
            }

            $newIds[] = $tagId;
            $db->query(
                'INSERT INTO xf_wrxt_portfolio_tag_map (portfolio_id, tag_id, display_order) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE display_order = VALUES(display_order)',
                [$portfolio->portfolio_id, $tagId, $order++]
            );
        }

        $removeIds = array_diff($existingIds, $newIds);
        foreach ($removeIds as $tagId)
        {
            $db->query(
                'DELETE FROM xf_wrxt_portfolio_tag_map WHERE portfolio_id = ? AND tag_id = ?',
                [$portfolio->portfolio_id, $tagId]
            );
        }

        $affected = array_unique(array_merge($existingIds, $newIds));
        foreach ($affected as $tagId)
        {
            $count = (int)$db->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_tag_map WHERE tag_id = ?', $tagId);
            if ($count)
            {
                $db->query('UPDATE xf_wrxt_portfolio_tag SET use_count = ? WHERE tag_id = ?', [$count, $tagId]);
            }
            else
            {
                $db->query('DELETE FROM xf_wrxt_portfolio_tag WHERE tag_id = ?', $tagId);
            }
        }

        $portfolio->tags_cache = implode(', ', array_values($tags));
        $portfolio->tag_count = count($tags);
        $portfolio->saveIfChanged();
    }
}
