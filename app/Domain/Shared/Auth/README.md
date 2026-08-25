# Sécurité renforcée (Étape 9 §2)

## Authentification à deux facteurs (TOTP)

Implémentation : `pragmarx/google2fa` (TOTP pur PHP, aucun service tiers).
Secret et codes de récupération chiffrés en base (`encrypted`/
`encrypted:array` cast sur `User::two_factor_secret`/`two_factor_recovery_codes`
— voir `User::casts()`).

### Activable par tout utilisateur, obligatoire pour certains rôles

`User::ROLES_REQUIRING_TWO_FACTOR` :

- `administrateur`
- `direction`
- `directeur_medical`
- `specialiste_pma`
- `psychiatre`
- `psychologue`

Rationale : rôles à accès large (`administrateur`/`direction`/
`directeur_medical`) ou aux données les plus sensibles selon le cahier des
charges (PMA, santé mentale — déjà traitées avec une confidentialité
renforcée dans le système de permissions, voir `RolePermissionSeeder`).
Tout autre utilisateur peut activer la 2FA volontairement
(`POST /auth/2fa/setup`) mais n'y est pas contraint.

### Flux d'activation (résout le problème de l'œuf et la poule)

Un utilisateur à qui la 2FA est désormais obligatoire mais qui ne l'a pas
encore configurée doit pouvoir se connecter *juste assez* pour l'activer,
sans que cela ouvre un accès complet au reste de l'API :

1. `POST /auth/login` réussit normalement et retourne un token Sanctum
   complet, avec un flag `two_factor_setup_required: true` si le rôle
   l'exige et que la 2FA n'est pas encore confirmée.
2. Le middleware `two_factor` (`EnsureTwoFactorSetupComplete`, alias
   enregistré dans `bootstrap/app.php`) bloque (423) tout accès aux routes
   protégées tant que ce cas persiste.
3. Seules `/auth/logout`, `/auth/me` et `/auth/2fa/setup|confirm|disable`
   restent accessibles avec ce token restreint (groupe de routes séparé
   dans `routes/api.php`, volontairement hors du middleware `two_factor`).
4. Une fois `POST /auth/2fa/confirm` réussi, le token existant devient
   pleinement valide pour tout le reste de l'API — pas de reconnexion
   nécessaire.

### Flux de connexion pour un utilisateur ayant déjà la 2FA active

`POST /auth/login` ne délivre **aucun token** dans ce cas — seulement un
`challenge` temporaire (`Cache`, 5 minutes). Le client doit ensuite
appeler `POST /auth/2fa/challenge` avec ce `challenge` et un code TOTP (ou
un code de récupération à usage unique) pour obtenir un vrai token
Sanctum. Un compteur d'échecs (`ManagesAuthTokens::registerFailedAttempt`)
verrouille le compte après 5 tentatives infructueuses, comme pour le mot
de passe.

### Codes de récupération

8 codes générés à la confirmation (`User::confirmTwoFactor()`), affichés
une seule fois, chacun à usage unique (`User::consumeRecoveryCode()`
retire le code utilisé de la liste stockée).

## Gestion des sessions / connexions actives

`SessionController` (`GET /auth/sessions`, `DELETE /auth/sessions/{id}`,
`POST /auth/sessions/revoke-others`) expose les tokens Sanctum de
l'utilisateur connecté (IP, user-agent, dernière utilisation, indicateur
« session courante »), avec révocation individuelle ou groupée. Un token
révoqué est immédiatement rejeté par `auth:sanctum` (comportement standard
du package, aucune logique supplémentaire nécessaire).

## Chiffrement applicatif des champs sensibles

Cast Laravel `encrypted`/`encrypted:array` (pas de `Crypt::encrypt`/`decrypt`
manuel) sur les champs texte libre les plus sensibles :

| Modèle | Champs chiffrés |
|---|---|
| `MentalHealthRecord` | `consultation_reason`, `clinical_evaluation`, `ongoing_treatment` |
| `PmaRecord` | `fertility_history`, `exams_performed` |
| `PmaCycleMonitoring` | `echo_observations` |
| `PmaStimulationProtocol` | `medications` |

Chacun de ces modèles déclare aussi `logExcept([...])` sur les mêmes
champs dans `getActivitylogOptions()` : sans cela, `LogsActivity`
écrirait les valeurs **en clair** dans `activity_log.properties` malgré
le chiffrement en base — le journal d'audit serait alors la fuite, pas la
table elle-même. Ce point n'a pas été demandé explicitement mais a été
identifié comme un risque direct du chiffrement seul.

`attempt_result` (`PmaRecord`) n'est délibérément **pas** chiffré : c'est
un enum PostgreSQL, incompatible avec le cast `encrypted` (qui produit du
texte chiffré non contraignable par un `CHECK`/`ENUM`).

## Continuité et sauvegarde (documentation uniquement — aucune infrastructure)

Ce projet n'inclut aucun mécanisme de sauvegarde automatisé (pas de job,
pas de configuration cloud). Ce qui suit documente l'approche recommandée
pour un déploiement réel, à mettre en œuvre au niveau infrastructure :

- **Base de données (PostgreSQL)** : sauvegarde continue (WAL archiving)
  ou au minimum un `pg_dump` quotidien complet, retenu au moins 30 jours,
  stocké hors du serveur applicatif (objet de stockage séparé, chiffré au
  repos). Le chiffrement applicatif des champs les plus sensibles
  (ci-dessus) reste actif dans une sauvegarde `pg_dump` : un dump ne
  déchiffre rien, la protection survit à la sauvegarde.
- **Fichiers uploadés** (dictées vocales, futurs documents) : réplication
  du disque de stockage (`storage/app`) vers un stockage objet
  versionné, synchronisée au même rythme que la base pour rester
  cohérente avec les références en base (`audio_path`, etc.).
- **Test de restauration** : une sauvegarde jamais restaurée n'est pas une
  garantie — prévoir un exercice de restauration périodique (ex.
  trimestriel) sur un environnement isolé, avec un objectif de temps de
  restauration (RTO) et de perte de données maximale tolérée (RPO)
  formalisés par l'exploitant, non fixés ici (dépendent du contrat
  d'exploitation réel, hors du périmètre de ce projet).
- **Journal d'audit** : couvert séparément par sa propre garantie
  d'immutabilité applicative (append-only, voir
  `app/Domain/Audit/README.md`) — la sauvegarde infrastructure du journal
  suit les mêmes règles que le reste de la base, sans traitement
  particulier.
