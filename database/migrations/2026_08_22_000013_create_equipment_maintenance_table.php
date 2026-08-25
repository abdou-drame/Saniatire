<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biomedical_equipment_id')->constrained('biomedical_equipment')->cascadeOnDelete();
            $table->foreignId('intervenant_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['preventive', 'corrective']);
            $table->date('date_prevue');
            $table->date('date_realisee')->nullable();
            $table->string('intervenant_externe')->nullable();
            $table->decimal('cout', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->enum('statut', ['planifiee', 'realisee', 'annulee'])->default('planifiee');

            $table->timestamps();

            $table->index(['biomedical_equipment_id', 'type', 'date_prevue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance');
    }
};
