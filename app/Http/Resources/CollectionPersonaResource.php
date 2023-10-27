<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionPersonaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'intro' => $this->meta['intro'],
            'subtitle' => $this->meta['subtitle'],
            'order' => $this->order,
            'enabled' => $this->enabled,
            'homepage' => $this->homepage,
            'sideboxes' => $this->meta['sideboxes'],
            'category_taxonomies' => TaxonomyResource::collection($this->taxonomies),
            'created_at' => $this->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $this->updated_at->format(CarbonImmutable::ISO8601),
        ];
    }
}
