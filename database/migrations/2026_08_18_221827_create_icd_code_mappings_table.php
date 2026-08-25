<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CIM-10 <-> CIM-11 correspondence. Deliberately plain string columns
     * (not FKs to icd_codes.id) so the official WHO GEM mapping tables —
     * which ship as flat code-to-code lists — can be imported directly
     * later, even for codes not yet present in icd_codes.
     */
    public function up(): void
    {
        Schema::create('icd_code_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('code_source');
            $table->enum('version_source', ['CIM-10', 'CIM-11']);
            $table->string('code_cible');
            $table->enum('version_cible', ['CIM-10', 'CIM-11']);
            $table->timestamps();

            $table->index(['code_source', 'version_source'], 'icd_code_mappings_source_index');
            $table->index(['code_cible', 'version_cible'], 'icd_code_mappings_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd_code_mappings');
    }
};
