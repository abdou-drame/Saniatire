<?php

namespace App\Http\Controllers\Api\Fhir;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Fhir\Converters\Concerns\BuildsFhirBundle;
use App\Domain\Fhir\Converters\EncounterFhirConverter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EncounterController extends Controller implements HasMiddleware
{
    use BuildsFhirBundle;

    public static function middleware(): array
    {
        return [new Middleware('permission:fhir.view')];
    }

    public function show(int $id): JsonResponse
    {
        $consultation = Consultation::findOrFail($id);

        return response()->json(EncounterFhirConverter::convert($consultation));
    }

    /**
     * GET /fhir/Encounter?patient=123 — toutes les consultations d'un
     * patient (déjà borné à la structure courante par TenantScope).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['patient' => ['nullable', 'integer']]);

        $consultations = Consultation::query()
            ->when(! empty($data['patient']), fn ($q) => $q->where('patient_id', $data['patient']))
            ->get();

        return response()->json(self::bundle($consultations->map(fn (Consultation $c) => EncounterFhirConverter::convert($c))->all()));
    }
}
