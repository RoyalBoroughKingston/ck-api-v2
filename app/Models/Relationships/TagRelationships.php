<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Service;

trait TagRelationships
{
    /**
     * The services that belong to the tag.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }
}
