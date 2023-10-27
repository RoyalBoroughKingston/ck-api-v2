<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ReportType;

trait ReportScheduleRelationships
{
    public function reportType(): BelongsTo
    {
        return $this->belongsTo(ReportType::class);
    }
}
