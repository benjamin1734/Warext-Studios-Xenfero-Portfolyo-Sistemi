<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use XF\Util\File;
use Warext\Portfolio\Entity\PortfolioFile;
use Warext\Portfolio\Exception\ProcessingUnavailableException;
use Warext\Portfolio\Exception\ModelRejectedException;

class ProcessingPipeline extends AbstractService
{
    public function process(PortfolioFile $file): string
    {
        if ($file->state !== 'processing')
        {
            return (string)$file->state;
        }
        $stateMachine = new StateMachine();
        $file->processing_attempts = (int)$file->processing_attempts + 1;
        $file->last_processing_date = \XF::$time;
        $file->processing_status = 'running';
        $file->reason_code = '';
        $file->save();

        try
        {
            if ((string)$file->extension === 'glb')
            {
                $result = $this->service('Warext\Portfolio:ModelProcessor')->process($file);
            }
            else
            {
                $result = $this->service('Warext\Portfolio:ImageProcessor')->process($file);
            }
        }
        catch (ModelRejectedException $e)
        {
            $reason = mb_substr($e->getMessage(), 0, 100);
            $file->processing_status = 'error';
            $file->reason_code = $reason;
            $file->next_processing_date = 0;
            $file->save();
            $stateMachine->blockFile($file, $reason ?: 'model_rejected');
            $this->service('Warext\Portfolio:BlobManager')->cleanupStaging($file);
            if ($file->storage_name)
            {
                try
                {
                    File::deleteFromAbstractedPath((string)$file->storage_name);
                    $file->storage_name = '';
                    $file->save();
                }
                catch (\Throwable $ignored) {}
            }
            if ($file->Portfolio)
            {
                $this->service('Warext\Portfolio:PortfolioSecurityState')->refresh($file->Portfolio);
            }
            return 'blocked';
        }
        catch (ProcessingUnavailableException $e)
        {
            $this->scheduleRetry($file, mb_substr($e->getMessage(), 0, 100));
            $stateMachine->logFileEvent($file, 'processing_unavailable', 'warning', (string)$file->reason_code, [
                'attempt' => (int)$file->processing_attempts,
                'next_processing_date' => (int)$file->next_processing_date
            ]);
            return 'processing_pending';
        }
        catch (\Throwable $e)
        {
            $hardFailures = ['model_source_hash_mismatch', 'model_hash_changed_during_processing', 'processing_source_hash_mismatch'];
            if (in_array($e->getMessage(), $hardFailures, true))
            {
                $file->processing_status = 'error';
                $file->reason_code = mb_substr($e->getMessage(), 0, 100);
                $file->next_processing_date = 0;
                $file->save();
                $stateMachine->blockFile($file, (string)$file->reason_code);
                $this->service('Warext\Portfolio:BlobManager')->cleanupStaging($file);
                if ($file->Portfolio)
                {
                    $this->service('Warext\Portfolio:PortfolioSecurityState')->refresh($file->Portfolio);
                }
                return 'blocked';
            }
            $this->scheduleRetry($file, 'processing_failed');
            $stateMachine->logFileEvent($file, 'processing_failed', 'warning', 'processing_failed', [
                'attempt' => (int)$file->processing_attempts,
                'exception' => get_class($e),
                'next_processing_date' => (int)$file->next_processing_date
            ]);
            return 'processing_pending';
        }

        $file->processed_sha256 = $result['processed_sha256'];
        $file->processed_size = $result['processed_size'];
        $file->processed_mime = $result['processed_mime'];
        $file->processed_width = $result['processed_width'] ?? 0;
        $file->processed_height = $result['processed_height'] ?? 0;
        $file->thumbnail_width = $result['thumbnail_width'] ?? 0;
        $file->thumbnail_height = $result['thumbnail_height'] ?? 0;
        try
        {
            $this->service('Warext\Portfolio:BlobManager')->attachProcessedResult($file, $result);
        }
        catch (\Throwable $e)
        {
            $this->scheduleRetry($file, 'blob_publish_failed');
            $stateMachine->logFileEvent($file, 'blob_publish_failed', 'warning', 'blob_publish_failed', [
                'exception' => get_class($e),
                'attempt' => (int)$file->processing_attempts
            ]);
            return 'processing_pending';
        }
        if ((string)$file->extension === 'glb')
        {
            $stats = is_array($result['stats'] ?? null) ? $result['stats'] : [];
            $file->model_stats_json = json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $file->model_vertex_count = (int)($stats['vertices'] ?? 0);
            $file->model_triangle_count = (int)($stats['triangles'] ?? 0);
            $file->model_mesh_count = (int)($stats['meshes'] ?? 0);
            $file->model_node_count = (int)($stats['nodes'] ?? 0);
            $file->model_material_count = (int)($stats['materials'] ?? 0);
            $file->model_texture_count = (int)($stats['textures'] ?? 0);
            $file->model_animation_count = (int)($stats['animations'] ?? 0);
            $file->model_skin_count = (int)($stats['skins'] ?? 0);
            $file->model_joint_count = (int)($stats['joints'] ?? 0);
            $file->model_max_texture_dimension = (int)($stats['max_texture_dimension'] ?? 0);
        }
        $file->processing_status = 'passed';
        $file->next_processing_date = 0;
        $file->reason_code = '';
        $file->save();

        if ((string)$file->extension === 'glb')
        {
            $stateMachine->logFileEvent($file, 'model_analyzed', 'info', '', [
                'processed_size' => $result['processed_size'],
                'processed_sha256' => $result['processed_sha256'],
                'stats' => $result['stats'] ?? []
            ]);
        }
        else
        {
            $stateMachine->logFileEvent($file, 'image_reencoded', 'info', '', [
                'engine' => $result['worker']['engine'] ?? '',
                'source_width' => $result['worker']['source_width'] ?? 0,
                'source_height' => $result['worker']['source_height'] ?? 0,
                'processed_width' => $result['processed_width'],
                'processed_height' => $result['processed_height'],
                'processed_size' => $result['processed_size'],
                'processed_sha256' => $result['processed_sha256']
            ]);
        }

        if ($file->storage_name)
        {
            try
            {
                File::deleteFromAbstractedPath((string)$file->storage_name);
                $file->storage_name = '';
                $file->save();
            }
            catch (\Throwable $e)
            {
                $stateMachine->logFileEvent($file, 'original_delete_failed', 'warning', 'original_delete_failed');
            }
        }

        $stateMachine->transitionFile($file, 'security_passed');
        if ($file->Portfolio)
        {
            $this->service('Warext\Portfolio:PortfolioSecurityState')->refresh($file->Portfolio);
        }
        return 'security_passed';
    }

    private function scheduleRetry(PortfolioFile $file, string $reason): void
    {
        $attempt = max(1, (int)$file->processing_attempts);
        $baseMinutes = max(1, min(60, (int)$this->app->options()->wrxtPortfolioProcessingRetryMinutes));
        $delay = min(3600, $baseMinutes * 60 * min(6, $attempt));
        $file->processing_status = 'error';
        $file->reason_code = $reason ?: 'processing_unavailable';
        $file->next_processing_date = \XF::$time + $delay;
        $file->save();
    }
}
