<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * external_reference_url points at wherever the actual images live
     * (future PACS server) — no upload/storage is handled by this app.
     * See database/seeders/README-PACS.md for the intended integration.
     */
    public function up(): void
    {
        Schema::create('imaging_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_order_id')->constrained()->cascadeOnDelete();

            $table->string('study_instance_uid')->unique();
            $table->string('accession_number')->unique();
            $table->string('modality');
            $table->dateTime('performed_at')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_reference_url')->nullable();
            $table->string('storage_reference')->nullable();

            $table->enum('status', ['realise', 'en_interpretation', 'cr_redige', 'valide', 'transmis'])
                ->default('realise');

            $table->timestamps();

            $table->index(['imaging_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_studies');
    }
};
