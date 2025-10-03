<?php

namespace App\Services;

use Snipe\BanBuilder\CensorWords;

class ProfanityService
{
    private CensorWords $censor;

    public function __construct()
    {
        $this->censor = new CensorWords();
    }

    public function containsProfanity(string $text): bool
    {
        return count($this->getDetectedWords($text)) > 0;
    }

    public function getDetectedWords(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $result = $this->censor->censorString($normalized);

        $detected = [];
        if (!empty($result['matched'])) {
            foreach ($result['matched'] as $word) {
                if (!in_array($word, $detected, true)) {
                    $detected[] = $word;
                }
            }
        }
        return $detected;
    }

    public function getProfanityScore(string $text): float
    {
        $normalized = $this->normalizeText($text);
        $words = preg_split('/\s+/u', trim($normalized));
        if (!$words || count($words) === 0) {
            return 0.0;
        }
        $detected = $this->getDetectedWords($normalized);
        $score = (count($detected) / max(count($words), 1)) * 100.0;
        return round(min($score, 100.0), 2);
    }

    public function validate(string $text): array
    {
        $detected = $this->getDetectedWords($text);
        $score = $this->getProfanityScore($text);
        return [
            'contains' => count($detected) > 0,
            'detected_words' => $detected,
            'score' => $score,
        ];
    }

    private function normalizeText(string $text): string
    {
        $t = mb_strtolower($text, 'UTF-8');

        $map = [
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '@' => 'a',
            '$' => 's',
            '5' => 's',
            '7' => 't',
            '€' => 'e',
            '!' => 'i',
        ];

        $t = strtr($t, $map);
        $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        return trim($t);
    }
}