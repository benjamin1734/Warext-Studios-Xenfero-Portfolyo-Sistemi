<?php

namespace Warext\Portfolio\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Portfolio extends AbstractController
{
    public function actionIndex()
    {
        $this->assertAdminPermission('wrxtPortfolioManage');
        $finder = $this->finder('Warext\Portfolio:Portfolio')
            ->with(['User', 'Category', 'CoverFile'])
            ->order('created_date', 'DESC');
        $page = $this->filterPage();
        $perPage = 50;
        $total = $finder->total();

        return $this->view('Warext\Portfolio:Portfolio\List', 'wrxt_portfolio_admin_list', [
            'items' => $finder->limitByPage($page, $perPage)->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionModeration()
    {
        $this->assertAdminPermission('wrxtPortfolioModerate');

        $page = $this->filterPage();
        $perPage = 30;
        $offset = ($page - 1) * $perPage;
        $db = $this->app->db();

        $where = "(status = 'moderation' OR (status = 'published' AND pending_moderation = 1)) AND security_status = 'passed'";
        $total = (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio WHERE {$where}");
        $ids = array_map('intval', $db->fetchAllColumn(
            "SELECT portfolio_id FROM xf_wrxt_portfolio WHERE {$where} ORDER BY updated_date DESC, portfolio_id DESC LIMIT ?, ?",
            [$offset, $perPage]
        ));

        $items = [];
        if ($ids)
        {
            $items = $this->finder('Warext\Portfolio:Portfolio')
                ->where('portfolio_id', $ids)
                ->with(['User', 'Category', 'CoverFile'])
                ->order('updated_date', 'DESC')
                ->fetch();
        }

        return $this->view('Warext\Portfolio:Moderation\Queue', 'wrxt_portfolio_admin_moderation', [
            'items' => $items,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionSecurity()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $db = $this->app->db();

        return $this->view('Warext\Portfolio:Security\Dashboard', 'wrxt_portfolio_security_dashboard', [
            'summary' => [
                'quarantine' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_file WHERE state IN ('quarantine','validating','scanning','processing')"),
                'moderation' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio WHERE (status = 'moderation' OR (status = 'published' AND pending_moderation = 1)) AND security_status = 'passed'"),
                'blocked' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_file WHERE state = 'blocked'"),
                'open_reports' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_moderation_report WHERE state IN ('open','reviewing')"),
                'blocked_hashes' => (int)$db->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_blocked_hash WHERE is_active = 1'),
                'critical_24h' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_security_log WHERE severity = 'critical' AND created_date >= ?", \XF::$time - 86400),
                'clamav_available' => $this->service('Warext\Portfolio:ClamAvScanner')->ping(),
                'clamav_mode' => (string)($this->app->options()->wrxtPfClamMode ?? 'unix'),
                'proc_open_available' => function_exists('proc_open') && function_exists('proc_get_status'),
                'image_engine' => extension_loaded('imagick') && class_exists('Imagick')
                    ? 'Imagick'
                    : ((extension_loaded('gd') && function_exists('imagewebp')) ? 'GD/WebP' : 'Yok')
            ],
            'events' => $this->finder('Warext\Portfolio:SecurityLog')->order('created_date', 'DESC')->limit(20)->fetch()
        ]);
    }

    public function actionReports()
    {
        $this->assertAdminPermission('wrxtPortfolioModerate');
        $state = $this->filter('state', 'str');
        $finder = $this->finder('Warext\Portfolio:ModerationReport')
            ->with(['Portfolio', 'Reporter'])
            ->order('created_date', 'DESC');

        if (in_array($state, ['open', 'reviewing', 'resolved', 'rejected'], true))
        {
            $finder->where('state', $state);
        }

        $page = $this->filterPage();
        $perPage = 50;
        $total = $finder->total();

        return $this->view('Warext\Portfolio:Security\Reports', 'wrxt_portfolio_security_reports', [
            'reports' => $finder->limitByPage($page, $perPage)->fetch(),
            'state' => $state,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionReportUpdate()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('wrxtPortfolioModerate');

        $report = $this->assertRecordExists('Warext\Portfolio:ModerationReport', $this->filter('report_id', 'uint'));
        try
        {
            $this->service('Warext\Portfolio:ModerationManager')->resolveReport(
                $report,
                $this->filter('state', 'str'),
                $this->filter('note', 'str')
            );
        }
        catch (\RuntimeException $e)
        {
            return $this->error($e->getMessage());
        }

        return $this->redirect($this->buildLink('wrxt-portfolyo/reports'));
    }

    public function actionQuarantine()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $finder = $this->finder('Warext\Portfolio:PortfolioFile')
            ->where('state', ['quarantine', 'validating', 'scanning', 'processing'])
            ->with(['User', 'Portfolio'])
            ->order('created_date', 'DESC');

        $page = $this->filterPage();
        $perPage = 50;
        $total = $finder->total();

        return $this->view('Warext\Portfolio:Security\Quarantine', 'wrxt_portfolio_security_quarantine', [
            'files' => $finder->limitByPage($page, $perPage)->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionQuarantineRetry()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $file = $this->assertRecordExists('Warext\Portfolio:PortfolioFile', $this->filter('file_id', 'uint'));
        $state = (string)$file->state;

        if (in_array($state, ['quarantine', 'validating', 'scanning'], true))
        {
            $file->next_scan_date = 0;
            $file->save();
            \XF::app()->jobManager()->enqueueUnique(
                'wrxtPortfolioSecurity_' . (int)$file->file_id,
                'Warext\Portfolio:SecurityScan',
                ['file_id' => (int)$file->file_id],
                false,
                100
            );
        }
        elseif ($state === 'processing')
        {
            $file->next_processing_date = 0;
            $file->save();
            \XF::app()->jobManager()->enqueueUnique(
                'wrxtPortfolioProcess_' . (int)$file->file_id,
                'Warext\Portfolio:ProcessFile',
                ['file_id' => (int)$file->file_id],
                false,
                110
            );
        }
        else
        {
            return $this->error('Bu dosya güvenlik kuyruğunda değil.');
        }

        $this->service('Warext\Portfolio:AuditLogger')->log(
            'quarantine_retry',
            'portfolio_file',
            (int)$file->file_id,
            (int)$file->portfolio_id,
            (int)$file->file_id
        );

        return $this->redirect($this->buildLink('wrxt-portfolyo/quarantine'));
    }

    public function actionQuarantineContinue()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $file = $this->assertRecordExists('Warext\Portfolio:PortfolioFile', $this->filter('file_id', 'uint'));
        if (
            (string)$file->state !== 'scanning'
            || (string)$file->validation_status !== 'passed'
            || (string)$file->scan_status !== 'error'
        )
        {
            return $this->error('Yalnızca dosya yapısı doğrulanmış ve ClamAV erişim hatasında bekleyen dosyalarda bu işlem kullanılabilir.');
        }

        $reason = (string)$file->reason_code;
        $bypassableReasons = [
            'clamav_unavailable',
            'clamav_timeout',
            'clamav_empty_reply',
            'clamav_scan_error',
            'clamav_unknown_reply',
            'clamav_write_failed',
            'clamav_remote_tcp_forbidden',
            'clamav_socket_invalid'
        ];
        if (!in_array($reason, $bypassableReasons, true))
        {
            return $this->error('Bu güvenlik hatası yalnızca ClamAV servis/bağlantı problemi olmadığı için atlanamaz.');
        }

        $file->scan_status = 'skipped';
        $file->scan_signature = '';
        $file->next_scan_date = 0;
        $file->reason_code = '';
        $file->save();

        $stateMachine = new \Warext\Portfolio\Service\StateMachine();
        $stateMachine->logFileEvent($file, 'staff_scan_bypass', 'warning', 'clamav_unavailable_fallback', [
            'actor_user_id' => (int)\XF::visitor()->user_id,
            'previous_reason' => $reason
        ]);
        $stateMachine->transitionFile($file, 'processing', 'staff_clamav_bypass');

        \XF::app()->jobManager()->enqueueUnique(
            'wrxtPortfolioProcess_' . (int)$file->file_id,
            'Warext\Portfolio:ProcessFile',
            ['file_id' => (int)$file->file_id],
            false,
            110
        );

        return $this->redirect($this->buildLink('wrxt-portfolyo/quarantine'));
    }

    public function actionBlocked()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $finder = $this->finder('Warext\Portfolio:PortfolioFile')
            ->where('state', 'blocked')
            ->with(['User', 'Portfolio', 'ProcessedBlob'])
            ->order('checked_date', 'DESC');

        $page = $this->filterPage();
        $perPage = 50;
        $total = $finder->total();

        return $this->view('Warext\Portfolio:Security\Blocked', 'wrxt_portfolio_security_blocked', [
            'files' => $finder->limitByPage($page, $perPage)->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionEvents()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $severity = $this->filter('severity', 'str');

        $finder = $this->finder('Warext\Portfolio:SecurityLog')->order('created_date', 'DESC');
        if (in_array($severity, ['info', 'warning', 'critical'], true))
        {
            $finder->where('severity', $severity);
        }

        $page = $this->filterPage();
        $perPage = 100;
        $total = $finder->total();

        return $this->view('Warext\Portfolio:Security\Events', 'wrxt_portfolio_security_events', [
            'events' => $finder->limitByPage($page, $perPage)->fetch(),
            'severity' => $severity,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionAudit()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $finder = $this->finder('Warext\Portfolio:AuditLog')->order('created_date', 'DESC');
        $page = $this->filterPage();
        $perPage = 100;
        $total = $finder->total();

        return $this->view('Warext\Portfolio:Security\Audit', 'wrxt_portfolio_security_audit', [
            'logs' => $finder->limitByPage($page, $perPage)->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionHashes()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $page = $this->filterPage();
        $perPage = 100;
        $offset = ($page - 1) * $perPage;
        $db = $this->app->db();

        $total = (int)$db->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_blocked_hash');
        $rows = $db->fetchAll(
            'SELECT * FROM xf_wrxt_portfolio_blocked_hash ORDER BY is_active DESC, updated_date DESC, created_date DESC LIMIT ?, ?',
            [$offset, $perPage]
        );

        return $this->view('Warext\Portfolio:Security\Hashes', 'wrxt_portfolio_security_hashes', [
            'rows' => $rows,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ]);
    }

    public function actionHashSave()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $sha = strtolower(trim($this->filter('sha256', 'str')));
        $reason = $this->filter('reason_code', 'str');
        $note = $this->filter('note', 'str');

        try
        {
            $this->service('Warext\Portfolio:HashBlocklist')->add(
                $sha,
                $reason ?: 'manual_block',
                $note,
                (int)\XF::visitor()->user_id
            );
        }
        catch (\InvalidArgumentException $e)
        {
            return $this->error($e->getMessage());
        }

        $this->service('Warext\Portfolio:AuditLogger')->log(
            'hash_block_added',
            'hash',
            0,
            0,
            0,
            $reason,
            ['sha256' => $sha]
        );

        return $this->redirect($this->buildLink('wrxt-portfolyo/hashes'));
    }

    public function actionHashToggle()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $sha = strtolower(trim($this->filter('sha256', 'str')));
        if (!preg_match('/^[a-f0-9]{64}$/', $sha))
        {
            return $this->error('Invalid SHA-256');
        }

        $current = (int)$this->app->db()->fetchOne(
            'SELECT is_active FROM xf_wrxt_portfolio_blocked_hash WHERE sha256 = ?',
            $sha
        );

        $this->app->db()->update(
            'xf_wrxt_portfolio_blocked_hash',
            ['is_active' => $current ? 0 : 1, 'updated_date' => \XF::$time],
            'sha256 = ?',
            $sha
        );

        $this->service('Warext\Portfolio:AuditLogger')->log(
            $current ? 'hash_block_disabled' : 'hash_block_enabled',
            'hash',
            0,
            0,
            0,
            '',
            ['sha256' => $sha]
        );

        return $this->redirect($this->buildLink('wrxt-portfolyo/hashes'));
    }

    public function actionRescan()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('wrxtPortfolioSecurity');

        $portfolio = $this->assertRecordExists('Warext\Portfolio:Portfolio', $this->filter('portfolio_id', 'uint'));
        $this->service('Warext\Portfolio:ModerationManager')->enqueuePortfolioRescan($portfolio, 'staff_manual');

        return $this->redirect($this->buildLink('wrxt-portfolyo/security'));
    }

    public function actionApprove()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('wrxtPortfolioModerate');

        $portfolio = $this->assertRecordExists('Warext\Portfolio:Portfolio', $this->filter('portfolio_id', 'uint'));
        try
        {
            $this->service('Warext\Portfolio:ModerationManager')->approve($portfolio, $this->filter('note', 'str'));
        }
        catch (\RuntimeException $e)
        {
            return $this->error($e->getMessage());
        }

        return $this->redirect($this->buildLink('wrxt-portfolyo/moderation'));
    }

    public function actionReject()
    {
        $this->assertAdminPermission('wrxtPortfolioModerate');

        $portfolio = $this->assertRecordExists('Warext\Portfolio:Portfolio', $this->filter('portfolio_id', 'uint'));

        if ($this->isPost())
        {
            try
            {
                $this->service('Warext\Portfolio:ModerationManager')->reject(
                    $portfolio,
                    $this->filter('reason_code', 'str'),
                    $this->filter('note', 'str')
                );
            }
            catch (\RuntimeException $e)
            {
                return $this->error($e->getMessage());
            }

            return $this->redirect($this->buildLink('wrxt-portfolyo/moderation'));
        }

        return $this->view('Warext\Portfolio:Moderation\Reject', 'wrxt_portfolio_admin_reject', [
            'portfolio' => $portfolio
        ]);
    }

    public function actionSettings()
    {
        $this->assertAdminPermission('option');
        $this->repairOptionGroup();

        $group = $this->em()->find('XF:OptionGroup', 'wrxtPortfolioSettings');
        if (!$group)
        {
            return $this->error('Portfolyo ayar grubu oluşturulamadı. Eklenti verilerini yeniden oluşturun.');
        }

        return $this->redirect($this->buildLink('options/groups', $group));
    }

    private function repairOptionGroup(): void
    {
        $db = $this->app->db();
        $db->query(
            'INSERT IGNORE INTO xf_option_group (group_id, display_order, debug_only) VALUES (?, ?, ?)',
            ['wrxtPortfolioSettings', 9650, 0]
        );

        $optionIds = $db->fetchAllColumn(
            "SELECT option_id FROM xf_option WHERE option_id LIKE 'wrxtPf%' ORDER BY option_id"
        );
        $displayOrder = 10;

        foreach ($optionIds as $optionId)
        {
            $db->query(
                'INSERT IGNORE INTO xf_option_group_relation (group_id, option_id, display_order) VALUES (?, ?, ?)',
                ['wrxtPortfolioSettings', (string)$optionId, $displayOrder]
            );
            $displayOrder += 10;
        }
    }
}
