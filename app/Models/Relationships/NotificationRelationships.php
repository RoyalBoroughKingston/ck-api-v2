<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\MorphTo;

trait NotificationRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo('notifiable');
    }
}
