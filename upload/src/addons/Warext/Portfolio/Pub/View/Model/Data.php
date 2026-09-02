<?php

namespace Warext\Portfolio\Pub\View\Model;

use XF\Mvc\View;

class Data extends View
{
    public function renderRaw()
    {
        $this->response->contentType('model/gltf-binary', '');
        $this->response->header('X-Content-Type-Options', 'nosniff');
        $this->response->header('Access-Control-Allow-Origin', '*');
        $this->response->header('Cross-Origin-Resource-Policy', 'cross-origin');
        $this->response->header('Cache-Control', 'private, max-age=300');
        return (string)$this->params['content'];
    }
}
