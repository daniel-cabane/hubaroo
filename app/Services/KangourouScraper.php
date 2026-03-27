<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KangourouScraper
{
    /**
     * Scrape correct answers from Kangourou solutions page.
     *
     * @param int $year The year of the competition (2004-2025)
     * @param string $level The level letter (e, b, c, p, j, s)
     * @return array<int, string> Array of 26 answers (A-E or numeric) indexed 0-25
     *
     * @throws \Exception If scraping fails or page structure is unexpected
     */
    public function scrape(int $year, string $level): array
    {
        $url = "https://www.mathkang.org/concours/sol{$year}{$level}.html";

        try {
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                Log::warning("Failed to fetch Kangourou solutions page", [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                throw new \Exception("Failed to fetch solutions page: HTTP {$response->status()}");
            }

            $html = $response->body();

            // Use regex to find question-answer patterns
            // Pattern: number (1-26) followed by a space and then a single letter/digit
            $answers = $this->extractAnswersFromHtml($html);

            if (empty($answers) || count($answers) < 26) {
                Log::warning("Scraped fewer than 26 answers from Kangourou page", [
                    'url' => $url,
                    'count' => count($answers ?? []),
                    'extracted' => implode(',', $answers ?? []),
                ]);
                throw new \Exception("Could not extract all 26 answers from solutions page (got " . count($answers ?? []) . ")");
            }

            // Ensure we have exactly 26 answers
            $answers = array_slice($answers, 0, 26);

            Log::info("Successfully scraped Kangourou answers", [
                'url' => $url,
                'count' => count($answers),
                'answers' => implode(',', $answers),
            ]);

            return $answers;
        } catch (\Exception $e) {
            Log::error("Kangourou scraper error", [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract answers from HTML using regex patterns.
     *
     * @return array<int, string>|null
     */
    private function extractAnswersFromHtml(string $html): ?array
    {
        $answers = [];

        // Remove all HTML tags and extra whitespace
        $text = strip_tags($html);
        
        // Collapse multiple whitespaces
        $text = preg_replace('/\s+/', ' ', $text);

        // Look for question + answer patterns
        // Try finding pairs like: "1 D" or "Q1 D" or similar
        // We'll be very permissive with our regex
        $pattern = '/\b([1-9]|1[0-9]|2[0-6])\s+([A-E0-9])\b/';
        
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $questionNum = (int)$match[1];
                $answer = $match[2];

                // Store by question number
                if ($questionNum >= 1 && $questionNum <= 26 && !isset($answers[$questionNum])) {
                    $answers[$questionNum] = $answer;
                }
            }
        }

        // If we didn't get enough answers, try a fallback: look for any sequence of A-E/0-9
        if (count($answers) < 26) {
            $fallbackAnswers = [];
            preg_match_all('/\b([A-E0-9])\b/', $text, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $answer) {
                    // Only take letters and single digits that look like answers
                    if (preg_match('/^[A-E]$/', $answer) || ($answer >= '0' && $answer <= '9')) {
                        $fallbackAnswers[] = $answer;
                    }
                }
            }

            // If fallback found 26+ answer-like items, use them
            if (count($fallbackAnswers) >= 26) {
                // Filter for first 26 answers (likely to be letter answers first, then numeric)
                return array_slice($fallbackAnswers, 0, 26);
            }
        }

        // If we found ordered answers, reconstruct them
        if (!empty($answers)) {
            $orderedAnswers = [];
            for ($i = 1; $i <= 26; $i++) {
                if (isset($answers[$i])) {
                    $orderedAnswers[] = $answers[$i];
                } else {
                    break;
                }
            }

            if (count($orderedAnswers) >= 26) {
                return array_slice($orderedAnswers, 0, 26);
            }
        }

        return null;
    }
}
