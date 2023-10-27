<?php

namespace App\UpdateRequest;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\UpdateRequest;

trait UpdateRequests
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function updateRequests(): MorphMany
    {
        return $this->morphMany(UpdateRequest::class, 'updateable');
    }
}
