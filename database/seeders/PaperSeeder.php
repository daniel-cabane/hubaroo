<?php

namespace Database\Seeders;

use App\Models\Paper;
use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PaperSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed papers and questions from the generated JSON data file.
     */
    public function run(): void
    {
        $dataPath = database_path('seeders/data/papers.json');

        if (! File::exists($dataPath)) {
            $this->command?->error("Data file not found: {$dataPath}");
            $this->command?->info('Run "php artisan import:all-kangourou-papers" first to generate the data.');

            return;
        }

        $papers = json_decode(File::get($dataPath), true);

        $this->command?->info('Seeding '.count($papers).' papers...');

        DB::transaction(function () use ($papers) {
            foreach ($papers as $paperData) {
                $paper = Paper::create([
                    'title' => $paperData['title'],
                    'level' => $paperData['level'],
                    'year' => $paperData['year'],
                ]);

                foreach ($paperData['questions'] as $questionData) {
                    $question = Question::create([
                        'image' => $questionData['image'],
                        'correct_answer' => $questionData['correct_answer'],
                        'tier' => $questionData['tier'],
                    ]);

                    $paper->questions()->attach($question->id, [
                        'order' => $questionData['order'],
                    ]);
                }
            }
        });

        $this->command?->info('Papers seeded successfully.');
    }
}
