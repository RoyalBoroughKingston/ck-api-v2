<?php

namespace App\Models\Relationships;

use App\Models\Location;
use App\Models\Organisation;
use App\Models\OrganisationEventTaxonomy;
use App\Models\Taxonomy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait OrganisationEventRelationships
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
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, (new OrganisationEventTaxonomy())->getTable());
    }

    public function organisationEventTaxonomies(): HasMany
    {
        return $this->hasMany(OrganisationEventTaxonomy::class);
    }

    /**
     * Return the OrganisationEventTaxonomy relationship.
     */
    public function taxonomyRelationship(): HasMany
    {
        return $this->organisationEventTaxonomies();
    }
}
