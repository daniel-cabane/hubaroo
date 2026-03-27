<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PdfDownloader
{
    /**
     * Download a Kangourou paper PDF from the website.
     *
     * @param int $year The year of the competition (2004-2025)
     * @param string $level The level letter (e, b, c, p, j, s)
     * @return string The PDF file content as binary string
     *
     * @throws \Exception If download fails
     */
    public function download(int $year, string $level): string
    {
        $url = "https://www.mathkang.org/pdf/kangourou{$year}{$level}.pdf";

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::error("Failed to download Kangourou PDF", [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                throw new \Exception("Failed to download PDF: HTTP {$response->status()}");
            }

            $content = $response->body();

            // Validate that we received a PDF
            if (!$this->isPdfContent($content)) {
                Log::error("Downloaded content is not a valid PDF", [
                    'url' => $url,
                    'size' => strlen($content),
                ]);
                throw new \Exception("Downloaded content is not a valid PDF");
            }

            Log::info("Successfully downloaded Kangourou PDF", [
                'url' => $url,
                'size' => strlen($content),
            ]);

            return $content;
        } catch (\Exception $e) {
            Log::error("PDF downloader error", [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate that the content is a PDF file.
     *
     * A valid PDF starts with the magic bytes %PDF.
     */
    private function isPdfContent(string $content): bool
    {
        return str_starts_with($content, '%PDF');
    }
}
