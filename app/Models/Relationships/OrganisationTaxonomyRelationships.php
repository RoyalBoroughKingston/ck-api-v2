<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Organisation;
use App\Models\Taxonomy;

trait OrganisationTaxonomyRelationships
{
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }
}
