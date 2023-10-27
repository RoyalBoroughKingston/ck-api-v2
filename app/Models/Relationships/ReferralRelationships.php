<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Service;
use App\Models\StatusUpdate;
use App\Models\Taxonomy;

trait ReferralRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function statusUpdates(): HasMany
    {
        return $this->hasMany(StatusUpdate::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organisationTaxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'organisation_taxonomy_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestCompletedStatusUpdate(): HasOne
    {
        return $this->hasOne(StatusUpdate::class)
            ->orderByDesc(table(StatusUpdate::class, 'created_at'))
            ->where(table(StatusUpdate::class, 'to'), '=', StatusUpdate::TO_COMPLETED);
    }
}
