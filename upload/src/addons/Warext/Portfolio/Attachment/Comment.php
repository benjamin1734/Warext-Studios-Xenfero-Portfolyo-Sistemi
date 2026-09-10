<?php

namespace Warext\Portfolio\Attachment;

use Warext\Portfolio\Entity\Comment as CommentEntity;
use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class Comment extends AbstractHandler
{
    public function getContainerWith()
    {
        return ['Portfolio', 'User'];
    }

    public function canView(Attachment $attachment, Entity $container, &$error = null)
    {
        return $container instanceof CommentEntity
            && (string)$container->state === 'visible'
            && $container->Portfolio
            && $container->Portfolio->canView();
    }

    public function canManageAttachments(array $context, &$error = null)
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'comment'))
        {
            return false;
        }

        $portfolioId = (int)($context['portfolio_id'] ?? 0);

        if (!empty($context['comment_id']))
        {
            $comment = \XF::em()->find('Warext\Portfolio:Comment', (int)$context['comment_id'], ['Portfolio']);
            if (!$comment || (int)$comment->user_id !== (int)$visitor->user_id)
            {
                return false;
            }
            $portfolioId = (int)$comment->portfolio_id;
        }

        if (!$portfolioId)
        {
            return false;
        }

        $portfolio = \XF::em()->find('Warext\Portfolio:Portfolio', $portfolioId);
        return (bool)(
            $portfolio
            && (string)$portfolio->status === 'published'
            && $portfolio->canView()
        );
    }

    public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
    {
        if ($container instanceof CommentEntity && (int)$container->attach_count > 0)
        {
            $container->fastUpdate('attach_count', max(0, (int)$container->attach_count - 1));
        }
    }

    public function getConstraints(array $context)
    {
        return \XF::repository('XF:Attachment')->getDefaultAttachmentConstraints();
    }

    public function getContainerIdFromContext(array $context)
    {
        return !empty($context['comment_id']) ? (int)$context['comment_id'] : null;
    }

    public function getContainerLink(Entity $container, array $extraParams = [])
    {
        if (!($container instanceof CommentEntity) || !$container->Portfolio)
        {
            return '';
        }

        $url = \XF::app()->router('public')->buildLink('portfolyo/calisma', $container->Portfolio, $extraParams);
        return $url . '#comment-' . (int)$container->comment_id;
    }

    public function getContext(Entity $entity = null, array $extraContext = [])
    {
        if ($entity instanceof CommentEntity)
        {
            if ($entity->comment_id)
            {
                $extraContext['comment_id'] = (int)$entity->comment_id;
            }
            if ($entity->portfolio_id)
            {
                $extraContext['portfolio_id'] = (int)$entity->portfolio_id;
            }
            return $extraContext;
        }

        if ($entity === null && !empty($extraContext['portfolio_id']))
        {
            $extraContext['portfolio_id'] = (int)$extraContext['portfolio_id'];
            return $extraContext;
        }

        throw new \InvalidArgumentException('Entity must be a portfolio comment.');
    }
}
