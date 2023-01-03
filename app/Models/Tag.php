<?php

namespace App\Models;

use App\Models\Relationships\TagRelationships;

class Tag extends Model
{
    use TagRelationships;

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = ['slug', 'label'];
}
