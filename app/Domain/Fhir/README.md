# Interopérabilité HL7 FHIR R4 (Étape 9 §1)

Ce module expose une vue **FHIR R4 en lecture seule** des ressources
cliniques existantes. Il ne remplace aucune API interne : c'est une couche
de conversion à la demande (`App\Domain\Fhir\Converters\*`), appelée par
des contrôleurs dédiés (`App\Http\Controllers\Api\Fhir\*`), elle-même
jamais persistée.

## Principe général

- Chaque convertisseur expose une méthode statique `convert(...)` (ou
  `fromXxx(...)` quand deux sources internes distinctes alimentent une même
  ressource FHIR) qui prend un modèle Eloquent déjà chargé et retourne un
  tableau PHP structurellement conforme à FHIR R4, prêt à être sérialisé en
  JSON.
- `Concerns\StripsEmptyFhirFields::clean()` retire récursivement les
  `null`/tableaux vides du résultat, pour ne jamais émettre de champ FHIR
  optionnel absent comme `null` (ce qui serait structurellement invalide).
- `Concerns\BuildsFhirBundle::bundle()` enveloppe une liste de ressources
  converties dans un `Bundle` FHIR de type `searchset`, utilisé par tous les
  endpoints `index` (recherche).
- Isolation multi-tenant : chaque contrôleur s'appuie sur le
  `TenantScope` déjà appliqué aux modèles source. Pour les modèles qui
  n'ont pas leur propre `structure_id` (`ConsultationDiagnosis`,
  `LabResult`), l'isolation est héritée via leur relation `BelongsTo` vers
  un parent déjà scopé — un enregistrement d'une autre structure ressort
  avec cette relation à `null`, d'où un `abort_if(...,404)` explicite dans
  le contrôleur plutôt qu'une simple absence d'erreur.
- Toutes les routes sont protégées par la permission `fhir.view` (voir
  `RolePermissionSeeder`).

## Tableau de correspondance

| Ressource FHIR       | Source interne                                   | Convertisseur                          | Endpoints |
|-----------------------|---------------------------------------------------|-----------------------------------------|-----------|
| `Patient`              | `Patient`                                          | `PatientFhirConverter`                  | `GET /fhir/Patient/{id}`, `GET /fhir/Patient?identifier=` |
| `Encounter`            | `Consultation`                                     | `EncounterFhirConverter`                | `GET /fhir/Encounter/{id}`, `GET /fhir/Encounter?patient=` |
| `Condition`            | `ConsultationDiagnosis`                            | `ConditionFhirConverter`                | `GET /fhir/Condition/{id}` |
| `Observation`          | `LabResult`                                        | `ObservationFhirConverter`              | `GET /fhir/Observation/{id}`, `GET /fhir/Observation?patient=` |
| `DiagnosticReport`     | `LabOrder` (labo) **ou** `ImagingReport` (imagerie) | `DiagnosticReportFhirConverter`         | `GET /fhir/DiagnosticReport/{id}` (id `lab-{id}` / `imaging-{id}`) |
| `ServiceRequest`       | `LabOrder` **ou** `ImagingOrder`                   | `ServiceRequestFhirConverter`           | `GET /fhir/ServiceRequest/{id}` (id `lab-{id}` / `imaging-{id}`) |
| `MedicationRequest`    | — | — | **Non implémenté** : voir « Écart de dépendance » ci-dessous |

## Identifiants composites (`DiagnosticReport`, `ServiceRequest`)

Deux sources internes distinctes alimentent chacune de ces deux ressources
FHIR (un rapport de laboratoire n'a pas la même table qu'un compte-rendu
d'imagerie ; de même pour les demandes). Plutôt que de créer deux
ressources FHIR différentes (non conforme à FHIR, qui n'a qu'un seul type
`DiagnosticReport`/`ServiceRequest`), l'identifiant de route porte un
préfixe qui désambiguïse la source :

- `lab-{id}` → `LabOrder#{id}`
- `imaging-{id}` → `ImagingReport#{id}` (DiagnosticReport) ou
  `ImagingOrder#{id}` (ServiceRequest)

Le contrôleur parse ce préfixe (`str_starts_with`/`substr`) et route vers
le bon modèle. Le champ `id` de la ressource JSON retournée reprend le même
identifiant composite, pour que le client puisse le réutiliser tel quel.

## Écart de dépendance : `MedicationRequest`

Le cahier des charges de l'Étape 9 demande la conversion `MedicationRequest`.
**Aucun module de prescription médicamenteuse n'existe dans ce projet** —
aucune table ne modélise une ordonnance/prescription de médicament (le
domaine `Pharmacie` couvre le stock, pas la prescription). Un convertisseur
`MedicationRequest` n'a donc rien à convertir : il est volontairement omis
plutôt que de produire une conversion fictive. Point d'extension : le jour
où un module de prescription est ajouté, créer
`MedicationRequestFhirConverter::convert(Prescription $prescription)` en
suivant exactement le même patron que les six convertisseurs existants
(mêmes traits `StripsEmptyFhirFields`/`BuildsFhirBundle`, même garde-fou de
tenant-isolation si le modèle source n'a pas de `structure_id` propre).

## Étendre à une nouvelle ressource FHIR

1. Créer `App\Domain\Fhir\Converters\{Ressource}FhirConverter` avec une
   méthode `convert()` statique, en utilisant `self::clean([...])` pour le
   tableau retourné.
2. Créer `App\Http\Controllers\Api\Fhir\{Ressource}Controller`,
   `implements HasMiddleware` avec `permission:fhir.view`, méthode `show()`
   (et `index()` avec `BuildsFhirBundle` si une recherche basique a du sens).
3. Ajouter la route dans `routes/api.php`, dans le bloc
   `// --- Étape 9 §1 : interopérabilité HL7 FHIR R4 ---`.
4. Ajouter une ligne au tableau de correspondance ci-dessus.
5. Ajouter un test de conformité structurelle dans `tests/Feature/FhirTest.php`
   (vérifier la présence des champs obligatoires FHIR R4 pour cette
   ressource, pas seulement l'absence d'erreur HTTP).

## Exemple — `Patient`

```json
{
  "resourceType": "Patient",
  "id": "42",
  "identifier": [{"system": "urn:sanitaire:patient-number", "value": "PT-000042"}],
  "name": [{"family": "Diallo", "given": ["Awa"]}],
  "gender": "female",
  "birthDate": "1990-04-12",
  "telecom": [{"system": "phone", "value": "+221771234567"}]
}
```

## Exemple — `Encounter`

```json
{
  "resourceType": "Encounter",
  "id": "17",
  "status": "finished",
  "class": {"system": "http://terminology.hl7.org/CodeSystem/v3-ActCode", "code": "AMB", "display": "ambulatory"},
  "subject": {"reference": "Patient/42"},
  "participant": [{"individual": {"reference": "Practitioner/5"}}],
  "period": {"start": "2026-08-20T09:12:00+00:00", "end": "2026-08-20T09:40:00+00:00"}
}
```
