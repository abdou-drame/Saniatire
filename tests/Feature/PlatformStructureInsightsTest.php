<?php

namespace Tests\Feature;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Patient\Models\Patient;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Livraison D : supervision des structures par l'administrateur de
 * plateforme (personnel, activité, statistiques globales). Même patron que
 * PlatformUserAdministrationTest : vrais tokens Bearer et
 * Auth::forgetGuards() avant chaque requête. Le token platform réel
 * vérifie au passage le piège TenantScope (currentStructureId() null sous
 * ce guard) : chaque test mélange plusieurs structures et vérifie que
 * seules les lignes de la structure demandée sont comptées.
 */
class PlatformStructureInsightsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $platformToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function platform(string $method, string $uri, array $data = []): TestResponse
    {
        if ($this->platformToken === null) {
            PlatformAdmin::create([
                'name' => 'Admin Plateforme',
                'email' => 'platform-admin@example.test',
                'password' => Hash::make('un-mot-de-passe-solide'),
            ]);

            Auth::forgetGuards();
            $this->platformToken = $this->postJson('/api/platform/login', [
                'email' => 'platform-admin@example.test',
                'password' => 'un-mot-de-passe-solide',
            ])->assertOk()->json('token');
        }

        Auth::forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$this->platformToken}")->json($method, $uri, $data);
    }

    private function platformAuditCount(): int
    {
        return Activity::query()->where('log_name', 'administration_plateforme')->count();
    }

    /**
     * La connexion plateforme est elle-même journalisée (PlatformAuthController) :
     * on se connecte d'abord pour ne mesurer ensuite que les lectures.
     */
    private function platformAuditCountAfterLogin(): int
    {
        $this->platform('GET', '/api/platform/me')->assertOk();

        return $this->platformAuditCount();
    }

    private function logRow(?int $structureId, string $createdAt, string $description = 'Action', array $properties = []): void
    {
        DB::table('activity_log')->insert([
            'structure_id' => $structureId,
            'log_name' => 'default',
            'description' => $description,
            'properties' => json_encode($properties),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_routes_require_the_platform_guard(): void
    {
        $structure = Structure::factory()->create();
        $routes = [
            "/api/platform/structures/{$structure->id}/users",
            "/api/platform/structures/{$structure->id}/activity",
            '/api/platform/stats',
        ];

        foreach ($routes as $uri) {
            Auth::forgetGuards();
            $this->withHeaders(['Authorization' => ''])->getJson($uri)->assertUnauthorized();
        }

        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');
        $staffToken = $admin->createToken('test')->plainTextToken;

        foreach ($routes as $uri) {
            Auth::forgetGuards();
            $this->withHeader('Authorization', "Bearer {$staffToken}")->getJson($uri)->assertUnauthorized();
        }
    }

    public function test_users_lists_only_the_structure_staff_with_roles_and_account_state(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));

        $structure = Structure::factory()->create();
        $other = Structure::factory()->create();

        $zongo = User::factory()->for($structure)->create([
            'last_name' => 'Zongo', 'first_name' => 'Ali',
            'last_login_at' => '2026-10-14 08:30:00',
        ]);
        $zongo->assignRole('medecin');
        $zongo->assignRole('administrateur');

        $bamba = User::factory()->for($structure)->create([
            'last_name' => 'Bamba', 'first_name' => 'Awa',
            'is_active' => false,
            'locked_until' => '2026-10-15 11:00:00',
        ]);

        $deleted = User::factory()->for($structure)->create(['last_name' => 'Supprime']);
        $deleted->delete();

        $foreign = User::factory()->for($other)->create(['last_name' => 'Etranger']);

        $response = $this->platform('GET', "/api/platform/structures/{$structure->id}/users");

        $response->assertOk();
        $this->assertSame([$bamba->id, $zongo->id], array_column($response->json('data'), 'id'));
        $response->assertJsonStructure([
            'data' => [['id', 'first_name', 'last_name', 'email', 'roles', 'is_active', 'is_locked', 'locked_until', 'last_login_at']],
            'meta' => ['current_page', 'per_page', 'total'],
            'links',
        ]);
        $this->assertSame(25, $response->json('meta.per_page'));
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertCount(9, $response->json('data.0'));

        $this->assertSame([], $response->json('data.0.roles'));
        $this->assertFalse($response->json('data.0.is_active'));
        $this->assertTrue($response->json('data.0.is_locked'));
        $this->assertSame(Carbon::parse('2026-10-15 11:00:00')->toISOString(), $response->json('data.0.locked_until'));
        $this->assertNull($response->json('data.0.last_login_at'));

        $roles = $response->json('data.1.roles');
        sort($roles);
        $this->assertSame(['administrateur', 'medecin'], $roles);
        $this->assertTrue($response->json('data.1.is_active'));
        $this->assertFalse($response->json('data.1.is_locked'));
        $this->assertNull($response->json('data.1.locked_until'));
        $this->assertSame(Carbon::parse('2026-10-14 08:30:00')->toISOString(), $response->json('data.1.last_login_at'));

        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($foreign->id, $ids);
        $this->assertNotContains($deleted->id, $ids);

        // Une structure archivée reste consultable.
        $structure->delete();
        $archived = $this->platform('GET', "/api/platform/structures/{$structure->id}/users");
        $archived->assertOk();
        $this->assertSame([$bamba->id, $zongo->id], array_column($archived->json('data'), 'id'));
    }

    public function test_activity_counts_only_the_structure_rows_per_day_without_exposing_log_content(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));

        $structure = Structure::factory()->create();
        $other = Structure::factory()->create();

        User::factory()->for($structure)->create(['last_login_at' => '2026-10-10 08:00:00']);
        User::factory()->for($structure)->create(['last_login_at' => '2026-10-01 08:00:00']);
        User::factory()->for($structure)->create(['is_active' => false, 'last_login_at' => null]);
        User::factory()->for($structure)->create(['last_login_at' => '2026-10-14 08:00:00'])->delete();
        User::factory()->for($other)->create(['last_login_at' => '2026-10-15 08:00:00']);

        $secret = 'SECRET-PATIENT-Kouassi-PAT-000042';

        $this->logRow($structure->id, '2026-10-08 23:59:59', $secret); // hors période
        $this->logRow($structure->id, '2026-10-09 00:00:00', $secret, ['patient' => $secret]);
        $this->logRow($structure->id, '2026-10-12 09:00:00', $secret);
        $this->logRow($structure->id, '2026-10-12 18:00:00', 'Action', ['nom' => $secret]);
        $this->logRow($structure->id, '2026-10-15 09:00:00', 'Action');
        $this->logRow($other->id, '2026-10-12 10:00:00', $secret);
        $this->logRow($other->id, '2026-10-13 10:00:00', $secret);
        $this->logRow($other->id, '2026-10-15 09:30:00', $secret);
        $this->logRow(null, '2026-10-13 10:00:00', $secret);

        $auditBefore = $this->platformAuditCountAfterLogin();

        $response = $this->platform('GET', "/api/platform/structures/{$structure->id}/activity?days=7");

        $response->assertOk();
        $this->assertSame(['from' => '2026-10-09', 'to' => '2026-10-15', 'days' => 7], $response->json('data.period'));
        $this->assertSame(Carbon::parse('2026-10-10 08:00:00')->toISOString(), $response->json('data.last_login_at'));
        $this->assertSame(3, $response->json('data.users_total'));
        $this->assertSame(2, $response->json('data.users_active'));
        $this->assertSame(1, $response->json('data.users_logged_in_period'));
        $this->assertSame(4, $response->json('data.actions_total'));
        $this->assertSame([
            ['date' => '2026-10-09', 'count' => 1],
            ['date' => '2026-10-10', 'count' => 0],
            ['date' => '2026-10-11', 'count' => 0],
            ['date' => '2026-10-12', 'count' => 2],
            ['date' => '2026-10-13', 'count' => 0],
            ['date' => '2026-10-14', 'count' => 0],
            ['date' => '2026-10-15', 'count' => 1],
        ], $response->json('data.actions_per_day'));

        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertStringNotContainsString('Kouassi', $response->getContent());

        // Valeur par défaut : 30 jours, aujourd'hui inclus.
        $default = $this->platform('GET', "/api/platform/structures/{$structure->id}/activity");
        $default->assertOk();
        $this->assertSame(['from' => '2026-09-16', 'to' => '2026-10-15', 'days' => 30], $default->json('data.period'));
        $this->assertCount(30, $default->json('data.actions_per_day'));
        $this->assertSame('2026-09-16', $default->json('data.actions_per_day.0.date'));
        $this->assertSame('2026-10-15', $default->json('data.actions_per_day.29.date'));
        $this->assertSame(5, $default->json('data.actions_total'));
        $this->assertSame(2, $default->json('data.users_logged_in_period'));

        // Lecture seule : aucune entrée d'audit plateforme ajoutée.
        $this->assertSame($auditBefore, $this->platformAuditCount());
    }

    public function test_activity_validates_days_and_stays_readable_once_archived(): void
    {
        $structure = Structure::factory()->create();

        foreach (['6', '91', 'abc', '7.5'] as $days) {
            $this->platform('GET', "/api/platform/structures/{$structure->id}/activity?days={$days}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors('days');
        }

        $this->platform('GET', "/api/platform/structures/{$structure->id}/activity?days=7")->assertOk();
        $this->platform('GET', "/api/platform/structures/{$structure->id}/activity?days=90")
            ->assertOk()
            ->assertJsonCount(90, 'data.actions_per_day');

        $structure->delete();
        $this->platform('GET', "/api/platform/structures/{$structure->id}/activity")->assertOk();
    }

    public function test_stats_aggregates_per_structure_and_per_month_without_patient_details(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));

        $alpha = Structure::factory()->create(['legal_name' => 'Alpha Clinique']);
        $beta = Structure::factory()->create(['legal_name' => 'Beta Cabinet', 'is_active' => false]);
        $gamma = Structure::factory()->create(['legal_name' => 'Gamma Centre']);

        // Utilisateurs actifs sur 30 jours (last_login_at >= 2026-09-15 10:00).
        $alphaDoctor = User::factory()->for($alpha)->create(['last_login_at' => '2026-10-13 08:00:00']);
        User::factory()->for($alpha)->create(['last_login_at' => '2026-09-05 08:00:00']);
        User::factory()->for($alpha)->create(['last_login_at' => null]);
        User::factory()->for($alpha)->create(['last_login_at' => '2026-10-14 08:00:00'])->delete();
        $betaDoctor = User::factory()->for($beta)->create(['last_login_at' => '2026-10-05 08:00:00']);
        $gammaDoctor = User::factory()->for($gamma)->create(['last_login_at' => '2026-10-10 08:00:00']);

        $alphaSite = Site::factory()->for($alpha)->create();
        $betaSite = Site::factory()->for($beta)->create();
        $gammaSite = Site::factory()->for($gamma)->create();

        $alphaPatients = Patient::factory()->count(3)->for($alpha)->create([
            'first_name' => 'Fatoumata', 'last_name' => 'Kouassi', 'email' => 'fatoumata.kouassi@example.test',
        ]);
        Patient::factory()->for($alpha)->create()->delete();
        $betaPatient = Patient::factory()->for($beta)->create(['first_name' => 'Mamadou', 'last_name' => 'Traore']);
        $gammaPatient = Patient::factory()->for($gamma)->create(['first_name' => 'Aminata', 'last_name' => 'Ouattara']);

        $consult = fn (Structure $structure, Patient $patient, User $doctor, Site $site, string $at) => Consultation::factory()->create([
            'structure_id' => $structure->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $doctor->id,
            'site_id' => $site->id,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $alphaPatient = $alphaPatients->first();
        $consult($alpha, $alphaPatient, $alphaDoctor, $alphaSite, '2026-10-01 00:00:00');
        $consult($alpha, $alphaPatient, $alphaDoctor, $alphaSite, '2026-10-15 09:00:00');
        $consult($alpha, $alphaPatient, $alphaDoctor, $alphaSite, '2026-09-30 23:59:59');
        $consult($alpha, $alphaPatient, $alphaDoctor, $alphaSite, '2026-05-01 00:00:00');
        $consult($alpha, $alphaPatient, $alphaDoctor, $alphaSite, '2026-04-30 23:59:59'); // hors fenêtre
        $consult($alpha, $alphaPatient, $alphaDoctor, $alphaSite, '2026-10-10 09:00:00')->delete();
        $consult($beta, $betaPatient, $betaDoctor, $betaSite, '2026-08-20 09:00:00');
        $consult($gamma, $gammaPatient, $gammaDoctor, $gammaSite, '2026-10-02 09:00:00');

        $gamma->delete();

        $auditBefore = $this->platformAuditCountAfterLogin();

        $response = $this->platform('GET', '/api/platform/stats');

        $response->assertOk();
        $months = ['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'];
        $this->assertSame($months, $response->json('data.months'));
        $this->assertSame([
            'structures' => ['total' => 3, 'active' => 1, 'inactive' => 1, 'archived' => 1],
            'users_active_30d' => 3,
            'patients' => 5,
            'consultations_this_month' => 3,
        ], $response->json('data.totals'));

        $perMonth = fn (array $counts) => array_map(
            fn (string $month, int $count) => ['month' => $month, 'count' => $count],
            $months,
            $counts,
        );

        $this->assertSame([
            [
                'id' => $alpha->id,
                'legal_name' => 'Alpha Clinique',
                'is_active' => true,
                'is_archived' => false,
                'users_active_30d' => 1,
                'patients' => 3,
                'consultations_per_month' => $perMonth([1, 0, 0, 0, 1, 2]),
            ],
            [
                'id' => $beta->id,
                'legal_name' => 'Beta Cabinet',
                'is_active' => false,
                'is_archived' => false,
                'users_active_30d' => 1,
                'patients' => 1,
                'consultations_per_month' => $perMonth([0, 0, 0, 1, 0, 0]),
            ],
            [
                'id' => $gamma->id,
                'legal_name' => 'Gamma Centre',
                'is_active' => true,
                'is_archived' => true,
                'users_active_30d' => 1,
                'patients' => 1,
                'consultations_per_month' => $perMonth([0, 0, 0, 0, 0, 1]),
            ],
        ], $response->json('data.structures'));

        $raw = $response->getContent();
        foreach ([$alphaPatient, $betaPatient, $gammaPatient] as $patient) {
            $this->assertStringNotContainsString($patient->patient_number, $raw);
            $this->assertStringNotContainsString($patient->last_name, $raw);
            $this->assertStringNotContainsString($patient->first_name, $raw);
        }
        $this->assertStringNotContainsString('fatoumata.kouassi@example.test', $raw);

        $this->assertSame($auditBefore, $this->platformAuditCount());
    }
}
