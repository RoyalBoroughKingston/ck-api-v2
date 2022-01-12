<?php

namespace App\Models\Relationships;

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
}
