<?php

namespace App\Domain\Notification\Events;

use App\Domain\Rh\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CongeValide
{
    use Dispatchable, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest)
    {
    }
}
