<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\User;

trait UpdateRequestRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function actioningUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioning_user_id')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function updateable(): MorphTo
    {
        return $this->morphTo('updateable');
    }
}
