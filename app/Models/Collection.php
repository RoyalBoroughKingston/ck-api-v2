<?php

namespace App\Models;

use App\Models\Mutators\CollectionMutators;
use App\Models\Relationships\CollectionRelationships;
use App\Models\Scopes\CollectionScopes;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class Collection extends Model
{
    use CollectionMutators;
    use CollectionRelationships;
    use CollectionScopes;

    const TYPE_CATEGORY = 'category';
    const TYPE_PERSONA = 'persona';

    /**
     * @return \App\Models\Collection
     */
    public function touchServices(): Collection
    {
        static::services($this)->get()->each->save();

        return $this;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $taxonomies
     * @return \App\Models\Collection
     */
    public function syncCollectionTaxonomies(EloquentCollection $taxonomies): Collection
    {
        // Delete all existing collection taxonomies.
        $this->collectionTaxonomies()->delete();

        // Create a collection taxonomy record for each taxonomy and their parents.
        foreach ($taxonomies as $taxonomy) {
            $this->createCollectionTaxonomy($taxonomy);
        }

        return $this;
    }

    /**
     * @param \App\Models\Taxonomy $taxonomy
     * @return \App\Models\CollectionTaxonomy
     */
    protected function createCollectionTaxonomy(Taxonomy $taxonomy): CollectionTaxonomy
    {
        $hasParent = $taxonomy->parent !== null;
        $parentIsNotTopLevel = $taxonomy->parent->id !== Taxonomy::category()->id;

        if ($hasParent && $parentIsNotTopLevel) {
            $this->createCollectionTaxonomy($taxonomy->parent);
        }

        return $this->collectionTaxonomies()->updateOrCreate(['taxonomy_id' => $taxonomy->id]);
    }
}
