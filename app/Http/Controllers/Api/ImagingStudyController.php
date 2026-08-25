<?php

namespace App\Http\Controllers\Api;

use App\Domain\Imagerie\Events\ResultatImagerieTransmis;
use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Imagerie\Models\ImagingStudy;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImagingStudyRequest;
use App\Http\Resources\ImagingStudyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ImagingStudyController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:imagerie.create', only: ['store']),
            new Middleware('permission:imagerie.validate', only: ['transmit']),
        ];
    }

    public function store(ImagingStudyRequest $request, ImagingOrder $imagingOrder): JsonResponse
    {
        abort_if(in_array($imagingOrder->status, ['transmis', 'annule'], true), 422, 'Cette demande ne peut plus recevoir d\'examen.');

        $study = $imagingOrder->studies()->create([
            ...$request->validated(),
            'performed_at' => $request->validated('performed_at') ?? now(),
            'performed_by' => $request->user()->id,
            'status' => 'realise',
        ])->refresh();

        $imagingOrder->syncStatusFromChildren();

        return (new ImagingStudyResource($study))->response()->setStatusCode(201);
    }

    /**
     * Blocking rule (§7 cahier des charges) : une étude ne peut être
     * transmise sans un compte rendu validé.
     */
    public function transmit(ImagingStudy $imagingStudy): ImagingStudyResource
    {
        abort_if(
            ! $imagingStudy->report || $imagingStudy->report->status !== 'valide',
            422,
            'Ce compte rendu doit être validé avant transmission.'
        );

        $imagingStudy->update(['status' => 'transmis']);
        $imagingStudy->imagingOrder->syncStatusFromChildren();

        ResultatImagerieTransmis::dispatch($imagingStudy);

        return new ImagingStudyResource($imagingStudy);
    }
}
