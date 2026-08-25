<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->enum('type', [
                'cabinet',
                'centre_specialise',
                'laboratoire',
                'imagerie',
                'clinique',
                'polyclinique',
                'groupe_sante',
            ]);
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('color_primary', 7)->nullable();
            $table->string('color_secondary', 7)->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->string('locale', 5)->default('fr');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structures');
    }
};
