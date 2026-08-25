<?php

namespace Database\Seeders;

use App\Domain\Notification\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * Templates par défaut globaux (structure_id = null) : filet de sécurité
 * utilisé par NotificationDispatcher::resolveTemplate() quand une structure
 * n'a pas défini son propre template pour un type_evenement + canal donné.
 * Canal email uniquement ici (le seul canal réellement fonctionnel par
 * défaut) — une structure peut ajouter ses propres templates sms/whatsapp/
 * push via NotificationTemplateController.
 */
class NotificationTemplateSeeder extends Seeder
{
    private const TEMPLATES = [
        [
            'type_evenement' => 'rdv_cree',
            'sujet' => 'Confirmation de votre rendez-vous',
            'contenu' => 'Bonjour {patient_nom}, votre rendez-vous du {date_rdv} avec {praticien_nom} est confirmé.',
        ],
        [
            'type_evenement' => 'rdv_modifie',
            'sujet' => 'Votre rendez-vous a été modifié',
            'contenu' => 'Bonjour {patient_nom}, votre rendez-vous a été déplacé au {date_rdv} avec {praticien_nom}.',
        ],
        [
            'type_evenement' => 'rdv_annule',
            'sujet' => 'Votre rendez-vous a été annulé',
            'contenu' => 'Bonjour {patient_nom}, votre rendez-vous du {date_rdv} a été annulé.',
        ],
        [
            'type_evenement' => 'rdv_rappel',
            'sujet' => 'Rappel de rendez-vous',
            'contenu' => 'Bonjour {patient_nom}, rappel : vous avez rendez-vous le {date_rdv} avec {praticien_nom}.',
        ],
        [
            'type_evenement' => 'conge_valide',
            'sujet' => 'Votre demande de congé est validée',
            'contenu' => 'Bonjour {user_nom}, votre congé du {date_debut} au {date_fin} a été validé.',
        ],
        [
            'type_evenement' => 'resultat_disponible',
            'sujet' => 'Résultat disponible',
            'contenu' => 'Bonjour {patient_nom}, votre résultat de laboratoire est disponible.',
        ],
        [
            'type_evenement' => 'facture_echeance',
            'sujet' => "Rappel d'échéance de facture",
            'contenu' => 'Bonjour {patient_nom}, la facture {facture_numero} de {montant_restant} FCFA arrive à échéance le {date_echeance}.',
        ],
        // Étape 7b — portails externes
        [
            'type_evenement' => 'resultat_disponible_prescripteur',
            'sujet' => 'Résultat transmis pour votre patient',
            'contenu' => 'Bonjour, le résultat que vous avez demandé pour {patient_nom} est disponible.',
        ],
        [
            'type_evenement' => 'referencement_accepte',
            'sujet' => 'Référencement accepté',
            'contenu' => 'Bonjour {praticien_nom}, votre référencement du patient {patient_nom} vers {structure_destination_nom} a été accepté.',
        ],
        [
            'type_evenement' => 'referencement_refuse',
            'sujet' => 'Référencement refusé',
            'contenu' => 'Bonjour {praticien_nom}, votre référencement du patient {patient_nom} vers {structure_destination_nom} a été refusé.',
        ],
        [
            'type_evenement' => 'teleconsultation_rappel',
            'sujet' => 'Rappel de téléconsultation',
            'contenu' => 'Bonjour {patient_nom}, rappel : votre téléconsultation du {date_rdv} avec {praticien_nom} approche.',
        ],
        [
            'type_evenement' => 'patient_portal_activation',
            'sujet' => 'Activez votre accès au portail',
            'contenu' => 'Bonjour {patient_nom}, activez votre compte en suivant ce lien : {lien_activation}',
        ],
        [
            'type_evenement' => 'patient_password_reset',
            'sujet' => 'Réinitialisation de votre mot de passe',
            'contenu' => 'Bonjour {patient_nom}, réinitialisez votre mot de passe via ce lien : {lien_reinitialisation}',
        ],
        // Étape 8 — qualité/satisfaction
        [
            'type_evenement' => 'enquete_satisfaction',
            'sujet' => 'Votre avis nous intéresse',
            'contenu' => 'Bonjour {patient_nom}, merci de nous donner votre avis sur votre prise en charge ({service}).',
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $template) {
            NotificationTemplate::withoutGlobalScopes()->firstOrCreate(
                [
                    'structure_id' => null,
                    'type_evenement' => $template['type_evenement'],
                    'canal' => 'email',
                ],
                [
                    'sujet' => $template['sujet'],
                    'contenu' => $template['contenu'],
                    'actif' => true,
                ]
            );
        }
    }
}
