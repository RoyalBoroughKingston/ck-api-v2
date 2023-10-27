<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Referral;
use App\Models\User;

trait StatusUpdateRelationships
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }
}
