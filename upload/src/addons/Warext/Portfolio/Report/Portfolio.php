<?php

namespace Warext\Portfolio\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;

class Portfolio extends \XF\Report\AbstractHandler
{
    protected function canViewContent(Report $report) { return (bool)\XF::visitor()->hasPermission('wrxtPortfolio', 'manage'); }
    protected function canActionContent(Report $report) { return (bool)\XF::visitor()->hasPermission('wrxtPortfolio', 'manage'); }
    public function setupReportEntityContent(Report $report, Entity $content)
    {
        $report->content_user_id = (int)$content->user_id;
        $report->content_info = ['title' => (string)$content->title, 'message' => (string)$content->description, 'user_id' => (int)$content->user_id, 'username' => (string)$content->username];
    }
    public function getContentTitle(Report $report) { return 'Portfolyo: ' . (string)($report->content_info['title'] ?? $report->content_id); }
    public function getContentMessage(Report $report) { return (string)($report->content_info['message'] ?? ''); }
    public function getContentLink(Report $report) { return \XF::app()->router('public')->buildLink('portfolyo/calisma', ['portfolio_id' => (int)$report->content_id, 'title' => (string)($report->content_info['title'] ?? '')]); }
    public function getEntityWith() { return ['User', 'Category']; }
}
