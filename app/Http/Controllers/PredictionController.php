<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PredictionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Prediction::query()
            ->with(['standardizedDrug', 'tender', 'currency', 'user'])
            ->where('user_id', auth()->id());

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('uuid', 'like', "%{$search}%")
                    ->orWhereHas('standardizedDrug', function ($drugQuery) use ($search) {
                        $drugQuery->where('display_name', 'like', "%{$search}%")
                            ->orWhere('inn', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($riskLevel = $request->input('risk_level')) {
            $query->where('risk_level', $riskLevel);
        }

        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('user_id') && $this->canFilterByUser()) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        $predictions = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $baseQuery = Prediction::query()->where('user_id', auth()->id());

        return view('predictions.index', [
            'predictions' => $predictions,
            'filters' => $request->only(['search', 'status', 'risk_level', 'source', 'date_from', 'date_to', 'user_id']),
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
                'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
                'processing' => (clone $baseQuery)->whereIn('status', ['processing', 'pending'])->count(),
                'avg_confidence' => round((float) (clone $baseQuery)->where('status', 'completed')->avg('confidence_score'), 1),
            ],
            'users' => $this->canFilterByUser()
                ? User::query()->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    public function show(Prediction $prediction): RedirectResponse
    {
        abort_unless($prediction->user_id === auth()->id(), 403);

        return redirect()->route('ai.recommendations.show', $prediction);
    }

    protected function canFilterByUser(): bool
    {
        return User::query()->count() > 1;
    }
}
