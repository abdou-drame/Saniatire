<?php

namespace App\Http\Controllers\Api;

use App\Domain\Caisse\Models\CashSession;
use App\Domain\Caisse\Models\Payment;
use App\Domain\Facturation\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:caisse.view', only: ['index']),
            new Middleware('permission:caisse.encaisser', only: ['store']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->orderByDesc('paid_at');

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        return PaymentResource::collection($query->paginate())->response();
    }

    /**
     * §3 cahier des charges : un paiement en espèces exige une session de
     * caisse ouverte pour ce caissier/site — pas de mouvement d'espèces en
     * dehors d'une caisse ouverte. Reprend le pattern verrouillage +
     * recalcul de PurchaseOrderReceptionController : la facture est
     * verrouillée, le paiement créé, puis son statut recalculé à partir de
     * la somme de tous ses paiements.
     */
    public function store(PaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $cashSession = null;

        if ($data['mode_paiement'] === 'especes') {
            $cashSession = CashSession::where('caissier_id', $request->user()->id)
                ->where('site_id', $data['site_id'])
                ->where('statut', 'ouverte')
                ->first();

            abort_if(! $cashSession, 422, "Aucune session de caisse ouverte : impossible d'encaisser un paiement en espèces.");
        }

        $payment = DB::transaction(function () use ($data, $request, $cashSession) {
            $invoice = Invoice::lockForUpdate()->findOrFail($data['invoice_id']);

            abort_if($invoice->statut === 'annulee', 422, 'Cette facture est annulée.');

            $payment = Payment::create([
                'site_id' => $data['site_id'],
                'invoice_id' => $invoice->id,
                'cash_session_id' => $cashSession?->id,
                'caissier_id' => $request->user()->id,
                'mode_paiement' => $data['mode_paiement'],
                'reference_transaction' => $data['reference_transaction'] ?? null,
                'statut_mobile_money' => $data['mode_paiement'] === 'mobile_money'
                    ? ($data['statut_mobile_money'] ?? 'pending')
                    : null,
                'montant' => $data['montant'],
                'paid_at' => now(),
            ]);

            $totalPaye = (float) $invoice->payments()->sum('montant');

            $invoice->update([
                'statut' => $totalPaye >= (float) $invoice->montant_total ? 'payee' : 'partiellement_payee',
            ]);

            return $payment;
        });

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }
}
