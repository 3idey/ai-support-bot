<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class TextExtractor
{
    public function extract(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'txt' => File::get($path),
            'pdf' => $this->fromPdf($path),
            default => '',
        };
    }

    protected function fromPdf(string $path)
    {
        return (new \Spatie\PdfToText\Pdf())
            ->setPdf($path)
            ->text();
    }
}
