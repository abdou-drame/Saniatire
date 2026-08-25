<?php

namespace App\Http\Controllers\Api;

use App\Domain\Rh\Models\EmployeeProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeProfileRequest;
use App\Http\Resources\EmployeeProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmployeeProfileController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:rh.view', only: ['index', 'show']),
            new Middleware('permission:rh.create', only: ['store']),
            new Middleware('permission:rh.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = EmployeeProfile::query();

        if ($request->filled('statut_emploi')) {
            $query->where('statut_emploi', $request->string('statut_emploi'));
        }

        if ($request->integer('site_id')) {
            $siteId = $request->integer('site_id');
            $query->whereHas('user.sites', fn ($q) => $q->where('sites.id', $siteId));
        }

        return EmployeeProfileResource::collection($query->orderBy('id')->paginate())->response();
    }

    public function store(EmployeeProfileRequest $request): JsonResponse
    {
        $profile = EmployeeProfile::create($request->validated())->refresh();

        return (new EmployeeProfileResource($profile))->response()->setStatusCode(201);
    }

    public function show(EmployeeProfile $employeeProfile): EmployeeProfileResource
    {
        return new EmployeeProfileResource($employeeProfile);
    }

    public function update(EmployeeProfileRequest $request, EmployeeProfile $employeeProfile): EmployeeProfileResource
    {
        $employeeProfile->update($request->validated());

        return new EmployeeProfileResource($employeeProfile);
    }
}
