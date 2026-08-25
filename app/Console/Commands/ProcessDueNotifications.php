<?php

namespace App\Console\Commands;

use App\Domain\Facturation\Models\Invoice;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Programmée via Schedule::command() dans routes/console.php. Deux
 * responsabilités :
 *  1. Envoyer les notifications déjà en base dont scheduled_for est échu
 *     (couvre les rappels de RDV programmés par ScheduleRendezVousRappel).
 *  2. Détecter les factures impayées dont l'échéance approche et créer +
 *     envoyer un rappel "facture_echeance" (une seule fois par facture,
 *     via une vérification d'existence avant création).
 *
 * Tourne sans utilisateur authentifié (contexte console) : TenantScope ne
 * filtre donc aucune structure ici, exactement ce qu'il faut pour un
 * balayage global — même comportement que les seeders.
 *
 * Rappel de vaccination : non implémenté. pediatric_vaccinations ne porte
 * qu'administered_at (date de l'injection déjà faite), aucune notion de
 * prochaine dose due / calendrier vaccinal n'existe dans le socle
 * aujourd'hui — dépendance documentée, pas de calendrier vaccinal inventé
 * ici.
 */
class ProcessDueNotifications extends Command
{
    protected $signature = 'notifications:process-due';

    protected $description = 'Envoie les notifications programmées échues et génère les rappels d\'échéance de facture.';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $sent = $this->sendDueNotifications($dispatcher);
        $created = $this->createInvoiceEcheanceReminders($dispatcher);

        $this->info("{$sent} notification(s) envoyée(s), {$created} rappel(s) d'échéance de facture créé(s).");

        return self::SUCCESS;
    }

    private function sendDueNotifications(NotificationDispatcher $dispatcher): int
    {
        $due = Notification::query()
            ->where('statut', 'en_attente')
            ->where('scheduled_for', '<=', now())
            ->get();

        foreach ($due as $notification) {
            $dispatcher->attemptSend($notification);
        }

        return $due->count();
    }

    private function createInvoiceEcheanceReminders(NotificationDispatcher $dispatcher): int
    {
        $joursAvant = config('notifications.rappel_facture_jours_avant');
        $seuil = now()->addDays($joursAvant)->toDateString();

        $invoices = Invoice::query()
            ->whereNotIn('statut', ['payee', 'annulee', 'brouillon'])
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<=', $seuil)
            ->get();

        $created = 0;

        foreach ($invoices as $invoice) {
            if (! $invoice->patient) {
                continue;
            }

            // Pas de colonne dédiée "déjà notifiée" sur invoices : on
            // vérifie plutôt la présence du numéro de facture dans le
            // contenu déjà rendu, pour éviter un rappel en double à
            // chaque exécution de la commande.
            $alreadyNotified = Notification::query()
                ->where('type_evenement', 'facture_echeance')
                ->where('notifiable_type', $invoice->patient::class)
                ->where('notifiable_id', $invoice->patient_id)
                ->where('contenu_final', 'like', '%'.($invoice->numero ?? "#{$invoice->id}").'%')
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $dispatcher->send($invoice->patient, 'facture_echeance', [
                'patient_nom' => trim($invoice->patient->first_name.' '.$invoice->patient->last_name),
                'facture_numero' => $invoice->numero ?? "#{$invoice->id}",
                'date_echeance' => $invoice->date_echeance->format('d/m/Y'),
                'montant_restant' => number_format((float) $invoice->montant_total, 0, ',', ' '),
            ]);

            $created++;
        }

        return $created;
    }
}
