<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ReportSchedule;
use App\Models\ReportType;
use Illuminate\Support\Arr;

class ReportScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'report_type_id' => ReportType::usersExport()->id,
            'repeat_type' => Arr::random([ReportSchedule::REPEAT_TYPE_WEEKLY, ReportSchedule::REPEAT_TYPE_MONTHLY]),
        ];
    }
}
