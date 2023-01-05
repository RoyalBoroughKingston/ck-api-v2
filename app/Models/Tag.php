<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Relationships\TagRelationships;

class Tag extends Model
{
    use HasFactory;

    use TagRelationships;

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = ['slug', 'label'];
}
