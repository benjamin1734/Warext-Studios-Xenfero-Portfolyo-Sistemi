<?php
namespace Warext\Portfolio\Cron;
class CommunityMaintenance
{
    public static function run(): void
    {
        $db = \XF::db();
        $db->delete('xf_wrxt_portfolio_view', 'view_date < ?', [\XF::$time - 2592000]);
        $rows = $db->fetchAll("SELECT portfolio_id FROM xf_wrxt_portfolio WHERE status <> 'deleted' ORDER BY portfolio_id LIMIT 2000");
        foreach ($rows as $row)
        {
            $id=(int)$row['portfolio_id'];
            $db->query("UPDATE xf_wrxt_portfolio SET like_count=(SELECT COUNT(*) FROM xf_wrxt_portfolio_like WHERE portfolio_id=?), save_count=(SELECT COUNT(*) FROM xf_wrxt_portfolio_save WHERE portfolio_id=?), comment_count=(SELECT COUNT(*) FROM xf_wrxt_portfolio_comment WHERE portfolio_id=? AND state='visible') WHERE portfolio_id=?", [$id,$id,$id,$id]);
        }
    }
}
