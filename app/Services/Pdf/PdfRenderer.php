<?php

namespace App\Services\Pdf;

use Spatie\Browsershot\Browsershot;

/**
 * Blade view → PDF through headless Chromium, so Bangla conjuncts render
 * correctly (dompdf breaks them). Tests swap in FakePdfRenderer.
 */
class PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $format = 'A4', bool $landscape = false): string
    {
        return Browsershot::html(view($view, $data)->render())
            ->setChromePath(config('services.chrome.path'))
            ->noSandbox()
            ->format($format)
            ->landscape($landscape)
            ->margins(8, 8, 8, 8)
            ->showBackground()
            ->pdf();
    }
}
