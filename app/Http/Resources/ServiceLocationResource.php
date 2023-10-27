<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceLocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'location_id' => $this->location_id,
            'has_image' => $this->hasImage(),
            'name' => $this->name,
            'is_open_now' => $this->isOpenNow(),
            'regular_opening_hours' => RegularOpeningHourResource::collection($this->regularOpeningHours),
            'holiday_opening_hours' => HolidayOpeningHourResource::collection($this->holidayOpeningHours),
            'created_at' => $this->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $this->updated_at->format(CarbonImmutable::ISO8601),

            // Relationships.
            'location' => new LocationResource($this->whenLoaded('location')),
        ];
    }
}
