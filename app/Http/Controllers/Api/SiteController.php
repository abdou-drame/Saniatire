<?php

namespace App\Http\Controllers\Api;

use App\Domain\Platform\ModuleCatalog;
use App\Domain\Structure\Models\Site;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteRequest;
use App\Http\Resources\SiteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SiteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sites.view', only: ['index', 'show']),
            new Middleware('permission:sites.create', only: ['store']),
            new Middleware('permission:sites.update', only: ['update']),
            new Middleware('permission:sites.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        return SiteResource::collection(Site::query()->paginate())->response();
    }

    public function store(SiteRequest $request): JsonResponse
    {
        if ($request->validated('is_active', true)) {
            $this->ensureAnotherActiveSiteIsAllowed($request);
        }

        $site = Site::create($request->validated());

        return (new SiteResource($site))->response()->setStatusCode(201);
    }

    public function show(Site $site): SiteResource
    {
        return new SiteResource($site);
    }

    public function update(SiteRequest $request, Site $site): SiteResource
    {
        if (! $site->is_active && $request->validated('is_active', false)) {
            $this->ensureAnotherActiveSiteIsAllowed($request, $site);
        }

        $site->update($request->validated());

        return new SiteResource($site);
    }

    /**
     * Livraison B : le socle couvre un seul site actif. Créer ou réactiver un
     * 2ᵉ site actif (et au-delà) exige le module premium `multi_sites` ; les
     * sites déjà actifs ne sont jamais désactivés d'office, et modifier un
     * site actif ou en désactiver un reste toujours possible.
     */
    private function ensureAnotherActiveSiteIsAllowed(SiteRequest $request, ?Site $except = null): void
    {
        $structureId = $request->user()->structure_id;

        if (ModuleCatalog::isActiveFor($structureId, 'multi_sites')) {
            return;
        }

        $otherActiveSites = Site::query()
            ->where('is_active', true)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        abort_if($otherActiveSites, 403, "Votre structure dispose déjà d'un site actif. Un site actif supplémentaire nécessite l'option « Multi-sites » : contactez Saliha Health pour l'ajouter à votre abonnement.");
    }

    public function destroy(Site $site): JsonResponse
    {
        $site->delete();

        return response()->json(null, 204);
    }
}
