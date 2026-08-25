<?php

namespace App\Http\Controllers\Api\Fhir;

use App\Domain\Fhir\Converters\DiagnosticReportFhirConverter;
use App\Domain\Imagerie\Models\ImagingReport;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Id composite `lab-{id}` / `imaging-{id}` — deux sources internes
 * distinctes alimentent cette unique ressource FHIR. Voir
 * DiagnosticReportFhirConverter et app/Domain/Fhir/README.md.
 */
class DiagnosticReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:fhir.view')];
    }

    public function show(string $id): JsonResponse
    {
        if (str_starts_with($id, 'lab-')) {
            $order = LabOrder::with('items.result')->findOrFail((int) substr($id, 4));

            return response()->json(DiagnosticReportFhirConverter::fromLabOrder($order));
        }

        if (str_starts_with($id, 'imaging-')) {
            $report = ImagingReport::with('study.imagingOrder')->findOrFail((int) substr($id, 8));

            abort_if(! $report->study?->imagingOrder, 404);

            return response()->json(DiagnosticReportFhirConverter::fromImagingReport($report));
        }

        abort(404, "Identifiant DiagnosticReport invalide : préfixe 'lab-' ou 'imaging-' attendu.");
    }
}
