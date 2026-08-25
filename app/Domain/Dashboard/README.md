# Dashboard (étape 8)

Tableaux de bord médical, financier, direction/groupe et qualité, plus
export CSV. Contrainte volontaire de cette étape : **pas de
pré-agrégation** (pas de job planifié, pas de table de stats dédiée) — les
indicateurs sont calculés à la volée, avec un cache court comme filet de
sécurité sur les requêtes les plus coûteuses.

## Approche cache actuelle

`Cache::remember()` (driver par défaut de l'application — voir
`config/cache.php`) est utilisé à exactement deux endroits :

- `IcdCodeController::stats()` (épidémiologie, réutilisé par le tableau
  médical et l'export CSV).
- `DashboardDirectionController::index()` (vue consolidée multi-sites).

TTL : **10 minutes**. Ce ne sont pas des données temps réel (occupation
des lits, file d'attente) qui, elles, restent non cachées et toujours
calculées à la demande.

**Point de vigilance critique** : le store `Cache` de Laravel est global,
il ne connaît rien de `TenantScope`. Chaque clé de cache **doit** donc
inclure explicitement le `structure_id` courant (via
`TenantScope::currentStructureId()`), en plus des paramètres de filtre
(from/to/site_id/...). Un oubli ferait fuiter la réponse mise en cache
d'une structure vers une autre. Exemple :

```php
$cacheKey = sprintf('dashboard.direction.%s.%s.%s', $structureId ?? 'none', $from->toDateString(), $to->toDateString());
```

Les autres indicateurs (financier, qualité, occupation des lits, temps
d'attente) ne sont pas cachés : ce sont soit des requêtes déjà bornées et
peu coûteuses, soit des indicateurs volontairement temps réel.

## Évoluer vers une vraie pré-agrégation

Le cache court n'est qu'un filet de sécurité, pas une solution de
passage à l'échelle. Quand le volume réel de production le justifiera,
voici la trajectoire concrète à suivre, dans cet ordre :

1. **Mesurer avant d'optimiser.** Activer le slow query log (ou
   `DB::listen()` en environnement de préprod) sur les endpoints de ce
   domaine. Tant qu'une requête reste sous ~200ms à la volumétrie réelle,
   il n'y a rien à faire.
2. **Tables de stats précalculées** (`daily_medical_stats`,
   `daily_financial_stats`, ...) alimentées par un `job` planifié
   (Laravel Scheduler, `php artisan schedule:run` quotidien ou horaire
   selon le besoin), une fois qu'une requête franchit le seuil ci-dessus
   de façon répétée — typiquement quand une table source dépasse
   plusieurs centaines de milliers de lignes sur la période interrogée
   (`consultations`, `billable_items`, `payments`). Chaque table
   précalculée reste scopée `structure_id` (+ éventuellement `site_id`,
   `date`) comme le reste du schéma.
3. **Materialized views Postgres** comme alternative aux tables de stats
   si la logique d'agrégation est complexe (jointures multiples) et que
   la fraîcheur à quelques heures près est acceptable — `REFRESH
   MATERIALIZED VIEW CONCURRENTLY` planifié par le même scheduler.
4. **Ne dupliquer une formule qu'une fois qu'elle est stable.** Les
   formules vivent aujourd'hui dans
   `app/Domain/Dashboard/Services/*DashboardService.php` — un seul
   endroit par indicateur. Le jour où une pré-agrégation devient
   nécessaire, c'est cette classe qui doit lire depuis la table
   précalculée plutôt que dupliquer la requête ailleurs ; les
   contrôleurs n'ont pas à changer.

Ne pas introduire ces mécanismes avant d'avoir mesuré un besoin réel —
c'est le choix explicite de cette étape.

## Limitation connue

`billable_items` n'a pas de colonne `site_id` (voir
`app/Domain/Facturation/Models/BillableItem.php`) : les indicateurs qui
en dépendent (`actes_par_specialite`, `recettes_facturees_par_prestation`)
restent donc structure-wide, jamais filtrables par site. Signalé
explicitement dans la réponse JSON du tableau médical
(`actes_par_specialite.site_filtre_applique = false`).
