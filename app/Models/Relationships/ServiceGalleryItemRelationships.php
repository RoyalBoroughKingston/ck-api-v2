<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\File;
use App\Models\Service;

trait ServiceGalleryItemRelationships
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
