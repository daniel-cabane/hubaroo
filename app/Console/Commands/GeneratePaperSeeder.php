<?php

namespace App\Console\Commands;

use App\Models\Paper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GeneratePaperSeeder extends Command
{
    protected $signature = 'generate:paper-seeder';

    protected $description = 'Generate PaperSeeder data from current database state';

    public function handle(): int
    {
        $papers = Paper::with(['questions' => fn ($q) => $q->orderByPivot('order')])->get();

        if ($papers->isEmpty()) {
            $this->error('No papers found in database.');

            return self::FAILURE;
        }

        $data = $papers->map(fn (Paper $paper) => [
            'title' => $paper->title,
            'level' => $paper->level,
            'year' => $paper->year,
            'questions' => $paper->questions->map(fn ($q) => [
                'image' => $q->image,
                'correct_answer' => $q->correct_answer,
                'tier' => $q->tier,
                'order' => $q->pivot->order,
            ])->toArray(),
        ])->toArray();

        $dataDir = database_path('seeders/data');
        File::ensureDirectoryExists($dataDir);

        $jsonPath = $dataDir.'/papers.json';
        File::put($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Generated {$jsonPath} with ".count($data).' papers.');

        return self::SUCCESS;
    }
}
