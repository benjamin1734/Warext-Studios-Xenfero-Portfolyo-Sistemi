<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use XF\Util\File;
use Warext\Portfolio\Entity\PortfolioFile;
use Warext\Portfolio\Exception\ScanUnavailableException;

class SecurityPipeline extends AbstractService
{
    public function process(PortfolioFile $file): string
    {
        if (in_array($file->state, ['blocked', 'deleted', 'rejected', 'published', 'security_passed', 'moderation', 'processing'], true))
        {
            return (string)$file->state;
        }
        if (!$file->storage_name)
        {
            $this->block($file, 'quarantine_payload_missing');
            return 'blocked';
        }

        $stateMachine = new StateMachine();
        $tmp = $this->service('Warext\Portfolio:LocalFileMaterializer')->materialize((string)$file->storage_name);

        try
        {
            if ($file->state === 'quarantine')
            {
                $stateMachine->transitionFile($file, 'validating');
            }

            if ($file->state === 'validating')
            {
                try
                {
                    $result = $this->service('Warext\Portfolio:FileInspector')->inspect($file, $tmp);
                    $file->sha256 = $result['sha256'];
                    $file->detected_mime = $result['detected_mime'];
                    $file->magic_type = $result['magic_type'];
                    $file->validation_details_json = json_encode($result['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $blocklist = $this->service('Warext\Portfolio:HashBlocklist');
                    if ($blocklist->isBlocked($result['sha256']))
                    {
                        throw new \RuntimeException($blocklist->getReason($result['sha256']) ?: 'blocked_hash');
                    }

                    $file->validation_status = 'passed';
                    $file->reason_code = '';
                    $file->save();
                    $stateMachine->logFileEvent($file, 'validation_passed', 'info', '', [
                        'sha256' => $result['sha256'],
                        'mime' => $result['detected_mime'],
                        'magic' => $result['magic_type'],
                        'details' => $result['details']
                    ]);
                    $stateMachine->transitionFile($file, 'scanning');
                }
                catch (\RuntimeException $e)
                {
                    $file->validation_status = 'failed';
                    $file->reason_code = mb_substr($e->getMessage(), 0, 100);
                    $file->save();
                    $this->block($file, $file->reason_code ?: 'validation_failed');
                    return 'blocked';
                }
            }

            if ($file->state === 'scanning')
            {
                $currentHash = hash_file('sha256', $tmp);
                if (!is_string($currentHash) || !$file->sha256 || !hash_equals((string)$file->sha256, $currentHash))
                {
                    $this->block($file, 'hash_changed_after_validation');
                    return 'blocked';
                }

                $file->scan_attempts = (int)$file->scan_attempts + 1;
                $file->last_scan_date = \XF::$time;
                $file->save();

                try
                {
                    $scan = $this->service('Warext\Portfolio:ClamAvScanner')->scan($tmp);
                }
                catch (ScanUnavailableException $e)
                {
                    $file->scan_status = 'error';
                    $file->reason_code = mb_substr($e->getMessage(), 0, 100);
                    $file->next_scan_date = \XF::$time + min(3600, 60 * max(5, (int)$file->scan_attempts * 5));
                    $file->save();
                    $stateMachine->logFileEvent($file, 'scan_unavailable', 'warning', $file->reason_code, [
                        'attempt' => (int)$file->scan_attempts,
                        'next_scan_date' => (int)$file->next_scan_date
                    ]);
                    return 'scan_pending';
                }

                if ($scan['status'] === 'infected')
                {
                    $file->scan_status = 'infected';
                    $file->scan_signature = $scan['signature'];
                    $file->reason_code = 'malware_detected';
                    $file->save();
                    if ($file->sha256)
                    {
                        $this->service('Warext\Portfolio:HashBlocklist')->add((string)$file->sha256, 'malware_detected', (string)$scan['signature']);
                    }
                    $this->block($file, 'malware_detected', ['signature' => $scan['signature']]);
                    return 'blocked';
                }

                $file->scan_status = 'clean';
                $file->scan_signature = '';
                $file->reason_code = '';
                $file->next_scan_date = 0;
                $file->save();
                $stateMachine->logFileEvent($file, 'scan_clean', 'info', '', ['attempt' => (int)$file->scan_attempts]);
                $stateMachine->transitionFile($file, 'processing');
                try
                {
                    \XF::app()->jobManager()->enqueueUnique(
                        'wrxtPortfolioProcess_' . (int)$file->file_id,
                        'Warext\Portfolio:ProcessFile',
                        ['file_id' => (int)$file->file_id],
                        false,
                        110
                    );
                }
                catch (\Throwable $e)
                {
                    $stateMachine->logFileEvent($file, 'processing_job_enqueue_failed', 'warning', 'job_enqueue_failed');
                }
                return 'processing';
            }

            return (string)$file->state;
        }
        finally
        {
            @unlink($tmp);
        }
    }

    private function block(PortfolioFile $file, string $reasonCode, array $details = []): void
    {
        (new StateMachine())->blockFile($file, $reasonCode, $details);
        if ($file->Portfolio)
        {
            $this->service('Warext\Portfolio:PortfolioSecurityState')->refresh($file->Portfolio);
        }
        if ($file->storage_name)
        {
            try
            {
                File::deleteFromAbstractedPath((string)$file->storage_name);
            }
            catch (\Throwable $e)
            {
                (new StateMachine())->logFileEvent($file, 'blocked_payload_delete_failed', 'warning', 'payload_delete_failed');
            }
            $file->storage_name = '';
            $file->save();
        }
    }
}
