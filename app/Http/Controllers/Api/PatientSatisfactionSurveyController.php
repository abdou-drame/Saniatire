<?php

namespace App\Http\Controllers\Api;

use App\Domain\Notification\NotificationDispatcher;
use App\Domain\Patient\Models\Patient;
use App\Domain\Qualite\Models\PatientSatisfactionSurvey;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientSatisfactionSurveyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PatientSatisfactionSurveyController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:qualite.view', only: ['index']),
            new Middleware('permission:qualite.create', only: ['store', 'sendInvitation']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $surveys = PatientSatisfactionSurvey::query()
            ->when($request->filled('service'), fn ($q) => $q->where('service', $request->string('service')))
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('date')
            ->paginate();

        return PatientSatisfactionSurveyResource::collection($surveys)->response();
    }

    /**
     * note : between:1,10 — hors plage (ex. 15) rejeté en 422 (étape 8
     * §7, testé explicitement).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'prestation_type' => ['nullable', 'string'],
            'prestation_id' => ['nullable', 'integer'],
            'service' => ['nullable', 'string'],
            'note' => ['required', 'integer', 'between:1,10'],
            'commentaire' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
        ]);

        $data['date'] = $data['date'] ?? now()->toDateString();

        $survey = PatientSatisfactionSurvey::create($data)->refresh();

        return (new PatientSatisfactionSurveyResource($survey))->response()->setStatusCode(201);
    }

    /**
     * Geste explicite du personnel (décision 4 de l'étape 8) : pas de
     * listener automatique sur la clôture d'une prestation. Passe par
     * NotificationDispatcher::send() (étape 7a), envoi immédiat
     * (scheduledFor=null).
     */
    public function sendInvitation(Request $request, NotificationDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'service' => ['nullable', 'string'],
        ]);

        $patient = Patient::findOrFail($data['patient_id']);

        $dispatcher->send($patient, 'enquete_satisfaction', [
            'patient_nom' => trim("{$patient->first_name} {$patient->last_name}"),
            'service' => $data['service'] ?? '',
        ]);

        return response()->json(['message' => 'Invitation envoyée.']);
    }
}
