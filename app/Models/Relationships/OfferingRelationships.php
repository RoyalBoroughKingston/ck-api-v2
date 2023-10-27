<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Service;

trait OfferingRelationships
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
