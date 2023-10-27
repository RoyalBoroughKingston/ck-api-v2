<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ReportType;

trait ReportScheduleRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function reportType(): BelongsTo
    {
        return $this->belongsTo(ReportType::class);
    }
}
