<?php

namespace App\Http\Controllers\Api;

use App\Domain\Laboratoire\Models\LabOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\LabSampleRequest;
use App\Http\Resources\LabSampleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LabSampleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:laboratoire.create', only: ['store']),
        ];
    }

    /**
     * A single sample can cover several requested analyses, so registering
     * it marks every still-"demande" item on the order as "prelevee" —
     * see LabOrder::syncStatusFromChildren for how the order's own status
     * then follows.
     */
    public function store(LabSampleRequest $request, LabOrder $labOrder): JsonResponse
    {
        abort_if(in_array($labOrder->status, ['transmis', 'annule'], true), 422, 'Cette demande ne peut plus recevoir de prélèvement.');

        $sample = $labOrder->samples()->create([
            ...$request->validated(),
            'collected_at' => $request->validated('collected_at') ?? now(),
            'collected_by' => $request->user()->id,
        ])->refresh();

        $labOrder->items()->where('status', 'demande')->update(['status' => 'prelevee']);
        $labOrder->syncStatusFromChildren();

        return (new LabSampleResource($sample))->response()->setStatusCode(201);
    }
}
