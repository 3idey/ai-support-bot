<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class TextExtractor
{
    private const SUPPORTED_EXTENSIONS = ['txt', 'pdf', 'docx'];

    private const DOCX_DOCUMENT_PATH = 'word/document.xml';

    private const PARAGRAPH_TAG = '</w:p>';

    /**
     * Extract text content from a file
     *
     * @param  string  $path  Absolute path to the file
     * @return string Extracted text content
     */
    public function extract(string $path): string
    {
        $extension = $this->getFileExtension($path);

        return match ($extension) {
            'txt' => $this->extractFromText($path),
            'pdf' => $this->extractFromPdf($path),
            'docx' => $this->extractFromDocx($path),
            default => '',
        };
    }

    /**
     * Get lowercase file extension
     */
    private function getFileExtension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * Extract text from plain text file
     */
    private function extractFromText(string $path): string
    {
        return File::get($path);
    }

    /**
     * Extract text from PDF file
     */
    private function extractFromPdf(string $path): string
    {
        return (new \Spatie\PdfToText\Pdf)
            ->setPdf($path)
            ->text();
    }

    /**
     * Extract text from DOCX file
     */
    private function extractFromDocx(string $path): string
    {
        $content = '';
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return $content;
        }

        $index = $zip->locateName(self::DOCX_DOCUMENT_PATH);

        if ($index !== false) {
            $xml = $zip->getFromIndex($index);
            $content = $this->cleanDocxXml($xml);
        }

        $zip->close();

        return trim($content);
    }

    /**
     * Clean DOCX XML content
     */
    private function cleanDocxXml(string $xml): string
    {
        // Separate paragraphs with newlines
        $xml = str_replace(self::PARAGRAPH_TAG, "\n", $xml);

        // Remove all XML tags
        return strip_tags($xml);
    }
}
