<?php

namespace Warext\Portfolio\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Portfolio extends AbstractController
{
    public function actionIndex()
    {
        $this->assertAdminPermission('wrxtPortfolioManage');
        $finder = $this->finder('Warext\Portfolio:Portfolio')->with(['User', 'Category'])->order('created_date', 'DESC');
        $page = $this->filterPage(); $perPage = 50; $total = $finder->total();
        return $this->view('Warext\Portfolio:Portfolio\\List', 'wrxt_portfolio_admin_list', [
            'items' => $finder->limitByPage($page, $perPage)->fetch(), 'page' => $page, 'perPage' => $perPage, 'total' => $total
        ]);
    }

    public function actionSecurity()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $db = $this->app->db();
        return $this->view('Warext\Portfolio:Security\\Dashboard', 'wrxt_portfolio_security_dashboard', [
            'summary' => [
                'quarantine' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_file WHERE state IN ('quarantine','validating','scanning','processing')"),
                'blocked' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_file WHERE state = 'blocked'"),
                'open_reports' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_moderation_report WHERE state IN ('open','reviewing')"),
                'blocked_hashes' => (int)$db->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_blocked_hash WHERE is_active = 1'),
                'critical_24h' => (int)$db->fetchOne("SELECT COUNT(*) FROM xf_wrxt_portfolio_security_log WHERE severity = 'critical' AND created_date >= ?", \XF::$time - 86400)
            ],
            'events' => $this->finder('Warext\Portfolio:SecurityLog')->order('created_date','DESC')->limit(20)->fetch()
        ]);
    }

    public function actionReports()
    {
        $this->assertAdminPermission('wrxtPortfolioModerate');
        $state = $this->filter('state', 'str');
        $finder = $this->finder('Warext\Portfolio:ModerationReport')->with(['Portfolio','Reporter'])->order('created_date','DESC');
        if (in_array($state, ['open','reviewing','resolved','rejected'], true)) { $finder->where('state', $state); }
        $page=$this->filterPage(); $perPage=50; $total=$finder->total();
        return $this->view('Warext\Portfolio:Security\\Reports','wrxt_portfolio_security_reports',[
            'reports'=>$finder->limitByPage($page,$perPage)->fetch(),'state'=>$state,'page'=>$page,'perPage'=>$perPage,'total'=>$total
        ]);
    }

    public function actionReportUpdate()
    {
        $this->assertPostOnly(); $this->assertAdminPermission('wrxtPortfolioModerate');
        $report = $this->assertRecordExists('Warext\Portfolio:ModerationReport', $this->filter('report_id','uint'));
        try { $this->service('Warext\Portfolio:ModerationManager')->resolveReport($report, $this->filter('state','str'), $this->filter('note','str')); }
        catch (\RuntimeException $e) { return $this->error($e->getMessage()); }
        return $this->redirect($this->buildLink('wrxt-portfolyo/reports'));
    }

    public function actionQuarantine()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $finder=$this->finder('Warext\Portfolio:PortfolioFile')->where('state',['quarantine','validating','scanning','processing'])->with(['User','Portfolio'])->order('created_date','DESC');
        $page=$this->filterPage(); $perPage=50; $total=$finder->total();
        return $this->view('Warext\Portfolio:Security\\Quarantine','wrxt_portfolio_security_quarantine',['files'=>$finder->limitByPage($page,$perPage)->fetch(),'page'=>$page,'perPage'=>$perPage,'total'=>$total]);
    }

    public function actionBlocked()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $finder=$this->finder('Warext\Portfolio:PortfolioFile')->where('state','blocked')->with(['User','Portfolio','ProcessedBlob'])->order('checked_date','DESC');
        $page=$this->filterPage(); $perPage=50; $total=$finder->total();
        return $this->view('Warext\Portfolio:Security\\Blocked','wrxt_portfolio_security_blocked',['files'=>$finder->limitByPage($page,$perPage)->fetch(),'page'=>$page,'perPage'=>$perPage,'total'=>$total]);
    }

    public function actionEvents()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $severity=$this->filter('severity','str');
        $finder=$this->finder('Warext\Portfolio:SecurityLog')->order('created_date','DESC');
        if(in_array($severity,['info','warning','critical'],true)){$finder->where('severity',$severity);}
        $page=$this->filterPage();$perPage=100;$total=$finder->total();
        return $this->view('Warext\Portfolio:Security\\Events','wrxt_portfolio_security_events',['events'=>$finder->limitByPage($page,$perPage)->fetch(),'severity'=>$severity,'page'=>$page,'perPage'=>$perPage,'total'=>$total]);
    }

    public function actionAudit()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $finder=$this->finder('Warext\Portfolio:AuditLog')->order('created_date','DESC');
        $page=$this->filterPage();$perPage=100;$total=$finder->total();
        return $this->view('Warext\Portfolio:Security\\Audit','wrxt_portfolio_security_audit',['logs'=>$finder->limitByPage($page,$perPage)->fetch(),'page'=>$page,'perPage'=>$perPage,'total'=>$total]);
    }

    public function actionHashes()
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $page=$this->filterPage(); $perPage=100; $offset=($page-1)*$perPage;
        $db=$this->app->db();
        $total=(int)$db->fetchOne('SELECT COUNT(*) FROM xf_wrxt_portfolio_blocked_hash');
        $rows=$db->fetchAll('SELECT * FROM xf_wrxt_portfolio_blocked_hash ORDER BY is_active DESC, updated_date DESC, created_date DESC LIMIT ?, ?',[$offset,$perPage]);
        return $this->view('Warext\Portfolio:Security\\Hashes','wrxt_portfolio_security_hashes',['rows'=>$rows,'page'=>$page,'perPage'=>$perPage,'total'=>$total]);
    }

    public function actionHashSave()
    {
        $this->assertPostOnly(); $this->assertAdminPermission('wrxtPortfolioSecurity');
        $sha=strtolower(trim($this->filter('sha256','str'))); $reason=$this->filter('reason_code','str'); $note=$this->filter('note','str');
        try { $this->service('Warext\Portfolio:HashBlocklist')->add($sha, $reason ?: 'manual_block', $note, (int)\XF::visitor()->user_id); }
        catch (\InvalidArgumentException $e) { return $this->error($e->getMessage()); }
        $this->service('Warext\Portfolio:AuditLogger')->log('hash_block_added','hash',0,0,0,$reason,['sha256'=>$sha]);
        return $this->redirect($this->buildLink('wrxt-portfolyo/hashes'));
    }

    public function actionHashToggle()
    {
        $this->assertPostOnly(); $this->assertAdminPermission('wrxtPortfolioSecurity');
        $sha=strtolower(trim($this->filter('sha256','str'))); if(!preg_match('/^[a-f0-9]{64}$/',$sha)){return $this->error('Invalid SHA-256');}
        $current=(int)$this->app->db()->fetchOne('SELECT is_active FROM xf_wrxt_portfolio_blocked_hash WHERE sha256 = ?',$sha);
        $this->app->db()->update('xf_wrxt_portfolio_blocked_hash',['is_active'=>$current?0:1,'updated_date'=>\XF::$time],'sha256 = ?',$sha);
        $this->service('Warext\Portfolio:AuditLogger')->log($current?'hash_block_disabled':'hash_block_enabled','hash',0,0,0,'',['sha256'=>$sha]);
        return $this->redirect($this->buildLink('wrxt-portfolyo/hashes'));
    }

    public function actionRescan()
    {
        $this->assertPostOnly(); $this->assertAdminPermission('wrxtPortfolioSecurity');
        $portfolio=$this->assertRecordExists('Warext\Portfolio:Portfolio',$this->filter('portfolio_id','uint'));
        $count=$this->service('Warext\Portfolio:ModerationManager')->enqueuePortfolioRescan($portfolio,'staff_manual');
        return $this->redirect($this->buildLink('wrxt-portfolyo/security'));
    }

    public function actionApprove()
    {
        $this->assertPostOnly(); $this->assertAdminPermission('wrxtPortfolioModerate');
        $portfolio=$this->assertRecordExists('Warext\Portfolio:Portfolio',$this->filter('portfolio_id','uint'));
        try{$this->service('Warext\Portfolio:ModerationManager')->approve($portfolio,$this->filter('note','str'));}
        catch(\RuntimeException $e){return $this->error($e->getMessage());}
        return $this->redirect($this->buildLink('wrxt-portfolyo'));
    }

    public function actionReject()
    {
        $this->assertPostOnly(); $this->assertAdminPermission('wrxtPortfolioModerate');
        $portfolio=$this->assertRecordExists('Warext\Portfolio:Portfolio',$this->filter('portfolio_id','uint'));
        try{$this->service('Warext\Portfolio:ModerationManager')->reject($portfolio,$this->filter('reason_code','str'),$this->filter('note','str'));}
        catch(\RuntimeException $e){return $this->error($e->getMessage());}
        return $this->redirect($this->buildLink('wrxt-portfolyo'));
    }
}
