# Guide de déploiement — Sanitaire sur Dokploy (Nixpacks + GitHub)

> Remplacez `[mondomaine]` par votre vrai domaine partout dans ce document.  
> Ce guide suppose que Dokploy est déjà installé et accessible sur votre serveur.

---

## Architecture cible

| Composant | Application Dokploy | Domaine |
|---|---|---|
| Backend Laravel | `sanitaire-api` | `https://api.[mondomaine]` |
| Frontend React/Vite | `sanitaire-app` | `https://app.[mondomaine]` |
| Base de données | `sanitaire-db` (service Dokploy) | interne au réseau Dokploy |

---

## Étape 1 — Créer la base de données PostgreSQL dans Dokploy

1. Dans Dokploy, allez dans **Services → Database → New Database**
2. Choisissez **PostgreSQL**
3. Nommez-la `sanitaire-db`
4. Notez les valeurs générées :
   - **Host** : affiché dans Dokploy (souvent `localhost` ou le nom du container Docker)
   - **Port** : `5432`
   - **Database** : le nom que vous avez choisi
   - **Username** : généré par Dokploy
   - **Password** : généré par Dokploy

> ⚠️ Ces valeurs sont disponibles dans l'onglet **Connection** de la base dans Dokploy.  
> Copiez-les maintenant, vous en aurez besoin pour les variables d'environnement du backend.

---

## Étape 2 — Créer l'application Backend (Laravel)

### Dans Dokploy : New Application → `sanitaire-api`

