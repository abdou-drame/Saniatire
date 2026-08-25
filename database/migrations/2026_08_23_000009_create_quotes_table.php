<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_convention_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->string('numero')->nullable();
            $table->date('date_emission');
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->enum('statut', ['brouillon', 'emis', 'converti', 'expire', 'annule'])->default('brouillon');

            $table->timestamps();

            $table->index(['patient_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
