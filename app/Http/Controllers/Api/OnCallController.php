<?php

namespace App\Http\Controllers\Api;

use App\Domain\Shared\Scheduling\PractitionerPresenceService;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkScheduleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OnCallController extends Controller implements HasMiddleware
{
    /**
     * "now" is deliberately left open to any authenticated user — in an
     * emergency, anyone needs to be able to find out who is on
     * garde/astreinte without first being granted an RH permission.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:rh.view', only: ['index']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $from = ($request->date('from') ?? now())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();

        $entries = app(PractitionerPresenceService::class)->onCallBetween(
            $request->user()->structure_id,
            $request->integer('site_id') ?: null,
            $from,
            $to,
        );

        return response()->json(['data' => $entries]);
    }

    public function now(Request $request): JsonResponse
    {
        $onCall = app(PractitionerPresenceService::class)->onCallNow(
            $request->user()->structure_id,
            $request->integer('site_id') ?: null,
            now(),
        );

        return WorkScheduleResource::collection($onCall)->response();
    }
}
