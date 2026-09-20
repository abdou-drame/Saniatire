<?php

namespace App\Http\Controllers\Api;

use App\Domain\Caisse\Models\CashSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashSessionCloseRequest;
use App\Http\Requests\CashSessionRequest;
use App\Http\Resources\CashSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CashSessionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:caisse.view', only: ['index', 'show']),
            new Middleware('permission:caisse.create', only: ['store']),
            new Middleware('permission:caisse.update', only: ['close']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = CashSession::query()
            ->with(['caissier:id,first_name,last_name', 'site:id,name'])
            ->orderByDesc('id');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        if ($request->filled('caissier_id')) {
            $query->where('caissier_id', $request->integer('caissier_id'));
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }

        return CashSessionResource::collection($query->paginate())->response();
    }

    public function store(CashSessionRequest $request): JsonResponse
    {
        $data = $request->validated();

        abort_if(
            CashSession::where('caissier_id', $request->user()->id)
                ->where('site_id', $data['site_id'])
                ->where('statut', 'ouverte')
                ->exists(),
            422,
            'Une session de caisse est déjà ouverte pour ce caissier sur ce site.'
        );

        $session = CashSession::create([
            'site_id' => $data['site_id'],
            'caissier_id' => $request->user()->id,
            'montant_ouverture' => $data['montant_ouverture'],
            'ouverte_le' => now(),
            'statut' => 'ouverte',
        ])->refresh();

        return (new CashSessionResource($session))->response()->setStatusCode(201);
    }

    public function show(CashSession $cashSession): CashSessionResource
    {
        return new CashSessionResource($cashSession->load(['caissier:id,first_name,last_name', 'site:id,name']));
    }

    /**
     * Écart de caisse = montant compté à la fermeture - (fonds de
     * démarrage + espèces réellement encaissées pendant la session).
     */
    public function close(CashSessionCloseRequest $request, CashSession $cashSession): CashSessionResource
    {
        abort_if($cashSession->statut !== 'ouverte', 422, 'Cette session est déjà fermée.');

        $montantCloture = (float) $request->validated('montant_cloture');
        $totalEspeces = (float) $cashSession->payments()->where('mode_paiement', 'especes')->sum('montant');
        $ecart = round($montantCloture - ((float) $cashSession->montant_ouverture + $totalEspeces), 2);

        $cashSession->update([
            'montant_cloture' => $montantCloture,
            'ecart' => $ecart,
            'fermee_le' => now(),
            'statut' => 'fermee',
        ]);

        return new CashSessionResource($cashSession);
    }
}
