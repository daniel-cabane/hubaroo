<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class QuestionExtractor
{
    /**
     * Extract questions from a Kangourou PDF paper.
     *
     * @param  string  $pdfPath  Path to the PDF file (temp or storage path)
     * @param  int  $year  The year of the competition
     * @param  string  $level  The level letter (e, b, c, p, j, s)
     * @return array<int, array{image: string, tier: int}> Array of 26 questions with image paths and tiers
     *
     * @throws \Exception If extraction fails
     */
    public function extract(string $pdfPath, int $year, string $level): array
    {
        try {
            Log::info('Starting question extraction from PDF', [
                'path' => $pdfPath,
                'year' => $year,
                'level' => $level,
            ]);

            $images = $this->extractQuestionImages($pdfPath, $year, $level);

            if (empty($images)) {
                throw new \Exception('Failed to extract question images from PDF');
            }

            $questions = [];

            foreach ($images as $index => $imagePath) {
                $questionNumber = $index + 1;
                $tier = $this->calculateTier($questionNumber);

                $questions[] = [
                    'image' => $imagePath,
                    'tier' => $tier,
                ];
            }

            if (count($questions) < 26) {
                throw new \Exception('Expected 26 questions but got '.count($questions));
            }

            $questions = array_slice($questions, 0, 26);

            Log::info('Successfully extracted questions from PDF', [
                'count' => count($questions),
                'year' => $year,
                'level' => $level,
            ]);

            return $questions;
        } catch (Throwable $e) {
            Log::error('Question extraction error', [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract individual question images from the PDF using the Node.js script.
     *
     * @return array<int, string> Array of image paths indexed 0-25
     *
     * @throws \Exception If extraction fails
     */
    private function extractQuestionImages(string $pdfPath, int $year, string $level): array
    {
        $outputDir = public_path('questions');
        $scriptPath = base_path('scripts/extract-questions.mjs');

        $result = Process::timeout(120)->run([
            'node', $scriptPath, $pdfPath, $outputDir, (string) $year, $level,
        ]);

        if ($result->failed()) {
            Log::error('Question extraction script failed', [
                'exitCode' => $result->exitCode(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
            throw new \Exception('Question extraction script failed: '.$result->errorOutput());
        }

        $output = json_decode($result->output(), true);

        if (! $output || ! ($output['success'] ?? false)) {
            throw new \Exception('Question extraction failed: '.($output['error'] ?? 'Unknown error'));
        }

        return array_map(
            fn (array $q) => $q['image'],
            $output['questions']
        );
    }

    /**
     * Calculate the tier for a question based on its number.
     *
     * Tier assignments:
     * - Questions 1-8: Tier 1
     * - Questions 9-16: Tier 2
     * - Questions 17-24: Tier 3
     * - Questions 25-26: Tier 4
     */
    private function calculateTier(int $questionNumber): int
    {
        if ($questionNumber <= 8) {
            return 1;
        } elseif ($questionNumber <= 16) {
            return 2;
        } elseif ($questionNumber <= 24) {
            return 3;
        } else {
            return 4;
        }
    }
}
