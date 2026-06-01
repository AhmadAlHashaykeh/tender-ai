<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlobalSearchRequest;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected GlobalSearchService $globalSearch,
    ) {}

    public function __invoke(GlobalSearchRequest $request): JsonResponse
    {
        $query = $request->sanitizedQuery();

        return response()->json(
            $this->globalSearch->search($query, (int) $request->user()->id),
        );
    }
}
