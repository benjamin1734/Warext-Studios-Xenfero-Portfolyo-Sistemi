<?php

namespace Warext\Portfolio\Setup;

trait DefaultsTrait
{
    public function postInstall(array &$stateChanges): void
    {
        $this->insertDefaults();
    }

    public function postUpgrade($previousVersion, array &$stateChanges): void
    {
        $this->insertDefaultGroupQuotas();
        $this->applyDefaultPermissions();
    }

    protected function insertDefaults(): void
    {
        $db = $this->app->db();
        if (!$db->fetchOne('SELECT category_id FROM xf_wrxt_portfolio_category LIMIT 1'))
        {
            foreach ([['Grafik Tasarım','image'],['3D Tasarım','image,model3d'],['Minecraft','image,model3d'],['Web Tasarım','image'],['Diğer','image,model3d']] as $i => $category)
            {
                $db->insert('xf_wrxt_portfolio_category', [
                    'title' => $category[0],
                    'allowed_types' => $category[1],
                    'display_order' => ($i + 1) * 10,
                    'is_active' => 1,
                    'created_date' => \XF::$time
                ]);
            }
        }
        $this->insertDefaultGroupQuotas();
        $this->applyDefaultPermissions();
    }

    protected function insertDefaultGroupQuotas(): void
    {
        $db = $this->app->db();
        foreach ([2=>[52428800,536870912,10,30,15,1,0],3=>[209715200,21474836480,100,500,100,1,1],4=>[104857600,5368709120,50,200,50,1,0]] as $groupId => $values)
        {
            $db->query('INSERT IGNORE INTO xf_wrxt_portfolio_group_quota (user_group_id,max_file_bytes,max_total_bytes,hourly_uploads,daily_uploads,max_files_per_portfolio,allow_model3d,is_unlimited) VALUES (?,?,?,?,?,?,?,?)', [$groupId, ...$values]);
        }
    }

    protected function applyDefaultPermissions(): void
    {
        $map = [
            1 => ['view'],
            2 => ['view','create','editOwn','deleteOwn','like','comment','save','follow','deleteOwnComment','report'],
            3 => ['view','create','editOwn','deleteOwn','like','comment','save','follow','deleteOwnComment','report','manage'],
            4 => ['view','moderate','report']
        ];
        foreach ($map as $groupId => $permissionIds)
        {
            $group = \XF::em()->find('XF:UserGroup', $groupId);
            if (!$group) { continue; }
            $existing = \XF::repository('XF:PermissionEntry')->getGlobalUserGroupPermissionEntries($groupId);
            $configured = $existing['wrxtPortfolio'] ?? [];
            $values = [];
            foreach ($permissionIds as $id)
            {
                if (!array_key_exists($id, $configured)) { $values[$id] = 'allow'; }
            }
            if ($values)
            {
                $service = \XF::service('XF:UpdatePermissions');
                $service->setUserGroup($group);
                $service->setGlobal();
                $service->updatePermissions(['wrxtPortfolio' => $values]);
            }
        }
    }

    public function uninstallStep1(): void
    {
        $this->cleanupPortfolioStorage();
        try { $this->app->db()->delete('xf_approval_queue', 'content_type = ?', 'wrxt_portfolio'); } catch (\Throwable $e) {}
        try { $this->app->db()->delete('xf_report', 'content_type = ?', 'wrxt_portfolio'); } catch (\Throwable $e) {}
        foreach (['xf_wrxt_portfolio_audit_log','xf_wrxt_portfolio_moderation_report','xf_wrxt_portfolio_view','xf_wrxt_portfolio_comment','xf_wrxt_portfolio_follow','xf_wrxt_portfolio_save','xf_wrxt_portfolio_like','xf_wrxt_portfolio_blob','xf_wrxt_portfolio_blocked_hash','xf_wrxt_portfolio_upload_session','xf_wrxt_portfolio_group_quota','xf_wrxt_portfolio_tag_map','xf_wrxt_portfolio_tag','xf_wrxt_portfolio_upload_rate','xf_wrxt_portfolio_security_log','xf_wrxt_portfolio_file','xf_wrxt_portfolio','xf_wrxt_portfolio_category'] as $table)
        {
            $this->schemaManager()->dropTable($table);
        }
    }

    private function cleanupPortfolioStorage(): void
    {
        $base = \XF::getRootDirectory() . '/internal_data/wrxt_portfolio';
        if (!is_dir($base) || is_link($base))
        {
            return;
        }
        try
        {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item)
            {
                $path = $item->getPathname();
                if ($item->isLink() || $item->isFile()) { @unlink($path); }
                elseif ($item->isDir()) { @rmdir($path); }
            }
            @rmdir($base);
        }
        catch (\Throwable $e) {}
    }
}
