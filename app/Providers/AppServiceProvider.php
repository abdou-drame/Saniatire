<?php

namespace App\Providers;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Providers\AnthropicAiProvider;
use App\Domain\Ai\Providers\SimulatedAiProvider;
use App\Domain\Appointment\Events\RendezVousAnnule;
use App\Domain\Appointment\Events\RendezVousCree;
use App\Domain\Appointment\Events\RendezVousModifie;
use App\Domain\Imagerie\Events\ResultatImagerieTransmis;
use App\Domain\Laboratoire\Events\ResultatLaboratoireDisponible;
use App\Domain\Laboratoire\Events\ResultatLaboratoireTransmis;
use App\Domain\Notification\Events\CongeValide;
use App\Domain\Notification\Listeners\ScheduleRendezVousRappel;
use App\Domain\Notification\Listeners\ScheduleTeleconsultationRappel;
use App\Domain\Notification\Listeners\SendCongeValideNotification;
use App\Domain\Notification\Listeners\SendReferencementStatutNotification;
use App\Domain\Notification\Listeners\SendRendezVousAnnuleNotification;
use App\Domain\Notification\Listeners\SendRendezVousCreeNotification;
use App\Domain\Notification\Listeners\SendRendezVousModifieNotification;
use App\Domain\Notification\Listeners\SendResultatDisponibleNotification;
use App\Domain\Notification\Listeners\SendResultatDisponiblePrescripteurNotification;
use App\Domain\Referral\Events\ReferencementAccepte;
use App\Domain\Referral\Events\ReferencementRefuse;
use App\Domain\Shared\Auth\Events\PortailActivationDemandee;
use App\Domain\Shared\Auth\Listeners\SendPortailActivationNotification;
use App\Domain\Shared\Tenancy\TenantAwareValidator;
use App\Domain\Shared\Tenancy\TenantScope;
use App\Domain\Teleconsultation\Events\TeleconsultationPlanifiee;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Étape 9 : même patron de résolution que les canaux de
        // notification (config-driven), mais un seul binding suffit ici —
        // il n'y a jamais qu'un fournisseur actif à la fois, pas un
        // éventail de canaux à parcourir.
        $this->app->bind(AiProvider::class, function () {
            return config('services.anthropic.key')
                ? new AnthropicAiProvider
                : new SimulatedAiProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureLoginRateLimiting();
        $this->configureSensitiveRateLimiting();

        // Les règles exists: ne passent pas par TenantScope : voir
        // TenantAwareValidator, qui les borne à la structure connectée.
        Validator::resolver(fn ($translator, $data, $rules, $messages, $attributes) => new TenantAwareValidator($translator, $data, $rules, $messages, $attributes));

        $this->configureStaffPasswordReset();

        // spatie/laravel-activitylog doesn't capture the request IP by
        // default; the socle's audit requirements ask for it explicitly.
        // Étape 9 : structure_id est aussi renseigné ici, pour que
        // AuditLogController puisse borner ses résultats à la structure de
        // l'utilisateur courant — activity_log n'a pas BelongsToTenant (le
        // modèle vient du package), donc l'isolation doit être posée à
        // l'écriture plutôt qu'via un scope global.
        Activity::creating(function (Activity $activity) {
            if (request()) {
                $activity->ip_address = request()->ip();
            }

            if (! $activity->getAttribute('structure_id')) {
                $activity->structure_id = TenantScope::currentStructureId();
            }
        });

        // Étape 9 §3 : le journal d'audit est append-only, y compris pour un
        // administrateur — aucun contrôleur n'expose de route de
        // modification/suppression, mais ce garde-fou empêche aussi toute
        // tentative directe via Tinker/un futur endpoint mal écrit. Limite
        // connue : un DELETE en requête de masse (Activity::where(...)->delete())
        // contourne les événements Eloquent — voir app/Domain/Audit/README.md.
        Activity::updating(function () {
            throw new RuntimeException("Le journal d'audit est en lecture seule (append-only) : modification refusée.");
        });

        Activity::deleting(function () {
            throw new RuntimeException("Le journal d'audit est en lecture seule (append-only) : suppression refusée.");
        });

        // Models live under App\Domain\{X}\Models instead of App\Models,
        // so the default "strip App\Models, prefix Database\Factories"
        // guesser can't find their factories. Resolve by class basename.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Étape 7a : le reste de l'application se contente de dispatch() ces
        // événements métier — c'est ici, et seulement ici, qu'on les relie
        // au système de notifications générique (aucune découverte
        // automatique par convention de dossier dans ce projet).
        Event::listen(RendezVousCree::class, SendRendezVousCreeNotification::class);
        Event::listen(RendezVousCree::class, ScheduleRendezVousRappel::class);
        Event::listen(RendezVousModifie::class, SendRendezVousModifieNotification::class);
        Event::listen(RendezVousAnnule::class, SendRendezVousAnnuleNotification::class);
        Event::listen(CongeValide::class, SendCongeValideNotification::class);
        Event::listen(ResultatLaboratoireDisponible::class, SendResultatDisponibleNotification::class);

        // Étape 7b : portails externes — même patron, wiring exclusif ici.
        Event::listen(PortailActivationDemandee::class, SendPortailActivationNotification::class);
        Event::listen(ResultatLaboratoireTransmis::class, [SendResultatDisponiblePrescripteurNotification::class, 'handleLaboratoire']);
        Event::listen(ResultatImagerieTransmis::class, [SendResultatDisponiblePrescripteurNotification::class, 'handleImagerie']);
        Event::listen(TeleconsultationPlanifiee::class, ScheduleTeleconsultationRappel::class);
        Event::listen(ReferencementAccepte::class, [SendReferencementStatutNotification::class, 'handleAccepte']);
        Event::listen(ReferencementRefuse::class, [SendReferencementStatutNotification::class, 'handleRefuse']);
    }

    /**
     * Limiteur `login`, appliqué aux 4 routes de connexion (personnel,
     * patient, prescripteur, plateforme) et au challenge 2FA. Deux
     * plafonds cumulés : 5 tentatives/minute par couple e-mail + IP (même
     * seuil que le verrouillage de compte personnel, ManagesAuthTokens) —
     * ou par challenge + IP pour /auth/2fa/challenge, qui n'a pas d'e-mail —
     * et 60/minute par IP seule pour freiner un balayage de nombreux
     * e-mails depuis une même adresse, sans gêner une structure dont tout
     * le personnel sort par la même IP (NAT). Indispensable pour les
     * guards patient/prescripteur/plateforme, qui n'ont aucun verrouillage
     * de compte propre.
     */
    /**
     * « Mot de passe oublié » du personnel : la notification native de
     * Laravel construit son lien avec route('password.reset'), qui n'existe
     * pas dans cette API — d'où l'erreur 500. Le lien pointe vers l'écran
     * de réinitialisation de l'application web, et l'email est en français.
     */
    private function configureStaffPasswordReset(): void
    {
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim((string) config('app.frontend_url'), '/')
            .'/reinitialiser-mot-de-passe?token='.urlencode($token).'&email='.urlencode($user->getEmailForPasswordReset()));

        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe Saliha Health')
            ->greeting('Bonjour,')
            ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte Saliha Health.')
            ->action('Choisir un nouveau mot de passe', call_user_func(ResetPassword::$createUrlCallback, $user, $token))
            ->line('Ce lien expire dans '.config('auth.passwords.users.expire').' minutes.')
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre mot de passe reste inchangé.")
            ->salutation('Saliha Health'));
    }

    /**
     * Limiteurs des actions sensibles hors connexion. password-reset couvre
     * « mot de passe oublié », la réinitialisation et l'activation des
     * portails (5/minute par email ou jeton + IP, 20/minute par IP) : sans
     * eux, un robot pouvait inonder une boîte mail ou deviner des jetons.
     * ai-assistance borne l'assistant IA, facturé à chaque appel.
     */
    private function configureSensitiveRateLimiting(): void
    {
        RateLimiter::for('password-reset', function (Request $request) {
            $response = fn (Request $request, array $headers) => response()->json([
                'message' => 'Trop de demandes. Réessayez dans '.($headers['Retry-After'] ?? 60).' secondes.',
            ], 429, $headers);

            $identity = mb_strtolower((string) ($request->input('email') ?? $request->input('token')));

            return [
                Limit::perMinute(5)->by('password-reset:'.sha1($identity).'|'.$request->ip())->response($response),
                Limit::perMinute(20)->by('password-reset-ip:'.$request->ip())->response($response),
            ];
        });

        RateLimiter::for('ai-assistance', function (Request $request) {
            $response = fn (Request $request, array $headers) => response()->json([
                'message' => "Trop de demandes à l'assistant IA. Réessayez dans ".($headers['Retry-After'] ?? 60).' secondes.',
            ], 429, $headers);

            $user = $request->user();

            return [
                Limit::perMinute(10)->by('ai-user:'.$user?->getKey())->response($response),
                Limit::perDay(200)->by('ai-structure:'.$user?->structure_id)->response($response),
            ];
        });
    }

    private function configureLoginRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $response = fn (Request $request, array $headers) => response()->json([
                'message' => 'Trop de tentatives de connexion. Réessayez dans '.($headers['Retry-After'] ?? 60).' secondes.',
            ], 429, $headers);

            $identity = mb_strtolower((string) ($request->input('email') ?? $request->input('challenge')));

            return [
                Limit::perMinute(5)->by('login:'.$identity.'|'.$request->ip())->response($response),
                Limit::perMinute(60)->by('login-ip:'.$request->ip())->response($response),
            ];
        });
    }
}
