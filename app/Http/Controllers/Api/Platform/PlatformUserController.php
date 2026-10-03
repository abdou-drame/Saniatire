<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Shared\Tenancy\TenantScope;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use App\Http\Controllers\Api\Platform\Concerns\AuditsPlatformActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformStaffUserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Livraison C : gestion des comptes du personnel (modèle User uniquement)
 * par l'administrateur de plateforme — administrateurs d'une structure,
 * déblocage et réinitialisation de mot de passe. Les patients et
 * prescripteurs n'ont aucune route ici : ils gardent leur propre lien
 * « mot de passe oublié ».
 *
 * TenantScope : sous le guard platform, currentStructureId() est null (il
 * ne consulte que sanctum/patient/prescriber), le scope global ne filtre
 * donc rien. Chaque requête filtre quand même explicitement par
 * structure_id, et le scope est retiré explicitement plutôt que de compter
 * sur cette absence de filtre. Les utilisateurs sont résolus manuellement
 * (pas de route model binding sur {user}) pour la même raison.
 */
class PlatformUserController extends Controller
{
    use AuditsPlatformActions;

    public function administrators(Structure $structure): JsonResponse
    {
        $administrators = $this->administratorsOf($structure)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return PlatformStaffUserResource::collection($administrators)->response();
    }

    /**
     * Même logique que PlatformStructureController::store() pour le premier
     * administrateur : mot de passe généré (jamais choisi par la
     * plateforme), must_change_password, rôle administrateur. Retourné en
     * clair une seule fois dans cette réponse, jamais journalisé.
     */
    public function storeAdministrator(Request $request, Structure $structure): JsonResponse
    {
        $this->abortIfArchived($structure);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
        ]);

        $result = DB::transaction(function () use ($data, $request, $structure) {
            $generatedPassword = Str::password(16);

            $admin = User::create([
                'structure_id' => $structure->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($generatedPassword),
                'must_change_password' => true,
                'is_active' => true,
            ]);
            $admin->assignRole('administrateur');

            $this->auditPlatformAction($request, $admin, $structure->id, 'creation_administrateur', [
                'user_id' => $admin->id,
                'email' => $admin->email,
            ]);

            return ['admin' => $admin, 'password' => $generatedPassword];
        });

        return response()->json([
            'data' => new PlatformStaffUserResource($result['admin']->refresh()),
            'generated_password' => $result['password'],
            'message' => "Mot de passe généré à communiquer une seule fois à l'administrateur — il ne sera plus jamais restitué, un changement est exigé à sa première connexion.",
        ], 201);
    }

    /**
     * Désactivation : is_active=false (refusé ensuite par
     * AuthController::login()) et révocation de tous ses tokens Sanctum,
     * sans quoi une session déjà ouverte resterait utilisable.
     */
    public function deactivateAdministrator(Request $request, Structure $structure, string $user): PlatformStaffUserResource
    {
        $this->abortIfArchived($structure);
        $admin = $this->findAdministrator($structure, $user);

        DB::transaction(function () use ($request, $structure, $admin) {
            $admin->forceFill(['is_active' => false])->save();
            $admin->tokens()->delete();

            $this->auditPlatformAction($request, $admin, $structure->id, 'desactivation_administrateur', [
                'user_id' => $admin->id,
                'email' => $admin->email,
            ]);
        });

        return new PlatformStaffUserResource($admin);
    }

    public function activateAdministrator(Request $request, Structure $structure, string $user): PlatformStaffUserResource
    {
        $this->abortIfArchived($structure);
        $admin = $this->findAdministrator($structure, $user);

        $admin->forceFill(['is_active' => true])->save();

        $this->auditPlatformAction($request, $admin, $structure->id, 'activation_administrateur', [
            'user_id' => $admin->id,
            'email' => $admin->email,
        ]);

        return new PlatformStaffUserResource($admin);
    }

    /**
     * Même remise à zéro que AuthController::login() après une connexion
     * réussie (failed_login_attempts=0, locked_until=null).
     */
    public function unlock(Request $request, string $user): PlatformStaffUserResource
    {
        $staff = $this->findStaffUser($user);

        $staff->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $this->auditPlatformAction($request, $staff, $staff->structure_id, 'deblocage_compte', [
            'user_id' => $staff->id,
            'email' => $staff->email,
        ]);

        return new PlatformStaffUserResource($staff);
    }

    /**
     * Nouveau mot de passe généré (jamais choisi par la plateforme),
     * changement exigé à la prochaine connexion (EnsureNoPendingPasswordChange),
     * tous les tokens existants révoqués et compte débloqué. Le mot de passe
     * n'est jamais journalisé.
     */
    public function resetPassword(Request $request, string $user): JsonResponse
    {
        $staff = $this->findStaffUser($user);

        $structure = Structure::withTrashed()->find($staff->structure_id);
        abort_if($structure?->trashed(), 409, 'La structure de ce compte est archivée : elle reste consultable mais ne peut plus être modifiée.');

        $generatedPassword = Str::password(16);

        DB::transaction(function () use ($request, $staff, $generatedPassword) {
            $staff->forceFill([
                'password' => Hash::make($generatedPassword),
                'must_change_password' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();
            $staff->tokens()->delete();

            $this->auditPlatformAction($request, $staff, $staff->structure_id, 'reinitialisation_mot_de_passe', [
                'user_id' => $staff->id,
                'email' => $staff->email,
            ]);
        });

        return response()->json([
            'data' => new PlatformStaffUserResource($staff),
            'generated_password' => $generatedPassword,
            'message' => "Mot de passe généré à communiquer une seule fois à l'utilisateur — il ne sera plus jamais restitué, un changement est exigé à sa prochaine connexion.",
        ]);
    }

    private function administratorsOf(Structure $structure): Builder
    {
        return User::withoutGlobalScope(TenantScope::class)
            ->where('structure_id', $structure->id)
            ->role('administrateur');
    }

    /**
     * 404 (et non 403) pour un compte d'une autre structure ou sans le rôle
     * administrateur, même convention que TenantScope : ne pas révéler
     * l'existence d'un compte hors du périmètre demandé.
     */
    private function findAdministrator(Structure $structure, string $id): User
    {
        return $this->administratorsOf($structure)->whereKey($id)->firstOrFail();
    }

    private function findStaffUser(string $id): User
    {
        return User::withoutGlobalScope(TenantScope::class)->whereKey($id)->firstOrFail();
    }

    private function abortIfArchived(Structure $structure): void
    {
        abort_if($structure->trashed(), 409, 'Cette structure est archivée : elle reste consultable mais ne peut plus être modifiée.');
    }
}
