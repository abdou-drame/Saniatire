<?php

namespace App\Http\Resources;

use App\Domain\Shared\Specialty\SpecialtyRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'patient_id' => $this->patient_id,
            'practitioner_id' => $this->practitioner_id,
            'site_id' => $this->site_id,
            'appointment_id' => $this->appointment_id,
            'specialty_type' => $this->specialty_type,
            'specialty' => $this->when((bool) $this->specialty_type, function () use ($request) {
                $modelClass = SpecialtyRegistry::modelFor($this->specialty_type);
                $resourceClass = SpecialtyRegistry::resourceFor($this->specialty_type);

                if (! $modelClass || ! $resourceClass) {
                    return null;
                }

                // Embedding the specialty record here (e.g. in the unified
                // patient timeline) must not bypass that specialty's own
                // permission gate — patients_medical.view alone is not
                // enough, exactly as it isn't on PmaRecordController /
                // MentalHealthRecordController. The module slug is always
                // the specialty_type string (see SpecialtyRegistry::MAP).
                if (! $request->user()?->can("{$this->specialty_type}.view")) {
                    return null;
                }

                $record = $modelClass::where('consultation_id', $this->id)->first();

                return $record ? new $resourceClass($record) : null;
            }),
            'reason' => $this->reason,
            'history_of_illness' => $this->history_of_illness,
            'vitals' => [
                'weight_kg' => $this->weight_kg,
                'height_cm' => $this->height_cm,
                'bmi' => $this->bmi,
                'temperature_c' => $this->temperature_c,
                'blood_pressure_systolic' => $this->blood_pressure_systolic,
                'blood_pressure_diastolic' => $this->blood_pressure_diastolic,
                'heart_rate' => $this->heart_rate,
                'respiratory_rate' => $this->respiratory_rate,
                'spo2' => $this->spo2,
                'glycemia' => $this->glycemia,
                'pain_scale' => $this->pain_scale,
                'extra' => $this->extra_vitals,
            ],
            'clinical_exam' => $this->clinical_exam,
            'recommendations' => $this->recommendations,
            'referral' => $this->referral,
            'follow_up_suggested_at' => $this->follow_up_suggested_at?->format('Y-m-d'),
            'status' => $this->status,
            'closed_at' => $this->closed_at,
            'diagnoses' => ConsultationDiagnosisResource::collection($this->whenLoaded('diagnoses')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
