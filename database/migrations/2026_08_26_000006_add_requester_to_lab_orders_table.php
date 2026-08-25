<?php

use App\Domain\User\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * prescriber_id devient un couple polymorphe requester_type/_id afin
     * qu'une demande d'examen puisse être créée soit par un praticien
     * interne (User), soit par un prescripteur externe (ExternalPrescriber,
     * étape 7b). Les lignes existantes sont rétro-remplies vers User::class
     * pour ne rien casser.
     */
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->nullableMorphs('requester');
        });

        DB::table('lab_orders')->update([
            'requester_type' => User::class,
            'requester_id' => DB::raw('prescriber_id'),
        ]);

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropForeign(['prescriber_id']);
            $table->dropColumn('prescriber_id');
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->foreignId('prescriber_id')->nullable()->constrained('users')->cascadeOnDelete();
        });

        DB::table('lab_orders')->update([
            'prescriber_id' => DB::raw('requester_id'),
        ]);

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropMorphs('requester');
        });
    }
};
