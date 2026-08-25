# Journal d'audit — append-only (Étape 9 §3)

Le journal d'audit s'appuie sur `spatie/laravel-activitylog`
(`activity_log`, modèle `Spatie\Activitylog\Models\Activity`). Ce document
couvre uniquement ce que l'Étape 9 y ajoute : consultation filtrable,
isolation multi-tenant, et la garantie d'immutabilité.

## Consultation : `GET /api/audit-logs`

`App\Http\Controllers\Api\AuditLogController::index`, réservé à la
permission `audit.view` (portée uniquement par le rôle `conformite` et par
`administrateur` via son wildcard — ni `direction` ni `directeur_medical`
ne l'ont : lire le journal d'audit est une fonction de conformité, pas de
management).

Filtres (tous optionnels, combinables) :

| Paramètre | Effet |
|---|---|
| `user_id` | `causer_id` = valeur (et `causer_type` = `User`) |
| `action` | recherche sur `event`/`description`/`log_name` |
| `table` | `subject_type` LIKE `%valeur%` |
| `from` / `to` | `created_at` dans l'intervalle |
| `per_page` | pagination, défaut 50, max 200 |

## Isolation multi-tenant

`Activity` (fourni par le package) n'a pas `BelongsToTenant` — c'est un
modèle générique, sans notion de structure. L'isolation est donc posée à
l'**écriture**, pas via un scope global de lecture :
`AppServiceProvider::boot()` renseigne `structure_id` sur chaque
`Activity` au moment de sa création (`Activity::creating`), à partir de
`TenantScope::currentStructureId()`. `AuditLogController` filtre
explicitement `where('structure_id', $request->user()->structure_id)` —
il ne peut pas compter sur un scope automatique comme les autres modèles
du projet.

## Garantie d'immutabilité (append-only)

`AppServiceProvider::boot()` enregistre deux listeners qui lèvent une
exception sur toute tentative de modification ou de suppression d'une
`Activity`, **y compris pour un administrateur** :

```php
Activity::updating(fn () => throw new RuntimeException(...));
Activity::deleting(fn () => throw new RuntimeException(...));
```

Aucun contrôleur de ce projet n'expose de route `PATCH`/`PUT`/`DELETE` sur
`/api/audit-logs` — ce garde-fou protège contre une tentative directe
(Tinker, script, futur endpoint mal écrit) qui contournerait l'absence de
route. Testé explicitement dans `tests/Feature/AuditLogTest.php` : une
tentative de `Activity::find($id)->update(...)` ou `->delete()`, y compris
exécutée par un administrateur, lève l'exception.

### Limite connue

Ce garde-fou s'appuie sur les événements Eloquent (`updating`/`deleting`),
qui ne se déclenchent que pour des opérations passant par le modèle
(`$activity->save()`, `$activity->delete()`, `Activity::find($id)->delete()`).
Une requête de masse au niveau du query builder —
`Activity::where(...)->delete()` ou `DB::table('activity_log')->delete()`
— **contourne ces listeners**, car Eloquent n'instancie pas de modèle pour
chaque ligne concernée. Aucun contrôleur de ce projet n'exécute une telle
requête ; ce point est documenté ici pour qu'une future revue de code (ou
un accès direct à la base) sache que ce n'est pas couvert par le garde-fou
applicatif, et qu'une protection complémentaire (permission SQL restreinte
sur `activity_log`, ou trigger PostgreSQL `BEFORE UPDATE OR DELETE`) serait
nécessaire pour fermer ce dernier chemin si le besoin s'en fait sentir.
