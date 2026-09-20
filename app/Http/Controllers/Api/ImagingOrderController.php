<?php

namespace App\Http\Controllers\Api;

use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Imagerie\Models\ImagingReport;
use App\Domain\Imagerie\Models\ImagingStudy;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImagingOrderRequest;
use App\Http\Resources\ImagingOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ImagingOrderController extends Controller implements HasMiddleware
{
    private const CLOSED_STATUSES = ['transmis', 'annule'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:imagerie.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:imagerie.create', only: ['store']),
            new Middleware('permission:imagerie.update', only: ['update']),
            new Middleware('permission:imagerie.cancel', only: ['cancel']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $orders = ImagingOrder::query()
            ->with(['patient:id,first_name,last_name,patient_number', 'requester', 'site:id,name'])
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when($request->integer('requester_id'), fn ($q, $id) => $q->where('requester_id', $id)->where('requester_type', User::class))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->string('exam_type')->isNotEmpty(), fn ($q) => $q->where('exam_type', $request->string('exam_type')))
            ->orderByDesc('ordered_at')
            ->paginate();

        return ImagingOrderResource::collection($orders)->response();
    }

    public function stats(): JsonResponse
    {
        return response()->json(['data' => [
            'examens_en_attente' => ImagingOrder::where('status', 'demande')->count(),
            'realises_aujourdhui' => ImagingStudy::whereDate('performed_at', now())->count(),
            'comptes_rendus_en_attente_validation' => ImagingReport::where('status', 'brouillon')->count(),
            'transmis_aujourdhui' => ImagingStudy::where('status', 'transmis')->whereDate('updated_at', now())->count(),
        ]]);
    }

    public function store(ImagingOrderRequest $request): JsonResponse
    {
        $data = $this->translatePrescriberId($request->validated());

        $order = ImagingOrder::create([...$data, 'ordered_at' => now()])->refresh();

        return (new ImagingOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(ImagingOrder $imagingOrder): ImagingOrderResource
    {
        return new ImagingOrderResource($imagingOrder->load([
            'patient:id,first_name,last_name,patient_number',
            'requester',
            'site:id,name',
            'studies.report.author',
            'studies.report.validator',
        ]));
    }

    public function update(ImagingOrderRequest $request, ImagingOrder $imagingOrder): ImagingOrderResource
    {
        abort_if(in_array($imagingOrder->status, self::CLOSED_STATUSES, true), 422, 'Cette demande ne peut plus être modifiée.');

        $imagingOrder->update($this->translatePrescriberId($request->validated()));

        return new ImagingOrderResource($imagingOrder);
    }

    public function cancel(ImagingOrder $imagingOrder): ImagingOrderResource
    {
        abort_if(in_array($imagingOrder->status, self::CLOSED_STATUSES, true), 422, 'Cette demande ne peut plus être annulée.');

        $imagingOrder->update(['status' => 'annule']);

        return new ImagingOrderResource($imagingOrder);
    }

    /**
     * Étape 7b : le payload staff continue d'accepter prescriber_id (contrat
     * inchangé) mais l'écriture cible désormais le couple polymorphe
     * requester_type/requester_id — cf. ImagingOrder::requester().
     */
    private function translatePrescriberId(array $data): array
    {
        if (array_key_exists('prescriber_id', $data)) {
            $data['requester_type'] = User::class;
            $data['requester_id'] = $data['prescriber_id'];
            unset($data['prescriber_id']);
        }

        return $data;
    }
}
