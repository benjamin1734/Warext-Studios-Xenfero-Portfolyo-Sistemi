<?php
namespace Warext\Portfolio\Setup;
use XF\Db\Schema\Alter;
trait UpgradeTrait
{
    public function upgrade1000020Step1(): void
    {
            $this->schemaManager()->alterTable('xf_wrxt_portfolio', function(Alter $table)
            {
                $table->addColumn('programs', 'varchar', 255)->setDefault('')->after('description');
                $table->addColumn('tags_cache', 'varchar', 500)->setDefault('')->after('programs');
                $table->addColumn('tag_count', 'tinyint')->unsigned()->setDefault(0)->after('tags_cache');
            });
            $this->schemaManager()->alterTable('xf_wrxt_portfolio_file', function(Alter $table)
            {
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(10)->after('file_role');
            });
            $this->createTagTables();
        }

    public function upgrade1000030Step1(): void
    {
            $this->createUploadPolicyTables();
            $this->insertDefaultGroupQuotas();
        }

    public function upgrade1000040Step1(): void
    {
            $this->schemaManager()->alterTable('xf_wrxt_portfolio_file', function(Alter $table)
            {
                $table->addColumn('magic_type', 'varchar', 32)->setDefault('')->after('detected_mime');
                $table->addColumn('validation_details_json', 'mediumtext')->nullable()->after('magic_type');
                $table->addColumn('scan_signature', 'varchar', 255)->setDefault('')->after('scan_status');
                $table->addColumn('scan_attempts', 'int')->unsigned()->setDefault(0)->after('scan_signature');
                $table->addColumn('last_scan_date', 'int')->unsigned()->setDefault(0)->after('scan_attempts');
                $table->addColumn('next_scan_date', 'int')->unsigned()->setDefault(0)->after('last_scan_date');
                $table->addKey(['state', 'next_scan_date']);
            });
            $this->createSecurityTables();
        }

    public function upgrade1000050Step1(): void
    {
            $this->schemaManager()->alterTable('xf_wrxt_portfolio_file', function(Alter $table)
            {
                $table->addColumn('processing_status', 'varchar', 32)->setDefault('pending')->after('validation_status');
                $table->addColumn('processing_attempts', 'int')->unsigned()->setDefault(0)->after('processing_status');
                $table->addColumn('last_processing_date', 'int')->unsigned()->setDefault(0)->after('processing_attempts');
                $table->addColumn('next_processing_date', 'int')->unsigned()->setDefault(0)->after('last_processing_date');
                $table->addColumn('processed_storage_name', 'varchar', 255)->setDefault('')->after('next_processing_date');
                $table->addColumn('thumbnail_storage_name', 'varchar', 255)->setDefault('')->after('processed_storage_name');
                $table->addColumn('processed_sha256', 'char', 64)->setDefault('')->after('thumbnail_storage_name');
                $table->addColumn('processed_size', 'bigint')->unsigned()->setDefault(0)->after('processed_sha256');
                $table->addColumn('processed_mime', 'varchar', 100)->setDefault('')->after('processed_size');
                $table->addColumn('processed_width', 'int')->unsigned()->setDefault(0)->after('processed_mime');
                $table->addColumn('processed_height', 'int')->unsigned()->setDefault(0)->after('processed_width');
                $table->addColumn('thumbnail_width', 'int')->unsigned()->setDefault(0)->after('processed_height');
                $table->addColumn('thumbnail_height', 'int')->unsigned()->setDefault(0)->after('thumbnail_width');
                $table->addKey(['state', 'next_processing_date']);
                $table->addKey('processed_sha256');
            });
        }

    public function upgrade1000060Step1(): void
    {
            $this->schemaManager()->alterTable('xf_wrxt_portfolio_file', function(Alter $table)
            {
                $table->addColumn('model_stats_json', 'mediumtext')->nullable()->after('thumbnail_height');
                $table->addColumn('model_vertex_count', 'int')->unsigned()->setDefault(0)->after('model_stats_json');
                $table->addColumn('model_triangle_count', 'int')->unsigned()->setDefault(0)->after('model_vertex_count');
                $table->addColumn('model_mesh_count', 'int')->unsigned()->setDefault(0)->after('model_triangle_count');
                $table->addColumn('model_node_count', 'int')->unsigned()->setDefault(0)->after('model_mesh_count');
                $table->addColumn('model_material_count', 'int')->unsigned()->setDefault(0)->after('model_node_count');
                $table->addColumn('model_texture_count', 'int')->unsigned()->setDefault(0)->after('model_material_count');
                $table->addColumn('model_animation_count', 'int')->unsigned()->setDefault(0)->after('model_texture_count');
                $table->addColumn('model_skin_count', 'int')->unsigned()->setDefault(0)->after('model_animation_count');
                $table->addColumn('model_joint_count', 'int')->unsigned()->setDefault(0)->after('model_skin_count');
                $table->addColumn('model_max_texture_dimension', 'int')->unsigned()->setDefault(0)->after('model_joint_count');
            });
        }

    public function upgrade1000070Step1(): void
    {
            $this->schemaManager()->alterTable('xf_wrxt_portfolio_file', function(Alter $table)
            {
                $table->addColumn('processed_blob_id', 'int')->unsigned()->setDefault(0)->after('processed_storage_name');
                $table->addColumn('thumbnail_blob_id', 'int')->unsigned()->setDefault(0)->after('thumbnail_storage_name');
                $table->addKey('processed_blob_id');
                $table->addKey('thumbnail_blob_id');
            });
            $this->createBlobTable();
        }


    public function upgrade1000080Step1(): void
    {
        $this->schemaManager()->alterTable('xf_wrxt_portfolio', function(Alter $table)
        {
            $table->addColumn('save_count', 'int')->unsigned()->setDefault(0)->after('like_count');
            $table->addKey(['status', 'like_count']);
            $table->addKey(['status', 'view_count']);
        });
        $this->createCommunityTables();
    }
    public function upgrade1000090Step1(): void
    {
        $this->schemaManager()->alterTable('xf_wrxt_portfolio_blob', function(Alter $table)
        {
            $table->addColumn('security_state', 'varchar', 20)->setDefault('clean')->after('state');
            $table->addColumn('blocked_reason', 'varchar', 100)->setDefault('')->after('security_state');
            $table->addColumn('last_security_scan_date', 'int')->unsigned()->setDefault(0)->after('blocked_reason');
            $table->addColumn('next_security_scan_date', 'int')->unsigned()->setDefault(0)->after('last_security_scan_date');
            $table->addKey(['security_state', 'next_security_scan_date']);
        });
        $this->createModerationTables();
    }

    public function upgrade1000100Step1(): void
    {
        $this->schemaManager()->alterTable('xf_wrxt_portfolio', function(Alter $table)
        {
            $table->addColumn('pending_moderation', 'tinyint')->setDefault(0)->after('security_status');
            $table->addColumn('pending_revision_json', 'mediumtext')->nullable()->after('pending_moderation');
            $table->addColumn('pending_revision_date', 'int')->unsigned()->setDefault(0)->after('pending_revision_json');
            $table->addKey(['status', 'pending_moderation']);
        });
    }

}
