<?php
namespace Warext\Portfolio\Setup;
use XF\Db\Schema\Create;
trait AuxSchemaTrait
{
    protected function createBlobTable(): void
    {
            $this->schemaManager()->createTable('xf_wrxt_portfolio_blob', function(Create $table)
            {
                $table->addColumn('blob_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('sha256', 'char', 64);
                $table->addColumn('asset_type', 'varchar', 20)->setDefault('image');
                $table->addColumn('mime', 'varchar', 100)->setDefault('application/octet-stream');
                $table->addColumn('extension', 'varchar', 16)->setDefault('bin');
                $table->addColumn('file_size', 'bigint')->unsigned()->setDefault(0);
                $table->addColumn('storage_name', 'varchar', 255)->setDefault('');
                $table->addColumn('ref_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('state', 'varchar', 20)->setDefault('ready');
                $table->addColumn('security_state', 'varchar', 20)->setDefault('clean');
                $table->addColumn('blocked_reason', 'varchar', 100)->setDefault('');
                $table->addColumn('last_security_scan_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('next_security_scan_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned();
                $table->addColumn('last_ref_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('delete_after_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_verify_date', 'int')->unsigned()->setDefault(0);
                $table->addPrimaryKey('blob_id');
                $table->addUniqueKey('sha256');
                $table->addKey(['ref_count', 'delete_after_date']);
                $table->addKey(['state', 'created_date']);
                $table->addKey('last_verify_date');
                $table->addKey(['security_state', 'next_security_scan_date']);
            });
        }

    protected function createTagTables(): void
    {
            $this->schemaManager()->createTable('xf_wrxt_portfolio_tag', function(Create $table)
            {
                $table->addColumn('tag_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('tag', 'varchar', 50);
                $table->addColumn('tag_normalized', 'varchar', 50);
                $table->addColumn('use_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned();
                $table->addPrimaryKey('tag_id');
                $table->addUniqueKey('tag_normalized');
            });
            $this->schemaManager()->createTable('xf_wrxt_portfolio_tag_map', function(Create $table)
            {
                $table->addColumn('portfolio_id', 'int')->unsigned();
                $table->addColumn('tag_id', 'int')->unsigned();
                $table->addColumn('display_order', 'tinyint')->unsigned()->setDefault(0);
                $table->addPrimaryKey(['portfolio_id', 'tag_id']);
                $table->addKey(['tag_id', 'portfolio_id']);
            });
        }

    protected function createUploadPolicyTables(): void
    {
            $this->schemaManager()->createTable('xf_wrxt_portfolio_upload_session', function(Create $table)
            {
                $table->addColumn('session_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('session_key', 'char', 32);
                $table->addColumn('portfolio_id', 'int')->unsigned();
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('ip_hash', 'char', 64)->setDefault('');
                $table->addColumn('state', 'varchar', 20)->setDefault('open');
                $table->addColumn('accepted_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('uploaded_bytes', 'bigint')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned();
                $table->addColumn('last_activity_date', 'int')->unsigned();
                $table->addColumn('expires_date', 'int')->unsigned();
                $table->addPrimaryKey('session_id');
                $table->addUniqueKey('session_key');
                $table->addKey(['user_id', 'state', 'expires_date']);
            });
            $this->schemaManager()->createTable('xf_wrxt_portfolio_group_quota', function(Create $table)
            {
                $table->addColumn('user_group_id', 'int')->unsigned();
                $table->addColumn('max_file_bytes', 'bigint')->unsigned()->setDefault(52428800);
                $table->addColumn('max_total_bytes', 'bigint')->unsigned()->setDefault(536870912);
                $table->addColumn('hourly_uploads', 'int')->unsigned()->setDefault(10);
                $table->addColumn('daily_uploads', 'int')->unsigned()->setDefault(30);
                $table->addColumn('max_files_per_portfolio', 'int')->unsigned()->setDefault(15);
                $table->addColumn('allow_model3d', 'tinyint')->setDefault(1);
                $table->addColumn('is_unlimited', 'tinyint')->setDefault(0);
                $table->addPrimaryKey('user_group_id');
            });
        }

    protected function createSecurityTables(): void
    {
            $this->schemaManager()->createTable('xf_wrxt_portfolio_blocked_hash', function(Create $table)
            {
                $table->addColumn('sha256', 'char', 64);
                $table->addColumn('reason_code', 'varchar', 100)->setDefault('blocked_hash');
                $table->addColumn('note', 'varchar', 255)->setDefault('');
                $table->addColumn('is_active', 'tinyint')->setDefault(1);
                $table->addColumn('created_by', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned();
                $table->addColumn('updated_date', 'int')->unsigned()->setDefault(0);
                $table->addPrimaryKey('sha256');
                $table->addKey(['is_active', 'updated_date']);
            });
        }

    protected function createCommunityTables(): void
    {
        $this->schemaManager()->createTable('xf_wrxt_portfolio_like', function(Create $table)
        {
            $table->addColumn('portfolio_id','int')->unsigned(); $table->addColumn('user_id','int')->unsigned(); $table->addColumn('like_date','int')->unsigned();
            $table->addPrimaryKey(['portfolio_id','user_id']); $table->addKey(['user_id','like_date']);
        });
        $this->schemaManager()->createTable('xf_wrxt_portfolio_save', function(Create $table)
        {
            $table->addColumn('portfolio_id','int')->unsigned(); $table->addColumn('user_id','int')->unsigned(); $table->addColumn('save_date','int')->unsigned();
            $table->addPrimaryKey(['portfolio_id','user_id']); $table->addKey(['user_id','save_date']);
        });
        $this->schemaManager()->createTable('xf_wrxt_portfolio_follow', function(Create $table)
        {
            $table->addColumn('follower_user_id','int')->unsigned(); $table->addColumn('followed_user_id','int')->unsigned(); $table->addColumn('follow_date','int')->unsigned();
            $table->addPrimaryKey(['follower_user_id','followed_user_id']); $table->addKey(['followed_user_id','follow_date']);
        });
        $this->schemaManager()->createTable('xf_wrxt_portfolio_comment', function(Create $table)
        {
            $table->addColumn('comment_id','int')->unsigned()->autoIncrement(); $table->addColumn('portfolio_id','int')->unsigned(); $table->addColumn('user_id','int')->unsigned();
            $table->addColumn('username','varchar',50)->setDefault(''); $table->addColumn('message','text'); $table->addColumn('state','varchar',16)->setDefault('visible');
            $table->addColumn('created_date','int')->unsigned(); $table->addColumn('updated_date','int')->unsigned()->setDefault(0); $table->addColumn('deleted_date','int')->unsigned()->setDefault(0);
            $table->addPrimaryKey('comment_id'); $table->addKey(['portfolio_id','state','created_date']); $table->addKey(['user_id','created_date']);
        });
        $this->schemaManager()->createTable('xf_wrxt_portfolio_view', function(Create $table)
        {
            $table->addColumn('view_key','char',64); $table->addColumn('portfolio_id','int')->unsigned(); $table->addColumn('user_id','int')->unsigned()->setDefault(0); $table->addColumn('view_date','int')->unsigned();
            $table->addPrimaryKey('view_key'); $table->addKey(['portfolio_id','view_date']); $table->addKey('view_date');
        });
    }
    protected function createModerationTables(): void
    {
        $this->schemaManager()->createTable('xf_wrxt_portfolio_moderation_report', function(Create $table)
        {
            $table->addColumn('report_id', 'int')->unsigned()->autoIncrement();
            $table->addColumn('portfolio_id', 'int')->unsigned();
            $table->addColumn('file_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('reporter_user_id', 'int')->unsigned();
            $table->addColumn('reporter_username', 'varchar', 50)->setDefault('');
            $table->addColumn('reason_code', 'varchar', 32);
            $table->addColumn('message', 'text');
            $table->addColumn('state', 'varchar', 16)->setDefault('open');
            $table->addColumn('security_rescan_requested', 'tinyint')->setDefault(0);
            $table->addColumn('assigned_user_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('resolution_note', 'text');
            $table->addColumn('created_date', 'int')->unsigned();
            $table->addColumn('updated_date', 'int')->unsigned()->setDefault(0);
            $table->addColumn('resolved_date', 'int')->unsigned()->setDefault(0);
            $table->addPrimaryKey('report_id');
            $table->addKey(['state', 'created_date']);
            $table->addKey(['portfolio_id', 'state', 'created_date']);
            $table->addKey(['reporter_user_id', 'created_date']);
        });
        $this->schemaManager()->createTable('xf_wrxt_portfolio_audit_log', function(Create $table)
        {
            $table->addColumn('audit_id', 'int')->unsigned()->autoIncrement();
            $table->addColumn('actor_user_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('actor_username', 'varchar', 50)->setDefault('');
            $table->addColumn('action', 'varchar', 64);
            $table->addColumn('target_type', 'varchar', 32)->setDefault('');
            $table->addColumn('target_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('portfolio_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('file_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('reason_code', 'varchar', 100)->setDefault('');
            $table->addColumn('details_json', 'mediumtext')->nullable();
            $table->addColumn('ip_hash', 'char', 64)->setDefault('');
            $table->addColumn('created_date', 'int')->unsigned();
            $table->addPrimaryKey('audit_id');
            $table->addKey(['action', 'created_date']);
            $table->addKey(['portfolio_id', 'created_date']);
            $table->addKey(['actor_user_id', 'created_date']);
        });
    }

}
