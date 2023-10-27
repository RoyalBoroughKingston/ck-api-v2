<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\MorphTo;

trait SocialMediaRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function sociable(): MorphTo
    {
        return $this->morphTo();
    }
}
