<?php

namespace App\Models\Relationships;

use App\Models\Collection;
use App\Models\File;

trait PageRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function image()
    {
        return $this->belongsTo(File::class, 'image_file_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function collections()
    {
        return $this->belongsToMany(Collection::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function collectionCategories()
    {
        return $this->collections()->where('type', Collection::TYPE_CATEGORY);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function collectionPersonas()
    {
        return $this->collections()->where('type', Collection::TYPE_PERSONA);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function landingPageAncestors()
    {
        return $this->ancestors()->where('page_type', static::PAGE_TYPE_LANDING);
    }
}
