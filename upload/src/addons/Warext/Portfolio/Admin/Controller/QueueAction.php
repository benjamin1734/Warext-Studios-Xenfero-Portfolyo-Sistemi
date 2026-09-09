<?php

namespace Warext\Portfolio\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class QueueAction extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('wrxtPortfolioSecurity');
        $this->setSectionContext('wrxtPortfolioAdmin');
    }

    public function actionRun()
    {
        $this->assertPostOnly();

        $file = $this->assertRecordExists(
            'Warext\Portfolio:PortfolioFile',
            $this->filter('file_id', 'uint')
        );

        try
        {
            $result = $this->runNow($file);
        }
        catch (\Throwable $e)
        {
            \XF::logException($e, false, 'Warext Portfolio manual processing: ');
            return $this->error('Dosya işlenirken hata oluştu: ' . $this->safeReason($e->getMessage()));
        }

        $this->service('Warext\Portfolio:AuditLogger')->log(
            'quarantine_run_now',
            'portfolio_file',
            (int)$file->file_id,
            (int)$file->portfolio_id,
            (int)$file->file_id,
            (string)$result
        );

        return $this->redirectAfterRun($file, $result);
    }

    public function actionContinue()
    {
        $this->assertPostOnly();

        $file = $this->assertRecordExists(
            'Warext\Portfolio:PortfolioFile',
            $this->filter('file_id', 'uint')
        );

        if (
            (string)$file->state !== 'scanning'
            || (string)$file->validation_status !== 'passed'
            || (string)$file->scan_status !== 'error'
        )
        {
            return $this->error('Yalnızca yapısal doğrulamayı geçmiş ve ClamAV erişim hatasında bekleyen dosyalarda bu işlem kullanılabilir.');
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
            return $this->error('Bu hata ClamAV servis/bağlantı problemi olmadığı için güvenlik taraması atlanamaz.');
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

        try
        {
            $result = $this->service('Warext\Portfolio:ProcessingPipeline')->process($file);
        }
        catch (\Throwable $e)
        {
            \XF::logException($e, false, 'Warext Portfolio ClamAV bypass processing: ');
            return $this->error('ClamAV adımı geçildi fakat dosya işlenemedi: ' . $this->safeReason($e->getMessage()));
        }

        $this->service('Warext\Portfolio:AuditLogger')->log(
            'staff_clamav_bypass',
            'portfolio_file',
            (int)$file->file_id,
            (int)$file->portfolio_id,
            (int)$file->file_id,
            $reason,
            ['result' => $result]
        );

        return $this->redirectAfterRun($file, $result);
    }

    private function runNow($file): string
    {
        $state = (string)$file->state;

        if (in_array($state, ['quarantine', 'validating', 'scanning'], true))
        {
            $file->next_scan_date = 0;
            $file->save();
            $result = $this->service('Warext\Portfolio:SecurityPipeline')->process($file);

            if ($result === 'processing' && (string)$file->state === 'processing')
            {
                $result = $this->service('Warext\Portfolio:ProcessingPipeline')->process($file);
            }

            return (string)$result;
        }

        if ($state === 'processing')
        {
            $file->next_processing_date = 0;
            $file->save();
            return (string)$this->service('Warext\Portfolio:ProcessingPipeline')->process($file);
        }

        if (in_array($state, ['security_passed', 'moderation'], true))
        {
            if ($file->Portfolio)
            {
                $this->service('Warext\Portfolio:PortfolioSecurityState')->refresh($file->Portfolio);
            }
            return 'moderation_ready';
        }

        if ($state === 'blocked')
        {
            throw new \RuntimeException('Dosya güvenlik nedeniyle engellenmiş. Engellenen dosya manuel onayla yayınlanamaz.');
        }

        throw new \RuntimeException('Bu dosya aktif güvenlik/işleme kuyruğunda değil.');
    }

    private function redirectAfterRun($file, string $result)
    {
        $portfolioId = (int)$file->portfolio_id;
        $portfolioRow = $portfolioId > 0
            ? $this->app->db()->fetchRow('SELECT status, security_status, pending_moderation FROM xf_wrxt_portfolio WHERE portfolio_id = ?', $portfolioId)
            : null;

        if (
            $portfolioRow
            && (string)$portfolioRow['security_status'] === 'passed'
            && ((string)$portfolioRow['status'] === 'moderation' || ((string)$portfolioRow['status'] === 'published' && (bool)$portfolioRow['pending_moderation']))
        )
        {
            return $this->redirect(
                $this->buildLink('wrxt-portfolyo/moderation'),
                'Teknik kontroller tamamlandı. Çalışma artık Portfolyo Moderasyonu ekranında manuel yayın onayı bekliyor.'
            );
        }

        $reason = (string)$this->app->db()->fetchOne(
            'SELECT reason_code FROM xf_wrxt_portfolio_file WHERE file_id = ?',
            (int)$file->file_id
        );

        $message = match ($result)
        {
            'scan_pending' => 'Dosya yapısal kontrolden geçti ancak ClamAV erişilemiyor. Karantina ekranında “ClamAV olmadan devam” seçeneğini kullanabilirsiniz.',
            'processing_pending' => 'Dosya işleme aşamasında bekliyor. Sunucu işleme nedeni: ' . ($reason ?: 'bilinmiyor'),
            'blocked' => 'Dosya güvenlik kontrolünden geçemedi ve engellendi.',
            'security_passed', 'moderation_ready' => 'Dosyanın teknik kontrolleri tamamlandı. Diğer dosyalar da tamamlandığında çalışma moderasyona geçer.',
            default => 'İşlem çalıştırıldı. Güncel durum: ' . $result
        };

        return $this->redirect($this->buildLink('wrxt-portfolyo/quarantine'), $message);
    }

    private function safeReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '')
        {
            return 'bilinmeyen hata';
        }
        return mb_substr($reason, 0, 160, 'UTF-8');
    }
}
