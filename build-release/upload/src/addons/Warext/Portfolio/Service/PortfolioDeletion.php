<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use XF\Util\File;
use Warext\Portfolio\Entity\Portfolio;

class PortfolioDeletion extends AbstractService
{
    public function delete(Portfolio $portfolio): void
    {
        if ((string)$portfolio->status === 'deleted')
        {
            return;
        }
        $files = $this->finder('Warext\Portfolio:PortfolioFile')
            ->where('portfolio_id', (int)$portfolio->portfolio_id)
            ->where('state', '<>', 'deleted')
            ->fetch();
        $stateMachine = new StateMachine();
        foreach ($files as $file)
        {
            if ($file->storage_name)
            {
                try { File::deleteFromAbstractedPath((string)$file->storage_name); } catch (\Throwable $e) {}
                $file->storage_name = '';
                $file->save();
            }
            $this->service('Warext\Portfolio:BlobManager')->cleanupStaging($file);
            $this->service('Warext\Portfolio:BlobManager')->detachFile($file);
            if ((string)$file->state !== 'deleted')
            {
                $stateMachine->transitionFile($file, 'deleted', 'portfolio_deleted');
            }
        }
        $portfolio->cover_file_id = 0;
        $portfolio->model_file_id = 0;
        $portfolio->gallery_count = 0;
        $portfolio->pending_moderation = false;
        $portfolio->pending_revision_json = null;
        $portfolio->pending_revision_date = 0;
        $portfolio->save();
        $stateMachine->transitionPortfolio($portfolio, 'deleted');
        $this->service('Warext\Portfolio:AuditLogger')->log('portfolio_deleted', 'portfolio', (int)$portfolio->portfolio_id, (int)$portfolio->portfolio_id);
    }
}
