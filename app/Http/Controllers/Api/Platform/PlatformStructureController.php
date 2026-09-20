<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Structure\Models\Structure;
use App\Domain\Structure\Models\StructureModule;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformStructureStoreRequest;
use App\Http\Requests\StructureRequest;
use App\Http\Resources\StructureResource;
use App\Http\Resources\UserResource;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Gestion des structures par l'administrateur de plateforme — seul acteur
 * pouvant créer une nouvelle structure cliente (voir la faille fermée sur
 * StructureController::store(), retiré du guard sanctum). Toute méthode
 * ici est gardée uniquement par `auth:platform` (routes/api.php) : aucune
 * permission Spatie supplémentaire, comme pour les guards patient/prescriber
 * — être authentifié sur ce guard suffit, il n'y a qu'une seule sorte
 * d'acteur possible.
 */
class PlatformStructureController extends Controller
{
    public function index(): JsonResponse
    {
        $structures = Structure::query()->orderBy('legal_name')->paginate();

        return StructureResource::collection($structures)->response();
    }

    public function show(Structure $structure): StructureResource
    {
        return new StructureResource($structure);
    }

    /**
     * Crée la structure et son tout premier compte administrateur dans la
     * même transaction — sans ce compte, personne ne pourrait jamais se
     * connecter à la structure nouvellement créée. Le mot de passe est
     * généré (jamais choisi par l'administrateur de plateforme) et
     * `must_change_password` force son changement dès la première
     * connexion (EnsureNoPendingPasswordChange). Retourné en clair une
     * seule fois dans cette réponse, jamais restitué ensuite.
     */
    public function store(PlatformStructureStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data, $request) {
            // Arr::except plutôt qu'un tableau explicite : currency/locale
            // ont un défaut en base (XOF/fr) qu'une clé forcée à null
            // écraserait, d'où l'omission des champs structure absents de
            // la requête plutôt que leur passage explicite à null.
            $structureData = collect($data)
                ->except(['admin_first_name', 'admin_last_name', 'admin_email'])
                ->merge(['is_active' => $data['is_active'] ?? true])
                ->all();

            $structure = Structure::create($structureData);

            $generatedPassword = Str::password(16);

            $admin = User::create([
                'structure_id' => $structure->id,
                'first_name' => $data['admin_first_name'],
                'last_name' => $data['admin_last_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($generatedPassword),
                'must_change_password' => true,
                'is_active' => true,
            ]);
            $admin->assignRole('administrateur');

            $now = now();
            StructureModule::insert(
                collect(RolePermissionSeeder::MODULES)->map(fn (string $module) => [
                    'structure_id' => $structure->id,
                    'module' => $module,
                    'is_active' => true,
                    'activated_at' => $now,
                    'deactivated_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );

            $this->auditPlatformAction($request, $structure, $structure->id, 'creation_structure', [
                'legal_name' => $structure->legal_name,
                'code' => $structure->code,
            ]);
            $this->auditPlatformAction($request, $admin, $structure->id, 'creation_premier_administrateur', [
                'user_id' => $admin->id,
                'email' => $admin->email,
            ]);

            return ['structure' => $structure, 'admin' => $admin, 'password' => $generatedPassword];
        });

        return response()->json([
            'structure' => new StructureResource($result['structure']),
            'admin' => new UserResource($result['admin']),
            'admin_generated_password' => $result['password'],
            'message' => "Mot de passe généré à communiquer une seule fois à l'administrateur — il ne sera plus jamais restitué, un changement est exigé à sa première connexion.",
        ], 201);
    }

    public function update(StructureRequest $request, Structure $structure): StructureResource
    {
        $structure->update($request->validated());

        $this->auditPlatformAction($request, $structure, $structure->id, 'modification_structure', [
            'champs' => array_keys($request->validated()),
        ]);

        return new StructureResource($structure);
    }

    public function activate(Request $request, Structure $structure): StructureResource
    {
        $structure->update(['is_active' => true]);

        $this->auditPlatformAction($request, $structure, $structure->id, 'activation_structure');

        return new StructureResource($structure);
    }

    public function deactivate(Request $request, Structure $structure): StructureResource
    {
        $structure->update(['is_active' => false]);

        $this->auditPlatformAction($request, $structure, $structure->id, 'desactivation_structure');

        return new StructureResource($structure);
    }

    /**
     * log_name distinct 'administration_plateforme', même patron que
     * 'partage_inter_structure' pour le référencement inter-structures :
     * chaque franchissement volontaire de l'isolation normale a sa propre
     * trace explicite. structure_id forcé à la structure concernée par
     * l'action (pas celle de l'acteur, qui n'en a pas) — sans ce tap(),
     * Activity::creating ne renseignerait rien, TenantScope::currentStructureId()
     * ne reconnaissant pas le guard platform.
     */
    private function auditPlatformAction(Request $request, $subject, int $structureId, string $action, array $properties = []): void
    {
        activity('administration_plateforme')
            ->causedBy($request->user('platform'))
            ->performedOn($subject)
            ->withProperties([
                ...$properties,
                'action' => $action,
                'hors_isolation' => true,
            ])
            ->tap(function ($activity) use ($structureId) {
                $activity->structure_id = $structureId;
            })
            ->log("Action de l'administrateur de plateforme ({$action}), hors du cadre normal d'isolation par structure.");
    }
}
