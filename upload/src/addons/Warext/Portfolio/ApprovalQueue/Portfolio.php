<?php

namespace Warext\Portfolio\ApprovalQueue;

use XF\Mvc\Entity\Entity;

class Portfolio extends \XF\ApprovalQueue\AbstractHandler
{
    public function canView(Entity $content, &$error = null)
    {
        $visitor = \XF::visitor();
        return (bool)($visitor->hasPermission('wrxtPortfolio', 'manage') || $visitor->hasPermission('wrxtPortfolio', 'moderate'));
    }

    protected function canActionContent(Entity $content, &$error = null)
    {
        return $this->canView($content, $error);
    }

    public function actionApprove(\Warext\Portfolio\Entity\Portfolio $portfolio)
    {
        \XF::service('Warext\\Portfolio:ModerationManager')->approve($portfolio, 'XenForo Approval Queue');
    }

    public function actionDelete(\Warext\Portfolio\Entity\Portfolio $portfolio)
    {
        \XF::service('Warext\\Portfolio:ModerationManager')->reject($portfolio, 'approval_queue_rejected', 'XenForo Approval Queue');
    }
}
