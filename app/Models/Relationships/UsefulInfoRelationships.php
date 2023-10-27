<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Service;

trait UsefulInfoRelationships
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
