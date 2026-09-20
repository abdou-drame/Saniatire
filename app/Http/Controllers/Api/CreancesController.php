<?php

namespace App\Http\Controllers\Api;

use App\Domain\Facturation\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CreancesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:facturation.creances', only: ['balanceAgee']),
        ];
    }

    /**
     * §4 cahier des charges : balance âgée des créances, calculée à la
     * volée sur les factures non soldées (pas de table dédiée). Chaque
     * facture est classée par ancienneté depuis sa date d'émission.
     */
    public function balanceAgee(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->whereIn('statut', ['emise', 'partiellement_payee'])
            ->with(['payments', 'patient:id,first_name,last_name,patient_number', 'insuranceConvention.provider']);

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->filled('insurance_convention_id')) {
            $query->where('insurance_convention_id', $request->integer('insurance_convention_id'));
        }

        $buckets = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
        $lignes = [];

        foreach ($query->get() as $invoice) {
            $solde = $invoice->solde();

            if ($solde <= 0) {
                continue;
            }

            // Carbon 3's diffInDays() returns a signed float (negative for
            // past dates) instead of the pre-3.x absolute value — abs()
            // avoids every invoice collapsing into the "0-30" bucket.
            $anciennete = (int) floor(abs(now()->diffInDays($invoice->date_emission)));
            $bucket = match (true) {
                $anciennete <= 30 => '0-30',
                $anciennete <= 60 => '31-60',
                $anciennete <= 90 => '61-90',
                default => '90+',
            };

            $buckets[$bucket] = round($buckets[$bucket] + $solde, 2);

            $lignes[] = [
                'invoice_id' => $invoice->id,
                'numero' => $invoice->numero,
                'patient_id' => $invoice->patient_id,
                'patient_label' => $invoice->patient ? trim("{$invoice->patient->first_name} {$invoice->patient->last_name}") : null,
                'insurance_convention_id' => $invoice->insurance_convention_id,
                'insurance_provider_label' => $invoice->insuranceConvention?->provider?->nom,
                'date_emission' => $invoice->date_emission,
                'anciennete_jours' => $anciennete,
                'solde' => $solde,
                'bucket' => $bucket,
            ];
        }

        return response()->json([
            'buckets' => $buckets,
            'lignes' => $lignes,
        ]);
    }
}
