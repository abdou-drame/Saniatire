<?php

namespace App\Http\Controllers\Api;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.view', only: ['index', 'show']),
            new Middleware('permission:users.create', only: ['store']),
            new Middleware('permission:users.update', only: ['update']),
            new Middleware('permission:users.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        $users = User::query()->with('roles', 'sites')->paginate();

        return UserResource::collection($users)->response();
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();

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

    public function update(UserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        $user->update([
            ...$data,
            'password' => filled($data['password'] ?? null) ? Hash::make($data['password']) : $user->password,
        ]);

        $user->syncRoles([$data['role']]);

        if (array_key_exists('site_ids', $data)) {
            $user->sites()->sync($data['site_ids'] ?? []);
        }

        return new UserResource($user->load('roles', 'sites'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(null, 204);
    }
}
