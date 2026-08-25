<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Rh\Models\EmployeeProfile;
use App\Domain\Rh\Models\LeaveRequest;
use App\Domain\Rh\Models\WorkSchedule;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Step6RhTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private Site $siteA2;

    private User $medecinA;

    private User $secretaireA;

    private User $administrateurA;

    private User $rhA;

    private User $managerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();
        $this->siteA2 = Site::factory()->for($this->structureA)->create();

        $this->medecinA = User::factory()->for($this->structureA)->create();
        $this->medecinA->assignRole('medecin');

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->administrateurA = User::factory()->for($this->structureA)->create();
        $this->administrateurA->assignRole('administrateur');

        $this->rhA = User::factory()->for($this->structureA)->create();
        $this->rhA->assignRole('rh');

        $this->managerA = User::factory()->for($this->structureA)->create();
        $this->managerA->assignRole('manager');
        $this->managerA->sites()->attach($this->siteA->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // --- §3 : intégration avec le module Rendez-vous ----------------------

    public function test_an_appointment_cannot_be_created_outside_the_practitioners_presence_planning(): void
    {
        $day = now()->addWeek()->startOfDay();

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'jour_semaine' => $day->dayOfWeek,
            'date' => null,
            'heure_debut' => '08:00:00',
            'heure_fin' => '12:00:00',
            'type' => 'normal',
        ]);

        $response = $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => Patient::factory()->for($this->structureA)->create()->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $day->clone()->setTime(15, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation hors planning',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('indisponible', $response->json('message'));
    }

    public function test_an_appointment_can_be_created_inside_a_normal_work_schedule_no_false_positive(): void
    {
        $day = now()->addWeek()->startOfDay();

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'jour_semaine' => $day->dayOfWeek,
            'date' => null,
            'heure_debut' => '08:00:00',
            'heure_fin' => '12:00:00',
            'type' => 'normal',
        ]);

        $patient = Patient::factory()->for($this->structureA)->create();

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $day->clone()->setTime(9, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation dans le planning',
        ])->assertCreated();
    }

    public function test_an_appointment_cannot_be_created_during_a_validated_leave(): void
    {
        $leaveDay = now()->addWeeks(2)->startOfDay();

        LeaveRequest::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'type' => 'conge_annuel',
            'date_debut' => $leaveDay->toDateString(),
            'date_fin' => $leaveDay->clone()->addDays(3)->toDateString(),
            'statut' => 'valide',
            'validated_by' => $this->rhA->id,
        ]);

        $patient = Patient::factory()->for($this->structureA)->create();

        $response = $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $leaveDay->clone()->setTime(10, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation pendant congé',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('congé', $response->json('message'));
    }

    public function test_an_administrator_can_force_an_appointment_outside_planning_and_it_is_audited(): void
    {
        $day = now()->addWeek()->startOfDay();

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'jour_semaine' => $day->dayOfWeek,
            'date' => null,
            'heure_debut' => '08:00:00',
            'heure_fin' => '12:00:00',
            'type' => 'normal',
        ]);

        $patient = Patient::factory()->for($this->structureA)->create();

        $response = $this->actingAs($this->administrateurA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $day->clone()->setTime(18, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Urgence hors planning',
            'force_override' => true,
        ]);

        $response->assertCreated();

        $this->assertTrue(
            Activity::where('log_name', 'derogation_planning')
                ->where('causer_id', $this->administrateurA->id)
                ->exists()
        );
    }

    public function test_a_user_without_override_permission_cannot_force_an_appointment_outside_planning(): void
    {
        $day = now()->addWeek()->startOfDay();

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'jour_semaine' => $day->dayOfWeek,
            'date' => null,
            'heure_debut' => '08:00:00',
            'heure_fin' => '12:00:00',
            'type' => 'normal',
        ]);

        $patient = Patient::factory()->for($this->structureA)->create();

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $day->clone()->setTime(18, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Tentative de dérogation non autorisée',
            'force_override' => true,
        ])->assertStatus(422);

        $this->assertFalse(Activity::where('log_name', 'derogation_planning')->exists());
    }

    public function test_an_appointment_can_be_created_during_a_garde_outside_normal_work_schedule(): void
    {
        $day = now()->addWeek()->startOfDay();

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'jour_semaine' => null,
            'date' => $day->toDateString(),
            'heure_debut' => '20:00:00',
            'heure_fin' => '23:00:00',
            'type' => 'garde',
        ]);

        $patient = Patient::factory()->for($this->structureA)->create();

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $day->clone()->setTime(21, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Urgence pendant la garde',
        ])->assertCreated();
    }

    // --- §2/§5 : congés, portée équipe --------------------------------------

    public function test_a_manager_can_validate_leave_only_for_their_own_team(): void
    {
        $teamMate = User::factory()->for($this->structureA)->create();
        $teamMate->assignRole('medecin');
        $teamMate->sites()->attach($this->siteA->id);

        $outsider = User::factory()->for($this->structureA)->create();
        $outsider->assignRole('medecin');
        $outsider->sites()->attach($this->siteA2->id);

        $teamLeave = LeaveRequest::factory()->for($this->structureA)->create([
            'user_id' => $teamMate->id,
            'statut' => 'demande',
        ]);

        $outsiderLeave = LeaveRequest::factory()->for($this->structureA)->create([
            'user_id' => $outsider->id,
            'statut' => 'demande',
        ]);

        $this->actingAs($this->managerA)
            ->patchJson("/api/leave-requests/{$teamLeave->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.statut', 'valide');

        $this->actingAs($this->managerA)
            ->patchJson("/api/leave-requests/{$outsiderLeave->id}/validate")
            ->assertStatus(403);
    }

    public function test_validating_a_leave_that_overlaps_a_planned_schedule_warns_but_does_not_block(): void
    {
        $day = now()->addWeeks(3)->startOfDay();

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'jour_semaine' => null,
            'date' => $day->toDateString(),
            'heure_debut' => '08:00:00',
            'heure_fin' => '17:00:00',
            'type' => 'normal',
        ]);

        $leave = LeaveRequest::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'date_debut' => $day->toDateString(),
            'date_fin' => $day->toDateString(),
            'statut' => 'demande',
        ]);

        $response = $this->actingAs($this->rhA)
            ->patchJson("/api/leave-requests/{$leave->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.statut', 'valide');

        $this->assertNotEmpty($response->json('warnings'));
        $this->assertEquals('overlap_schedule', $response->json('warnings.0.type'));
    }

    // --- §4 : gardes et astreintes -------------------------------------------

    public function test_on_call_now_reflects_the_current_time(): void
    {
        $today = now()->startOfDay();

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'jour_semaine' => null,
            'date' => $today->toDateString(),
            'heure_debut' => '20:00:00',
            'heure_fin' => '23:59:59',
            'type' => 'garde',
        ]);

        Carbon::setTestNow($today->clone()->setTime(21, 0));

        $this->actingAs($this->secretaireA)
            ->getJson('/api/on-call/now')
            ->assertOk()
            ->assertJsonFragment(['user_id' => $this->medecinA->id]);

        Carbon::setTestNow($today->clone()->setTime(6, 0));

        $this->actingAs($this->secretaireA)
            ->getJson('/api/on-call/now')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_on_call_view_is_filterable_by_site(): void
    {
        $day = now()->addWeek()->startOfDay();

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'jour_semaine' => null,
            'date' => $day->toDateString(),
            'heure_debut' => '20:00:00',
            'heure_fin' => '23:00:00',
            'type' => 'garde',
        ]);

        $doctorSiteA2 = User::factory()->for($this->structureA)->create();
        $doctorSiteA2->assignRole('medecin');

        WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $doctorSiteA2->id,
            'site_id' => $this->siteA2->id,
            'jour_semaine' => null,
            'date' => $day->toDateString(),
            'heure_debut' => '20:00:00',
            'heure_fin' => '23:00:00',
            'type' => 'astreinte',
        ]);

        $response = $this->actingAs($this->rhA)->getJson(
            '/api/on-call?site_id='.$this->siteA->id.'&from='.$day->toDateString().'&to='.$day->toDateString()
        );

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertTrue(collect($data)->every(fn ($entry) => $entry['site_id'] === $this->siteA->id));
        $this->assertFalse(collect($data)->contains('user_id', $doctorSiteA2->id));
    }

    // --- §5 : un utilisateur standard ne voit que son propre planning -------

    public function test_a_standard_user_cannot_view_another_users_planning_or_schedules(): void
    {
        $schedule = WorkSchedule::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
        ]);

        $leave = LeaveRequest::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
        ]);

        // secretaireA holds neither rh.view nor conges.view: own data is
        // reachable (auto-consultation, §5), another user's is not.
        $this->actingAs($this->secretaireA)->getJson("/api/work-schedules/{$schedule->id}")->assertStatus(403);
        $this->actingAs($this->secretaireA)->getJson("/api/leave-requests/{$leave->id}")->assertStatus(403);
        $this->actingAs($this->secretaireA)->getJson("/api/employees/{$this->medecinA->id}/planning")->assertStatus(403);

        $this->actingAs($this->secretaireA)->getJson('/api/work-schedules?user_id='.$this->medecinA->id)->assertStatus(403);

        $ownLeave = LeaveRequest::factory()->for($this->structureA)->create([
            'user_id' => $this->secretaireA->id,
        ]);

        $this->actingAs($this->secretaireA)->getJson("/api/leave-requests/{$ownLeave->id}")->assertOk();
        $this->actingAs($this->secretaireA)->getJson("/api/employees/{$this->secretaireA->id}/planning")->assertOk();
    }

    // --- Isolation multi-tenant ------------------------------------------

    public function test_employee_profiles_work_schedules_and_leave_requests_are_tenant_isolated(): void
    {
        $userB = User::factory()->for($this->structureB)->create();

        $profileB = EmployeeProfile::factory()->for($this->structureB)->create(['user_id' => $userB->id]);
        $scheduleB = WorkSchedule::factory()->for($this->structureB)->create([
            'user_id' => $userB->id,
            'site_id' => Site::factory()->for($this->structureB)->create()->id,
        ]);
        $leaveB = LeaveRequest::factory()->for($this->structureB)->create(['user_id' => $userB->id]);

        $this->actingAs($this->rhA)->getJson("/api/employee-profiles/{$profileB->id}")->assertNotFound();
        $this->actingAs($this->rhA)->getJson("/api/work-schedules/{$scheduleB->id}")->assertNotFound();
        $this->actingAs($this->rhA)->getJson("/api/leave-requests/{$leaveB->id}")->assertNotFound();
    }
}
