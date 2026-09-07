<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;

class LocalFileMaterializer extends AbstractService
{
    public function materialize(string $abstractPath): string
    {
        $source = \XF::fs()->readStream($abstractPath);
        if (!is_resource($source))
        {
            throw new \RuntimeException('Unable to open quarantined file');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'wrxtp_');
        if ($tmp === false)
        {
            fclose($source);
            throw new \RuntimeException('Unable to create security temp file');
        }

        @chmod($tmp, 0600);
        $target = fopen($tmp, 'wb');
        if (!is_resource($target))
        {
            fclose($source);
            @unlink($tmp);
            throw new \RuntimeException('Unable to create security temp stream');
        }

        try
        {
            if (stream_copy_to_stream($source, $target) === false)
            {
                throw new \RuntimeException('Unable to materialize quarantined file');
            }
        }
        finally
        {
            fclose($source);
            fclose($target);
        }

        return $tmp;
    }
}
