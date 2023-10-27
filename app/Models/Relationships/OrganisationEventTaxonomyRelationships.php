<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\OrganisationEvent;
use App\Models\Taxonomy;

trait OrganisationEventTaxonomyRelationships
{
    public function organisationEvent(): BelongsTo
    {
        return $this->belongsTo(OrganisationEvent::class);
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }
}
