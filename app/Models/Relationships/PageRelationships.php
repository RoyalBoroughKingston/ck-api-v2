<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Collection;
use App\Models\File;

trait PageRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(File::class, 'image_file_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function collectionCategories(): BelongsToMany
    {
        return $this->collections()->where('type', Collection::TYPE_CATEGORY);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function collectionPersonas(): BelongsToMany
    {
        return $this->collections()->where('type', Collection::TYPE_PERSONA);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function landingPageAncestors(): BelongsToMany
    {
        return $this->ancestors()->where('page_type', static::PAGE_TYPE_LANDING);
    }
}
