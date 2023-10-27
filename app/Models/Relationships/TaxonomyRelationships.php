<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\CollectionTaxonomy;
use App\Models\OrganisationEvent;
use App\Models\OrganisationEventTaxonomy;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServiceTaxonomy;
use App\Models\Taxonomy;

trait TaxonomyRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Taxonomy::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function collectionTaxonomies(): HasMany
    {
        return $this->hasMany(CollectionTaxonomy::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function serviceTaxonomies(): HasMany
    {
        return $this->hasMany(ServiceTaxonomy::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'organisation_taxonomy_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, (new ServiceTaxonomy())->getTable());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function organisationEvents(): BelongsToMany
    {
        return $this->belongsToMany(OrganisationEvent::class, (new OrganisationEventTaxonomy())->getTable());
    }
}
