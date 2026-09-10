<?php

namespace Warext\Portfolio\Pub\Controller;

use XF\ControllerPlugin\EditorPlugin;
use XF\Pub\Controller\AbstractController;

class CommentPreview extends AbstractController
{
    public function actionIndex()
    {
        $this->assertPostOnly();

        $portfolioId = $this->filter('portfolio_id', 'uint');
        $portfolio = $this->em()->find('Warext\Portfolio:Portfolio', $portfolioId);
        if (!$portfolio || !$portfolio->canView())
        {
            return $this->notFound();
        }

        $visitor = \XF::visitor();
        if (!$visitor->user_id || !$visitor->hasPermission('wrxtPortfolio', 'comment') || (string)$portfolio->status !== 'published')
        {
            return $this->noPermission();
        }

        $message = $this->plugin(EditorPlugin::class)->fromInput('message');
        if (trim(strip_tags($message)) === '')
        {
            return $this->error(\XF::phrase('please_enter_valid_message'));
        }

        $attachments = [];
        $attachmentHash = trim((string)$this->filter('attachment_hash', 'str'));
        if ($attachmentHash !== '')
        {
            if (!preg_match('/^[a-f0-9]{32}$/i', $attachmentHash))
            {
                return $this->noPermission();
            }

            $attachmentRepo = $this->repository('XF:Attachment');
            $handler = $attachmentRepo->getAttachmentHandler('wrxt_portfolio_comment');
            if (!$handler || !$handler->canManageAttachments(['portfolio_id' => (int)$portfolio->portfolio_id]))
            {
                return $this->noPermission();
            }

            $attachments = $attachmentRepo->findAttachmentsByTempHash($attachmentHash)
                ->with('Data')
                ->fetch();

            foreach ($attachments as $attachment)
            {
                if ((string)$attachment->content_type !== 'wrxt_portfolio_comment'
                    || !$attachment->Data
                    || (int)$attachment->Data->user_id !== (int)$visitor->user_id)
                {
                    return $this->noPermission();
                }
            }
        }

        return $this->plugin('XF:BbCodePreview')->actionPreview(
            $message,
            'wrxt_portfolio_comment',
            $visitor,
            $attachments,
            true
        );
    }
}
