<?php

namespace Tests\Feature;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Notification\Mail\GenericNotificationMail;
use App\Domain\Notification\Models\Notification as NotificationLog;
use App\Domain\Patient\Models\Patient;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Shared\Auth\PortalActivationService;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Corrections de sécurité 5a, points 3 à 7 : jetons, mot de passe oublié,
 * limiteurs, en-têtes HTTP, liens secrets des notifications, verrouillage
 * du compte plateforme.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->structure = Structure::factory()->create();
    }

    // --- Point 3 : jetons et changement de mot de passe -------------------

    public function test_api_tokens_expire_after_twelve_hours(): void
    {
        $this->assertSame(720, config('sanctum.expiration'));

        $user = User::factory()->for($this->structure)->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        $this->travel(721)->minutes();
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_change_password_requires_the_current_password(): void
    {
        $user = User::factory()->for($this->structure)->create();

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'pas-le-bon',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_change_password_revokes_every_other_session_but_keeps_the_current_one(): void
    {
        $user = User::factory()->for($this->structure)->create();
        $current = $user->createToken('api')->plainTextToken;
        $other = $user->createToken('api')->plainTextToken;

        $this->withToken($current)->postJson('/api/auth/change-password', [
            'current_password' => 'password',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertOk();

        $this->assertSame(1, $user->tokens()->count());

        Auth::forgetGuards();
        $this->withToken($current)->getJson('/api/auth/me')->assertOk();

        Auth::forgetGuards();
        $this->withToken($other)->getJson('/api/auth/me')->assertUnauthorized();
    }

    // --- Point 4 : mot de passe oublié du personnel ------------------------

    public function test_staff_forgot_password_answers_the_same_whether_the_email_exists_or_not(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.example.test']);
        $user = User::factory()->for($this->structure)->create(['email' => 'agent@example.test']);

        $known = $this->postJson('/api/auth/forgot-password', ['email' => 'agent@example.test'])->assertOk();
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'inconnu@example.test'])->assertOk();

        $this->assertSame($known->json(), $unknown->json());

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_starts_with($mail->actionUrl, 'https://app.example.test/reinitialiser-mot-de-passe?token=')
                && $mail->subject === 'Réinitialisation de votre mot de passe Saliha Health';
        });
    }

    public function test_staff_forgot_password_sends_a_real_email_without_server_error(): void
    {
        Mail::fake();
        User::factory()->for($this->structure)->create(['email' => 'agent@example.test']);

        // Avant correctif : route('password.reset') inexistante → 500.
        $this->postJson('/api/auth/forgot-password', ['email' => 'agent@example.test'])->assertOk();
    }

    public function test_staff_password_reset_works_and_closes_every_session(): void
    {
        Notification::fake();
        $user = User::factory()->for($this->structure)->create(['email' => 'agent@example.test']);
        $user->createToken('api');

        $this->postJson('/api/auth/forgot-password', ['email' => 'agent@example.test'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'agent@example.test',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertOk();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_password_reset_routes_are_throttled(): void
    {
        Notification::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'cible@example.test'])->assertOk();
        }

        $this->postJson('/api/auth/forgot-password', ['email' => 'cible@example.test'])->assertStatus(429);
        $this->postJson('/api/portail-patient/mot-de-passe-oublie', ['email' => 'cible@example.test'])->assertStatus(429);
    }

    public function test_portal_activation_is_throttled(): void
    {
        $payload = ['token' => 'jeton-invente', 'password' => 'un-mot-de-passe', 'password_confirmation' => 'un-mot-de-passe'];

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/portail-patient/activer', $payload)->assertNotFound();
        }

        $this->postJson('/api/portail-patient/activer', $payload)->assertStatus(429);
        $this->postJson('/api/portail-prescripteur/activer', $payload)->assertStatus(429);
    }

    public function test_the_paid_ai_endpoint_is_throttled(): void
    {
        $site = Site::factory()->for($this->structure)->create();
        $doctor = User::factory()->for($this->structure)->create();
        $doctor->assignRole('medecin');
        $consultation = Consultation::factory()
            ->for($this->structure)
            ->for(Patient::factory()->for($this->structure))
            ->for($doctor, 'practitioner')
            ->for($site)
            ->create();

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($doctor)->postJson("/api/consultations/{$consultation->id}/ai-summary")->assertOk();
        }

        $this->actingAs($doctor)->postJson("/api/consultations/{$consultation->id}/ai-summary")->assertStatus(429);
    }

    // --- Point 5 : en-têtes de sécurité ------------------------------------

    public function test_every_api_response_carries_the_security_headers(): void
    {
        $user = User::factory()->for($this->structure)->create();

        $responses = [
            $this->actingAs($user)->getJson('/api/auth/me'),
            $this->getJson('/api/route-inexistante'),
            $this->postJson('/api/auth/login', []),
        ];

        foreach ($responses as $response) {
            $response->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Referrer-Policy', 'no-referrer');
            $response->assertHeaderMissing('X-Powered-By');
        }
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $this->getJson('https://localhost/api/auth/me')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    // --- Point 6 : liens secrets jamais conservés --------------------------

    public function test_activation_link_is_emailed_but_never_stored_in_notification_logs(): void
    {
        Mail::fake();
        $this->seed(NotificationTemplateSeeder::class);
        config(['app.frontend_url' => 'https://app.example.test']);

        $patient = Patient::factory()->for($this->structure)->create(['email' => 'patient@example.test']);
        $token = app(PortalActivationService::class)->createFor($patient);

        $log = NotificationLog::withoutGlobalScopes()->where('type_evenement', 'patient_portal_activation')->sole();
        $this->assertStringNotContainsString($token, $log->contenu_final);
        $this->assertStringContainsString('[lien confidentiel, non conservé]', $log->contenu_final);
        $this->assertSame('envoyee', $log->statut);

        // Le patient reçoit bien le vrai lien, vers la route du portail.
        Mail::assertSent(GenericNotificationMail::class, fn (GenericNotificationMail $mail) => str_contains(
            $mail->notification->contenu_final,
            "https://app.example.test/portail/activer?token={$token}"
        ));

        // Et l'activation fonctionne avec ce jeton.
        $this->postJson('/api/portail-patient/activer', [
            'token' => $token,
            'password' => 'un-mot-de-passe-solide',
            'password_confirmation' => 'un-mot-de-passe-solide',
        ])->assertOk();
    }

    public function test_patient_password_reset_link_is_not_stored_and_targets_the_portal_route(): void
    {
        Mail::fake();
        $this->seed(NotificationTemplateSeeder::class);
        config(['app.frontend_url' => 'https://app.example.test']);

        Patient::factory()->withPortalActivated()->for($this->structure)->create(['email' => 'patient@example.test']);

        $this->postJson('/api/portail-patient/mot-de-passe-oublie', ['email' => 'patient@example.test'])->assertOk();

        $log = NotificationLog::withoutGlobalScopes()->where('type_evenement', 'patient_password_reset')->sole();
        $this->assertStringNotContainsString('token=', $log->contenu_final);

        Mail::assertSent(GenericNotificationMail::class, fn (GenericNotificationMail $mail) => str_contains(
            $mail->notification->contenu_final,
            'https://app.example.test/portail/reinitialiser-mot-de-passe?token='
        ));
    }

    public function test_the_migration_masks_links_already_stored(): void
    {
        $id = DB::table('notification_logs')->insertGetId([
            'structure_id' => $this->structure->id,
            'notifiable_type' => Patient::class,
            'notifiable_id' => 1,
            'type_evenement' => 'patient_portal_activation',
            'canal' => 'email',
            'contenu_final' => 'Bonjour, activez votre compte en suivant ce lien : https://app.example.test/portail-patient/activer?token=SECRET123',
            'destinataire' => 'patient@example.test',
            'statut' => 'envoyee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_05_000001_redact_secret_links_from_notification_logs.php');
        $migration->up();
        $migration->up(); // idempotente

        $this->assertSame(
            'Bonjour, activez votre compte en suivant ce lien : [lien confidentiel, non conservé]',
            DB::table('notification_logs')->where('id', $id)->value('contenu_final')
        );
    }

    // --- Point 7 : verrouillage du compte plateforme -----------------------

    public function test_platform_admin_is_locked_after_five_failed_attempts(): void
    {
        $admin = PlatformAdmin::create([
            'name' => 'Admin Plateforme',
            'email' => 'plateforme@example.test',
            'password' => Hash::make('un-mot-de-passe-solide'),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/platform/login', ['email' => $admin->email, 'password' => 'mauvais'])->assertStatus(422);
        }

        $this->assertTrue($admin->fresh()->isLocked());

        // Passé le limiteur par minute, le bon mot de passe reste refusé.
        $this->travel(61)->seconds();
        $this->postJson('/api/platform/login', ['email' => $admin->email, 'password' => 'un-mot-de-passe-solide'])
            ->assertStatus(423);

        // Fin du verrouillage (15 min) : accès rétabli.
        $this->travel(15)->minutes();
        $this->postJson('/api/platform/login', ['email' => $admin->email, 'password' => 'un-mot-de-passe-solide'])
            ->assertOk();

        $this->assertSame(0, $admin->fresh()->failed_login_attempts);
    }
}
