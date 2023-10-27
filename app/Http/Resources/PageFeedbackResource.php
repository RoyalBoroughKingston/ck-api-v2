<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;

class PageFeedbackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'feedback' => $this->feedback,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'consented_at' => $this->consented_at?->format(CarbonImmutable::ISO8601),
            'created_at' => $this->created_at?->format(CarbonImmutable::ISO8601),
            'updated_at' => $this->updated_at?->format(CarbonImmutable::ISO8601),
        ];
    }
}
