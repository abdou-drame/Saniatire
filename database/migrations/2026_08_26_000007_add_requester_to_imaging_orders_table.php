<?php

use App\Domain\User\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->nullableMorphs('requester');
        });

        DB::table('imaging_orders')->update([
            'requester_type' => User::class,
            'requester_id' => DB::raw('prescriber_id'),
        ]);

        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->dropForeign(['prescriber_id']);
            $table->dropColumn('prescriber_id');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->foreignId('prescriber_id')->nullable()->constrained('users')->cascadeOnDelete();
        });

        DB::table('imaging_orders')->update([
            'prescriber_id' => DB::raw('requester_id'),
        ]);

        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->dropMorphs('requester');
        });
    }
};
