<?php

namespace App\Models\Relationships;

use App\Models\Organisation;
use App\Models\Taxonomy;

trait OrganisationTaxonomyRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class);
    }
}
