<?php

namespace App\Http\Controllers\Api;

use App\Domain\Imagerie\Models\ImagingReport;
use App\Domain\Imagerie\Models\ImagingStudy;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImagingReportRequest;
use App\Http\Resources\ImagingReportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ImagingReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:imagerie.interpreter', only: ['store']),
            new Middleware('permission:imagerie.validate', only: ['validateReport']),
        ];
    }

    public function store(ImagingReportRequest $request, ImagingStudy $imagingStudy): JsonResponse
    {
        abort_if($imagingStudy->report()->exists(), 422, 'Un compte rendu existe déjà pour cet examen.');

        $report = $imagingStudy->report()->create([
            ...$request->validated(),
            'author_id' => $request->user()->id,
        ])->refresh();

        $imagingStudy->update(['status' => 'cr_redige']);
        $imagingStudy->imagingOrder->syncStatusFromChildren();

        return (new ImagingReportResource($report))->response()->setStatusCode(201);
    }

    public function validateReport(ImagingReport $imagingReport): ImagingReportResource
    {
        abort_if($imagingReport->status !== 'brouillon', 422, "Ce compte rendu n'est pas en attente de validation.");

        $imagingReport->update([
            'status' => 'valide',
            'validated_at' => now(),
        ]);

        $imagingReport->study->update(['status' => 'valide']);
        $imagingReport->study->imagingOrder->syncStatusFromChildren();

        return new ImagingReportResource($imagingReport);
    }
}
