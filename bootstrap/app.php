<?php

use App\Http\Middleware\EnsureNoPendingPasswordChange;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTwoFactorSetupComplete;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only backend: there is no "login" web route to redirect
        // guests to. Without this, Laravel's default redirectGuestsTo()
        // crashes (RouteNotFoundException) on any unauthenticated request
        // that doesn't explicitly send Accept: application/json.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'tenant' => EnsureTenantContext::class,
            'two_factor' => EnsureTwoFactorSetupComplete::class,
            'password_change' => EnsureNoPendingPasswordChange::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only backend: every response should be JSON, regardless of
        // the client's Accept header (many HTTP clients don't send one).
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Reinforced audit trail for PMA / santé mentale (see RolePermissionSeeder
        // and Pma/MentalHealth RecordControllers): a denied permission check must
        // be traced, not just rejected. This can't be done via ->report(), because
        // UnauthorizedException extends Symfony's HttpException, which is in
        // Laravel's Handler::$internalDontReport — report() short-circuits before
        // any reportable() callback runs. render() has no such gate, so a
        // renderable() callback is the reliable hook.
        //
        // It also replaces Spatie's raw, untranslated "User does not have the
        // right permissions." with a clean French message: frontend/api-error.ts
        // deliberately preserves any server-provided 403 message verbatim (some
        // controllers send a precise, intentional one), so without this the raw
        // English string was reaching end users as-is, app-wide, on every
        // permission/role-gated route.
        $exceptions->renderable(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            $permissions = $e->getRequiredPermissions();

            if (collect($permissions)->contains(fn ($p) => str_starts_with($p, 'pma.') || str_starts_with($p, 'sante_mentale.'))) {
                activity('acces_sensible')
                    ->causedBy($request->user())
                    ->withProperties([
                        'resultat' => 'refuse',
                        'permissions_requises' => $permissions,
                        'route' => $request->path(),
                        'methode' => $request->method(),
                    ])
                    ->log("Tentative d'accès refusée à une donnée sensible (PMA/santé mentale)");
            }

            return response()->json([
                'message' => "Vous n'avez pas les autorisations nécessaires pour effectuer cette action.",
            ], 403);
        });
    })->create();
