<?php

namespace App\Http\Controllers\Api\Fhir;

use App\Domain\Fhir\Converters\Concerns\BuildsFhirBundle;
use App\Domain\Fhir\Converters\PatientFhirConverter;
use App\Domain\Patient\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PatientController extends Controller implements HasMiddleware
{
    use BuildsFhirBundle;

    public static function middleware(): array
    {
        return [new Middleware('permission:fhir.view')];
    }

    public function show(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        return response()->json(PatientFhirConverter::convert($patient));
    }

    /**
     * GET /fhir/Patient?identifier=PT-... — recherche basique par numéro
     * patient interne (le seul identifiant stable exposé côté FHIR).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['identifier' => ['nullable', 'string']]);

        $patients = Patient::query()
            ->when(! empty($data['identifier']), fn ($q) => $q->where('patient_number', $data['identifier']))
            ->get();

        return response()->json(self::bundle($patients->map(fn (Patient $p) => PatientFhirConverter::convert($p))->all()));
    }
}
