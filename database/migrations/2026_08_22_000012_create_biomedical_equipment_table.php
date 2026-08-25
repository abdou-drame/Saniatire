<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biomedical_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            $table->string('nom');
            $table->string('categorie');
            $table->string('numero_serie');
            $table->date('date_acquisition');
            $table->date('date_fin_garantie')->nullable();
            $table->enum('statut', ['en_service', 'en_maintenance', 'hors_service', 'reforme'])->default('en_service');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biomedical_equipment');
    }
};
