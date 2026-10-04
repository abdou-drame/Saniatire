<?php

namespace App\Http\Controllers\Api;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    /**
     * Seul ce rôle gère les accès (rôles, identifiants, comptes
     * administrateur). users.create/users.update (rh, direction) ne
     * suffisent pas : sans cette barrière, un compte rh pouvait se donner
     * le rôle administrateur, ou changer le mot de passe de
     * l'administrateur puis se connecter à sa place.
     */
    private const ACCESS_MANAGER_ROLE = 'administrateur';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.view', only: ['index', 'show', 'roles']),
            new Middleware('permission:users.create', only: ['store']),
            new Middleware('permission:users.update', only: ['update', 'updateTwoFactorRequirement']),
            new Middleware('permission:users.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        $users = User::query()->with('roles', 'sites')->paginate();

        return UserResource::collection($users)->response();
    }

    /**
     * Rôles que l'utilisateur connecté peut attribuer — utilisée par le
     * sélecteur de rôle de l'écran de gestion des comptes. Gardée derrière
     * users.view (même garde que index/show) plutôt qu'une nouvelle
     * permission dédiée : c'est un référentiel de lecture, pas une action.
     */
    public function roles(Request $request): JsonResponse
    {
        $actor = $request->user();

        $actorPermissions = $actor->getAllPermissions()->pluck('name');

        $roles = Role::query()->orderBy('name')->get()
            ->filter(fn (Role $role) => $this->canAssignRole($actor, $role->name, $actorPermissions))
            ->pluck('name')
            ->values();

        return response()->json(['data' => $roles]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $this->canAssignRole($request->user(), $data['role'])) {
            return $this->forbidden("Vous ne pouvez pas attribuer un rôle disposant de droits que vous n'avez pas.");
        }

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);
        $user->sites()->sync($data['site_ids'] ?? []);

        return (new UserResource($user->load('roles', 'sites')))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles', 'sites'));
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        if (! $this->isAccessManager($actor)) {
            if ($user->hasRole(self::ACCESS_MANAGER_ROLE)) {
                return $this->forbidden('Seul un administrateur peut modifier un compte administrateur.');
            }

            if (! $user->hasRole($data['role']) || $user->roles->count() !== 1) {
                return $this->forbidden("Seul un administrateur peut changer le rôle d'un utilisateur.");
            }

            // Email et mot de passe sont les identifiants de connexion : les
            // changer revient à prendre le contrôle du compte (directement,
            // ou via « mot de passe oublié » vers une adresse choisie).
            if (filled($data['password'] ?? null) || strcasecmp($data['email'], $user->email) !== 0) {
                return $this->forbidden("Seul un administrateur peut changer l'email ou le mot de passe d'un utilisateur.");
            }
        }

        $user->update([
            ...$data,
            'password' => filled($data['password'] ?? null) ? Hash::make($data['password']) : $user->password,
        ]);

        $user->syncRoles([$data['role']]);

        // Nouveau mot de passe imposé : les sessions ouvertes avec l'ancien
        // sont fermées.
        if (filled($data['password'] ?? null)) {
            $user->tokens()->delete();
        }

        if (array_key_exists('site_ids', $data)) {
            $user->sites()->sync($data['site_ids'] ?? []);
        }

        return (new UserResource($user->load('roles', 'sites')))->response();
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->hasRole(self::ACCESS_MANAGER_ROLE) && ! $this->isAccessManager($request->user())) {
            return $this->forbidden('Seul un administrateur peut supprimer un compte administrateur.');
        }

        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * L'administrateur exige (ou n'exige plus) la 2FA pour un compte. Une
     * fois exigée, le compte doit la configurer à sa prochaine connexion
     * (EnsureTwoFactorSetupComplete) et ne peut plus la désactiver.
     */
    public function updateTwoFactorRequirement(Request $request, User $user): JsonResponse
    {
        if (! $this->isAccessManager($request->user())) {
            return $this->forbidden("Seul un administrateur peut exiger l'authentification à deux facteurs.");
        }

        $data = $request->validate(['required' => ['required', 'boolean']]);

        $user->forceFill(['two_factor_required' => $data['required']])->save();

        activity('2fa')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties(['two_factor_required' => $data['required']])
            ->log($data['required']
                ? 'Authentification à deux facteurs exigée pour ce compte.'
                : "L'authentification à deux facteurs n'est plus exigée pour ce compte.");

        return (new UserResource($user->load('roles', 'sites')))->response();
    }

    private function isAccessManager(User $actor): bool
    {
        return $actor->hasRole(self::ACCESS_MANAGER_ROLE);
    }

    /**
     * Un administrateur attribue n'importe quel rôle. Les autres ne peuvent
     * attribuer qu'un rôle dont ils détiennent déjà toutes les permissions :
     * on ne délègue jamais un droit qu'on n'a pas soi-même.
     */
    private function canAssignRole(User $actor, string $roleName, ?Collection $actorPermissions = null): bool
    {
        if ($this->isAccessManager($actor)) {
            return true;
        }

        if ($roleName === self::ACCESS_MANAGER_ROLE) {
            return false;
        }

        $role = Role::query()->with('permissions')->where('name', $roleName)->first();

        if (! $role) {
            return false;
        }

        $actorPermissions ??= $actor->getAllPermissions()->pluck('name');

        return $role->permissions->pluck('name')->diff($actorPermissions)->isEmpty();
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 403);
    }
}
