<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\File;
use App\Models\ReportType;

trait ReportRelationships
{
    public function reportType(): BelongsTo
    {
        return $this->belongsTo(ReportType::class);
    }

    /**
     * @return mixed
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
