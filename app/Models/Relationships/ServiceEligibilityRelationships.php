<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Service;
use App\Models\Taxonomy;

trait ServiceEligibilityRelationships
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }
}
