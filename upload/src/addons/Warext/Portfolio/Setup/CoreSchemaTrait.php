<?php
namespace Warext\Portfolio\Setup;
use XF\Db\Schema\Create;
trait CoreSchemaTrait
{
    protected function createCoreTables(): void
    {
            $this->schemaManager()->createTable('xf_wrxt_portfolio_category', function(Create $table)
            {
                $table->addColumn('category_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('parent_category_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('title', 'varchar', 100);
                $table->addColumn('description', 'text')->nullable();
                $table->addColumn('allowed_types', 'varchar', 100)->setDefault('image,model3d');
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(10);
                $table->addColumn('is_active', 'tinyint')->setDefault(1);
                $table->addColumn('created_date', 'int')->unsigned();
                $table->addPrimaryKey('category_id');
                $table->addKey(['parent_category_id', 'display_order']);
                $table->addKey(['is_active', 'display_order']);
            });
            $this->schemaManager()->createTable('xf_wrxt_portfolio', function(Create $table)
            {
                $table->addColumn('portfolio_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('portfolio_key', 'char', 32);
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('username', 'varchar', 50)->setDefault('');
                $table->addColumn('category_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('title', 'varchar', 150);
                $table->addColumn('description', 'mediumtext');
                $table->addColumn('programs', 'varchar', 255)->setDefault('');
                $table->addColumn('tags_cache', 'varchar', 500)->setDefault('');
                $table->addColumn('tag_count', 'tinyint')->unsigned()->setDefault(0);
                $table->addColumn('portfolio_type', 'varchar', 20)->setDefault('image');
                $table->addColumn('status', 'varchar', 32)->setDefault('draft');
                $table->addColumn('security_status', 'varchar', 32)->setDefault('none');
                $table->addColumn('pending_moderation', 'tinyint')->setDefault(0);
                $table->addColumn('pending_revision_json', 'mediumtext')->nullable();
                $table->addColumn('pending_revision_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('cover_file_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_file_id', 'int')->unsigned()->setDefault(0);
                foreach (['gallery_count','view_count','like_count','save_count','comment_count','updated_date','published_date','deleted_date'] as $name)
                {
                    $table->addColumn($name, 'int')->unsigned()->setDefault(0);
                }

                $table->addColumn('created_date', 'int')->unsigned();
                $table->addPrimaryKey('portfolio_id');
                $table->addUniqueKey('portfolio_key');
                $table->addKey(['user_id', 'status', 'created_date']);
                $table->addKey(['category_id', 'status', 'published_date']);
                $table->addKey(['status', 'published_date']);
                $table->addKey(['status', 'pending_moderation']);
            });
            $this->schemaManager()->createTable('xf_wrxt_portfolio_file', function(Create $table)
            {
                $table->addColumn('file_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('file_key', 'char', 32);
                $table->addColumn('portfolio_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('file_role', 'varchar', 20)->setDefault('gallery');
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(10);
                $table->addColumn('original_name', 'varchar', 255)->setDefault('');
                $table->addColumn('extension', 'varchar', 16)->setDefault('');
                $table->addColumn('declared_mime', 'varchar', 100)->setDefault('');
                $table->addColumn('detected_mime', 'varchar', 100)->setDefault('');
                $table->addColumn('magic_type', 'varchar', 32)->setDefault('');
                $table->addColumn('validation_details_json', 'mediumtext')->nullable();
                $table->addColumn('file_size', 'bigint')->unsigned()->setDefault(0);
                $table->addColumn('sha256', 'char', 64)->setDefault('');
                $table->addColumn('storage_name', 'varchar', 255)->setDefault('');
                $table->addColumn('state', 'varchar', 32)->setDefault('uploading');
                $table->addColumn('scan_status', 'varchar', 32)->setDefault('pending');
                $table->addColumn('scan_signature', 'varchar', 255)->setDefault('');
                $table->addColumn('scan_attempts', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_scan_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('next_scan_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('validation_status', 'varchar', 32)->setDefault('pending');
                $table->addColumn('processing_status', 'varchar', 32)->setDefault('pending');
                $table->addColumn('processing_attempts', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_processing_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('next_processing_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('processed_storage_name', 'varchar', 255)->setDefault('');
                $table->addColumn('processed_blob_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('thumbnail_storage_name', 'varchar', 255)->setDefault('');
                $table->addColumn('thumbnail_blob_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('processed_sha256', 'char', 64)->setDefault('');
                $table->addColumn('processed_size', 'bigint')->unsigned()->setDefault(0);
                $table->addColumn('processed_mime', 'varchar', 100)->setDefault('');
                $table->addColumn('processed_width', 'int')->unsigned()->setDefault(0);
                $table->addColumn('processed_height', 'int')->unsigned()->setDefault(0);
                $table->addColumn('thumbnail_width', 'int')->unsigned()->setDefault(0);
                $table->addColumn('thumbnail_height', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_stats_json', 'mediumtext')->nullable();
                $table->addColumn('model_vertex_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_triangle_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_mesh_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_node_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_material_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_texture_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_animation_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_skin_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_joint_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('model_max_texture_dimension', 'int')->unsigned()->setDefault(0);
                $table->addColumn('reason_code', 'varchar', 100)->setDefault('');
                $table->addColumn('created_date', 'int')->unsigned();
                $table->addColumn('checked_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('published_date', 'int')->unsigned()->setDefault(0);
                $table->addPrimaryKey('file_id');
                $table->addUniqueKey('file_key');
                $table->addKey(['portfolio_id', 'file_role', 'display_order']);
                $table->addKey(['user_id', 'created_date']);
                $table->addKey(['state', 'next_scan_date']);
                $table->addKey(['state', 'next_processing_date']);
                $table->addKey('sha256');
                $table->addKey('processed_sha256');
                $table->addKey('processed_blob_id');
                $table->addKey('thumbnail_blob_id');
            });
            $this->schemaManager()->createTable('xf_wrxt_portfolio_security_log', function(Create $table)
            {
                $table->addColumn('log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('portfolio_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('file_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('event', 'varchar', 64);
                $table->addColumn('severity', 'varchar', 16)->setDefault('info');
                $table->addColumn('reason_code', 'varchar', 100)->setDefault('');
                $table->addColumn('details_json', 'mediumtext')->nullable();
                $table->addColumn('created_date', 'int')->unsigned();
                $table->addPrimaryKey('log_id');
                $table->addKey(['file_id', 'created_date']);
                $table->addKey(['severity', 'created_date']);
            });
            $this->schemaManager()->createTable('xf_wrxt_portfolio_upload_rate', function(Create $table)
            {
                $table->addColumn('rate_key', 'char', 64);
                $table->addColumn('user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('window_start', 'int')->unsigned();
                $table->addColumn('upload_count', 'int')->unsigned()->setDefault(0);
                $table->addColumn('uploaded_bytes', 'bigint')->unsigned()->setDefault(0);
                $table->addColumn('updated_date', 'int')->unsigned();
                $table->addPrimaryKey('rate_key');
                $table->addKey(['user_id', 'window_start']);
                $table->addKey('updated_date');
            });
        }
}
