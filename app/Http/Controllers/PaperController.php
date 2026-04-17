<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use Illuminate\Http\JsonResponse;

class PaperController extends Controller
{
    public function show(Paper $paper): JsonResponse
    {
        $paper->load(['questions' => function ($query) {
            $query->select('questions.id', 'questions.image', 'questions.tier')
                ->orderByPivot('order');
        }]);

        return response()->json(['paper' => $paper]);
    }
}
