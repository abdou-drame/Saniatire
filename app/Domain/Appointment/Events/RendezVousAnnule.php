<?php

namespace App\Domain\Appointment\Events;

use App\Domain\Appointment\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Handled by SendRendezVousAnnuleNotification (registered in
 * AppServiceProvider), regardless of which code path dispatches it
 * (staff-side AppointmentController::cancel() or the patient portal's
 * PatientPortalController::cancelAppointment()).
 */
class RendezVousAnnule
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }
}
