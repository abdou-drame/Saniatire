<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dashboard\Services\QualityDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

class DashboardQualiteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboards.qualite', only: ['index']),
        ];
    }

    public function index(Request $request, QualityDashboardService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'service' => ['nullable', 'string'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subMonth()->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $service_ = $data['service'] ?? null;

        return response()->json([
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'service' => $service_],
            'score_moyen_satisfaction' => $service->scoreMoyenSatisfaction($service_, $from, $to),
            'reclamations' => $service->repartitionReclamations($from, $to),
            'delai_moyen_resolution_heures' => $service->delaiMoyenResolutionHeures($from, $to),
        ]);
    }
}
