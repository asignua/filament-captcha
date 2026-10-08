<?php

declare(strict_types=1);

namespace Asignua\FilamentCaptcha\Http\Controllers;

use Asignua\FilamentCaptcha\View\Components\Scripts;
use Illuminate\Http\Response;

class ScriptController
{
    public function __invoke(): Response
    {
        return response((string) file_get_contents(Scripts::scriptFile()), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
