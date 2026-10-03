<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Platform\Models\Plan;
use App\Http\Controllers\Api\Platform\Concerns\AuditsPlatformActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grille des formules Saliha Health (insérée par la migration
 * create_plans_and_subscriptions_tables). Référence commerciale
 * uniquement : aucune logique de paiement, aucun lien avec les modules.
 */
class PlatformPlanController extends Controller
{
    use AuditsPlatformActions;

    /** Écran utilisé par l'équipe plateforme : messages lisibles en français. */
    private const MESSAGES = [
        'code.required' => 'Le code est obligatoire.',
        'code.alpha_dash' => 'Le code ne peut contenir que des lettres, chiffres, tirets et underscores (sans espace ni accent).',
        'code.unique' => 'Une formule utilise déjà ce code.',
        'code.max' => 'Le code ne doit pas dépasser 50 caractères.',
        'name.required' => 'Le nom est obligatoire.',
        'name.max' => 'Le nom ne doit pas dépasser 255 caractères.',
        '*.integer' => 'Le prix doit être un nombre entier en FCFA.',
        '*.min' => 'Le prix ne peut pas être négatif.',
    ];

    public function index(): JsonResponse
    {
        return PlanResource::collection(Plan::query()->orderBy('id')->get())->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:plans,code'],
            'name' => ['required', 'string', 'max:255'],
            'monthly_price_fcfa' => ['nullable', 'integer', 'min:0'],
            'annual_price_fcfa' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $plan = Plan::create([...$data, 'is_active' => $data['is_active'] ?? true]);

        $this->auditPlatformAction($request, $plan, null, 'creation_formule', [
            'code' => $plan->code,
            'name' => $plan->name,
        ]);

        return (new PlanResource($plan))->response()->setStatusCode(201);
    }

    /**
     * Le code reste fixe (identifiant stable dans le journal). Les périodes
     * déjà enregistrées gardent leur formule ; is_active=false retire
     * seulement la formule du choix pour les nouvelles périodes.
     */
    public function update(Request $request, Plan $plan): PlanResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'monthly_price_fcfa' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'annual_price_fcfa' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], self::MESSAGES);

        $before = $plan->only(array_keys($data));
        $plan->update($data);

        $this->auditPlatformAction($request, $plan, null, 'modification_formule', [
            'code' => $plan->code,
            'avant' => $before,
            'apres' => $plan->only(array_keys($data)),
        ]);

        return new PlanResource($plan);
    }
}
