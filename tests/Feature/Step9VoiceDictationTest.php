<?php

namespace Tests\Feature;

use App\Domain\Ai\Models\VoiceDictation;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Step9VoiceDictationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_storing_an_audio_file_creates_a_pending_record_without_transcription(): void
    {
        $structure = Structure::factory()->create();
        $doctor = User::factory()->for($structure)->create();
        $doctor->assignRole('medecin');

        $response = $this->actingAs($doctor)
            ->postJson('/api/voice-dictations', [
                'audio' => UploadedFile::fake()->create('note.mp3', 500, 'audio/mpeg'),
            ])
            ->assertCreated();

        $this->assertSame('en_attente', $response->json('status'));
        $this->assertNull($response->json('transcription'));

        $dictation = VoiceDictation::findOrFail($response->json('id'));
        $this->assertSame($structure->id, $dictation->structure_id);
        $this->assertSame($doctor->id, $dictation->user_id);
        $this->assertNull($dictation->transcription);
        $this->assertSame('en_attente', $dictation->status);
        Storage::disk('local')->assertExists($dictation->audio_path);
    }

    public function test_storing_requires_the_dedicated_permission(): void
    {
        $structure = Structure::factory()->create();
        $secretary = User::factory()->for($structure)->create();
        $secretary->assignRole('secretaire');

        $this->actingAs($secretary)
            ->postJson('/api/voice-dictations', [
                'audio' => UploadedFile::fake()->create('note.mp3', 500, 'audio/mpeg'),
            ])
            ->assertForbidden();
    }

    public function test_a_user_only_sees_their_own_dictations(): void
    {
        $structure = Structure::factory()->create();
        $doctorA = User::factory()->for($structure)->create();
        $doctorA->assignRole('medecin');
        $doctorB = User::factory()->for($structure)->create();
        $doctorB->assignRole('medecin');

        $ownDictation = VoiceDictation::create([
            'structure_id' => $structure->id,
            'user_id' => $doctorA->id,
            'audio_path' => 'voice-dictations/a.mp3',
            'status' => 'en_attente',
        ]);

        VoiceDictation::create([
            'structure_id' => $structure->id,
            'user_id' => $doctorB->id,
            'audio_path' => 'voice-dictations/b.mp3',
            'status' => 'en_attente',
        ]);

        $response = $this->actingAs($doctorA)->getJson('/api/voice-dictations')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ownDictation->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_dictations_are_isolated_per_tenant(): void
    {
        $structureA = Structure::factory()->create();
        $doctorA = User::factory()->for($structureA)->create();
        $doctorA->assignRole('medecin');

        $structureB = Structure::factory()->create();
        $doctorB = User::factory()->for($structureB)->create();
        $doctorB->assignRole('medecin');

        $dictationB = VoiceDictation::create([
            'structure_id' => $structureB->id,
            'user_id' => $doctorB->id,
            'audio_path' => 'voice-dictations/b.mp3',
            'status' => 'en_attente',
        ]);

        $this->actingAs($doctorA)
            ->getJson("/api/voice-dictations/{$dictationB->id}")
            ->assertNotFound();
    }
}
