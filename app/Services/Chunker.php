<?php

namespace App\Services;

class Chunker
{
    public function split(string $text, int $maxLength = 800): array
    {
        $sentences = preg_split('/(?<=[.?!])\s+/', trim($text));

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {

            if (strlen($current . ' ' . $sentence) > $maxLength) {
                $chunks[] = trim($current);
                $current = $sentence;
            } else {
                $current .= ' ' . $sentence;
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
