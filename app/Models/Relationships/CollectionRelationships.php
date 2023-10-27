<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\CollectionTaxonomy;
use App\Models\Page;
use App\Models\Taxonomy;

trait CollectionRelationships
{
    public function collectionTaxonomies(): HasMany
    {
        return $this->hasMany(CollectionTaxonomy::class);
    }

    public function taxonomies(): HasManyThrough
    {
        return $this->belongsToMany(Taxonomy::class, (new CollectionTaxonomy())->getTable());
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class);
    }
}
