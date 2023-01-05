<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Referral;

class ReferralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'service_id' => function () {
                return \App\Models\Service::factory()->create()->id;
            },
            'status' => Referral::STATUS_NEW,
            'name' => $this->faker->name,
        ];
    }
}
