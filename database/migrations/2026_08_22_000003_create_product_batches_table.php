<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            $table->string('numero_lot');
            $table->date('date_peremption');
            $table->unsignedInteger('quantite_stock')->default(0);
            $table->decimal('prix_achat_unitaire', 10, 2);

            $table->timestamps();

            $table->index(['product_id', 'site_id']);
            $table->index(['date_peremption']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
