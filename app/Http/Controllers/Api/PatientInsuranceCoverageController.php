<?php

namespace App\Http\Controllers\Api;

use App\Domain\Assurance\Models\PatientInsuranceCoverage;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientInsuranceCoverageRequest;
use App\Http\Resources\PatientInsuranceCoverageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PatientInsuranceCoverageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:assurance.view', only: ['index', 'show']),
            new Middleware('permission:assurance.create', only: ['store']),
            new Middleware('permission:assurance.update', only: ['update']),
            new Middleware('permission:assurance.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = PatientInsuranceCoverage::query()
            ->with(['patient:id,first_name,last_name,patient_number', 'convention.provider'])
            ->orderByDesc('id');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        return PatientInsuranceCoverageResource::collection($query->paginate())->response();
    }

    public function store(PatientInsuranceCoverageRequest $request): JsonResponse
    {
        $coverage = PatientInsuranceCoverage::create($request->validated())->refresh();

        return (new PatientInsuranceCoverageResource($coverage))->response()->setStatusCode(201);
    }

    public function show(PatientInsuranceCoverage $patientInsuranceCoverage): PatientInsuranceCoverageResource
    {
        return new PatientInsuranceCoverageResource($patientInsuranceCoverage->load(['patient:id,first_name,last_name,patient_number', 'convention.provider']));
    }

    public function update(PatientInsuranceCoverageRequest $request, PatientInsuranceCoverage $patientInsuranceCoverage): PatientInsuranceCoverageResource
    {
        $patientInsuranceCoverage->update($request->validated());

        return new PatientInsuranceCoverageResource($patientInsuranceCoverage);
    }

    public function destroy(PatientInsuranceCoverage $patientInsuranceCoverage): JsonResponse
    {
        $patientInsuranceCoverage->delete();

        return response()->json(null, 204);
    }
}
