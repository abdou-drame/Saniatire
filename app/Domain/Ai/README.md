# Assistance IA (Étape 9 §4)

## Règle non négociable

**Aucune suggestion générée par ce module ne peut être enregistrée comme
donnée clinique définitive sans validation explicite et tracée d'un
praticien.** Concrètement :

- `AiAssistanceController::summary()` renvoie un texte (`summary`) et
  `"persisted": false` — il n'écrit jamais dans `Consultation` ni ailleurs.
  La seule façon de faire entrer ce texte au dossier est un geste distinct
  et explicite du praticien : appeler l'endpoint standard de mise à jour de
  la consultation (`PUT /api/consultations/{consultation}`, déjà tracé par
  `LogsActivity`), en recopiant (éventuellement modifié) le texte proposé.
- La génération elle-même est tracée (`activity('ai')->event('ia_suggestion_generee')`),
  pour distinguer dans le journal d'audit « une suggestion a été générée »
  de « le praticien l'a validée » (cette seconde trace vient de
  `LogsActivity` sur `Consultation`, séparément).
- `AiAssistanceController::anomalies()` est une fonction de lecture pure :
  elle ne modifie jamais rien, quel que soit son résultat.

## `AiProvider` — fournisseur générique

`App\Domain\Ai\Contracts\AiProvider` est la seule interface connue du
reste de l'application. Deux implémentations :

- `AnthropicAiProvider` — appelle l'API Messages d'Anthropic (`Http`
  facade, pas de SDK dédié). Résolue si `config('services.anthropic.key')`
  est renseignée.
- `SimulatedAiProvider` — comportement dégradé : aucune clé configurée,
  aucun appel réseau, un texte clairement marqué `[Résumé simulé...]` est
  retourné. **Ne bloque jamais l'endpoint** — `POST .../ai-summary`
  répond normalement même sans clé API.

Le binding est fait dans `AppServiceProvider::register()`. Pour brancher un
nouveau fournisseur (ex. un autre modèle), créer une classe implémentant
`AiProvider` et l'y résoudre — aucun appelant ne change.

### Comportement en cas d'échec réel (clé invalide, timeout, API down)

`AiAssistanceController::summary()` capture toute exception du provider et
répond `503` avec un message explicite, sans jamais laisser remonter une
erreur 500 ni bloquer la consultation en cours — testé explicitement dans
`tests/Feature/AiAssistanceTest.php` (provider qui lève, endpoint qui
répond proprement).

## Détection d'anomalies — comparaison simple, pas de l'IA

`App\Domain\Ai\Services\AnomalyDetectionService::detectForConsultation()`
compare :

- les constantes vitales de la `Consultation` à des plages de référence
  **adulte fixes** (température, FC, FR, SpO2, PA systolique/diastolique) ;
- les résultats de labo rattachés (via `LabOrder.consultation_id`) à leurs
  propres `reference_min`/`reference_max`, recoupés avec le champ
  `interpretation` déjà saisi par le biologiste.

C'est de la comparaison arithmétique pure (`if $value < $min || $value >
$max`), aucun appel IA. **Limite explicite** : les plages vitales sont
fixes et adultes — aucun ajustement pédiatrique ou gériatrique n'est
appliqué. Un nouveau-né avec une FC de 130 bpm (normale pour son âge)
ressortira donc signalé comme anomalie par cette version. Évolution
possible : faire dépendre `VITAL_RANGES` de `Patient::age()` une fois un
besoin clinique concret formulé — non implémenté ici pour éviter des
plages pédiatriques approximatives non validées médicalement.

## Dictée vocale — structure uniquement

`voice_dictations` (migration, modèle `VoiceDictation`,
`VoiceDictationController`) ne fait que : accepter un fichier audio
(`POST /api/voice-dictations`, `multipart/form-data`, champ `audio`),
créer un enregistrement `status = en_attente`, et permettre de consulter
son statut (`GET /api/voice-dictations/{id}`). **Aucune transcription
réelle n'est effectuée** — `transcription` reste `null` et `status` reste
`en_attente` indéfiniment tant qu'aucun processus externe ne le fait
évoluer.

### Point d'extension (transcription réelle)

Pour brancher un vrai service de transcription (ex. Whisper API) sans
changer ce schéma : ajouter un job en file d'attente déclenché après
`store()`, qui lit `audio_path` depuis le disque `local`, appelle le
service de transcription, puis met à jour `transcription` et `status`
(`terminee` ou `echouee`). Le contrôleur et le modèle actuels n'ont besoin
d'aucune modification pour ça — seul un nouveau `Job`/`Listener` est à
ajouter, suivant le patron déjà utilisé pour les notifications
asynchrones du projet (`App\Domain\Notification\Listeners\*`).

## Permissions

| Action | Permission |
|---|---|
| Résumé de consultation | `ai.consultation_summary` |
| Détection d'anomalies | `ai.anomaly_detection` |
| Dépôt d'un audio | `voice_dictation.create` |
| Consultation du statut | `voice_dictation.view` |

Voir `RolePermissionSeeder::MEDICAL_PERMISSIONS` pour la justification de
chacune et `ROLE_PERMISSIONS` pour les rôles qui les portent (medecin,
infirmier, directeur_medical — cohérent avec les rôles ayant déjà accès en
écriture aux consultations).
