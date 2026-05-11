<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->smallInteger('difficulty')->nullable()->after('tier');
        });

        $levelValues = ['e' => 1, 'b' => 2, 'c' => 3, 'j' => 4, 'p' => 4, 's' => 5];

        $questions = DB::table('questions')->get();

        foreach ($questions as $question) {
            $paperLevels = DB::table('paper_question')
                ->join('papers', 'papers.id', '=', 'paper_question.paper_id')
                ->where('paper_question.question_id', $question->id)
                ->pluck('papers.level')
                ->toArray();

            $maxLevel = 1;
            foreach ($paperLevels as $paperLevel) {
                $val = $levelValues[$paperLevel] ?? 1;
                if ($val > $maxLevel) {
                    $maxLevel = $val;
                }
            }

            $difficulty = 300 * $maxLevel + 100 * (int) round(pow(2, $question->tier - 1));
            DB::table('questions')->where('id', $question->id)->update(['difficulty' => $difficulty]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('difficulty');
        });
    }
};
