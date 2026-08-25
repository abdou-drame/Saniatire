<?php

namespace App\Http\Controllers\Api;

use App\Domain\Laboratoire\Events\ResultatLaboratoireDisponible;
use App\Domain\Laboratoire\Events\ResultatLaboratoireTransmis;
use App\Domain\Laboratoire\Models\LabResult;
use App\Domain\Laboratoire\Models\LabSample;
use App\Http\Controllers\Controller;
use App\Http\Requests\LabResultRequest;
use App\Http\Resources\LabResultResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LabResultController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:laboratoire.create', only: ['store']),
            new Middleware('permission:laboratoire.validate_technique', only: ['validateTechnique']),
            new Middleware('permission:laboratoire.validate_biologique', only: ['validateBiologique']),
            new Middleware('permission:laboratoire.validate_biologique', only: ['transmit']),
        ];
    }

    public function store(LabResultRequest $request, LabSample $labSample): JsonResponse
    {
        $result = $labSample->results()->create($request->validated())->refresh();

        $labSample->labOrder->syncStatusFromChildren();

        return (new LabResultResource($result))->response()->setStatusCode(201);
    }

    public function validateTechnique(Request $request, LabResult $labResult): LabResultResource
    {
        abort_if($labResult->status !== 'validation_technique_attente', 422, "Ce résultat n'est pas en attente de validation technique.");

        $labResult->update([
            'status' => 'validation_biologique_attente',
            'technical_validated_by' => $request->user()->id,
            'technical_validated_at' => now(),
        ]);

        return new LabResultResource($labResult);
    }

    /**
     * Blocking rule (§7 cahier des charges) : la validation biologique ne
     * peut jamais précéder la validation technique.
     */
    public function validateBiologique(Request $request, LabResult $labResult): LabResultResource
    {
        abort_if(! $labResult->technical_validated_at, 422, 'La validation technique doit être faite avant la validation biologique.');
        abort_if($labResult->status !== 'validation_biologique_attente', 422, "Ce résultat n'est pas en attente de validation biologique.");

        $labResult->update([
            'status' => 'valide',
            'biological_validated_by' => $request->user()->id,
            'biological_validated_at' => now(),
        ]);

        $labResult->orderItem->labOrder->syncStatusFromChildren();

        ResultatLaboratoireDisponible::dispatch($labResult);

        return new LabResultResource($labResult);
    }

    public function transmit(LabResult $labResult): LabResultResource
    {
        abort_if($labResult->status !== 'valide', 422, 'Ce résultat doit être validé biologiquement avant transmission.');

        $labResult->update(['status' => 'transmis']);

        $labResult->orderItem->labOrder->syncStatusFromChildren();

        ResultatLaboratoireTransmis::dispatch($labResult);

        return new LabResultResource($labResult);
    }
}
