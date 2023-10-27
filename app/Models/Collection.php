<?php

namespace App\Models;

use App\Models\Collection;
use App\Models\Mutators\CollectionMutators;
use App\Models\Relationships\CollectionRelationships;
use App\Models\Scopes\CollectionScopes;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class Collection extends Model
{
    use HasFactory;
    use CollectionMutators;
    use CollectionRelationships;
    use CollectionScopes;

    const TYPE_CATEGORY = 'category';

    const TYPE_PERSONA = 'persona';

    const TYPE_ORGANISATION_EVENT = 'organisation-event';

    /**
     * Attributes that need to be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'enabled' => 'boolean',
        'homepage' => 'boolean',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'homepage' => false,
    ];

    public function touchServices(): Collection
    {
        static::services($this)->get()->each->save();

        return $this;
    }

    public function syncCollectionTaxonomies(EloquentCollection $taxonomies): Collection
    {
        // Delete all existing collection taxonomies.
        $this->collectionTaxonomies()->delete();

        // Create a collection taxonomy record for each taxonomy.
        foreach ($taxonomies as $taxonomy) {
            $this->collectionTaxonomies()->updateOrCreate(['taxonomy_id' => $taxonomy->id]);
        }

        return $this;
    }

    /**
     * @return \App\Models\File|\Illuminate\Http\Response|\Illuminate\Contracts\Support\Responsable
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException|\InvalidArgumentException
     */
    public static function personaPlaceholderLogo(int $maxDimension = null)
    {
        if ($maxDimension !== null) {
            return File::resizedPlaceholder($maxDimension, File::META_PLACEHOLDER_FOR_COLLECTION_PERSONA);
        }

        return response()->make(
            Storage::disk('local')->get('/placeholders/collection_persona.png'),
            Response::HTTP_OK,
            ['Content-Type' => File::MIME_TYPE_PNG]
        );
    }

    /**
     * @return \App\Models\File|\Illuminate\Http\Response|\Illuminate\Contracts\Support\Responsable
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException|\InvalidArgumentException
     */
    public static function categoryPlaceholderLogo(int $maxDimension = null)
    {
        if ($maxDimension !== null) {
            return File::resizedPlaceholder($maxDimension, File::META_PLACEHOLDER_FOR_COLLECTION_CATEGORY);
        }

        return response()->make(
            Storage::disk('local')->get('/placeholders/collection_category.png'),
            Response::HTTP_OK,
            ['Content-Type' => File::MIME_TYPE_PNG]
        );
    }

    /**
     * @return \App\Models\File|\Illuminate\Http\Response|\Illuminate\Contracts\Support\Responsable
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException|\InvalidArgumentException
     */
    public static function organisationEventPlaceholderLogo(int $maxDimension = null)
    {
        if ($maxDimension !== null) {
            return File::resizedPlaceholder($maxDimension, File::META_PLACEHOLDER_FOR_ORGANISATION_EVENT);
        }

        return response()->make(
            Storage::disk('local')->get('/placeholders/organisation_event.png'),
            Response::HTTP_OK,
            ['Content-Type' => File::MIME_TYPE_PNG]
        );
    }

    /**
     * Enable the Collection.
     */
    public function enable(): Collection
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable the Collection.
     */
    public function disable(): Collection
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Add the Collection to the homepage.
     */
    public function addToHomepage(): Collection
    {
        $this->homepage = true;

        return $this;
    }

    /**
     * Remove the Collection from the homepage.
     */
    public function removeFromHomepage(): Collection
    {
        $this->homepage = false;

        return $this;
    }
}
