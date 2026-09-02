<?php

namespace Warext\Portfolio\Pub\View\Model;

use XF\Mvc\View;

class Frame extends View
{
    public function renderRaw()
    {
        $dataUrl = htmlspecialchars((string)$this->params['dataUrl'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $scriptUrl = htmlspecialchars((string)$this->params['scriptUrl'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $origin = (string)$this->params['origin'];
        $this->response->contentType('text/html', 'utf-8');
        $this->response->header('X-Content-Type-Options', 'nosniff');
        $this->response->header('Referrer-Policy', 'no-referrer');
        $this->response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $this->response->header('Content-Security-Policy', "default-src 'none'; script-src {$origin}; connect-src {$origin}; img-src blob: data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors {$origin}");

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#15171a;font-family:system-ui,sans-serif}.wrxtViewer{position:relative;width:100%;height:100%}canvas{display:block;width:100%;height:100%;touch-action:none}.wrxtToolbar{position:absolute;left:10px;right:10px;bottom:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:7px;background:rgba(0,0,0,.58);border-radius:8px;backdrop-filter:blur(5px)}button,select{border:0;border-radius:6px;padding:7px 9px;background:#fff;color:#111;font:inherit}button{cursor:pointer}.wrxtStatus{margin-left:auto;color:#fff;font-size:12px;white-space:nowrap}.wrxtError{position:absolute;inset:0;display:none;align-items:center;justify-content:center;padding:20px;color:#fff;text-align:center;background:#15171a}.wrxtError.is-active{display:flex}</style></head><body><div class="wrxtViewer" data-wrxt-model-viewer data-model-url="' . $dataUrl . '"><canvas aria-label="3D model görüntüleyici"></canvas><div class="wrxtToolbar"><button type="button" data-action="reset">Sıfırla</button><button type="button" data-action="play">Durdur</button><select data-animation aria-label="Animasyon"><option value="-1">Animasyon yok</option></select><button type="button" data-action="fullscreen">Tam ekran</button><span class="wrxtStatus" data-status>Yükleniyor…</span></div><div class="wrxtError" data-error></div></div><script src="' . $scriptUrl . '"></script></body></html>';
    }
}
