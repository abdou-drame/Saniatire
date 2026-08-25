<?php

namespace App\Http\Controllers\Api\Fhir;

use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Fhir\Converters\ConditionFhirConverter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ConditionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:fhir.view')];
    }

    public function show(int $id): JsonResponse
    {
        $diagnosis = ConsultationDiagnosis::with('consultation')->findOrFail($id);

        // ConsultationDiagnosis n'a pas de structure_id propre (isolation
        // héritée de sa consultation, scoped via BelongsToTenant) : une
        // consultation d'une autre structure ressort déjà `null` ici grâce
        // au scope global, d'où ce garde-fou explicite plutôt qu'une simple
        // absence d'erreur.
        abort_if(! $diagnosis->consultation, 404);

        return response()->json(ConditionFhirConverter::convert($diagnosis));
    }
}
