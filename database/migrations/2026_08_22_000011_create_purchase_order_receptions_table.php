<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_receptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receptionne_par')->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('quantite_recue');
            $table->date('date_reception');
            $table->enum('controle_qualite', ['conforme', 'non_conforme']);
            $table->string('numero_lot');
            $table->date('date_peremption');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receptions');
    }
};
