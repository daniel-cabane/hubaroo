<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Client;
use Throwable;

class QuestionExtractor
{
    private Client $openai;

    public function __construct(Client $openai)
    {
        $this->openai = $openai;
    }

    /**
     * Extract questions from a Kangourou PDF paper.
     *
     * @param string $pdfPath Path to the PDF file (temp or storage path)
     * @param int $year The year of the competition
     * @param string $level The level letter (e, b, c, p, j, s)
     * @return array<int, array{image: string, tier: int}> Array of 26 questions with image paths and tiers
     *
     * @throws \Exception If extraction fails
     */
    public function extract(string $pdfPath, int $year, string $level): array
    {
        try {
            Log::info("Starting question extraction from PDF", [
                'path' => $pdfPath,
                'year' => $year,
                'level' => $level,
            ]);

            // Convert PDF to images (one per page)
            $images = $this->pdfToImages($pdfPath, $year, $level);

            if (empty($images)) {
                throw new \Exception("Failed to convert PDF to images");
            }

            $questions = [];

            // Process each page/question
            foreach ($images as $index => $imagePath) {
                $questionNumber = $index + 1;
                $tier = $this->calculateTier($questionNumber);

                Log::debug("Processing question", [
                    'number' => $questionNumber,
                    'tier' => $tier,
                    'image' => $imagePath,
                ]);

                $questions[] = [
                    'image' => $imagePath,
                    'tier' => $tier,
                ];
            }

            if (count($questions) < 26) {
                Log::warning("Extracted fewer than 26 questions from PDF", [
                    'count' => count($questions),
                    'expected' => 26,
                ]);
                throw new \Exception("Expected 26 questions but got " . count($questions));
            }

            // Keep only the first 26 questions
            $questions = array_slice($questions, 0, 26);

            Log::info("Successfully extracted questions from PDF", [
                'count' => count($questions),
                'year' => $year,
                'level' => $level,
            ]);

            return $questions;
        } catch (Throwable $e) {
            Log::error("Question extraction error", [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Convert PDF pages to individual images.
     *
     * @return array<int, string> Array of image file paths
     *
     * @throws \Exception If conversion fails
     */
    private function pdfToImages(string $pdfPath, int $year, string $level): array
    {
        try {
            $images = [];

            // For now, create placeholder images for each question
            // In production, you would use ImageMagick or Ghostscript
            // Both require system installation and configuration
            
            for ($i = 1; $i <= 26; $i++) {
                $storagePath = "questions/{$year}_{$level}_q{$i}.png";
                $publicPath = public_path($storagePath);

                if (!is_dir(dirname($publicPath))) {
                    mkdir(dirname($publicPath), 0755, true);
                }

                // Create a simple placeholder PNG image
                // In production, extract actual question from PDF
                $this->createPlaceholderImage($publicPath, "Question {$i}");
                
                $images[] = $storagePath;
            }

            return $images;
        } catch (\Throwable $e) {
            Log::error("PDF to images conversion failed", [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to convert PDF to images: " . $e->getMessage());
        }
    }

    /**
     * Create a simple placeholder PNG image.
     */
    private function createPlaceholderImage(string $path, string $text): void
    {
        // Create a simple 200x150 white PNG with text
        $image = imagecreatetruecolor(200, 150);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        // Fill with white
        imagefilledrectangle($image, 0, 0, 200, 150, $white);

        // Add black border
        imagerectangle($image, 0, 0, 199, 149, $black);

        // Add text
        imagestring($image, 2, 10, 70, $text, $black);

        // Save PNG
        imagepng($image, $path);
        imagedestroy($image);
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
