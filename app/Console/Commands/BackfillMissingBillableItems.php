<?php

namespace App\Console\Commands;

use App\Domain\BlocOperatoire\Models\SurgicalProcedure;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Dialyse\Models\DialysisSession;
use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Hospitalisation\Models\Hospitalization;
use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Kinesitherapie\Models\KineSession;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Shared\Billing\Billable;
use App\Domain\Shared\Billing\BillingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * One-off correction for the bug fixed alongside this command:
 * BillingService::recordService() used to return null and create nothing
 * when no active ServiceTariff matched yet, while still being a one-shot
 * hook fired exactly once at act completion — so a tariff added later
 * never retroactively produced the missing BillableItem on its own.
 *
 * Scans every Billable-implementing table for acts that reached their own
 * completion state but have no matching billable_items row, and re-runs
 * recordService() for each — which now always creates a line (a proper one
 * if a tariff exists today, an 'a_tarifer' stub otherwise). Safe to run
 * more than once: the unique index on (billable_type, billable_id) added
 * alongside this fix means an act already billed is never picked up again.
 */
class BackfillMissingBillableItems extends Command
{
    protected $signature = 'billing:backfill-missing {--dry-run : List the affected acts without creating anything}';

    protected $description = 'Crée rétroactivement le BillableItem des actes cliniques terminés qui n\'en ont jamais eu (bug de facturation silencieuse).';

    /**
     * @var array<class-string<Model&Billable>, array{0: string, 1: string}|null>
     *      null means the act is billable as soon as it exists (Dialyse/Kiné : une session = un acte facturé à la création).
     */
    private const COMPLETION_FILTER = [
        Consultation::class => ['status', 'terminee'],
        LabOrder::class => ['status', 'transmis'],
        ImagingOrder::class => ['status', 'transmis'],
        Hospitalization::class => ['status', 'sorti'],
        SurgicalProcedure::class => ['status', 'terminee'],
        DialysisSession::class => null,
        KineSession::class => null,
    ];

    public function handle(BillingService $billing): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach (self::COMPLETION_FILTER as $modelClass => $filter) {
            $alreadyBilledIds = BillableItem::where('billable_type', $modelClass)->pluck('billable_id');

            $query = $modelClass::query()->whereNotIn('id', $alreadyBilledIds);
            if ($filter !== null) {
                [$column, $value] = $filter;
                $query->where($column, $value);
            }

            foreach ($query->get() as $record) {
                $total++;
                $this->line(sprintf(
                    '%s #%d — patient #%d%s',
                    class_basename($modelClass),
                    $record->id,
                    $record->billingPatientId(),
                    $dryRun ? ' [dry-run]' : ''
                ));

                if (! $dryRun) {
                    $item = $billing->recordService($record);
                    $this->line('  -> billable_item #'.$item?->id.' ('.$item?->statut.')');
                }
            }
        }

        $this->info($total === 0
            ? 'Aucun acte terminé sans ligne de facturation trouvé.'
            : "{$total} acte(s) traité(s)."
        );

        return self::SUCCESS;
    }
}
