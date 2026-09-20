<?php

namespace App\Console\Commands;

use App\Domain\Platform\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Seule façon de créer un compte administrateur de plateforme — pas de
 * seeder avec identifiants en dur, pas de formulaire d'inscription public.
 * Le mot de passe est saisi au clavier (masqué) et jamais écrit dans un
 * fichier committé.
 */
class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:create-admin';

    protected $description = "Crée un compte administrateur de plateforme (guard `platform`), de façon interactive.";

    public function handle(): int
    {
        $name = $this->ask('Nom complet');

        $email = $this->ask('Email');
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'unique:platform_admins,email'],
        ]);
        if ($emailValidator->fails()) {
            $this->error($emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = $this->secret('Mot de passe (min. 8 caractères)');
        $passwordConfirmation = $this->secret('Confirmer le mot de passe');

        $passwordValidator = Validator::make(
            ['password' => $password, 'password_confirmation' => $passwordConfirmation],
            ['password' => ['required', 'string', 'min:8', 'confirmed']]
        );
        if ($passwordValidator->fails()) {
            $this->error($passwordValidator->errors()->first('password'));

            return self::FAILURE;
        }

        $admin = PlatformAdmin::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Administrateur de plateforme créé : {$admin->email} (id {$admin->id}).");

        return self::SUCCESS;
    }
}
