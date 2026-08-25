<?php

namespace Database\Factories;

use App\Domain\Qualite\Models\Complaint;
use App\Domain\Qualite\Models\ComplaintResponse;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintResponse>
 */
class ComplaintResponseFactory extends Factory
{
    protected $model = ComplaintResponse::class;

    public function definition(): array
    {
        return [
            'complaint_id' => Complaint::factory(),
            'auteur_id' => User::factory(),
            'message' => $this->faker->sentence(),
        ];
    }
}
