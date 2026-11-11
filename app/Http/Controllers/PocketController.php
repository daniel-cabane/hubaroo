<?php

namespace App\Http\Controllers;

use App\Services\PocketAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PocketController extends Controller
{
    public function __invoke(Request $request, PocketAssembler $pocket): JsonResponse
    {
        return response()->json($pocket->for($request->user()));
    }
}
