<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\SubscriptionState;
use App\Domain\Structure\Models\Structure;
use App\Http\Controllers\Api\Platform\Concerns\AuditsPlatformActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Abonnements gérés manuellement par l'administration plateforme. Une
 * nouvelle période = une nouvelle ligne : une période existante n'est
 * jamais réécrite, seul son statut bascule entre actif et suspendu.
 * L'effet sur la structure (actif / grâce / lecture seule) est calculé par
 * SubscriptionState, jamais stocké.
 */
class PlatformSubscriptionController extends Controller
{
    use AuditsPlatformActions;

    public function index(Structure $structure): JsonResponse
    {
        $subscriptions = Subscription::query()
            ->with(['plan', 'creator'])
            ->where('structure_id', $structure->id)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

        $state = SubscriptionState::forStructure($structure->id);

        return SubscriptionResource::collection($subscriptions)
            ->additional(['current' => [
                'state' => $state->state,
                'subscription_id' => $state->subscription?->id,
            ]])
            ->response();
    }

    public function store(Request $request, Structure $structure): JsonResponse
    {
        abort_if($structure->trashed(), 409, 'Cette structure est archivée : elle reste consultable mais ne peut plus être modifiée.');

        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['essai', 'active'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subscription = Subscription::create([
            ...$data,
            'structure_id' => $structure->id,
            'created_by' => $request->user('platform')->id,
        ]);

        $this->auditPlatformAction($request, $subscription, $structure->id, 'creation_periode_abonnement', [
            'formule' => Plan::find($data['plan_id'])?->code,
            'starts_at' => $subscription->starts_at->toDateString(),
            'ends_at' => $subscription->ends_at->toDateString(),
            'status' => $subscription->status,
        ]);

        return (new SubscriptionResource($subscription->load(['plan', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function suspend(Request $request, Subscription $subscription): SubscriptionResource
    {
        abort_unless(in_array($subscription->status, ['essai', 'active'], true), 409, "Seule une période en essai ou active peut être suspendue.");

        return $this->changeStatus($request, $subscription, 'suspendue', 'suspension_abonnement');
    }

    /**
     * Reprise en statut `active` : une période suspendue ne retourne pas en
     * essai (l'ancien statut reste visible dans le journal de la suspension).
     */
    public function resume(Request $request, Subscription $subscription): SubscriptionResource
    {
        abort_unless($subscription->status === 'suspendue', 409, "Seule une période suspendue peut être reprise.");

        return $this->changeStatus($request, $subscription, 'active', 'reprise_abonnement');
    }

    private function changeStatus(Request $request, Subscription $subscription, string $status, string $action): SubscriptionResource
    {
        abort_if($subscription->structure?->trashed(), 409, 'Cette structure est archivée : elle reste consultable mais ne peut plus être modifiée.');

        $previous = $subscription->status;
        $subscription->update(['status' => $status]);

        $this->auditPlatformAction($request, $subscription, $subscription->structure_id, $action, [
            'ancien_statut' => $previous,
            'nouveau_statut' => $status,
        ]);

        return new SubscriptionResource($subscription->load(['plan', 'creator']));
    }
}
