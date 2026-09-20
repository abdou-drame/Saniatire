<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administration plateforme : le premier administrateur d'une structure
 * nouvellement créée par un administrateur de plateforme reçoit un mot de
 * passe généré (jamais saisi/choisi par le platform admin) — cette colonne
 * force son changement à la première connexion. `false` par défaut pour
 * tous les comptes créés via le flux normal (UserController::store), où
 * l'administrateur qui crée le compte tape lui-même le mot de passe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
