<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\SubscriptionState;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Centre de contrôle, livraison A : abonnements manuels, délai de grâce et
 * lecture seule réelle côté backend (EnsureSubscriptionWritable). Les
 * écritures sont testées avec un corps vide : 422 prouve que la requête a
 * franchi le middleware (validation atteinte), 423 qu'elle a été bloquée
 * avant. Un jeton par méthode de test, à cause du cache de guard Sanctum
 * documenté dans PlatformAdministrationAuditTest.
 */
class SubscriptionReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));
        $this->structure = Structure::factory()->create();
    }

    private function period(string $startsAt, string $endsAt, string $status = 'active'): Subscription
    {
        return Subscription::create([
            'structure_id' => $this->structure->id,
            'plan_id' => Plan::where('code', 'pro')->value('id'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
        ]);
    }

    private function staffToken(string $role = 'administrateur'): string
    {
        $user = User::factory()->for($this->structure)->create();
        $user->assignRole($role);

        return $user->createToken('api')->plainTextToken;
    }

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Admin Plateforme',
            'email' => 'platform-admin@example.test',
            'password' => Hash::make('un-mot-de-passe-solide'),
        ]);
    }

    public function test_the_five_plans_of_the_grid_are_inserted_by_the_migration(): void
    {
        $plans = Plan::orderBy('id')->get(['code', 'monthly_price_fcfa', 'annual_price_fcfa'])->toArray();

        $this->assertSame([
            ['code' => 'start', 'monthly_price_fcfa' => 15000, 'annual_price_fcfa' => 150000],
            ['code' => 'pro', 'monthly_price_fcfa' => 35000, 'annual_price_fcfa' => 350000],
            ['code' => 'business', 'monthly_price_fcfa' => 75000, 'annual_price_fcfa' => 750000],
            ['code' => 'premium', 'monthly_price_fcfa' => 150000, 'annual_price_fcfa' => 1500000],
            ['code' => 'enterprise', 'monthly_price_fcfa' => null, 'annual_price_fcfa' => null],
        ], $plans);
    }

    public function test_the_state_follows_the_period_then_the_seven_days_of_grace_then_read_only(): void
    {
        $this->period('2026-09-01', '2026-10-15');

        $at = fn (string $day) => SubscriptionState::forStructure($this->structure->id, Carbon::parse($day))->state;

        $this->assertSame(SubscriptionState::ACTIVE, $at('2026-10-15'));
        $this->assertSame(SubscriptionState::GRACE, $at('2026-10-16'));
        $this->assertSame(SubscriptionState::GRACE, $at('2026-10-22'));
        $this->assertSame(SubscriptionState::READ_ONLY, $at('2026-10-23'));
    }

    public function test_a_structure_without_any_subscription_is_untouched(): void
    {
        $token = $this->staffToken();

        $me = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me')->assertOk();
        $me->assertJsonPath('data.subscription.state', SubscriptionState::ACTIVE)
            ->assertJsonPath('data.subscription.read_only', false)
            ->assertJsonPath('data.subscription.alert', false);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/patients', [])->assertStatus(422);
    }

    public function test_a_future_period_does_not_apply_before_its_start(): void
    {
        $this->period('2026-08-01', '2026-08-31');
        $this->period('2026-11-01', '2026-11-30');

        // Seule la période déjà commencée compte : août est expiré depuis
        // plus de 7 jours, la période de novembre n'a pas encore commencé.
        $this->assertSame(SubscriptionState::READ_ONLY, SubscriptionState::forStructure($this->structure->id)->state);
    }

    public function test_during_grace_writes_still_work_and_only_administrative_roles_see_the_banner(): void
    {
        $this->period('2026-09-01', '2026-10-10');

        $adminToken = $this->staffToken('administrateur');
        $this->withHeader('Authorization', "Bearer {$adminToken}")->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.subscription.state', SubscriptionState::GRACE)
            ->assertJsonPath('data.subscription.alert', true)
            ->assertJsonPath('data.subscription.grace_ends_at', '2026-10-17')
            ->assertJsonPath('data.subscription.message', "L'abonnement de votre structure a expiré le 10/10/2026. Sans renouvellement, la structure passera en lecture seule après le 17/10/2026.");
        $this->withHeader('Authorization', "Bearer {$adminToken}")->postJson('/api/patients', [])->assertStatus(422);
    }

    public function test_during_grace_the_banner_is_hidden_for_a_clinical_role(): void
    {
        $this->period('2026-09-01', '2026-10-10');

        $token = $this->staffToken('medecin');
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.subscription.state', SubscriptionState::GRACE)
            ->assertJsonPath('data.subscription.alert', false)
            ->assertJsonPath('data.subscription.message', null);
    }

    public function test_the_direction_role_also_sees_the_grace_banner(): void
    {
        $this->period('2026-09-01', '2026-10-10');

        $token = $this->staffToken('direction');
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me')
            ->assertJsonPath('data.subscription.alert', true);
    }

    public function test_in_read_only_staff_writes_are_refused_with_423_while_reads_still_work(): void
    {
        $this->period('2026-08-01', '2026-10-01');
        $token = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/patients')->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/patients', [])
            ->assertStatus(423)
            ->assertJsonPath('message', SubscriptionState::forStructure($this->structure->id)->readOnlyMessage());
        $patient = Patient::factory()->for($this->structure)->create();
        $this->withHeader('Authorization', "Bearer {$token}")->patchJson("/api/patients/{$patient->id}", ['first_name' => 'Modifié'])->assertStatus(423);
        $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/patients/{$patient->id}")->assertStatus(423);
        $this->assertNotSame('Modifié', $patient->fresh()->first_name);
        $this->assertNull($patient->fresh()->deleted_at);

        // Tout le monde voit la bannière en lecture seule, pas seulement l'administration.
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me')
            ->assertJsonPath('data.subscription.read_only', true)
            ->assertJsonPath('data.subscription.alert', true);
    }

    /**
     * Le blocage ne dépend d'aucun rôle : chaque rôle du personnel est
     * testé sur plusieurs écritures, y compris celles qu'il a le droit de
     * faire. 2FA confirmée pour tous, afin que le 423 de la 2FA
     * obligatoire ne se confonde pas avec celui de la lecture seule (le
     * message exact est vérifié).
     */
    public function test_read_only_blocks_writes_for_every_staff_role_of_the_structure(): void
    {
        $this->period('2026-08-01', '2026-10-01');
        $message = SubscriptionState::forStructure($this->structure->id)->readOnlyMessage();
        $roles = Role::where('guard_name', 'sanctum')->orderBy('name')->pluck('name');
        $writes = [['POST', '/api/patients'], ['POST', '/api/appointments'], ['POST', '/api/consultations'], ['POST', '/api/invoices'], ['POST', '/api/lab-orders']];

        $this->assertGreaterThanOrEqual(10, $roles->count());
        $checked = [];

        foreach ($roles as $role) {
            $user = User::factory()->for($this->structure)->create(['two_factor_confirmed_at' => now()]);
            $user->assignRole($role);
            $token = $user->createToken('api')->plainTextToken;

            foreach ($writes as [$method, $uri]) {
                $this->app['auth']->forgetGuards();
                $response = $this->withHeader('Authorization', "Bearer {$token}")->json($method, $uri, []);
                $this->assertSame(423, $response->status(), "{$role} {$method} {$uri}");
                $this->assertSame($message, $response->json('message'), "{$role} {$method} {$uri}");
            }
            $checked[] = $role;
        }

        $this->assertSame($roles->all(), $checked);
        $this->assertSame(0, Patient::withoutGlobalScopes()->where('structure_id', $this->structure->id)->count());
    }

    /**
     * Contrôle positif du test précédent : pendant la grâce, aucun rôle
     * n'est bloqué par le middleware (la réponse dépend alors des
     * permissions et de la validation, jamais 423).
     */
    public function test_during_grace_no_staff_role_is_blocked_by_the_subscription(): void
    {
        $this->period('2026-09-01', '2026-10-10');

        foreach (Role::where('guard_name', 'sanctum')->pluck('name') as $role) {
            $user = User::factory()->for($this->structure)->create(['two_factor_confirmed_at' => now()]);
            $user->assignRole($role);
            $token = $user->createToken('api')->plainTextToken;

            $this->app['auth']->forgetGuards();
            $status = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/patients', [])->status();
            $this->assertContains($status, [403, 422], $role);
        }
    }

    public function test_in_read_only_session_management_and_logout_stay_possible(): void
    {
        $this->period('2026-08-01', '2026-10-01');
        $token = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/auth/sessions/revoke-others')->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/auth/logout')->assertOk();
    }

    public function test_in_read_only_patient_portal_writes_are_refused_but_reads_work(): void
    {
        $this->period('2026-08-01', '2026-10-01');
        $token = Patient::factory()->withPortalActivated()->for($this->structure)->create()
            ->createToken('patient-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/portail-patient/rendez-vous')->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/portail-patient/reclamations', [])->assertStatus(423);
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/portail-patient/logout')->assertOk();
    }

    public function test_in_read_only_prescriber_portal_writes_are_refused_but_reads_work(): void
    {
        $this->period('2026-08-01', '2026-10-01');
        $token = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create()
            ->createToken('prescriber-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/portail-prescripteur/demandes-labo')->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/portail-prescripteur/demandes-labo', [])->assertStatus(423);
    }

    public function test_read_only_on_one_structure_does_not_affect_another(): void
    {
        $this->period('2026-08-01', '2026-10-01');
        $other = Structure::factory()->create();
        $otherUser = User::factory()->for($other)->create();
        $otherUser->assignRole('administrateur');
        $token = $otherUser->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/patients', [])->assertStatus(422);
    }

    public function test_a_suspended_period_is_read_only_immediately_without_grace(): void
    {
        $this->period('2026-10-01', '2026-12-31', 'suspendue');
        $token = $this->staffToken();

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/patients', [])->assertStatus(423);
    }

    public function test_the_platform_assigns_a_period_and_renewal_lifts_read_only(): void
    {
        $this->period('2026-08-01', '2026-10-01');
        $platformAdmin = $this->platformAdmin();
        $this->assertTrue(SubscriptionState::forStructure($this->structure->id)->isReadOnly());

        $response = $this->actingAs($platformAdmin, 'platform')
            ->postJson("/api/platform/structures/{$this->structure->id}/subscriptions", [
                'plan_id' => Plan::where('code', 'business')->value('id'),
                'starts_at' => '2026-10-15',
                'ends_at' => '2027-10-14',
                'status' => 'active',
                'notes' => 'Renouvellement annuel',
            ])
            ->assertCreated()
            ->assertJsonPath('data.plan.code', 'business')
            ->assertJsonPath('data.grace_ends_at', '2027-10-21')
            ->assertJsonPath('data.created_by_name', 'Admin Plateforme');

        $this->assertSame(SubscriptionState::ACTIVE, SubscriptionState::forStructure($this->structure->id)->state);
        // L'ancienne période n'est jamais réécrite.
        $this->assertSame(2, Subscription::where('structure_id', $this->structure->id)->count());
        $this->assertSame('2026-10-01', Subscription::orderBy('id')->first()->ends_at->toDateString());

        $log = Activity::where('log_name', 'administration_plateforme')
            ->get()->sole(fn ($log) => $log->properties['action'] === 'creation_periode_abonnement');
        $this->assertSame($this->structure->id, $log->structure_id);
        $this->assertSame($response->json('data.id'), $log->subject_id);
        $this->assertSame('business', $log->properties['formule']);

        $this->actingAs($platformAdmin, 'platform')
            ->getJson("/api/platform/structures/{$this->structure->id}/subscriptions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.plan.code', 'business')
            ->assertJsonPath('current.state', SubscriptionState::ACTIVE)
            ->assertJsonPath('current.subscription_id', $response->json('data.id'));
    }

    public function test_period_validation_rejects_inconsistent_input(): void
    {
        $platformAdmin = $this->platformAdmin();
        $inactivePlan = Plan::create(['code' => 'ancienne', 'name' => 'Ancienne', 'is_active' => false]);

        $this->actingAs($platformAdmin, 'platform')
            ->postJson("/api/platform/structures/{$this->structure->id}/subscriptions", [
                'plan_id' => $inactivePlan->id,
                'starts_at' => '2026-10-15',
                'ends_at' => '2026-10-01',
                'status' => 'suspendue',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id', 'ends_at', 'status']);

        $this->assertSame(0, Subscription::count());
    }

    public function test_suspend_then_resume_are_journaled_and_toggle_read_only(): void
    {
        $subscription = $this->period('2026-10-01', '2026-12-31');
        $platformAdmin = $this->platformAdmin();

        $this->actingAs($platformAdmin, 'platform')
            ->postJson("/api/platform/subscriptions/{$subscription->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspendue');
        $this->assertTrue(SubscriptionState::forStructure($this->structure->id)->isReadOnly());

        $this->actingAs($platformAdmin, 'platform')
            ->postJson("/api/platform/subscriptions/{$subscription->id}/suspend")
            ->assertStatus(409);

        $this->actingAs($platformAdmin, 'platform')
            ->postJson("/api/platform/subscriptions/{$subscription->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertSame(SubscriptionState::ACTIVE, SubscriptionState::forStructure($this->structure->id)->state);

        $logs = Activity::where('log_name', 'administration_plateforme')
            ->orderBy('id')->get()
            ->filter(fn ($log) => in_array($log->properties['action'], ['suspension_abonnement', 'reprise_abonnement'], true))
            ->values();
        $this->assertSame(['suspension_abonnement', 'reprise_abonnement'], $logs->pluck('properties.action')->all());
        $this->assertSame(['ancien_statut' => 'active', 'nouveau_statut' => 'suspendue'], $logs[0]->properties->only(['ancien_statut', 'nouveau_statut'])->all());
        $this->assertTrue($logs->every(fn ($log) => $log->structure_id === $this->structure->id));
    }

    public function test_an_archived_structure_cannot_receive_a_new_period(): void
    {
        $platformAdmin = $this->platformAdmin();
        $this->structure->delete();

        $this->actingAs($platformAdmin, 'platform')
            ->postJson("/api/platform/structures/{$this->structure->id}/subscriptions", [
                'plan_id' => Plan::where('code', 'pro')->value('id'),
                'starts_at' => '2026-10-15',
                'ends_at' => '2027-10-14',
                'status' => 'active',
            ])
            ->assertStatus(409);

        $this->actingAs($platformAdmin, 'platform')
            ->getJson("/api/platform/structures/{$this->structure->id}/subscriptions")
            ->assertOk();
    }

    public function test_the_platform_can_create_a_plan_and_it_is_journaled(): void
    {
        $platformAdmin = $this->platformAdmin();

        $this->actingAs($platformAdmin, 'platform')
            ->postJson('/api/platform/plans', [
                'code' => 'clinique-plus',
                'name' => 'Clinique Plus',
                'monthly_price_fcfa' => 50000,
                'annual_price_fcfa' => 500000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($platformAdmin, 'platform')
            ->postJson('/api/platform/plans', ['code' => 'pro', 'name' => 'Doublon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Une formule utilise déjà ce code.']);

        $logs = Activity::where('log_name', 'administration_plateforme')->whereNull('structure_id')->get()
            ->filter(fn ($log) => $log->properties['action'] === 'creation_formule');
        $this->assertCount(1, $logs);
    }

    public function test_the_platform_can_edit_a_plan_without_touching_existing_periods(): void
    {
        $subscription = $this->period('2026-10-01', '2026-12-31');
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $platformAdmin = $this->platformAdmin();

        $this->actingAs($platformAdmin, 'platform')
            ->patchJson("/api/platform/plans/{$plan->id}", ['monthly_price_fcfa' => 40000, 'is_active' => false, 'code' => 'autre'])
            ->assertOk()
            ->assertJsonPath('data.code', 'pro')
            ->assertJsonPath('data.monthly_price_fcfa', 40000)
            ->assertJsonPath('data.is_active', false);

        // Formule désactivée : plus proposée pour une nouvelle période,
        // mais la période existante la garde et l'état ne change pas.
        $this->assertSame($plan->id, $subscription->fresh()->plan_id);
        $this->assertSame(SubscriptionState::ACTIVE, SubscriptionState::forStructure($this->structure->id)->state);
        $this->actingAs($platformAdmin, 'platform')
            ->postJson("/api/platform/structures/{$this->structure->id}/subscriptions", [
                'plan_id' => $plan->id, 'starts_at' => '2027-01-01', 'ends_at' => '2027-12-31', 'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');

        $log = Activity::where('log_name', 'administration_plateforme')->get()
            ->sole(fn ($log) => $log->properties['action'] === 'modification_formule');
        $this->assertSame(35000, $log->properties['avant']['monthly_price_fcfa']);
        $this->assertSame(40000, $log->properties['apres']['monthly_price_fcfa']);
    }

    public function test_plan_and_subscription_routes_are_reserved_to_the_platform_guard(): void
    {
        $subscription = $this->period('2026-10-01', '2026-12-31');
        $token = $this->staffToken();

        $endpoints = [
            ['GET', '/api/platform/plans'],
            ['POST', '/api/platform/plans'],
            ['PATCH', '/api/platform/plans/1'],
            ['GET', "/api/platform/structures/{$this->structure->id}/subscriptions"],
            ['POST', "/api/platform/structures/{$this->structure->id}/subscriptions"],
            ['POST', "/api/platform/subscriptions/{$subscription->id}/suspend"],
            ['POST', "/api/platform/subscriptions/{$subscription->id}/resume"],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $this->withHeader('Authorization', "Bearer {$token}")->json($method, $uri)->assertUnauthorized();
        }

        $this->assertSame('active', $subscription->fresh()->status);
    }
}
