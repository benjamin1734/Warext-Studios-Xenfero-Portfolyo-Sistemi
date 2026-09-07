<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Entity\Portfolio;

class ModerationPolicy extends AbstractService
{
    public function assertPublishable(Portfolio $portfolio): void
    {
        if ((string)$portfolio->security_status !== 'passed')
        {
            throw new \RuntimeException('wrxt_portfolio_security_not_passed');
        }
        $files = $this->finder('Warext\\Portfolio:PortfolioFile')
            ->where('portfolio_id', (int)$portfolio->portfolio_id)
            ->where('state', '<>', 'deleted')
            ->with(['ProcessedBlob', 'ThumbnailBlob'])
            ->fetch();
        if (!$files->count())
        {
            throw new \RuntimeException('wrxt_portfolio_no_safe_files');
        }
        foreach ($files as $file)
        {
            if (!in_array((string)$file->state, ['security_passed', 'moderation', 'published'], true))
            {
                throw new \RuntimeException('wrxt_portfolio_file_not_security_passed');
            }
            foreach ([$file->ProcessedBlob, $file->ThumbnailBlob] as $blob)
            {
                if ($blob && (string)$blob->security_state !== 'clean')
                {
                    throw new \RuntimeException('wrxt_portfolio_blob_security_blocked');
                }
            }
        }
    }
}
