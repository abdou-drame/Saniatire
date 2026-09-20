<?php

namespace App\Http\Controllers\Api;

use App\Domain\Structure\Models\Structure;
use App\Http\Controllers\Controller;
use App\Http\Requests\StructureRequest;
use App\Http\Resources\StructureResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Unlike Site/User/Patient, Structure has no structure_id column — it IS
 * the tenant boundary, so it can't use the BelongsToTenant/TenantScope
 * mechanism. Instead, every action here explicitly restricts to the
 * caller's own structure (404 on mismatch, same as a scoped-out record).
 * store() n'existe plus ici — création de structure réservée à
 * PlatformStructureController::store() (guard `platform`), voir
 * routes/api.php : aucun rôle de structure ne doit pouvoir créer une
 * structure (faille structures.create fermée).
 */
class StructureController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:structures.view', only: ['index', 'show']),
            new Middleware('permission:structures.update', only: ['update']),
            new Middleware('permission:structures.delete', only: ['destroy']),
            new Middleware('permission:referrals.create', only: ['directory']),
        ];
    }

    public function index(Request $request): StructureResource
    {
        return new StructureResource($request->user()->structure);
    }

    /**
     * Étape 15 : liste minimale des structures pouvant servir de destination
     * à un référencement inter-structures (routes/api.php, bloc patient-referrals).
     * Gardé sur referrals.create — même permission que l'envoi lui-même — pas
     * sur structures.view, qui gouverne un tout autre écran (gestion de la
     * structure). Actives uniquement, structure de l'appelant exclue (se
     * référencer soi-même n'a pas de sens) ; le backend ne rejette pas pour
     * autant un envoi vers sa propre structure si le frontend passait outre —
     * ce filtre est une simple commodité de picker, pas une règle serveur.
     */
    public function directory(Request $request): JsonResponse
    {
        $structures = Structure::query()
            ->where('is_active', true)
            ->where('id', '!=', $request->user()->structure_id)
            ->orderBy('legal_name')
            ->get(['id', 'code', 'legal_name', 'trade_name', 'city', 'is_active']);

        return response()->json([
            'data' => $structures->map(fn (Structure $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'legal_name' => $s->legal_name,
                'trade_name' => $s->trade_name,
                'city' => $s->city,
                'is_active' => $s->is_active,
            ]),
        ]);
    }

    public function show(Request $request, Structure $structure): StructureResource
    {
        $this->authorizeOwnStructure($request, $structure);

        return new StructureResource($structure);
    }

    public function update(StructureRequest $request, Structure $structure): StructureResource
    {
        $this->authorizeOwnStructure($request, $structure);

        $structure->update($request->validated());

        return new StructureResource($structure);
    }

    public function destroy(Request $request, Structure $structure): JsonResponse
    {
        $this->authorizeOwnStructure($request, $structure);

        $structure->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwnStructure(Request $request, Structure $structure): void
    {
        abort_unless($structure->id === $request->user()->structure_id, 404);
    }
}
