<?php

namespace App\Services;

class Chunker
{
    private const DEFAULT_MAX_LENGTH = 800;

    private const SENTENCE_DELIMITER_PATTERN = '/(?<=[.?!])\s+/';

    /**
     * Split text into chunks based on sentence boundaries
     *
     * @param  string  $text  The text to chunk
     * @param  int  $maxLength  Maximum length of each chunk
     * @return array<int, string> Array of text chunks
     */
    public function chunk(string $text, int $maxLength = self::DEFAULT_MAX_LENGTH): array
    {
        $sentences = $this->splitIntoSentences($text);

        return $this->groupSentencesIntoChunks($sentences, $maxLength);
    }

    /**
     * Split text into sentences
     */
    private function splitIntoSentences(string $text): array
    {
        return preg_split(self::SENTENCE_DELIMITER_PATTERN, trim($text));
    }

    /**
     * Group sentences into chunks respecting max length
     */
    private function groupSentencesIntoChunks(array $sentences, int $maxLength): array
    {
        $chunks = [];
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if (empty($sentence)) {
                continue;
            }

            if ($this->wouldExceedMaxLength($currentChunk, $sentence, $maxLength)) {
                if (! empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $sentence;
            } else {
                $currentChunk = $this->appendSentence($currentChunk, $sentence);
            }
        }

        if (! empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Check if adding a sentence would exceed max length
     */
    private function wouldExceedMaxLength(string $current, string $sentence, int $maxLength): bool
    {
        if (empty($current)) {
            return false;
        }

        return strlen($current.' '.$sentence) > $maxLength;
    }

    /**
     * Append sentence to current chunk
     */
    private function appendSentence(string $current, string $sentence): string
    {
        return empty($current) ? $sentence : $current.' '.$sentence;
    }
}
