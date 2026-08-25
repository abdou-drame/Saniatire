<?php

namespace Tests\Feature;

use App\Domain\Caisse\Models\Payment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Icd\Models\IcdCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsExportTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Site $siteA;

    private Patient $patientA;

    private User $directionA;

    private User $infirmierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();
        $this->patientA = Patient::factory()->for($this->structureA)->create();

        $this->directionA = User::factory()->for($this->structureA)->create();
        $this->directionA->assignRole('direction');

        $this->infirmierA = User::factory()->for($this->structureA)->create();
        $this->infirmierA->assignRole('infirmier');
    }

    private function parseCsv(string $content): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", trim($content))));

        return array_map('str_getcsv', $lines);
    }

    public function test_epidemiological_export_returns_a_csv_with_correct_counts(): void
    {
        $code = IcdCode::factory()->create(['code' => 'I10', 'level' => 'code']);
        $medecin = User::factory()->for($this->structureA)->create();

        $consult1 = Consultation::factory()->for($this->structureA)->create(['patient_id' => $this->patientA->id, 'practitioner_id' => $medecin->id, 'site_id' => $this->siteA->id]);
        $consult2 = Consultation::factory()->for($this->structureA)->create(['patient_id' => $this->patientA->id, 'practitioner_id' => $medecin->id, 'site_id' => $this->siteA->id]);

        ConsultationDiagnosis::factory()->create(['consultation_id' => $consult1->id, 'icd_code_id' => $code->id, 'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension']);
        ConsultationDiagnosis::factory()->create(['consultation_id' => $consult2->id, 'icd_code_id' => $code->id, 'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension']);

        $response = $this->actingAs($this->directionA)->get('/api/reports/epidemiologie/export')->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_shift($rows);
        $this->assertSame(['code', 'label', 'total'], $header);

        $dataRow = collect($rows)->first(fn ($r) => $r[0] === 'I10');
        $this->assertNotNull($dataRow);
        $this->assertEquals('2', $dataRow[2]);
    }

    public function test_revenue_export_returns_a_csv_with_correct_totals_by_site_and_mode(): void
    {
        $invoice = Invoice::factory()->for($this->structureA)->create(['site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id]);

        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoice->id, 'site_id' => $this->siteA->id,
            'mode_paiement' => 'especes', 'montant' => 7000, 'paid_at' => now(),
        ]);
        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoice->id, 'site_id' => $this->siteA->id,
            'mode_paiement' => 'carte', 'montant' => 3000, 'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->directionA)->get('/api/reports/chiffre-affaires/export')->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_shift($rows);
        $this->assertSame(['dimension', 'cle', 'total'], $header);

        $siteRow = collect($rows)->first(fn ($r) => $r[0] === 'site' && $r[1] === (string) $this->siteA->id);
        $this->assertEquals('10000', $siteRow[2]);

        $especesRow = collect($rows)->first(fn ($r) => $r[0] === 'mode_paiement' && $r[1] === 'especes');
        $this->assertEquals('7000', $especesRow[2]);

        $carteRow = collect($rows)->first(fn ($r) => $r[0] === 'mode_paiement' && $r[1] === 'carte');
        $this->assertEquals('3000', $carteRow[2]);
    }

    public function test_balance_agee_export_returns_a_csv_with_correct_aging_bucket(): void
    {
        $invoice = Invoice::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id,
            'date_emission' => now()->subDays(10)->toDateString(),
            'montant_total' => 1500, 'statut' => 'emise',
        ]);

        $response = $this->actingAs($this->directionA)->get('/api/reports/balance-agee/export')->assertOk();

        $rows = $this->parseCsv($response->streamedContent());
        $header = array_shift($rows);
        $this->assertContains('bucket', $header);
        $this->assertContains('solde', $header);

        $bucketIndex = array_search('bucket', $header);
        $soldeIndex = array_search('solde', $header);
        $invoiceIdIndex = array_search('invoice_id', $header);

        $row = collect($rows)->first(fn ($r) => $r[$invoiceIdIndex] === (string) $invoice->id);
        $this->assertNotNull($row);
        $this->assertEquals('0-30', $row[$bucketIndex]);
        $this->assertEquals('1500', $row[$soldeIndex]);
    }

    // --- Permissions --------------------------------------------------

    public function test_a_nurse_cannot_export_reports(): void
    {
        $this->actingAs($this->infirmierA)->get('/api/reports/epidemiologie/export')->assertForbidden();
    }
}