1. **Source** : GitHub → sélectionnez votre dépôt
2. **Branch** : `main` (ou votre branche de production)
3. **Build Type** : Nixpacks
4. **Root Directory** : `/` (racine du dépôt — là où se trouvent `artisan` et `composer.json`)
5. **Port** : `8080`
6. **Domain** : `api.[mondomaine]` (HTTPS via Let's Encrypt — Dokploy gère automatiquement)

### Variables d'environnement — Backend (onglet Environment)

Copiez-collez ces variables dans l'interface Dokploy, **une par ligne** :

```env
# ── Application ──────────────────────────────────────────────────
APP_NAME=Sanitaire
APP_ENV=production
APP_KEY=                          # ← OBLIGATOIRE — générer via: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://api.[mondomaine]

# Fuseau horaire — NE PAS laisser vide (bug connu avec les horodatages)
# Adaptez à votre localisation : Africa/Dakar, Africa/Abidjan, Europe/Paris…
APP_TIMEZONE=Africa/Dakar

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

# ── Base de données PostgreSQL (valeurs fournies par Dokploy) ─────
DB_CONNECTION=pgsql
DB_HOST=                          # ← récupérer dans Dokploy > sanitaire-db > Connection > Host
`DB_PORT=5432`
DB_DATABASE=                      # ← récupérer dans Dokploy > sanitaire-db > Connection > Database
DB_USERNAME=                      # ← récupérer dans Dokploy > sanitaire-db > Connection > Username
DB_PASSWORD=                      # ← récupérer dans Dokploy > sanitaire-db > Connection > Password

# ── Session (stockée en base — pas de Redis nécessaire pour l'instant) ──
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.${APP_URL}
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none            # OBLIGATOIRE pour cross-domain (app.X → api.X)

# ── Cache (base de données — pas de Redis pour l'instant) ────────
CACHE_STORE=database

# ── File d'attente — SYNC pour ce premier déploiement ───────────
# Pas de worker séparé à gérer. À changer en "database" + worker
# si le volume d'envoi d'emails/notifications augmente.
QUEUE_CONNECTION=sync

# ── Logs ─────────────────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning                 # "debug" en prod = risque de fuite de données

# ── Mail — placeholder (Brevo pas encore branché dans le code) ───
# Laisser à "log" : les emails seront écrits dans les logs Laravel, pas envoyés.
# À changer en "smtp" + credentials Brevo quand l'intégration sera prête.
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@[mondomaine]
MAIL_FROM_NAME=Sanitaire

# ── CORS / Sanctum ───────────────────────────────────────────────
# Autoriser le frontend à appeler l'API (deux sous-domaines distincts)
SANCTUM_STATEFUL_DOMAINS=app.[mondomaine]

# ── Filesystem ───────────────────────────────────────────────────
FILESYSTEM_DISK=local             # Les fichiers sont écrits dans storage/app/
                                  # → voir section "Volume persistant" ci-dessous

# ── Maintenance ──────────────────────────────────────────────────
APP_MAINTENANCE_DRIVER=file

# ── SMS / WhatsApp / Push (structure en place, pas encore actif) ─
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
WHATSAPP_BUSINESS_TOKEN=
WHATSAPP_BUSINESS_PHONE_ID=
FCM_SERVER_KEY=

# ── Assistance IA Anthropic (actif seulement si clé présente) ───
# Si vide → SimulatedAiProvider utilisé automatiquement (pas d'appel réseau)
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-5

# ── Reverse proxy / HTTPS ────────────────────────────────────────
# Dokploy est derrière un reverse proxy (Caddy ou Traefik) qui gère TLS.
# Laravel doit lui faire confiance pour détecter correctement HTTPS.
TRUSTED_PROXIES=*
```

> **Comment générer `APP_KEY`** :  
> Option A (locale) : exécutez `php artisan key:generate --show` dans le dossier du projet.  
> Option B (terminal Dokploy) : après le premier déploiement, ouvrez le terminal intégré de l'app `sanitaire-api` dans Dokploy et exécutez `php artisan key:generate`.  
> Collez la valeur `base64:...` dans la variable `APP_KEY`.

---

## Étape 3 — Créer l'application Frontend (React/Vite)

### Dans Dokploy : New Application → `sanitaire-app`

1. **Source** : GitHub → même dépôt
2. **Branch** : `main`
3. **Build Type** : Nixpacks
4. **Root Directory** : `/frontend` (le sous-dossier du dépôt)
5. **Port** : `8080`
6. **Domain** : `app.[mondomaine]` (HTTPS via Let's Encrypt)

### Variables d'environnement — Frontend (onglet Environment)

```env
# URL de l'API backend — injectée au moment du build Vite (VITE_ prefix obligatoire)
# Correspond exactement à la variable lue dans frontend/src/lib/api.ts (et les autres)
VITE_API_BASE_URL=https://api.[mondomaine]/api
```

> **Important** : les variables `VITE_*` sont **compilées dans le bundle JavaScript au moment du build**.  
> Si vous changez cette valeur dans Dokploy, vous devez **redéployer** (pas juste redémarrer) pour que le changement soit pris en compte.

---

## Étape 4 — Configuration CORS côté Laravel

Le projet n'a pas de fichier `config/cors.php` séparé — Laravel 12 gère CORS via middleware intégré.  
Il faut ajouter la configuration dans `bootstrap/app.php` ou via un fichier `config/cors.php`.

**Action requise** : ajoutez le fichier `config/cors.php` à la racine du backend (voir ci-dessous) **avant** le premier déploiement.

```php
<?php
// config/cors.php
return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => ['https://app.[mondomaine]'],
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => true,   // Nécessaire pour Sanctum cookie-based auth
];
```

> **Ne pas utiliser `*` pour `allowed_origins` en production** — cela autoriserait n'importe quel domaine à appeler votre API médicale.

---

## Étape 5 — HTTPS et reverse proxy (TrustProxies)

Dokploy utilise Caddy ou Traefik comme reverse proxy devant vos applications.  
Laravel doit faire confiance à ce proxy pour détecter correctement `https://` et générer les bons URLs.

**Action requise** : ajoutez dans `bootstrap/app.php` la configuration `TrustProxies` :

```php
// Dans la closure ->withMiddleware() de bootstrap/app.php, ajoutez :
$middleware->trustProxies(at: '*');
// ou, plus précis si vous connaissez l'IP de votre reverse proxy :
// $middleware->trustProxies(at: '172.16.0.0/12');
```

Sans ça, `request()->secure()` retourne `false`, et Laravel génère des URLs en `http://` même quand le client est en HTTPS — ce qui casse les cookies `Secure` et Sanctum.

---

## Étape 6 — Volume persistant (stockage de fichiers)

Le répertoire `storage/` de Laravel contient :
- Les **logs** (`storage/logs/laravel.log`)
- Les **sessions** si driver `file` (ici on utilise `database`, OK)
- Le **cache** si driver `file` (ici on utilise `database`, OK)
- Les **fichiers uploadés** (`storage/app/public/`) si le projet en a

**Avec Dokploy** : à chaque redéploiement, le container est recréé et le système de fichiers est **remis à zéro** sauf si vous montez un volume persistant.

**Action recommandée dans Dokploy** :

1. Allez dans l'app `sanitaire-api` → **Volumes**
2. Ajoutez un volume :
   - **Host Path** : `/opt/sanitaire/storage` (un répertoire de votre serveur)
   - **Container Path** : `/app/storage`
3. Cela préserve logs et fichiers entre les redéploiements.

> Sans ce volume, les logs sont perdus à chaque redéploiement. Pour un projet de santé avec audit trail, c'est fortement recommandé.

---

## Étape 7 — Exécuter les migrations (MANUELLEMENT)

> ⚠️ Les migrations ne sont **pas exécutées automatiquement** au démarrage — c'est intentionnel pour ce projet de données de santé. Vous les lancez vous-même après chaque déploiement qui en introduit de nouvelles.

### Méthode recommandée : terminal intégré Dokploy

1. Dans Dokploy, allez dans l'app `sanitaire-api`
2. Cliquez sur l'onglet **Terminal**
3. Exécutez dans l'ordre :

```bash
# 1. Vérifier l'état des migrations en attente
php artisan migrate:status

# 2. Exécuter les migrations (avec confirmation explicite)
php artisan migrate --force

# 3. (Premier déploiement uniquement) Créer le lien symbolique storage → public
php artisan storage:link

# 4. (Premier déploiement uniquement) Exécuter les seeders si nécessaire
# php artisan db:seed --class=RolePermissionSeeder --force
```

### Méthode alternative : Deploy Script dans Dokploy

Vous pouvez aussi configurer un **Deploy Hook** (post-deploy script) dans Dokploy, mais pour des données médicales, préférez le terminal manuel pour garder le contrôle.

---

## Étape 8 — SPA Routing (React Router)

Le fichier `frontend/nixpacks.toml` configure `npx serve dist --single` qui :
- Sert les fichiers statiques du dossier `dist/`
- Redirige **toutes les routes non-trouvées** vers `index.html`
- Permet à React Router de gérer `/patients/123`, `/consultations`, etc.

Sans ce flag `--single`, un rechargement de page sur `/patients/123` retournerait une erreur 404.

---

## Récapitulatif — Checklist de premier déploiement

### Avant de pousser sur GitHub
- [ ] Créer `config/cors.php` avec `allowed_origins: ['https://app.[mondomaine]']`
- [ ] Ajouter `$middleware->trustProxies(at: '*')` dans `bootstrap/app.php`
- [ ] Vérifier que `frontend/nixpacks.toml` et `nixpacks.toml` (racine) sont committés
- [ ] Vérifier que `.env` et `.env.local` sont dans `.gitignore` ✅ (déjà le cas)

### Dans Dokploy — dans cet ordre
1. [ ] **Créer** la base PostgreSQL `sanitaire-db` → noter les credentials
2. [ ] **Créer** l'app `sanitaire-api` (backend) → branch `main`, root `/`, port `8080`
3. [ ] **Renseigner** toutes les variables d'environnement du backend (tableau ci-dessus)
4. [ ] **Configurer** le domaine `api.[mondomaine]` + activer Let's Encrypt
5. [ ] **Créer** le volume persistant pour `storage/`
6. [ ] **Déclencher** un premier déploiement du backend
7. [ ] **Ouvrir** le terminal → exécuter `php artisan key:generate` si pas encore fait
8. [ ] **Exécuter** `php artisan migrate --force` dans le terminal
9. [ ] **Exécuter** `php artisan storage:link`
10. [ ] **Tester** `https://api.[mondomaine]/up` → doit retourner HTTP 200
11. [ ] **Créer** l'app `sanitaire-app` (frontend) → branch `main`, root `/frontend`, port `8080`
12. [ ] **Renseigner** `VITE_API_BASE_URL=https://api.[mondomaine]/api`
13. [ ] **Configurer** le domaine `app.[mondomaine]` + activer Let's Encrypt
14. [ ] **Déclencher** un premier déploiement du frontend
15. [ ] **Tester** `https://app.[mondomaine]` → interface de connexion visible
16. [ ] **Tester** le login → vérifier les requêtes API dans l'onglet réseau du navigateur

---

## Points d'attention post-déploiement

### SESSION_SAME_SITE=none
Obligatoire pour que les cookies Sanctum fonctionnent entre deux sous-domaines distincts (`app.X` → `api.X`). Sans ça, le navigateur bloque les cookies cross-site et l'authentification échoue.

### Timezone
La valeur `Africa/Dakar` est définie via `APP_TIMEZONE`. La config `config/app.php` lit actuellement `'timezone' => 'UTC'` en dur — il faut la rendre dynamique :

```php
// config/app.php, ligne 68 — modifier :
'timezone' => env('APP_TIMEZONE', 'UTC'),
```

### Passage à Brevo (email) plus tard
Quand l'intégration Brevo sera prête, changez dans Dokploy :
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=votre@email.com    # compte Brevo
MAIL_PASSWORD=                   # clé SMTP Brevo (dans Brevo > SMTP & API)
MAIL_FROM_ADDRESS=noreply@[mondomaine]
MAIL_FROM_NAME=Sanitaire
```
Et si le volume d'emails justifie une file d'attente :
```env
QUEUE_CONNECTION=database
```
Dans ce cas, créez un **Worker** Dokploy séparé avec la commande :
```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

### Mobile Money / PACS
Les variables `TWILIO_*`, `WHATSAPP_*`, `FCM_*` sont déclarées dans `.env.example`.  
Tant qu'elles restent vides, les canaux correspondants sont inactifs (les Channels vérifient la présence de la clé avant tout appel réseau — cf. commentaires dans `.env.example`).

---

## Fichiers créés par ce guide

| Fichier | Rôle |
|---|---|
| [`nixpacks.toml`](./nixpacks.toml) | Config Nixpacks backend Laravel (racine du dépôt) |
| [`frontend/nixpacks.toml`](./frontend/nixpacks.toml) | Config Nixpacks frontend React/Vite |
| `config/cors.php` | **À créer** — CORS production (voir Étape 4) |
| `DEPLOIEMENT.md` | Ce document |
