<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;

class PendingRevision extends AbstractService
{
    public function set(Portfolio $portfolio, array $data, string $tags): void
    {
        $payload = [
            'title' => trim((string)($data['title'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'category_id' => (int)($data['category_id'] ?? 0),
            'portfolio_type' => (string)($data['portfolio_type'] ?? 'image'),
            'programs' => mb_substr(trim((string)($data['programs'] ?? '')), 0, 255, 'UTF-8'),
            'tags' => (string)$tags
        ];
        $this->assertPayload($payload);
        $portfolio->pending_revision_json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $portfolio->pending_revision_date = \XF::$time;
        $portfolio->pending_moderation = true;
        $portfolio->updated_date = \XF::$time;
        $portfolio->save();
        (new StateMachine())->syncApprovalQueue($portfolio);
    }

    public function validate(Portfolio $portfolio): void
    {
        if (!$portfolio->pending_revision_json) { return; }
        try { $payload = json_decode((string)$portfolio->pending_revision_json, true, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \RuntimeException('wrxt_portfolio_pending_revision_invalid'); }
        if (!is_array($payload)) { throw new \RuntimeException('wrxt_portfolio_pending_revision_invalid'); }
        $this->assertPayload($payload);
    }

    public function apply(Portfolio $portfolio): bool
    {
        $raw = (string)$portfolio->pending_revision_json;
        if ($raw === '')
        {
            return false;
        }
        try
        {
            $payload = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        }
        catch (\JsonException $e)
        {
            throw new \RuntimeException('wrxt_portfolio_pending_revision_invalid');
        }
        if (!is_array($payload))
        {
            throw new \RuntimeException('wrxt_portfolio_pending_revision_invalid');
        }
        $this->assertPayload($payload);

        $portfolio->title = trim((string)$payload['title']);
        $portfolio->description = trim((string)$payload['description']);
        $portfolio->category_id = (int)$payload['category_id'];
        $portfolio->portfolio_type = (string)$payload['portfolio_type'];
        $portfolio->programs = mb_substr(trim((string)$payload['programs']), 0, 255, 'UTF-8');
        $portfolio->pending_revision_json = null;
        $portfolio->pending_revision_date = 0;
        $portfolio->save();
        $this->repository('Warext\Portfolio:Portfolio')->syncTags($portfolio, (string)($payload['tags'] ?? ''));
        return true;
    }

    public function discard(Portfolio $portfolio): void
    {
        $portfolio->pending_revision_json = null;
        $portfolio->pending_revision_date = 0;
        $portfolio->saveIfChanged();
    }

    private function assertPayload(array $payload): void
    {
        $title = trim((string)($payload['title'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $type = (string)($payload['portfolio_type'] ?? '');
        $categoryId = (int)($payload['category_id'] ?? 0);
        if ($title === '' || mb_strlen($title, 'UTF-8') > 150 || $description === '' || !in_array($type, ['image', 'model3d'], true))
        {
            throw new \RuntimeException('wrxt_portfolio_pending_revision_invalid');
        }
        $category = $this->em()->find('Warext\Portfolio:Category', $categoryId);
        if (!$category || !$category->is_active || !$category->allowsType($type))
        {
            throw new \RuntimeException('wrxt_portfolio_pending_revision_invalid');
        }
    }
}
