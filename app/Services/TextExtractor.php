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
            'docx' => $this->fromDocx($path),
            default => '',
        };
    }

    protected function fromPdf(string $path)
    {
        return (new \Spatie\PdfToText\Pdf())
            ->setPdf($path)
            ->text();
    }

    protected function fromDocx(string $path): string
    {
        $content = '';
        $zip = new \ZipArchive;

        if ($zip->open($path) === true) {
            $index = $zip->locateName('word/document.xml');
            if ($index !== false) {
                $xml = $zip->getFromIndex($index);
                // Basic cleanup to separate paragraphs
                $xml = str_replace('</w:p>', "\n", $xml);
                $content = strip_tags($xml);
            }
            $zip->close();
        }

        return trim($content);
    }
}
