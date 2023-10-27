<?php

namespace App\UpdateRequest;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\UpdateRequest;

trait UpdateRequests
{
    public function updateRequests(): MorphMany
    {
        return $this->morphMany(UpdateRequest::class, 'updateable');
    }
}
