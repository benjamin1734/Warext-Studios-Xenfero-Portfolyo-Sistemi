<?php

namespace Warext\Portfolio\Pub\Controller;

use XF\ControllerPlugin\EditorPlugin;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View as ViewReply;

class ItemNative extends Item
{
    public function actionIndex(ParameterBag $params)
    {
        $reply = parent::actionIndex($params);
        if (!($reply instanceof ViewReply))
        {
            return $reply;
        }

        $portfolio = $reply->getParam('portfolio');
        if (!$portfolio)
        {
            return $reply;
        }

        $comments = $reply->getParam('comments');
        if ($comments)
        {
            $this->repository('XF:Attachment')->addAttachmentsToContent(
                $comments,
                'wrxt_portfolio_comment',
                'attach_count',
                'Attachments'
            );
        }

        $reply->setParam('attachmentData', $this->getCommentAttachmentData($portfolio));
        return $reply;
    }

    public function actionComment(ParameterBag $params)
    {
        $this->assertPostOnly();
        $portfolio = $this->assertPortfolio($params->portfolio_id);
        if (!$portfolio->canView())
        {
            return $this->noPermission();
        }

        $visitor = \XF::visitor();
        if (!$visitor->user_id
            || !$visitor->hasPermission('wrxtPortfolio', 'comment')
            || (string)$portfolio->status !== 'published')
        {
            return $this->noPermission();
        }

        $message = $this->plugin(EditorPlugin::class)->fromInput('message');
        $attachmentHash = trim((string)$this->filter('attachment_hash', 'str'));

        if ($attachmentHash !== '')
        {
            $this->assertValidCommentAttachmentHash($portfolio, $attachmentHash);
        }

        try
        {
            $comment = $this->service('Warext\Portfolio:Community')->addComment($portfolio, $message);

            if ($attachmentHash !== '')
            {
                $associated = $this->service('XF:Attachment\\Preparer')->associateAttachmentsWithContent(
                    $attachmentHash,
                    'wrxt_portfolio_comment',
                    (int)$comment->comment_id
                );

                if ($associated)
                {
                    $comment->fastUpdate('attach_count', (int)$associated);
                }
            }
        }
        catch (\RuntimeException $e)
        {
            return $this->error(\XF::phrase($e->getMessage()));
        }

        return $this->redirect(
            $this->buildLink('portfolyo/calisma', $portfolio) . '#comment-' . (int)$comment->comment_id,
            'Yorumunuz gönderildi.'
        );
    }

    protected function getCommentAttachmentData($portfolio): ?array
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id
            || !$visitor->hasPermission('wrxtPortfolio', 'comment')
            || (string)$portfolio->status !== 'published'
            || !$portfolio->canView())
        {
            return null;
        }

        return $this->repository('XF:Attachment')->getEditorData(
            'wrxt_portfolio_comment',
            null,
            null,
            ['portfolio_id' => (int)$portfolio->portfolio_id]
        );
    }

    protected function assertValidCommentAttachmentHash($portfolio, string $attachmentHash): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/i', $attachmentHash))
        {
            throw new \RuntimeException('wrxt_portfolio_comment_attachment_invalid');
        }

        $attachmentRepo = $this->repository('XF:Attachment');
        $handler = $attachmentRepo->getAttachmentHandler('wrxt_portfolio_comment');
        if (!$handler || !$handler->canManageAttachments([
            'portfolio_id' => (int)$portfolio->portfolio_id
        ]))
        {
            throw new \RuntimeException('wrxt_portfolio_comment_attachment_not_allowed');
        }

        $attachments = $attachmentRepo->findAttachmentsByTempHash($attachmentHash)
            ->with('Data')
            ->fetch();

        $visitorId = (int)\XF::visitor()->user_id;
        foreach ($attachments as $attachment)
        {
            if ((string)$attachment->content_type !== 'wrxt_portfolio_comment'
                || !$attachment->Data
                || (int)$attachment->Data->user_id !== $visitorId)
            {
                throw new \RuntimeException('wrxt_portfolio_comment_attachment_invalid');
            }
        }
    }
}
