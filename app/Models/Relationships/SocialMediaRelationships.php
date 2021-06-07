<?php

namespace App\Models\Relationships;

trait SocialMediaRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function sociable()
    {
        return $this->morphTo();
    }
}
