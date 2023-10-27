<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\File;
use App\Models\HolidayOpeningHour;
use App\Models\Location;
use App\Models\RegularOpeningHour;
use App\Models\Service;

trait ServiceLocationRelationships
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function regularOpeningHours(): HasMany
    {
        return $this->hasMany(RegularOpeningHour::class);
    }

    public function holidayOpeningHours(): HasMany
    {
        return $this->hasMany(HolidayOpeningHour::class);
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'image_file_id');
    }
}
