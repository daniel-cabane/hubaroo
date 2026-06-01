<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Division;
use App\Models\Question;
use App\Models\SuggestedQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestedQuestionController extends Controller
{
    public function index(Course $course, Request $request): JsonResponse
    {
        $this->authorize('update', $course->division);

        $questions = SuggestedQuestion::where('course_id', $course->id)
            ->with('question')
            ->orderBy('level')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($sq) => [
                'id' => $sq->id,
                'level' => $sq->level,
                'is_public' => $sq->is_public,
                'question' => [
                    'id' => $sq->question->id,
                    'image' => $sq->question->image,
                    'correct_answer' => $sq->question->correct_answer,
                    'difficulty' => $sq->question->difficulty,
                ],
            ]);

        return response()->json(['suggested_questions' => $questions]);
    }

    public function publicForDivision(Division $division, Request $request): JsonResponse
    {
        $user = $request->user();

        $isMember = $division->students()->where('users.id', $user->id)->exists();
        if (! $isMember) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $questions = SuggestedQuestion::whereHas('course', fn ($q) => $q->where('division_id', $division->id))
            ->where('is_public', true)
            ->with(['question', 'course'])
            ->orderBy('level')
            ->get()
            ->map(fn ($sq) => [
                'id' => $sq->id,
                'level' => $sq->level,
                'course_title' => $sq->course->title,
                'question' => [
                    'id' => $sq->question->id,
                    'image' => $sq->question->image,
                    'correct_answer' => $sq->question->correct_answer,
                ],
            ]);

        return response()->json(['suggested_questions' => $questions]);
    }

    public function togglePublic(SuggestedQuestion $suggestedQuestion, Request $request): JsonResponse
    {
        $this->authorize('update', $suggestedQuestion->course->division);

        $suggestedQuestion->update(['is_public' => ! $suggestedQuestion->is_public]);

        return response()->json(['suggested_question' => $suggestedQuestion->fresh()]);
    }

    public function destroy(SuggestedQuestion $suggestedQuestion, Request $request): JsonResponse
    {
        $this->authorize('update', $suggestedQuestion->course->division);

        $suggestedQuestion->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function random(Request $request): JsonResponse
    {
        $user = $request->user();

        $reference = $request->has('reference')
            ? $request->integer('reference')
            : ($user->mastery ?? 500);

        $questions = Question::where('difficulty', '>=', $reference)
            ->orderBy('difficulty')
            ->limit(20)
            ->get();

        if ($questions->isEmpty()) {
            $questions = Question::orderBy('difficulty', 'desc')
                ->limit(20)
                ->get();
        }

        $question = $questions->random();

        return response()->json([
            'question' => [
                'id' => $question->id,
                'image' => $question->image,
                'correct_answer' => $question->correct_answer,
                'difficulty' => $question->difficulty,
            ],
            'reference' => $reference,
        ]);
    }
}
