<?php

namespace App\Http\Controllers\Api;

use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabResult;
use App\Domain\Laboratoire\Models\LabSample;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\LabOrderRequest;
use App\Http\Resources\LabOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LabOrderController extends Controller implements HasMiddleware
{
    private const CLOSED_STATUSES = ['transmis', 'annule'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:laboratoire.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:laboratoire.create', only: ['store']),
            new Middleware('permission:laboratoire.update', only: ['update']),
            new Middleware('permission:laboratoire.cancel', only: ['cancel']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $orders = LabOrder::query()
            ->with(['patient:id,first_name,last_name,patient_number', 'requester', 'site:id,name'])
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when($request->integer('requester_id'), fn ($q, $id) => $q->where('requester_id', $id)->where('requester_type', User::class))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('ordered_at')
            ->paginate();

        return LabOrderResource::collection($orders)->response();
    }

    public function stats(): JsonResponse
    {
        return response()->json(['data' => [
            'demandes_en_attente' => LabOrder::where('status', 'demande')->count(),
            'prelevements_du_jour' => LabSample::whereDate('collected_at', now())->count(),
            'resultats_en_attente_validation' => LabResult::where('status', 'validation_biologique_attente')->count(),
            'resultats_transmis_aujourdhui' => LabResult::where('status', 'transmis')->whereDate('updated_at', now())->count(),
        ]]);
    }

    public function store(LabOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);
        $data = $this->translatePrescriberId($data);

        $order = LabOrder::create([...$data, 'ordered_at' => now()])->refresh();

        foreach ($items as $item) {
            $order->items()->create(['loinc_code_id' => $item['loinc_code_id']]);
        }

        return (new LabOrderResource($order->load('items.loincCode')))->response()->setStatusCode(201);
    }

    public function show(LabOrder $labOrder): LabOrderResource
    {
        return new LabOrderResource($labOrder->load([
            'patient:id,first_name,last_name,patient_number',
            'requester',
            'site:id,name',
            'items.loincCode',
            'items.result.technicalValidator',
            'items.result.biologicalValidator',
            'samples.results',
        ]));
    }

    public function update(LabOrderRequest $request, LabOrder $labOrder): LabOrderResource
    {
        abort_if(in_array($labOrder->status, self::CLOSED_STATUSES, true), 422, 'Cette demande ne peut plus être modifiée.');

        $data = $request->validated();
        unset($data['items']);
        $data = $this->translatePrescriberId($data);

        $labOrder->update($data);

        return new LabOrderResource($labOrder);
    }

    public function cancel(LabOrder $labOrder): LabOrderResource
    {
        abort_if(in_array($labOrder->status, self::CLOSED_STATUSES, true), 422, 'Cette demande ne peut plus être annulée.');

        $labOrder->update(['status' => 'annule']);

        return new LabOrderResource($labOrder);
    }

    /**
     * Étape 7b : le payload staff continue d'accepter prescriber_id (contrat
     * inchangé) mais l'écriture cible désormais le couple polymorphe
     * requester_type/requester_id — cf. LabOrder::requester().
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
