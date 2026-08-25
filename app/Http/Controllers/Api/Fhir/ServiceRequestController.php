<?php

namespace App\Http\Controllers\Api\Fhir;

use App\Domain\Fhir\Converters\ServiceRequestFhirConverter;
use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Id composite `lab-{id}` / `imaging-{id}` — même convention que
 * DiagnosticReportController.
 */
class ServiceRequestController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:fhir.view')];
    }

    public function show(string $id): JsonResponse
    {
        if (str_starts_with($id, 'lab-')) {
            $order = LabOrder::with('items.loincCode')->findOrFail((int) substr($id, 4));

            return response()->json(ServiceRequestFhirConverter::fromLabOrder($order));
        }

        if (str_starts_with($id, 'imaging-')) {
            $order = ImagingOrder::findOrFail((int) substr($id, 8));

            return response()->json(ServiceRequestFhirConverter::fromImagingOrder($order));
        }

        abort(404, "Identifiant ServiceRequest invalide : préfixe 'lab-' ou 'imaging-' attendu.");
    }
}
