# Spécification technique — Applications mobiles Sanitaire

> Document destiné à un développeur mobile externe qui n'a pas accès au code backend/frontend web. **Règle de rédaction : rien n'a été inventé.** Chaque endpoint, payload, message d'erreur et token de design cité ici a été vérifié directement dans le code source (fichier + ligne indiqués). Tout ce qui n'a pas pu être vérifié ou qui reste ambigu est explicitement marqué **« À clarifier avec l'équipe backend »** plutôt que deviné.

---

## 1. Vue d'ensemble

**Sanitaire** est un système de gestion hospitalière/clinique multi-structures (multi-tenant) : un backend API Laravel (PHP) sert un frontend web React/Vite utilisé par le personnel (staff), et gère les données de plusieurs modules cliniques (consultations, laboratoire, imagerie, hospitalisation, bloc opératoire, spécialités...), logistiques (pharmacie/stocks, achats, équipements biomédicaux) et administratifs (facturation, RH, notifications).

Deux applications mobiles sont attendues, consommant la **même API REST** que le frontend web, mais avec des guards d'authentification et des permissions différents :

1. **Application patient** — rendez-vous, documents/résultats, notifications, paiement. S'appuie sur le guard `patient` et les routes `/portail-patient/*`.
2. **Application professionnelle** — agenda, patients, alertes, accès clinique filtré par permission. S'appuie sur le guard `sanctum` (personnel) et la quasi-totalité des routes API du personnel, filtrées côté client selon les permissions retournées au login (voir §4).

L'API est servie sans préfixe de version (pas de `/v1`) sous `{APP_URL}/api`.

**URL de base en développement** : `http://127.0.0.1:8000/api` — c'est la valeur exacte utilisée par le frontend web (`frontend/.env` et `frontend/.env.example`, variable `VITE_API_BASE_URL=http://127.0.0.1:8000/api`).

**Configuration par environnement** : il n'existe **aucune configuration mobile dédiée** dans le dépôt (pas de fichier `.env` mobile, pas de convention de nommage établie pour staging/production). Le backend ne définit que `APP_URL` (`.env.example:5`, `APP_URL=http://localhost` en développement) ; l'URL de l'API en production sera `{APP_URL_PROD}/api`, à obtenir auprès de l'équipe qui gère le déploiement. **À clarifier avec l'équipe backend** : URL de staging/production réelles, et si un versionnement d'API (`/api/v1`) sera introduit avant l'ouverture au mobile.

Il n'existe pas de configuration CORS dans le dépôt (`config/cors.php` absent) — cela ne concerne de toute façon pas un client mobile natif (le CORS ne s'applique qu'aux requêtes navigateur), seulement le frontend web.

---

## 2. Authentification

### 2.1 Les trois guards

Le backend définit 3 guards Sanctum pertinents (`config/auth.php:42-62`), chacun avec son propre provider (table) et ses propres routes :

| Guard | Provider (table) | Modèle | Utilisé par |
|---|---|---|---|
| `sanctum` (guard par défaut) | `users` | `App\Domain\User\Models\User` | **App professionnelle** — tout le personnel (médecin, infirmier, secrétaire, direction, etc.), quel que soit le rôle métier |
| `patient` | `patients` | `App\Domain\Patient\Models\Patient` | **App patient** |
| `prescriber` | `external_prescribers` | `App\Domain\Prescripteur\Models\ExternalPrescriber` | Portail prescripteur externe — hors périmètre des deux apps mobiles demandées, mentionné pour information seulement |

Chaque guard utilise Sanctum en mode **Bearer token** (pas de cookies de session) — c'est le mode adapté à un client mobile natif. Le token doit être envoyé sur chaque requête authentifiée dans l'en-tête :
```
Authorization: Bearer {token}
```

### 2.2 App professionnelle — guard `sanctum` (`AuthController.php`)

| Action | Méthode + route | Auth requise |
|---|---|---|
| Login | `POST /auth/login` | aucune |
| Logout | `POST /auth/logout` | `auth:sanctum,tenant` |
| Profil courant | `GET /auth/me` | `auth:sanctum,tenant` |
| Mot de passe oublié | `POST /auth/forgot-password` | aucune |
| Réinitialisation | `POST /auth/reset-password` | aucune |
| Refresh token | **n'existe pas** — voir §2.5 | — |

**Payload login** (`AuthController.php:22-25`) :
```json
{ "email": "user@sainte-marie.demo", "password": "password" }
```

**Réponse succès (200)** — cas nominal sans 2FA (`AuthController.php:71-78`) :
```json
{
  "token": "1|abcdef123456...",
  "user": {
    "id": 4,
    "structure_id": 1,
    "first_name": "Yves",
    "last_name": "Brou",
    "email": "medecin@sainte-marie.demo",
    "phone": null,
    "photo_path": null,
    "is_active": true,
    "last_login_at": "2026-08-22T10:00:00.000000Z",
    "two_factor_enabled": false,
    "two_factor_required": false,
    "roles": ["medecin"],
    "permissions": ["patients.view", "patients.create", "..."],
    "sites": null,
    "created_at": "...",
    "updated_at": "..."
  },
  "two_factor_setup_required": false
}
```
(Champs `user` = `UserResource`, voir `app/Http/Resources/UserResource.php:12-29` — `permissions` contient **la liste complète des permissions effectives** de l'utilisateur, calculée côté serveur : c'est la source de vérité à utiliser côté mobile pour afficher/masquer les actions, voir §4.)

**Réponse quand la 2FA est déjà activée** (200, pas de token — `AuthController.php:56-59`) :
```json
{ "two_factor_required": true, "challenge": "aZ3f...40caractères" }
```
Le challenge est valide **5 minutes** et doit être échangé via `/auth/2fa/challenge` (§2.4).

