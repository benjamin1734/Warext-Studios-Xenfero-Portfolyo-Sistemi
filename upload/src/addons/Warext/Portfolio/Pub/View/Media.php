<?php
namespace Warext\Portfolio\Pub\View;
use XF\Mvc\View;
class Media extends View
{
    public function renderRaw()
    {
        $content=(string)$this->params['content']; $etag=(string)($this->params['etag']??'');
        $this->response->contentType('image/webp');
        $this->response->header('X-Content-Type-Options','nosniff');
        $this->response->header('Content-Disposition','inline');
        $this->response->header('Cache-Control','public, max-age=86400, stale-while-revalidate=604800');
        $this->response->header('Cross-Origin-Resource-Policy','same-origin');
        if($etag!=='') $this->response->header('ETag','"'.$etag.'"');
        return $content;
    }
}
