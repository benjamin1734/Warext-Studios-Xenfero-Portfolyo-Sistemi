<?php

namespace Warext\Portfolio\Service;

use XF\Http\Upload;
use XF\Service\AbstractService;
use XF\Util\File;
use Warext\Portfolio\Entity\Portfolio;
use Warext\Portfolio\Entity\PortfolioFile;

class Quarantine extends AbstractService
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'glb'];

    public function accept(Portfolio $portfolio, Upload $upload, string $role): PortfolioFile
    {
        if (!in_array($role, ['cover', 'gallery', 'model'], true))
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_invalid_file_role'));
        }
        $errors = [];
        if (!$upload->isValid($errors))
        {
            throw new \RuntimeException((string)($errors[0] ?? \XF::phrase('wrxt_portfolio_upload_failed')));
        }

        $tempFile = $upload->getTempFile();
        $fileSize = is_file($tempFile) ? (int)filesize($tempFile) : 0;
        $originalName = trim((string)$upload->getFileName());
        $extension = strtolower((string)$upload->getExtension());
        if (!in_array($extension, self::EXTENSIONS, true))
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_extension_not_allowed'));
        }
        if ($role === 'model' && $extension !== 'glb')
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_model_requires_glb'));
        }
        if ($role !== 'model' && $extension === 'glb')
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_image_required'));
        }

        $lock = 'wrxtp_upload_' . (int)$portfolio->user_id;
        if ((int)$this->db()->fetchOne('SELECT GET_LOCK(?, 15)', $lock) !== 1)
        {
            throw new \RuntimeException((string)\XF::phrase('wrxt_portfolio_upload_rate_limit'));
        }
        $storageName = '';
        try
        {
            $policy = $this->service('Warext\Portfolio:QuotaPolicy')->assertCanAccept($portfolio, $role, $fileSize);
            $this->service('Warext\Portfolio:StorageGuard')->assertCapacity($fileSize);
            $this->service('Warext\Portfolio:UploadRateLimiter')->consume((int)$portfolio->user_id, $fileSize, $policy);
            $session = $this->service('Warext\Portfolio:UploadSessionManager')->getOrCreate($portfolio);

            $fileKey = bin2hex(random_bytes(16));
            $storageName = 'internal-data://wrxt_portfolio/quarantine/' . substr($fileKey, 0, 2) . '/' . $fileKey . '.bin';
            File::copyFileToAbstractedPath($tempFile, $storageName);

            $db = $this->db();
            $db->beginTransaction();
            try
            {
                $file = $this->em()->create('Warext\Portfolio:PortfolioFile');
                $file->file_key = $fileKey;
                $file->portfolio_id = $portfolio->portfolio_id;
                $file->user_id = $portfolio->user_id;
                $file->file_role = $role;
                $file->display_order = $role === 'gallery' ? 100 : 10;
                $file->original_name = mb_substr($originalName, 0, 255, 'UTF-8');
                $file->extension = mb_substr($extension, 0, 16, 'UTF-8');
                $file->file_size = $fileSize;
                $file->storage_name = $storageName;
                $file->state = 'uploading';
                $file->created_date = \XF::$time;
                $file->save();

                $stateMachine = new StateMachine();
                $stateMachine->transitionFile($file, 'quarantine');
                $stateMachine->logFileEvent($file, 'quarantine_accept', 'info', '', [
                    'role' => $role,
                    'extension' => $extension,
                    'size' => $fileSize,
                    'session_id' => $session->session_id
                ]);

                $this->service('Warext\Portfolio:UploadSessionManager')->recordAccepted($session, $fileSize);
                if ((string)$portfolio->status !== 'published')
                {
                    $portfolio->status = 'awaiting_files';
                }
                else
                {
                    $portfolio->pending_moderation = true;
                }
                $portfolio->security_status = 'pending';
                $portfolio->updated_date = \XF::$time;
                $portfolio->save();
                $stateMachine->syncApprovalQueue($portfolio);
                $db->commit();
            }
            catch (\Throwable $e)
            {
                $db->rollback();
                File::deleteFromAbstractedPath($storageName);
                throw $e;
            }
        }
        finally
        {
            $this->db()->fetchOne('SELECT RELEASE_LOCK(?)', $lock);
        }

        try
        {
            \XF::app()->jobManager()->enqueueUnique(
                'wrxtPortfolioSecurity_' . (int)$file->file_id,
                'Warext\Portfolio:SecurityScan',
                ['file_id' => (int)$file->file_id],
                false,
                100
            );
        }
        catch (\Throwable $e)
        {
            (new StateMachine())->logFileEvent($file, 'security_job_enqueue_failed', 'warning', 'job_enqueue_failed');
        }
        return $file;
    }
}