**Erreurs** :
- `422 { "message": "Identifiants invalides." }` — email/mot de passe incorrect, ou compte inactif (`AuthController.php:30,43`).
- `423 { "message": "Compte verrouillé suite à trop de tentatives échouées. Réessayez plus tard.", "locked_until": "..." }` — après 5 tentatives échouées, verrouillage de 15 minutes (`ManagesAuthTokens.php:15-17`, `AuthController.php:33-38`).

### 2.3 App patient — guard `patient` (`PatientPortalAuthController.php`)

| Action | Méthode + route | Auth requise |
|---|---|---|
| Activation du compte | `POST /portail-patient/activer` | aucune |
| Login | `POST /portail-patient/login` | aucune |
| Logout | `POST /portail-patient/logout` | `auth:patient,tenant:patient` |
| Profil courant | `GET /portail-patient/me` | `auth:patient,tenant:patient` |
| Mot de passe oublié | `POST /portail-patient/mot-de-passe-oublie` | aucune |
| Réinitialisation | `POST /portail-patient/reinitialiser-mot-de-passe` | aucune |
| Refresh token | **n'existe pas** | — |

**Payload activation** (`PatientPortalAuthController.php:20-23`) — un patient reçoit un lien/token d'activation envoyé par le personnel via `POST /patients/{patient}/portal/send-activation` :
```json
{ "token": "...", "password": "MotDePasse123", "password_confirmation": "MotDePasse123" }
```

**Payload login** (`PatientPortalAuthController.php:32-35`) :
```json
{ "email": "patient@example.com", "password": "MotDePasse123" }
```
Refusé (422 « Identifiants invalides. ») si le compte n'est pas encore activé (`portal_activated_at` null, ligne 39).

**Réponse succès (200)** (`PatientPortalAuthController.php:49-52`) :
```json
{
  "token": "2|xyz...",
  "patient": {
    "id": 1,
    "structure_id": 1,
    "patient_number": "PT-0001-2026-000001",
    "first_name": "Verna",
    "last_name": "Bashirian",
    "sex": "F",
    "birth_date": "1990-01-01",
    "phone": "...",
    "email": "patient@example.com",
    "address": "...",
    "profession": null,
    "nationality": null,
    "emergency_contact_name": null,
    "emergency_contact_phone": null,
    "emergency_contact_relationship": null,
    "photo_path": null,
    "id_document_path": null,
    "portal_activated_at": "2026-08-20T10:00:00.000000Z",
    "created_at": "...",
    "updated_at": "..."
  }
}
```
(`PatientResource.php:12-35` — `medical_info`/`allergies` ne sont inclus que si explicitement chargés côté serveur, absents ici.)

**Aucun mécanisme 2FA sur ce guard** — aucune référence à `two_factor` dans `PatientPortalAuthController.php`.

### 2.4 2FA (TOTP) — obligatoire pour certains rôles du personnel uniquement

Concerne **uniquement le guard `sanctum`** (app professionnelle). Toutes les routes sont sous `/auth/2fa/*`, gérées par `TwoFactorController.php`.

| Action | Méthode + route | Auth |
|---|---|---|
| Générer secret + QR code | `POST /auth/2fa/setup` | `auth:sanctum,tenant` |
| Confirmer l'activation | `POST /auth/2fa/confirm` | `auth:sanctum,tenant` |
| Désactiver | `POST /auth/2fa/disable` | `auth:sanctum,tenant` |
| Échanger un challenge contre un token | `POST /auth/2fa/challenge` | **aucune** (volontaire — l'utilisateur n'a pas encore de token à ce stade) |

- **`setup`** → réponse `{ "secret": "...", "qr_code_url": "otpauth://..." }` (`TwoFactorController.php:43-46`). 422 si déjà activée.
- **`confirm`** — payload `{ "code": "123456" }` → réponse `{ "message": "2FA activée.", "recovery_codes": ["code1", "code2", ...] }` (`TwoFactorController.php:63-69`). **Les codes de récupération ne sont montrés qu'une seule fois**, l'app mobile doit permettre de les copier/exporter immédiatement. 422 `{"message":"Code invalide."}` si le code TOTP est faux.
- **`disable`** — payload `{ "password": "..." }` → `{"message":"2FA désactivée."}`. Refusé (422) avec `{"message":"La 2FA est obligatoire pour ce rôle et ne peut pas être désactivée."}` si le rôle de l'utilisateur impose la 2FA (voir plus bas).
- **`challenge`** — payload `{ "challenge": "...", "code": "123456" }` **ou** `{ "challenge": "...", "recovery_code": "..." }` → même forme de réponse qu'un login réussi : `{ "token": "...", "user": {...} }` (`TwoFactorController.php:146-149`). Erreurs : `422 {"message":"Challenge invalide ou expiré."}`, `423` verrouillage (mêmes règles que le login), `422 {"message":"Code invalide."}`.

**Rôles à 2FA obligatoire** : le mécanisme technique (`User::requiresTwoFactor()`) est en place mais **la liste définitive des rôles concernés doit être confirmée avec l'équipe backend / chaque structure cliente** (`reste.md`, section 10 : *« Rôles soumis à la 2FA obligatoire — Mécanisme technique en place, à confirmer la liste définitive avec chaque structure cliente »*). D'après les comptes de démonstration observés, `direction` en fait partie. **À clarifier avec l'équipe backend** : liste exacte et complète des rôles concernés en production.

L'app mobile professionnelle doit gérer le flux complet : `login` → si `two_factor_required: true`, écran de saisie du code TOTP → `challenge` → token réel. Et après un login réussi normal, si `two_factor_setup_required: true` dans la réponse, rediriger vers l'écran d'activation de la 2FA (`setup` → `confirm`) avant de laisser accéder au reste de l'app — c'est le comportement du frontend web, imposé côté serveur par le middleware `two_factor` qui bloque tout le reste de l'API tant que la 2FA obligatoire n'est pas confirmée (voir `bootstrap/app.php:25`, alias `'two_factor' => EnsureTwoFactorSetupComplete::class`).

### 2.5 Révocation de session, durée de vie des tokens, refresh

