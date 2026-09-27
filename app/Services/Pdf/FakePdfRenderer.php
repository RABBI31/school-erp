<?php

namespace App\Services\Pdf;

/**
 * For tests: returns the rendered HTML instead of starting Chromium, so tests
 * can assert on the content.
 */
class FakePdfRenderer extends PdfRenderer
{
    public function render(string $view, array $data, string $format = 'A4', bool $landscape = false): string
    {
        return view($view, $data)->render();
    }
}
