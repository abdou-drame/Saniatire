<?php

namespace App\Http\Controllers\Api\Fhir;

use App\Domain\Fhir\Converters\Concerns\BuildsFhirBundle;
use App\Domain\Fhir\Converters\ObservationFhirConverter;
use App\Domain\Laboratoire\Models\LabResult;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ObservationController extends Controller implements HasMiddleware
{
    use BuildsFhirBundle;

    public static function middleware(): array
    {
        return [new Middleware('permission:fhir.view')];
    }

    public function show(int $id): JsonResponse
    {
        $result = LabResult::with(['sample.labOrder', 'orderItem.loincCode'])->findOrFail($id);

        // Même logique que ConditionController : lab_results n'a pas de
        // structure_id propre, l'isolation est héritée via sample.labOrder
        // (BelongsToTenant) — un résultat d'une autre structure ressort
        // avec labOrder à null.
        abort_if(! $result->sample?->labOrder, 404);

        return response()->json(ObservationFhirConverter::convert($result));
    }

    /**
     * GET /fhir/Observation?patient=123 — tous les résultats de labo d'un
     * patient (recherche basique demandée par le cahier des charges).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['patient' => ['required', 'integer']]);

        $results = LabResult::with(['sample.labOrder', 'orderItem.loincCode'])
            ->whereHas('sample.labOrder', fn ($q) => $q->where('patient_id', $data['patient']))
            ->get();

        return response()->json(self::bundle($results->map(fn (LabResult $r) => ObservationFhirConverter::convert($r))->all()));
    }
}