- **Aucune expiration automatique** : `config/sanctum.php:53`, `'expiration' => null` — un token reste valide indéfiniment jusqu'à révocation explicite (logout ou révocation de session).
- **Aucun endpoint de refresh token n'existe**, sur aucun des 3 guards. L'app mobile doit donc soit conserver le token jusqu'à révocation, soit ré-authentifier l'utilisateur (login) si le token est révoqué côté serveur (401). **À clarifier avec l'équipe backend** : si un mécanisme de refresh/rotation est prévu avant l'ouverture au mobile (recommandé pour une app mobile stockant un token longue durée sur l'appareil).
- **Logout = révocation immédiate du token courant uniquement**, via `currentAccessToken()->delete()` — identique sur les 3 guards.
- **Gestion avancée des sessions — guard `sanctum` (personnel) uniquement** (`SessionController.php`, aucun équivalent pour `patient`/`prescriber`) :
  - `GET /auth/sessions` → liste des tokens actifs de l'utilisateur : `{ "data": [{ "id", "name", "ip_address", "user_agent", "last_used_at", "created_at", "is_current" }, ...] }`.
  - `DELETE /auth/sessions/{tokenId}` → révoque un token précis (utile pour « déconnecter cet appareil » depuis un autre appareil).
  - `POST /auth/sessions/revoke-others` → révoque tous les tokens sauf le courant.
  Ces routes existent pour les patients aussi. **À clarifier avec l'équipe backend** : demander l'ajout d'un endpoint équivalent pour `patient` si l'app patient doit offrir une gestion d'appareils connectés.
- **Verrouillage anti-bruteforce** (identique login classique et challenge 2FA) : 5 tentatives échouées → verrouillage 15 minutes (`ManagesAuthTokens.php:15-17`).

---

## 3. Endpoints API par module

Toutes les routes ci-dessous (sauf mention contraire) sont sous le groupe `auth:sanctum,tenant,two_factor` (`routes/api.php:165`) — donc réservées à l'**app professionnelle**. Les routes `/portail-patient/*` sont sous `auth:patient,tenant:patient` (`routes/api.php:109`) et sont celles consommées par l'**app patient**.

### 3.1 Patients

*Pertinence : app professionnelle (accès complet, selon permissions) ; app patient (lecture de son propre profil uniquement, via `/portail-patient/me`).*

| Endpoint | Permission | Description |
|---|---|---|
| `GET /patients` | `patients.view` | Liste paginée, recherche via `?search=` (nom, prénom, n° patient, téléphone) |
| `POST /patients` | `patients.create` | Création — voir champs ci-dessous |
| `GET /patients/{id}` | `patients.view` | Détail. Si l'utilisateur a `patients_medical.view`, inclut aussi `medical_info` et `allergies` |
| `PUT/PATCH /patients/{id}` | `patients.update` | Mise à jour |
| `DELETE /patients/{id}` | `patients.delete` | Suppression |
| `GET /patients/{id}/timeline` | `patients_medical.view` | Chronologie clinique agrégée (voir plus bas) |
| `POST /patients/{id}/portal/send-activation` | `patients.update` | Envoie le lien d'activation du portail patient |

**Payload de création** (`PatientRequest.php:17-33`) :
```json
{
  "first_name": "requis, string",
  "last_name": "requis, string",
  "sex": "requis, M ou F",
  "birth_date": "requis, date, <= aujourd'hui",
  "phone": "optionnel",
  "email": "optionnel, email",
  "address": "optionnel",
  "profession": "optionnel",
  "nationality": "optionnel",
  "emergency_contact_name": "optionnel",
  "emergency_contact_phone": "optionnel",
  "emergency_contact_relationship": "optionnel",
  "photo_path": "optionnel",
  "id_document_path": "optionnel"
}
```
Réponse `201` : `PatientResource` + `possible_duplicates` (liste de patients homonymes avec même date de naissance, pour alerter l'utilisateur sans bloquer la création — `PatientController.php:67-77`).

**`GET /patients/{id}/timeline`** — construite à la volée (pas de table dédiée), fusionne consultations clôturées, activité d'audit, résultats labo/imagerie **transmis**, hospitalisations terminées, interventions chirurgicales terminées, triés par date décroissante (`PatientController.php:126-203`) :
```json
{ "data": [
  { "type": "consultation", "date": "...", "summary": "Motif de consultation", "data": { /* ConsultationResource */ } },
  { "type": "lab_order", "date": "...", "summary": "Résultats de laboratoire transmis", "data": { /* LabOrderResource */ } }
] }
```
Types possibles : `consultation`, `activity`, `lab_order`, `imaging_order`, `hospitalization`, `surgical_procedure`.

### 3.2 Rendez-vous

*Pertinence : les deux apps.*

**App professionnelle** (`AppointmentController.php`) :

| Endpoint | Permission | Description |
|---|---|---|
| `GET /appointments?from=&to=&practitioner_id=&site_id=` | `appointments.view` | Vue calendrier, filtrable par plage de dates (par défaut la journée courante) |
| `POST /appointments` | `appointments.create` | Création |
| `GET /appointments/{id}` | `appointments.view` | Détail |
| `PUT/PATCH /appointments/{id}` | `appointments.update` | Modification |
| `POST /appointments/{id}/cancel` | `appointments.cancel` | Annulation (transition de statut, pas de suppression) |
| `GET /practitioners` | — | Liste des praticiens |

Payload création (validé par `AppointmentRequest`, champs observés dans le Resource) : `site_id`, `practitioner_id`, `starts_at`, `duration_minutes`, `reason`, `resource_name` (optionnel).

Réponse (`AppointmentResource.php:12-27`) :
```json
{
  "id": 1, "structure_id": 1, "site_id": 2, "patient_id": 1, "practitioner_id": 4,
  "appointment_series_id": null, "resource_name": null,
  "starts_at": "2026-08-25T09:00:00+00:00", "duration_minutes": 30,
  "reason": "Consultation de suivi", "status": "planifie", "is_recurring": false,
  "created_at": "...", "updated_at": "..."
}
```
Statuts observés : `planifie`, `annule` (et vraisemblablement d'autres transitions gérées côté clinique — **à clarifier avec l'équipe backend** la liste exhaustive des statuts).

Erreur 422 si conflit de créneau : `{"message":"Le praticien (ou la ressource) est déjà occupé sur ce créneau."}` ; ou si le praticien est indisponible selon son planning théorique RH : `{"message":"Praticien indisponible sur ce créneau : {raison}"}` — avec une dérogation possible (`force_override:true` dans le payload) réservée aux détenteurs de la permission `appointments.override_planning`.

**App patient** (`PatientPortalController.php`, préfixe `/portail-patient`) — le patient ne peut agir que sur ses **propres** rendez-vous, l'identité est dérivée du token, jamais d'un paramètre :

| Endpoint | Description |
|---|---|
| `GET /portail-patient/rendez-vous` | Liste paginée de ses propres RDV |
| `GET /portail-patient/creneaux-disponibles?practitioner_id=&from=&to=&duration_minutes=` | **Vrais créneaux libres** calculés côté serveur (planning RH théorique moins conflits déjà réservés) → `{"creneaux": ["2026-08-25T09:00:00+00:00", "2026-08-25T09:30:00+00:00", ...]}` |
| `POST /portail-patient/rendez-vous` | Création — payload `{ "site_id", "practitioner_id", "starts_at", "duration_minutes", "reason" }`. **Contrairement au flux staff, aucune dérogation n'est possible** : hors planning théorique, la demande est toujours refusée (`PatientPortalController.php:98-101`) |
| `GET /portail-patient/sites` | Sites actifs de sa structure, pour le choix du lieu |
| `GET /portail-patient/practitioners` | Praticiens ayant le rôle `medecin`, actifs |

### 3.3 File d'attente

*Pertinence : app professionnelle uniquement (écran back-office de la réception/accueil ; peu pertinent pour un patient mais peut informer un statut « en attente » côté app patient si demandé — à valider avec le produit).*

| Endpoint | Permission | Description |
|---|---|---|
| `GET /queue-entries?site_id=&service=&include_exited=` | `queue.view` | File active (statuts `en_attente`, `appele`, `en_consultation` par défaut) |
| `POST /queue-entries` | `queue.create` | Ajout d'une entrée |
| `PATCH /queue-entries/{id}/status` | `queue.update` | Payload `{ "status": "en_attente\|appele\|en_consultation\|sorti" }` |
| `GET /queue-entries-stats?from=&to=&site_id=` | `queue.view` | Temps d'attente moyen |

### 3.4 Consultations et diagnostic CIM

*Pertinence : app professionnelle (personnel clinique).*

| Endpoint | Permission | Description |
|---|---|---|
| `GET /consultations?patient_id=&practitioner_id=&status=` | `consultations.view` | Liste |
| `POST /consultations` | `consultations.create` | Création |
| `GET /consultations/{id}` | `consultations.view` | Détail |
| `PUT/PATCH /consultations/{id}` | `consultations.update` | Modification (refusée à 422 si déjà `terminee`) |
| `POST /consultations/{id}/close` | `consultations.validate` | Clôture — déclenche la facturation automatique des actes (`BillingService`) |
| `POST /consultations/{id}/diagnoses` | `consultations.validate` | Codage CIM — payload `{ "icd_code_id", "type", "status" }` |
| `PATCH /consultations/{id}/diagnoses/{diagnosisId}` | `consultations.validate` | Payload `{ "status": "provisoire\|confirme" }` uniquement (le code/libellé est figé une fois créé) |
| `GET /icd-codes`, `/icd-codes/{id}`, `/icd-codes/{id}/children`, `/icd-codes/{id}/equivalents`, `/icd-codes/stats` | `consultations.view` (implicite via contrôleur) | Référentiel CIM-10/CIM-11 (∼30 codes de démonstration seulement — voir §7) |

Réponse consultation (`ConsultationResource.php:12-68`) inclut `vitals` (poids, taille, IMC, température, tension, fréquence cardiaque, fréquence respiratoire, SpO2, glycémie, échelle de douleur), `clinical_exam`, `recommendations`, `diagnoses` (liste), et un bloc `specialty` conditionnel si la consultation est liée à une spécialité (maternité, dentaire, etc.) **et** que l'utilisateur a la permission `{specialty}.view` correspondante — sinon `null` même si la donnée existe (double filtrage, ne jamais s'y fier côté client pour la sécurité mais bien pour l'affichage).

### 3.5 Documents / résultats (labo, imagerie)

*Pertinence : app professionnelle (création/validation du côté clinique) ; app patient (lecture seule des résultats **transmis**).*

**App professionnelle** — laboratoire :

| Endpoint | Description |
|---|---|
| `GET /lab-orders`, `POST`, `GET /lab-orders/{id}`, `PUT` | Commandes d'analyses |
| `POST /lab-orders/{id}/cancel` | Annulation |
| `POST /lab-orders/{id}/samples` | Enregistrement d'un prélèvement |
| `POST /lab-samples/{id}/results` | Saisie d'un résultat |
| `PATCH /lab-results/{id}/validate-technique` | Validation technique |
| `PATCH /lab-results/{id}/validate-biologique` | Validation biologique |
| `PATCH /lab-results/{id}/transmit` | Transmission (rend le résultat visible côté portail patient) |

**App professionnelle** — imagerie : structure identique (`imaging-orders`, `imaging-studies`, `imaging-reports`, actions `cancel`/`transmit`/`validate`). Exemple réel d'un `ImagingOrder` transmis avec étude et compte rendu validé :
```json
{
  "id": 8, "structure_id": 1, "site_id": 2, "patient_id": 1,
  "exam_type": "scanner", "status": "valide", "billing_status": "pending",
  "ordered_at": "2026-08-25T14:26:19.000000Z", "notes": "...",
  "studies": [{
    "id": 4, "study_instance_uid": "IMG-8-...", "modality": "Scanner abdominal",
    "status": "valide",
    "report": {
      "id": 4, "content": "Examen scanner abdominal sans particularité...",
      "status": "valide", "validated_at": "2026-08-25T14:27:59.000000Z"
    }
  }],
  "patient": { "id": 1, "first_name": "Verna", "last_name": "Bashirian", "patient_number": "PT-0001-2026-000001" }
}
```

**App patient** — `GET /portail-patient/documents` (`PatientPortalController.php:207-246`) renvoie un DTO dédié, lisible pour un patient (libellés, pas d'ids bruts), **filtré strictement aux résultats déjà transmis/validés** — un résultat non transmis, même validé en interne, n'apparaît jamais ici :
```json
{
  "resultats_laboratoire": [{
    "id": 12, "type": "Glycémie à jeun", "date": "2026-08-20T10:00:00+00:00",
    "praticien": "Dr Nadège Bamba", "value": "0.92", "unit": "g/L",
    "reference_min": "0.70", "reference_max": "1.10", "interpretation": "normal"
  }],
  "comptes_rendus_imagerie": [{
    "id": 4, "type": "scanner", "date": "2026-08-25T14:27:59+00:00",
    "praticien": "Dr Aminata Koné", "content": "Examen scanner abdominal sans particularité..."
  }]
}
```
Pas de pagination sur cet endpoint (charge l'intégralité). **À clarifier avec l'équipe backend** si un volume important de résultats justifie d'en ajouter une avant l'ouverture au mobile. Il n'y a **pas d'endpoint dédié aux documents PDF** (pas d'export PDF implémenté, voir §7) — seul le contenu textuel du compte rendu est disponible actuellement.

### 3.6 Factures et paiements

*Pertinence : app professionnelle (caisse/comptabilité) ; app patient (consultation de ses factures + solde, paiement selon canal disponible).*

**App professionnelle** (`InvoiceController.php`, `PaymentController.php`) :

| Endpoint | Permission | Description |
|---|---|---|
| `GET /invoices?patient_id=&statut=` | `facturation.view` | Liste |
| `POST /invoices` | `facturation.create` | Génère une facture à partir de `billable_item_ids` déjà produits automatiquement par les modules cliniques — aucun montant n'est ressaisi manuellement |
| `GET /invoices/{id}` | `facturation.view` | Détail (lignes + paiements) |
| `POST /invoices/{id}/emit` | `facturation.validate` | Passage `brouillon` → `emise` |
| `POST /invoices/{id}/cancel` | `facturation.cancel` | Annulation (impossible si `payee`/`annulee`) |
| `GET /payments?invoice_id=` | `caisse.view` | Liste des paiements |
| `POST /payments` | `caisse.encaisser` | Enregistrement d'un paiement |

Payload paiement (`PaymentRequest.php:16-24`) :
```json
{
  "invoice_id": "requis, doit exister",
  "site_id": "requis, doit exister",
  "mode_paiement": "requis — especes | carte | virement | mobile_money",
  "reference_transaction": "optionnel — id de transaction opérateur",
  "statut_mobile_money": "optionnel — pending | confirmed | failed",
  "montant": "requis, numérique, >= 0.01"
}
```
Règle métier : un paiement `especes` exige une session de caisse ouverte pour ce caissier/site, sinon `422 {"message":"Aucune session de caisse ouverte : impossible d'encaisser un paiement en espèces."}`. Le statut de la facture est recalculé automatiquement après chaque paiement (`emise` → `partiellement_payee` → `payee`).

Réponse facture (`InvoiceResource.php:12-29`) :
```json
{
  "id": 1, "structure_id": 1, "site_id": 2, "patient_id": 1, "insurance_convention_id": null,
  "numero": "FAC-2026-000001", "date_emission": "2026-08-25",
  "montant_total": 15000, "montant_part_patient": 15000, "montant_part_assurance": 0,
  "statut": "emise",
  "items": [ /* InvoiceItemResource */ ],
  "payments": [ /* PaymentResource */ ]
}
```

**App patient** :

| Endpoint | Description |
|---|---|
| `GET /portail-patient/factures` | Liste paginée de ses propres factures |
| `GET /portail-patient/factures/{id}` | Détail — 404 (jamais 403) si la facture n'appartient pas au patient, pour ne pas confirmer son existence |
| `GET /portail-patient/solde` | `{"solde": 15000}` — somme du solde dû sur les factures `emise`/`partiellement_payee` |

**Aucun endpoint de paiement en ligne pour le patient n'existe** (`POST /payments` est réservé au personnel via `caisse.encaisser`). **À clarifier avec l'équipe backend** : si l'app patient doit permettre un paiement mobile money directement depuis le mobile, il faudra un nouvel endpoint patient-side — actuellement le mobile money n'est qu'un enregistrement de statut côté personnel (voir §7).

### 3.7 Notifications et préférences

*Pertinence : les deux apps, avec une limitation importante à connaître.*

| Endpoint | Description |
|---|---|
| `GET /notification-preferences` (staff) / `GET /portail-patient/preferences-notification` (patient) | Préférences de l'utilisateur courant |
| `PUT` (mêmes routes) | Mise à jour — payload `{ "canaux": ["email", "sms", "whatsapp", "push"] }` (`NotificationPreferenceRequest.php:16-19`), au moins un canal requis |
| `GET/PUT /patients/{id}/notification-preferences` | Staff gérant les préférences d'un patient (permission `patients.update`) |

Réponse (`NotificationPreferenceResource.php`) :
```json
{ "id": 1, "notifiable_type": "App\\Domain\\Patient\\Models\\Patient", "notifiable_id": 1, "canaux": ["email", "sms"] }
```
Absence de ligne enregistrée = préférences par défaut (`config/notifications.php:29`, actuellement `["email"]` seul).

**⚠️ Point critique pour le mobile — confirmé par lecture directe du code** : **il n'existe aucun endpoint pour lister/consulter les notifications déjà envoyées** (pas de `GET /notifications`, pas de marquage lu/non-lu). Seules les *préférences de canal* sont exposées. Une app mobile voulant afficher un centre de notifications in-app devra le demander comme nouveau développement backend.

**Canal push = entièrement simulé, aucune vraie intégration** (`PushChannel.php:10-20`) : pas d'appel réseau réel, juste un `Log::info`. Il n'existe **aucune colonne/mécanisme de jeton d'appareil** (`device_token`) dans le schéma actuel — le docblock du code le confirme explicitement (`PushChannel.php:14-19,31-36` : *« Nécessite aussi, côté client, la collecte d'un jeton d'appareil par notifiable (absente du socle actuel) »*). **Avant tout développement de notifications push mobiles, il faudra : (1) un vrai compte Firebase Cloud Messaging, (2) une nouvelle colonne/table pour stocker le jeton d'appareil par utilisateur/patient, (3) un endpoint pour l'enregistrer.** C'est un vrai chantier backend, pas une simple bascule de config — à planifier avec l'équipe backend en coordination avec le développement mobile (confirmé aussi par `reste.md` section 3).

---

## 4. Modèle de permissions

Basé sur **Spatie Laravel-Permission**, guard `sanctum` (donc uniquement pertinent pour l'app professionnelle — les patients n'ont pas de rôles/permissions Spatie).

**Mécanisme** : chaque contrôleur déclare ses permissions requises par action via `HasMiddleware` (ex. `PatientController.php:29-38`) — il n'y a **aucune** permission listée centralement dans `routes/api.php`. Les permissions suivent le motif `{module}.{action}` (ex. `patients.view`, `achats.approve`), avec quelques permissions spéciales hors grille standard (ex. `patients_medical.view`, `dashboards.medical`, `voice_dictation.create`).

**Pour le mobile** : la réponse de login/`me` (`UserResource`) contient déjà le tableau `permissions` = liste complète des permissions effectives de l'utilisateur connecté (`UserResource.php:25`, `$this->getAllPermissions()->pluck('name')`). **C'est la source de vérité à utiliser côté app pour afficher/masquer un bouton ou un écran** — jamais un calcul local basé sur le nom du rôle, car les permissions peuvent différer d'une structure cliente à l'autre (elles sont seedées, pas codées en dur dans le frontend web non plus).

### Rôles pertinents pour l'app professionnelle mobile (extrait — 29 rôles au total dans `RolePermissionSeeder.php:194-458`)

| Rôle | Permissions clés (résumé) |
|---|---|
| `administrateur` | toutes (`*`) |
| `direction` | vue/export transverse sur presque tous les modules, **jamais** `pma.*`/`sante_mentale.*` (confidentialité renforcée) |
| `directeur_medical` | idem direction + accès clinique complet + `pma.*`/`sante_mentale.*` (seul rôle non-spécialiste à y accéder) |
| `medecin` | patients, consultations, laboratoire/imagerie (création), hospitalisation, téléconsultation, IA (résumé/anomalies), dictée vocale |
| `infirmier` | patients (lecture/màj), consultations (création/màj), file d'attente, dictée vocale |
| `secretaire` | patients, rendez-vous, file d'attente, prescripteurs, réclamations |
| `caissier` | facturation (lecture), caisse (`caisse.encaisser`) |
| `comptable` | facturation complète, assurance, créances |
| `gestionnaire_stock`, `pharmacien`, `achats`, `biomedical` | modules logistiques (hors périmètre des apps mobiles décrites, mentionnés pour complétude) |
| `biologiste`, `technicien_laboratoire`, `radiologue`, `manipulateur_radio`, `chirurgien`, `anesthesiste`, et les rôles de spécialité (`gynecologue`, `dentiste`, `cardiologue`, `pediatre`, etc.) | accès clinique circonscrit à leur propre module |
| `conformite` | `audit.view` uniquement |

La liste complète (37 modules × actions + permissions spéciales) est dans `database/seeders/RolePermissionSeeder.php` — à ne pas dupliquer/recopier en dur côté mobile ; toujours lire `user.permissions` retourné par l'API.

---

## 5. Design system

Le frontend web n'utilise pas Tailwind config classique mais une définition inline (`frontend/src/index.css`, bloc `@theme`). Voici les tokens exacts.

### Couleurs (hex réels)

| Token | Valeur | Usage |
|---|---|---|
| `--color-bg` | `#0a0c12` | Fond de page (thème sombre uniquement, `color-scheme: dark` forcé) |
| `--color-surface` | `#12151d` | Cartes, panneaux |
| `--color-surface-hover` | `#1b1f2a` | État survol/actif |
| `--color-border` | `#242938` | Bordures standard |
| `--color-border-strong` | `#343b4e` | Bordures accentuées, scrollbar |
| `--color-text` | `#f4f5f7` | Texte principal |
| `--color-text-muted` | `#9aa1b2` | Texte secondaire |
| `--color-text-subtle` | `#5c6478` | Texte tertiaire, point neutre de badge |
| `--color-accent` | `#3d7eff` | Accent primaire (bleu) |
| `--color-accent-light` / `-dark` | `#6fa1ff` / `#2a5fd9` | Variantes |
| `--color-accent2` | `#8b6cf0` | Accent secondaire (violet) |
| `--color-accent2-light` / `-dark` | `#a894f5` / `#6b4fd1` | Variantes |
| `--color-success` | `#2fbf71` | Statuts positifs |
| `--color-warning` | `#e8a93b` | Statuts d'alerte |
| `--color-danger` | `#e4544c` | Statuts d'erreur/critiques |

L'app est en **thème sombre exclusivement** — pas de thème clair défini dans le code actuel.

### Typographies

- Titres (`h1`-`h6`) : **"Space Grotesk"**, fallback `"Segoe UI", sans-serif`, `letter-spacing: -0.01em`.
- Corps de texte : **"Inter"**, fallback `"Segoe UI", sans-serif`.
- Chiffres/données tabulaires, code : **"JetBrains Mono"**, fallback `"Consolas", monospace`.

### Espacement / rayons de bordure

**Aucune échelle personnalisée** (`--spacing-*`, `--radius-*`) n'existe dans `frontend/src/index.css` — le frontend web utilise l'échelle par défaut de Tailwind CSS partout (ex. le composant Badge utilise directement `rounded-full`). **À clarifier avec l'équipe backend/design** si une échelle spécifique est souhaitée pour le mobile, ou s'il faut simplement reprendre l'échelle Tailwind par défaut comme référence (4px/8px/12px/16px...).

### Patterns web à reprendre dans l'idiome mobile

**Badges de statut sémantique** (`frontend/src/components/ui/badge.tsx:11-25`) — 6 variantes, chacune = couleur de fond à 10% d'opacité + bordure à 30% + texte de la couleur pleine, avec un petit point (dot) de la même couleur :

| Variante | Couleur |
|---|---|
| `success` | vert `#2fbf71` |
| `warning` | orange `#e8a93b` |
| `danger` | rouge `#e4544c` |
| `accent` | bleu `#3d7eff` |
| `accent2` | violet `#8b6cf0` |
| `neutral` (défaut) | gris `#5c6478` |

À reprendre en mobile pour tous les statuts métier (rendez-vous, factures, résultats, commandes...) — c'est le langage visuel principal du produit pour communiquer un état sans texte long.

**Timeline patient** (`frontend/src/pages/patients/patient-timeline.tsx`) — liste chronologique verticale (ligne connectant des points), une icône distincte par type d'événement (stéthoscope pour consultation, flacon pour labo, scanner pour imagerie, lit pour hospitalisation, seringue pour intervention), alimentée par `GET /patients/{id}/timeline` (§3.1). Un équivalent mobile (liste chronologique avec icônes/couleurs par type) est recommandé pour la vue dossier patient de l'app professionnelle.

---

## 6. Fonctionnalités attendues par app

### App patient

| Fonctionnalité | Endpoint(s) | Statut |
|---|---|---|
| Connexion / activation de compte | `/portail-patient/login`, `/activer`, mot de passe oublié | ✅ Supporté |
| Consultation du profil | `GET /portail-patient/me` | ✅ Supporté |
| Prise de rendez-vous avec créneaux réels | `GET /creneaux-disponibles`, `POST /rendez-vous` | ✅ Supporté |
| Liste de ses rendez-vous | `GET /rendez-vous` | ✅ Supporté |
| Annulation de rendez-vous par le patient | — | ❌ **Non supporté** — aucune route d'annulation côté `/portail-patient/*` (l'annulation `POST /appointments/{id}/cancel` est réservée au staff, permission `appointments.cancel`). **À clarifier avec l'équipe backend.** |
| Documents/résultats (labo transmis, comptes rendus imagerie validés) | `GET /portail-patient/documents` | ✅ Supporté (texte uniquement, pas de PDF — voir §7) |
| Consultation de ses factures + solde | `GET /factures`, `/factures/{id}`, `/solde` | ✅ Supporté |
| Paiement en ligne (mobile money, carte) | — | ❌ **Non supporté actuellement** — pas d'endpoint de paiement côté patient ; le paiement mobile money existant n'est qu'un enregistrement de statut fait par un caissier (voir §7). Nécessite un nouveau développement backend + une vraie intégration opérateur. |
| Préférences de notification | `GET/PUT /preferences-notification` | ✅ Supporté (choix des canaux uniquement) |
| Notifications in-app (centre de notifications) | — | ❌ **Non supporté** — aucun endpoint de liste des notifications envoyées (voir §3.7) |
| Notifications push | — | ⚠️ **Structure prête, envoi simulé** — nécessite FCM + jeton d'appareil (chantier backend, voir §7) |

### App professionnelle

| Fonctionnalité | Endpoint(s) | Statut |
|---|---|---|
| Connexion + 2FA obligatoire selon rôle | `/auth/login`, `/auth/2fa/*` | ✅ Supporté |
| Agenda / vue calendrier des rendez-vous | `GET /appointments`, création, modification, annulation | ✅ Supporté |
| Liste et fiche patients (filtrée par permission) | `/patients`, `/patients/{id}`, `/patients/{id}/timeline` | ✅ Supporté |
| File d'attente en temps réel | `/queue-entries*` | ✅ Supporté (pas de push temps réel — polling à prévoir côté mobile, pas de WebSocket identifié dans le dépôt) |
| Consultations + codage CIM | `/consultations*`, `/icd-codes*` | ✅ Supporté (référentiel CIM partiel en développement — voir §7) |
| Résultats labo/imagerie (saisie, validation, transmission) | `/lab-orders*`, `/imaging-orders*` | ✅ Supporté |
| Facturation et encaissement | `/invoices*`, `/payments` | ✅ Supporté |
| Alertes (stock bas, péremption, maintenance en retard...) | `/stock/alerts/*`, `/equipment-maintenances/overdue` | ✅ Supporté côté API, à intégrer comme notifications in-app côté mobile si souhaité |
| Accès clinique différencié par rôle/spécialité (`pma`, `sante_mentale`, etc.) | permissions dédiées par module | ✅ Supporté — toujours piloter l'affichage par `user.permissions`, jamais par le nom du rôle |
| Assistant IA (résumé de consultation, détection d'anomalies) | `POST /consultations/{id}/ai-summary`, `GET /consultations/{id}/anomalies` | ✅ Supporté (intégration Anthropic Claude réelle, mode dégradé si pas de clé configurée) — **validation humaine systématique obligatoire, jamais persisté automatiquement** |
| Dictée vocale | `/voice-dictations*` | ⚠️ Endpoint de statut prêt, **pas de vraie transcription** (voir §7) |

---

## 7. Contraintes connues

Extrait de `reste.md` (checklist officielle des intégrations volontairement laissées en placeholder), filtré aux points pertinents pour le développement mobile :

| Intégration | État actuel | Impact mobile |
|---|---|---|
| **Push mobile (FCM ou équivalent)** | Structure/driver prêt, **envoi simulé** (`Log::info` seulement), **aucune colonne de jeton d'appareil dans le schéma actuel** | Bloquant pour toute notification push réelle — nécessite un vrai compte Firebase + un nouveau champ/endpoint pour enregistrer le jeton d'appareil. À développer en coordination directe avec l'équipe mobile. |
| **SMS / WhatsApp** | Structure/driver prêt, envoi simulé | Pertinent si l'app doit déclencher/recevoir des rappels par ces canaux — nécessite un compte fournisseur réel |
| **Email** | **Seul canal réellement fonctionnel** aujourd'hui (juste une bascule de config SMTP pour la prod) | Le plus fiable en attendant les autres canaux |
| **Mobile Money (Wave / Orange Money / Free Money)** | Uniquement un champ de référence de transaction + statut simple (`pending/confirmed/failed`), **pas d'intégration réelle avec un opérateur**, confirmé dans le code (`database/migrations/2026_08_23_000012_create_payments_table.php:10-13`) | Le paiement mobile mentionné dans les specs produit n'est aujourd'hui qu'un enregistrement manuel fait par un caissier côté staff — **pas encore un vrai paiement initié depuis un mobile**. Nécessite compte marchand + clés API + nouvel endpoint patient-side avant tout développement de paiement in-app réel. |
| **Export PDF** (factures, comptes rendus, reçus) | **Non implémenté du tout** (CSV seulement pour certains rapports) | Aucun document imprimable/partageable disponible via l'API actuellement — à prévoir comme chantier backend séparé si l'app doit permettre de télécharger/partager une facture ou un compte rendu en PDF |
| **Dictée vocale (transcription)** | Endpoint de statut prêt, pas de vraie transcription | Fonctionnalité affichable mais non opérationnelle tant qu'un service de transcription n'est pas branché |
| **Référentiel CIM-10/CIM-11, LOINC** | ~30 codes de démonstration seulement chacun, pas le référentiel complet | Le codage clinique dans l'app mobile professionnelle sera limité à ce sous-ensemble tant que l'import complet n'est pas fait |

### Connexion instable / mode hors-ligne

**Aucune stratégie de cache/synchronisation hors-ligne n'existe côté backend** — l'API est un service REST classique sans mécanisme de synchronisation différée, de idempotency-key, ni d'endpoint de type "sync delta". **À clarifier avec l'équipe backend** si une stratégie offline-first est requise pour l'une des deux apps (particulièrement pertinent pour l'app professionnelle en zone à connectivité faible) — cela nécessiterait probablement des endpoints dédiés (sync incrémental par timestamp, gestion de conflits) qui n'existent pas aujourd'hui. En l'absence de ce mécanisme, la recommandation par défaut est un cache local en lecture seule (dernières données chargées) avec retry/queue des écritures côté client, sans garantie de résolution de conflit serveur.

### Sécurité / conformité

Les données `pma` (procréation médicalement assistée) et `sante_mentale` bénéficient d'une confidentialité renforcée côté permissions (exclues même du rôle `direction`). Toute vue mobile affichant ces données doit respecter strictement `user.permissions`, ne jamais les précharger/cacher localement sur un appareil partagé sans vérification de permission à chaque affichage.

---

## 8. Comptes de test

⚠️ **Ces comptes sont des données de démonstration pour l'environnement de développement local uniquement. Ne jamais les utiliser en production — mot de passe identique et connu pour tous.**

### App professionnelle (guard `sanctum`)

Mot de passe pour **tous** les comptes ci-dessous : **`password`** (`database/seeders/DemoDataSeeder.php`). Structure démo : *Polyclinique Sainte-Marie* (`DEMO-001`), 3 sites (Plateau, Cocody, Marcory).

| Rôle | Email |
|---|---|
| administrateur | admin@sainte-marie.demo |
| direction (2FA obligatoire) | direction@sainte-marie.demo |
| directeur_medical | dirmed@sainte-marie.demo |
| medecin | medecin@sainte-marie.demo |
| infirmier | infirmier@sainte-marie.demo |
| secretaire | secretaire@sainte-marie.demo |
| caissier | caissier@sainte-marie.demo |
| technicien_laboratoire | technicien.labo@sainte-marie.demo |
| biologiste | biologiste@sainte-marie.demo |
| manipulateur_radio | manip.radio@sainte-marie.demo |
| radiologue | radiologue@sainte-marie.demo |
| chirurgien | chirurgien@sainte-marie.demo |
| anesthesiste | anesthesiste@sainte-marie.demo |
| gestionnaire_stock | gestionnaire.stock@sainte-marie.demo |
| pharmacien | pharmacien@sainte-marie.demo |
| achats | achats@sainte-marie.demo |
| biomedical | biomedical@sainte-marie.demo |

### App patient (guard `patient`)

**⚠️ Aucun compte patient de démonstration prêt à l'emploi n'existe actuellement.** Les patients créés par le seeder (`DemoDataSeeder.php`, 8 patients par structure via `Patient::factory()`) n'ont **ni portail activé, ni mot de passe défini** — se connecter au portail patient avec l'un d'eux est actuellement impossible en l'état. **À clarifier avec l'équipe backend** : demander soit l'ajout d'un compte patient de démo activé (état `withPortalActivated()` déjà présent dans `PatientFactory.php`, juste non utilisé par le seeder), soit créer manuellement un patient activé via l'API staff (`POST /patients` puis `POST /patients/{id}/portal/send-activation`) avant de démarrer le développement de l'app patient.

### Portail prescripteur externe (guard `prescriber`)

Hors périmètre des deux apps mobiles demandées. Pour information : aucun compte de démo n'existe non plus pour ce guard (la factory correspondante `ExternalPrescriberFactory` avec état `withPortalActivated()` existe mais n'est appelée par aucun seeder de production).
