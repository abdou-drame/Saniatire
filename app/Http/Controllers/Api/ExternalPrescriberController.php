<?php

namespace App\Http\Controllers\Api;

use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Shared\Auth\PortalActivationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalPrescriberRequest;
use App\Http\Resources\ExternalPrescriberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExternalPrescriberController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PortalActivationService $activationService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:prescripteurs.view', only: ['index', 'show']),
            new Middleware('permission:prescripteurs.create', only: ['store']),
            new Middleware('permission:prescripteurs.update', only: ['update', 'sendPortalActivation']),
            new Middleware('permission:prescripteurs.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        return ExternalPrescriberResource::collection(ExternalPrescriber::query()->paginate())->response();
    }

    public function store(ExternalPrescriberRequest $request): JsonResponse
    {
        $prescriber = ExternalPrescriber::create($request->validated());

        return (new ExternalPrescriberResource($prescriber))->response()->setStatusCode(201);
    }

    public function show(ExternalPrescriber $externalPrescriber): ExternalPrescriberResource
    {
        return new ExternalPrescriberResource($externalPrescriber);
    }

    public function update(ExternalPrescriberRequest $request, ExternalPrescriber $externalPrescriber): ExternalPrescriberResource
    {
        $externalPrescriber->update($request->validated());

        return new ExternalPrescriberResource($externalPrescriber);
    }

    public function destroy(ExternalPrescriber $externalPrescriber): JsonResponse
    {
        $externalPrescriber->delete();

        return response()->json(null, 204);
    }

    /**
     * Même patron que PatientController::sendPortalActivation — étape 7b §3.
     */
    public function sendPortalActivation(ExternalPrescriber $externalPrescriber): JsonResponse
    {
        abort_if($externalPrescriber->portal_activated_at !== null, 422, 'Le portail de ce prescripteur est déjà activé.');

        $this->activationService->createFor($externalPrescriber);

        return response()->json(['message' => "Lien d'activation envoyé."]);
    }
}
